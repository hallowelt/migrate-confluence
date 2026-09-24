#!/usr/bin/env python3
"""
Reports on the contents of a Confluence `entities.xml` export: counts of
spaces, pages, blog posts, comments, attachments, attachment references,
labels, users, ... at three verbosity levels.

Usage:
    bin/report-confluence-export.py <entities.xml> [-v | -vv]

    (no flag)  summary counts table, including current vs. historical page
               versions
    -v         + per-space breakdown, content status (draft/deleted/...)
               breakdown, file size by object class -- all as aligned tables
    -vv        + deduplicated page/attachment titles (with a version count
               per title), body content size split by the owning page's
               version/state (historical vs. live, and status)

Output assumes a terminal at least 120 columns wide (tables and multi-column
title/filename listings are sized accordingly).

Parsing approach mirrors bin/minimize-confluence-export.py: the file is
memory-mapped and parsed once with the C-accelerated `expat` parser,
recording only the small set of properties needed for the report (no DOM,
no second pass).
"""

import argparse
import mmap
import sys
from collections import Counter
from pathlib import Path
from xml.parsers import expat

# Classes that carry their owning space directly in a "space" property and
# get a per-space breakdown at -v.
DIRECT_OWNER_CLASSES = {"Page", "BlogPost", "Attachment"}

# Classes whose version/publication state (contentStatus, presence of a
# "space" back-reference) is reported at -v. A row of one of these classes
# with no "space" property is a superseded historical version snapshot kept
# only for version history, not a live piece of content.
VERSIONED_CLASSES = {"Page", "BlogPost", "Attachment"}


def build_index(data) -> dict:
    class_counts = Counter()
    size_by_class = Counter()  # cls -> total bytes of its top-level <object> elements
    spaces = {}  # spaceId -> {"key": ..., "name": ...}
    owner_space = {}  # contentId -> spaceId, for DIRECT_OWNER_CLASSES only
    owner_class = {}  # contentId -> cls, for DIRECT_OWNER_CLASSES only
    titles = {}  # contentId -> title, for DIRECT_OWNER_CLASSES only
    comment_parent = {}  # commentId -> containerContentId
    content_meta = {}  # contentId -> {"cls", "has_space", "status"}, for VERSIONED_CLASSES
    body_owner = {}  # bodyContentId -> owning contentId ("content" property)
    body_size = {}  # bodyContentId -> bytes of its top-level <object> element

    parser = expat.ParserCreate()
    parser.buffer_text = True

    state = {"depth": 0, "obj": None, "obj_depth": None, "prop_name": None, "prop_ref": None, "text": []}

    def finalize(obj):
        cls, oid, props = obj["cls"], obj["id"], obj["props"]
        size = obj["end"] - obj["start"]
        class_counts[cls] += 1
        size_by_class[cls] += size
        if cls == "Space":
            spaces[oid] = {"key": props.get("key", ""), "name": props.get("name", "")}
        elif cls in DIRECT_OWNER_CLASSES:
            owner_class[oid] = cls
            sp = props.get("space")
            if sp:
                owner_space[oid] = sp
            title = props.get("title")
            if title:
                titles[oid] = title
        elif cls == "Comment":
            cc = props.get("containerContent")
            if cc:
                comment_parent[oid] = cc

        if cls in VERSIONED_CLASSES:
            content_meta[oid] = {
                "cls": cls,
                "has_space": "space" in props,
                "status": props.get("contentStatus", "(none)"),
            }
        elif cls == "BodyContent":
            body_owner[oid] = props.get("content")
            body_size[oid] = size

    def start(name, attrs):
        state["depth"] += 1
        state["text"] = []
        obj = state["obj"]
        if obj is None:
            if name == "object":
                state["obj"] = {
                    "cls": attrs.get("class", ""), "id": None, "props": {},
                    "start": parser.CurrentByteIndex,
                }
                state["obj_depth"] = state["depth"]
            return
        rel = state["depth"] - state["obj_depth"]
        if rel == 1 and name == "property":
            state["prop_name"] = attrs.get("name")
            state["prop_ref"] = None

    def end(name):
        text = "".join(state["text"]).strip()
        state["text"] = []
        obj = state["obj"]
        if obj is None:
            state["depth"] -= 1
            return
        rel = state["depth"] - state["obj_depth"]
        if rel == 0:
            obj["end"] = data.find(b">", parser.CurrentByteIndex) + 1
            finalize(obj)
            state["obj"] = None
            state["obj_depth"] = None
        elif rel == 1:
            if name == "id":
                obj["id"] = text
            elif name == "property":
                obj["props"][state["prop_name"]] = state["prop_ref"] if state["prop_ref"] is not None else text
                state["prop_name"] = None
                state["prop_ref"] = None
        elif rel == 2 and name == "id" and state["prop_name"] is not None:
            state["prop_ref"] = text
        state["depth"] -= 1

    def char_data(text):
        state["text"].append(text)

    parser.StartElementHandler = start
    parser.EndElementHandler = end
    parser.CharacterDataHandler = char_data
    parser.Parse(data, True)

    return {
        "class_counts": class_counts,
        "size_by_class": size_by_class,
        "spaces": spaces,
        "owner_space": owner_space,
        "owner_class": owner_class,
        "titles": titles,
        "comment_parent": comment_parent,
        "content_meta": content_meta,
        "body_owner": body_owner,
        "body_size": body_size,
    }


TERM_WIDTH = 120


def format_size(num_bytes: int) -> str:
    size = float(num_bytes)
    for unit in ("B", "KB", "MB", "GB", "TB"):
        if size < 1024 or unit == "TB":
            return f"{size:.1f} {unit}" if unit != "B" else f"{int(size)} B"
        size /= 1024


def bar(pct: float, width: int = 24) -> str:
    fill = round(pct / 100 * width) if pct > 0 else 0
    fill = min(fill, width)
    return "[" + ("#" * fill).ljust(width) + "]"


def print_section(title: str) -> None:
    print()
    print(title)
    print("-" * len(title))


def print_table(headers, rows, aligns=None, indent: str = "  ") -> None:
    """Aligned ASCII table: header row, a '-' underline per column, then data rows."""
    if not rows:
        return
    aligns = aligns or ["l"] * len(headers)
    widths = [len(h) for h in headers]
    str_rows = [[str(c) for c in row] for row in rows]
    for row in str_rows:
        for i, cell in enumerate(row):
            widths[i] = max(widths[i], len(cell))

    def fmt(cells):
        parts = []
        for i, cell in enumerate(cells):
            parts.append(cell.rjust(widths[i]) if aligns[i] == "r" else cell.ljust(widths[i]))
        return indent + "  ".join(parts).rstrip()

    print(fmt(headers))
    print(indent + "  ".join("-" * w for w in widths))
    for row in str_rows:
        print(fmt(row))


def print_columns(items, width: int = TERM_WIDTH, indent: str = "  ") -> None:
    """Prints a list of short strings packed into as many columns as fit, row by row."""
    if not items:
        return
    col_width = max(len(i) for i in items) + 2
    avail = width - len(indent)
    num_cols = max(1, min(avail // col_width, len(items)))
    for row_start in range(0, len(items), num_cols):
        row_items = items[row_start:row_start + num_cols]
        line = "".join(it.ljust(col_width) for it in row_items)
        print(indent + line.rstrip())


def print_report(idx: dict, attachment_refs: int, verbosity: int) -> None:
    cc = idx["class_counts"]
    size_by_class = idx["size_by_class"]
    spaces = idx["spaces"]
    owner_space = idx["owner_space"]
    owner_class = idx["owner_class"]
    titles = idx["titles"]
    comment_parent = idx["comment_parent"]
    content_meta = idx["content_meta"]
    body_owner = idx["body_owner"]
    body_size = idx["body_size"]

    # A "live" row (has a "space" back-reference) is the one that actually
    # gets migrated; a VERSIONED_CLASSES row without one is a superseded
    # historical version snapshot, kept only for version history.
    live_current = sum(
        1 for m in content_meta.values()
        if m["cls"] == "Page" and m["has_space"] and m["status"] == "current"
    )
    historical = sum(1 for m in content_meta.values() if m["cls"] == "Page" and not m["has_space"])

    print_section("Summary")
    print_table(
        ["Metric", "Count", "Details"],
        [
            ["Spaces", cc.get("Space", 0), ""],
            ["Pages", cc.get("Page", 0), f"{live_current} current, {historical} historical versions"],
            ["Blog posts", cc.get("BlogPost", 0), ""],
            ["Comments", cc.get("Comment", 0), ""],
            ["Attachments", cc.get("Attachment", 0), ""],
            ["Attachment references", attachment_refs, "in page/blog post content"],
            ["Labels", cc.get("Label", 0), ""],
            ["Users", cc.get("ConfluenceUserImpl", 0), ""],
        ],
        aligns=["l", "r", "l"],
    )
    if verbosity == 0:
        return

    # -v: per-space breakdown of pages/blogposts/attachments, plus comments
    # attributed to their page/blogpost's space (comments carry no "space"
    # property of their own, only a containerContent back-reference).
    per_space = {sid: Counter() for sid in spaces}
    orphans = Counter()
    for oid, cls in owner_class.items():
        sid = owner_space.get(oid)
        if sid is None:
            orphans[cls] += 1
            continue
        per_space.setdefault(sid, Counter())[cls] += 1

    for comment_id, parent_id in comment_parent.items():
        sid = owner_space.get(parent_id)
        if sid is None:
            orphans["Comment"] += 1
            continue
        per_space.setdefault(sid, Counter())["Comment"] += 1

    print_section("Per-space breakdown")
    print_table(
        ["Space Key", "Pages", "Blog Posts", "Attachments", "Comments"],
        [
            [info["key"] or "(no key)", per_space.get(sid, Counter())["Page"],
             per_space.get(sid, Counter())["BlogPost"], per_space.get(sid, Counter())["Attachment"],
             per_space.get(sid, Counter())["Comment"]]
            for sid, info in sorted(spaces.items(), key=lambda kv: kv[1]["key"])
        ],
        aligns=["l", "r", "r", "r", "r"],
    )
    if orphans:
        summary = ", ".join(f"{n} {cls}" for cls, n in sorted(orphans.items()))
        print(f"  Note: rows with no resolvable owning space (e.g. historical versions): {summary}")

    # Content status breakdown: for each versioned class, how many live rows
    # are in each contentStatus (current/draft/deleted/archived/...), plus
    # how many rows are historical version snapshots (no "space" property).
    status_by_class = {}
    historical_by_class = Counter()
    all_statuses = []
    for meta in content_meta.values():
        if meta["has_space"]:
            counter = status_by_class.setdefault(meta["cls"], Counter())
            counter[meta["status"]] += 1
            if meta["status"] not in all_statuses:
                all_statuses.append(meta["status"])
        else:
            historical_by_class[meta["cls"]] += 1
    preferred_order = ["current", "draft", "deleted", "archived"]
    all_statuses.sort(key=lambda s: (preferred_order.index(s) if s in preferred_order else len(preferred_order), s))

    status_rows = []
    for cls in sorted(VERSIONED_CLASSES):
        statuses = status_by_class.get(cls)
        if not statuses and not historical_by_class.get(cls):
            continue
        row = [cls] + [statuses.get(s, 0) if statuses else 0 for s in all_statuses] + [historical_by_class.get(cls, 0)]
        status_rows.append(row)

    print_section("Content status (live rows by contentStatus; historical = old version snapshot)")
    print_table(
        ["Class"] + [s.capitalize() for s in all_statuses] + ["Historical"],
        status_rows,
        aligns=["l"] + ["r"] * (len(all_statuses) + 1),
    )

    # File size distribution across top-level object classes.
    total_size = sum(size_by_class.values())
    print_section("File size by top-level object class")
    print_table(
        ["Class", "Size", "Percent", "Bar"],
        [
            [cls, format_size(size), f"{100 * size / total_size:.1f}%" if total_size else "0.0%",
             bar(100 * size / total_size if total_size else 0)]
            for cls, size in size_by_class.most_common()
        ],
        aligns=["l", "r", "r", "l"],
    )

    if verbosity == 1:
        return

    # -vv: body content size, split by the owning content's version/status
    # bucket -- shows how much of the file is "live current text" vs.
    # "historical version bloat" vs. drafts/deleted content bodies.
    bucket_size = Counter()
    for bc_id, size in body_size.items():
        owner_id = body_owner.get(bc_id)
        meta = content_meta.get(owner_id)
        if meta is None:
            bucket = "other/unresolved (e.g. comment bodies)"
        elif meta["has_space"]:
            bucket = f"live {meta['cls']}, status={meta['status']}"
        else:
            bucket = f"historical {meta['cls']} version"
        bucket_size[bucket] += size
    total_body_size = sum(bucket_size.values())

    print_section("Body content size by owning page's version/state")
    print_table(
        ["Bucket", "Size", "Percent", "Bar"],
        [
            [bucket, format_size(size), f"{100 * size / total_body_size:.1f}%" if total_body_size else "0.0%",
             bar(100 * size / total_body_size if total_body_size else 0)]
            for bucket, size in bucket_size.most_common()
        ],
        aligns=["l", "r", "r", "l"],
    )

    # -vv: titles/file names, deduplicated with a version count (rows sharing
    # the same title/name are almost always the same page/attachment across
    # its version history -- title text is what's carried over unchanged).
    for cls, heading in (("Page", "Page titles"), ("BlogPost", "Blog post titles"), ("Attachment", "Attachment file names")):
        ids = [oid for oid, c in owner_class.items() if c == cls]
        if not ids:
            continue
        name_counts = Counter(titles.get(oid, "(untitled)") for oid in ids)
        entries = sorted(name_counts.items(), key=lambda kv: (-kv[1], kv[0]))
        labels = [name if count == 1 else f"{name} ({count} versions)" for name, count in entries]
        print_section(f"{heading} ({len(name_counts)} unique of {len(ids)} rows)")
        print_columns(labels)


def report(entities_path: Path, verbosity: int) -> None:
    with open(entities_path, "rb") as fh:
        data = mmap.mmap(fh.fileno(), 0, access=mmap.ACCESS_READ)
        try:
            idx = build_index(data)
            # ponytail: a raw byte count is all "attachment references in content"
            # needs -- these are <ri:attachment .../> tags inside BodyContent CDATA,
            # no need to parse/unescape the HTML to count them.
            attachment_refs = data[:].count(b"ri:attachment")

            print(f"entities.xml report: {entities_path} ({format_size(len(data))})")
            print_report(idx, attachment_refs, verbosity)
        finally:
            data.close()


def main(argv) -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("entities_xml", type=Path)
    parser.add_argument("-v", "--verbose", action="count", default=0, help="repeat for more detail (-v, -vv)")
    args = parser.parse_args(argv[1:])

    if not args.entities_xml.is_file():
        print(f"Not a file: {args.entities_xml}", file=sys.stderr)
        return 2

    report(args.entities_xml, min(args.verbose, 2))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
