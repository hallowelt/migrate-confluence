#!/usr/bin/env python3
"""
Strips foreign-space objects out of Confluence `entities.xml` exports.

Background: a known Confluence bug can make a single-space XML export
contain objects (pages, comments, attachments, ...) that actually belong to
other spaces on the same instance. See:
https://confluence.atlassian.com/confkb/a-space-export-contains-multiple-space-objects-and-fails-when-importing-into-another-confluence-instance-949242294.html

This tool walks an input folder, finds every `entities.xml` that has an
`exportDescriptor.properties` next to it, and writes a "minimized" copy that
only contains objects belonging to the space named by that file's
`spaceKey`. `attachments` folders are ignored (not scanned, not copied) --
this tool only ever touches `entities.xml` and `exportDescriptor.properties`.

Usage:
    bin/minimize-confluence-export.py <input_dir> [output_dir]

`output_dir` defaults to a sibling folder named `minimized` next to
`input_dir`, mirroring `input_dir`'s internal structure (useful for the
multi-space case where several `entities.xml`/`exportDescriptor.properties`
pairs live in sub-folders of the input folder).

Performance notes: the whole `entities.xml` is memory-mapped (not loaded
into RAM) and parsed exactly once with the C-accelerated `expat` parser to
record the byte range of every top-level `<object>` element plus the small
set of properties needed to decide ownership. Kept objects are then copied
byte-for-byte (no re-serialization, no encoding surprises) into the output
file. Two full read passes over the data (one parse, one copy) is all that
is needed regardless of file size.
"""

import mmap
import os
import shutil
import sys
from pathlib import Path
from xml.parsers import expat

# Object classes that carry their owning space id directly in a "space" property.
# CustomContentEntityObject is the generic content type many plugins use
# (whiteboards, drawio diagrams, content-appearance drafts, ...) -- easy to
# miss because it looks like housekeeping metadata, but it carries a real
# "space" property and can carry large BodyContent, so leaving it out of
# this set silently keeps every such object (and its body) regardless of
# space, in every space in the export.
DIRECT_OWNER_CLASSES = {"Page", "BlogPost", "PageTemplate", "Attachment", "CustomContentEntityObject"}

# Object classes whose ownership is resolved indirectly (own id looked up in
# content_space_map, built from direct owners + Comment/version chains + the
# Space -> SpaceDescription reverse link).
INDIRECT_OWNER_CLASSES = {"SpaceDescription", "Comment"}

# Object classes that reference their owning content via a "content" property
# instead of carrying/being the content themselves.
CONTENT_REF_CLASSES = {"BodyContent", "ContentProperty"}

FILTERED_CLASSES = {"Space", "Labelling"} | DIRECT_OWNER_CLASSES | INDIRECT_OWNER_CLASSES | CONTENT_REF_CLASSES


# Collections on owning content whose elements need an ownership map built
# (the referenced ids don't carry a back-reference of their own).
TRACKED_COLLECTIONS = {"labellings", "contentProperties"}


def parse_properties(path: Path) -> dict:
    """Minimal `key=value` properties file reader (mirrors the PHP analyzer)."""
    props = {}
    for line in path.read_text(encoding="utf-8", errors="replace").splitlines():
        if not line or line.startswith("#"):
            continue
        key, _, value = line.partition("=")
        props[key.strip()] = value.strip()
    return props


def build_index(data) -> tuple:
    """
    Single expat pass over `data` (bytes-like). Returns
    (records, space_keys, direct_owners, comment_parents, version_parents, collections_of):

    - records: list of dicts {start, end, cls, id, [ref]}, one per top-level
      <object>, in document order. "ref" is only set for CONTENT_REF_CLASSES
      (the value of their "content" property, if present).
    - space_keys: {spaceId: spaceKey}
    - direct_owners: {contentId: spaceId} for DIRECT_OWNER_CLASSES, plus
      {descriptionId: spaceId} via each Space's "description" property.
    - comment_parents: {commentId: containerContentId}
    - version_parents: {historicalContentId: originalVersionId} for
      DIRECT_OWNER_CLASSES rows that are superseded historical version
      snapshots -- these carry no "space" property of their own (Confluence
      leaves it unset on old versions), only an "originalVersionId"/
      "originalVersion" property (scalar or ref-style, depending on export)
      pointing back at the live content row they're a version of.
    - collections_of: {collectionName: {contentId: [elementId, ...]}} for
      every name in TRACKED_COLLECTIONS (e.g. "labellings", "contentProperties")
      -- collections are the only way to learn the ownership of elements that
      don't carry a back-reference to their owner themselves.
    """
    records = []
    space_keys = {}
    direct_owners = {}
    comment_parents = {}
    version_parents = {}
    collections_of = {name: {} for name in TRACKED_COLLECTIONS}

    parser = expat.ParserCreate()
    parser.buffer_text = True

    state = {
        "depth": 0,
        "obj": None,
        "obj_depth": None,
        "prop_name": None,
        "prop_ref": None,
        "cur_collection": None,
        "elem_ref": None,
        "text": [],
    }

    def finalize(obj):
        cls = obj["cls"]
        oid = obj["id"]
        props = obj["props"]
        rec = {"start": obj["start"], "end": obj["end"], "cls": cls, "id": oid}
        if cls in CONTENT_REF_CLASSES:
            rec["ref"] = props.get("content")
        # Every property whose value came from a nested <id> (an object
        # reference, e.g. Attachment's "containerContent") is a candidate for
        # the collateral-damage check: is a kept object still pointing at a
        # dropped/foreign id?
        refs = [props[name] for name in obj["ref_props"] if props.get(name)]
        if refs:
            rec["refs"] = refs
        records.append(rec)

        if cls == "Space":
            space_keys[oid] = props.get("key", "")
            desc = props.get("description")
            if desc:
                direct_owners[desc] = oid
        elif cls in DIRECT_OWNER_CLASSES:
            sp = props.get("space")
            if sp:
                direct_owners[oid] = sp
            else:
                # Confluence has two forms of this back-reference depending
                # on export/version: a scalar "originalVersionId", or a
                # ref-style "originalVersion" (class="Page" + nested <id>).
                # Both are captured generically as props[name] by the parser
                # above, so just check both names.
                ov = props.get("originalVersionId") or props.get("originalVersion")
                if ov:
                    version_parents[oid] = ov
        elif cls == "Comment":
            cc = props.get("containerContent")
            if cc:
                comment_parents[oid] = cc

        for name, elements in obj["collections"].items():
            collections_of[name][oid] = elements

    def start(name, attrs):
        state["depth"] += 1
        state["text"] = []
        obj = state["obj"]
        if obj is None:
            if name == "object":
                state["obj"] = {
                    "cls": attrs.get("class", ""),
                    "id": None,
                    "props": {},
                    "ref_props": set(),
                    "collections": {},
                    "start": parser.CurrentByteIndex,
                }
                state["obj_depth"] = state["depth"]
                state["cur_collection"] = None
            return

        rel = state["depth"] - state["obj_depth"]
        if rel == 1:
            if name == "property":
                state["prop_name"] = attrs.get("name")
                state["prop_ref"] = None
            elif name == "collection":
                col_name = attrs.get("name")
                if col_name in TRACKED_COLLECTIONS:
                    state["cur_collection"] = col_name
                    obj["collections"][col_name] = []
        elif rel == 2:
            if name == "element" and state["cur_collection"] is not None:
                state["elem_ref"] = None

    def end(name):
        text = "".join(state["text"]).strip()
        state["text"] = []
        obj = state["obj"]
        if obj is None:
            state["depth"] -= 1
            return

        rel = state["depth"] - state["obj_depth"]
        if rel == 0:
            end_pos = data.find(b">", parser.CurrentByteIndex) + 1
            obj["end"] = end_pos
            finalize(obj)
            state["obj"] = None
            state["obj_depth"] = None
        elif rel == 1:
            if name == "id":
                obj["id"] = text
            elif name == "property":
                value = state["prop_ref"] if state["prop_ref"] is not None else text
                obj["props"][state["prop_name"]] = value
                if state["prop_ref"] is not None:
                    obj["ref_props"].add(state["prop_name"])
                state["prop_name"] = None
                state["prop_ref"] = None
            elif name == "collection":
                state["cur_collection"] = None
        elif rel == 2:
            if name == "id" and state["prop_name"] is not None:
                state["prop_ref"] = text
            elif name == "element" and state["cur_collection"] is not None:
                value = state["elem_ref"] if state["elem_ref"] is not None else text
                obj["collections"][state["cur_collection"]].append(value)
                state["elem_ref"] = None
        elif rel == 3:
            if name == "id" and state["cur_collection"] is not None:
                state["elem_ref"] = text

        state["depth"] -= 1

    def char_data(text):
        state["text"].append(text)

    parser.StartElementHandler = start
    parser.EndElementHandler = end
    parser.CharacterDataHandler = char_data
    parser.Parse(data, True)

    return records, space_keys, direct_owners, comment_parents, version_parents, collections_of


def resolve_content_space_map(direct_owners: dict, comment_parents: dict, version_parents: dict = None) -> dict:
    """
    Merge Comment -> containerContent chains and historical-version ->
    originalVersionId chains into the direct-owner map. Both are id -> id
    back-references that ultimately resolve to a DIRECT_OWNER_CLASSES row
    carrying an explicit "space" property, so a bounded iterative resolution
    (comment reply nesting / version chains are never deep in practice)
    covers them uniformly.
    """
    content_space_map = dict(direct_owners)
    parent_of = dict(comment_parents)
    parent_of.update(version_parents or {})
    for _ in range(10):
        changed = False
        for child_id, parent_id in parent_of.items():
            if child_id in content_space_map:
                continue
            if parent_id in content_space_map:
                content_space_map[child_id] = content_space_map[parent_id]
                changed = True
        if not changed:
            break
    return content_space_map


def is_kept(rec, allowed_space_ids, content_space_map, labelling_space_map, content_property_space_map) -> bool:
    if rec["cls"] not in FILTERED_CLASSES:
        return True  # Label, User, and any other class: never filtered.
    sp = own_space(rec, content_space_map, labelling_space_map, content_property_space_map)
    return sp is None or sp in allowed_space_ids


def own_space(rec, content_space_map, labelling_space_map, content_property_space_map):
    """The space id a record belongs to, or None if unknown/not tracked."""
    cls, oid = rec["cls"], rec["id"]
    if cls == "Space":
        return oid
    if cls in DIRECT_OWNER_CLASSES or cls in INDIRECT_OWNER_CLASSES:
        return content_space_map.get(oid)
    if cls == "ContentProperty":
        # Some Confluence versions give ContentProperty a "content" back-reference
        # (picked up as rec["ref"] like BodyContent); others only expose ownership
        # via the owning content's "contentProperties" collection. Try both.
        ref = rec.get("ref")
        if ref:
            return content_space_map.get(ref)
        return content_property_space_map.get(oid)
    if cls in CONTENT_REF_CLASSES:
        ref = rec.get("ref")
        return content_space_map.get(ref) if ref else None
    if cls == "Labelling":
        return labelling_space_map.get(oid)
    return None


def find_collateral_damage(
    records, allowed_space_ids, content_space_map, labelling_space_map, content_property_space_map, space_keys
) -> dict:
    """
    The Server/DC root cause from the KB article: a content row's SPACEID
    was left pointing at the wrong space after a page move, so it now looks
    like it belongs to the kept space while some of its own references still
    point into the foreign space being dropped. If we pruned by ownership
    alone, the kept space would come out silently incomplete.

    Returns {foreign_space_id: [(cls, id, referenced_id), ...]} for every
    kept object that references a foreign-space id.
    """
    collateral = {}
    for rec in records:
        my_space = own_space(rec, content_space_map, labelling_space_map, content_property_space_map)
        if my_space not in allowed_space_ids:
            continue
        for ref_id in rec.get("refs", ()):
            ref_space = ref_id if ref_id in space_keys else content_space_map.get(ref_id)
            if ref_space is not None and ref_space not in allowed_space_ids:
                collateral.setdefault(ref_space, []).append((rec["cls"], rec["id"], ref_id))
    return collateral



def format_size(num_bytes: int) -> str:
    size = float(num_bytes)
    for unit in ("B", "KB", "MB", "GB", "TB"):
        if size < 1024 or unit == "TB":
            return f"{size:.1f} {unit}" if unit != "B" else f"{int(size)} B"
        size /= 1024


def build_collection_space_map(collection_of: dict, content_space_map: dict) -> dict:
    """{elementId: spaceId} for a TRACKED_COLLECTIONS map, via its owner's space."""
    result = {}
    for content_id, element_ids in collection_of.items():
        sp = content_space_map.get(content_id)
        if sp is None:
            continue
        for element_id in element_ids:
            result[element_id] = sp
    return result


def process_export(entities_path: Path, props_path: Path, output_path: Path) -> None:
    expected_key = ""
    if props_path.exists():
        expected_key = parse_properties(props_path).get("spaceKey", "")

    with open(entities_path, "rb") as fh:
        data = mmap.mmap(fh.fileno(), 0, access=mmap.ACCESS_READ)
        try:
            records, space_keys, direct_owners, comment_parents, version_parents, collections_of = build_index(data)

            allowed_space_ids = {sid for sid, key in space_keys.items() if key == expected_key}
            if not expected_key or not allowed_space_ids:
                # Can't determine the target space: don't risk dropping everything,
                # copy the file through unchanged.
                reason = "no exportDescriptor.properties / spaceKey" if not expected_key \
                    else f"no Space object matches spaceKey '{expected_key}'"
                print(f"  {entities_path}: skipped filtering ({reason}), copied as-is.")
                output_path.write_bytes(data)
                return

            content_space_map = resolve_content_space_map(direct_owners, comment_parents, version_parents)
            labelling_space_map = build_collection_space_map(collections_of["labellings"], content_space_map)
            content_property_space_map = build_collection_space_map(
                collections_of["contentProperties"], content_space_map
            )

            collateral = find_collateral_damage(
                records, allowed_space_ids, content_space_map, labelling_space_map,
                content_property_space_map, space_keys
            )
            for foreign_sid, refs in collateral.items():
                foreign_key = space_keys.get(foreign_sid, "?")
                example_cls, example_id, example_ref = refs[0]
                print(
                    f"  {entities_path}: WARNING: {len(refs)} object(s) kept for spaceKey"
                    f" '{expected_key}' still reference foreign space '{foreign_key}'"
                    f" (id {foreign_sid}), e.g. {example_cls} (ID:{example_id}) -> {example_ref}."
                    " This is cross-space reference corruption (see the Atlassian KB article);"
                    " fix SPACEID in the source database and re-export, or the kept space will"
                    " come out incomplete."
                )

            skip_counts = {}
            with open(output_path, "wb") as out:
                out.write(data[0: records[0]["start"]] if records else data[:])
                for rec in records:
                    if is_kept(
                        rec, allowed_space_ids, content_space_map, labelling_space_map, content_property_space_map
                    ):
                        out.write(data[rec["start"]: rec["end"]])
                        out.write(b"\n")
                    else:
                        skip_counts[rec["cls"]] = skip_counts.get(rec["cls"], 0) + 1
                if records:
                    out.write(data[records[-1]["end"]:])

            if skip_counts:
                summary = ", ".join(f"{count} {cls}" for cls, count in sorted(skip_counts.items()))
                print(f"  {entities_path}: kept spaceKey '{expected_key}', filtered out {summary}.")
            else:
                print(f"  {entities_path}: kept spaceKey '{expected_key}', nothing to filter out.")

            original_size = len(data)
            new_size = output_path.stat().st_size
            reduction = (100 * (original_size - new_size) / original_size) if original_size else 0
            print(
                f"  {entities_path}: {format_size(original_size)} -> {format_size(new_size)}"
                f" (-{reduction:.1f}%)"
            )
        finally:
            data.close()


def find_export_dirs(input_dir: Path, output_dir: Path):
    for root, dirs, files in os.walk(input_dir):
        root_path = Path(root)
        # ponytail: string-name prune is enough here, exports never nest an
        # "attachments" folder inside another one.
        dirs[:] = [
            d for d in dirs
            if d != "attachments" and (root_path / d).resolve() != output_dir
        ]
        if "entities.xml" in files:
            yield root_path


def main(argv) -> int:
    if len(argv) < 2:
        print(f"Usage: {argv[0]} <input_dir> [output_dir]", file=sys.stderr)
        return 2

    if argv[1] == '--help':
        print(__doc__)
        return 0

    input_dir = Path(argv[1]).resolve()
    if not input_dir.is_dir():
        print(f"Not a directory: {input_dir}", file=sys.stderr)
        return 2

    output_dir = Path(argv[2]).resolve() if len(argv) > 2 else input_dir.parent / "minimized"
    output_dir.mkdir(parents=True, exist_ok=True)

    found_any = False
    for export_dir in find_export_dirs(input_dir, output_dir):
        found_any = True
        rel = export_dir.relative_to(input_dir)
        out_subdir = output_dir / rel
        out_subdir.mkdir(parents=True, exist_ok=True)

        print(f"Processing {export_dir} ->")
        process_export(
            export_dir / "entities.xml",
            export_dir / "exportDescriptor.properties",
            out_subdir / "entities.xml",
        )
        props_path = export_dir / "exportDescriptor.properties"
        if props_path.exists():
            shutil.copy2(props_path, out_subdir / "exportDescriptor.properties")

    if not found_any:
        print(f"No entities.xml found under {input_dir}", file=sys.stderr)
        return 1

    print(f"Done. Output written to {output_dir}")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
