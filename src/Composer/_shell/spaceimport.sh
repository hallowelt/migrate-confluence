#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'EOF'
Usage: ./spaceimport.sh --wiki-root=/path/to/wiki-root [--src=/path/to/result/<namespace>] [--sfr=<wiki-instance>]

Runs MediaWiki imports from a result namespace directory.
Supports both single-file output (e.g. pages.xml) and split output
(e.g. pages-00000001.xml, pages-00000002.xml, ...).

Options:
  --wiki-root=PATH Path to the MediaWiki root directory
  --src=PATH       Result namespace directory (defaults to this script's directory)
  --add-default    Also import default-files*.xml and default-pages*.xml from _shared
  --sfr=NAME       MediaWiki wiki instance passed to the import maintenance scripts

Import order:
  1) files*.xml
  2) blogs*.xml
  3) comments*.xml (or page-talk*.xml + blog-talk*.xml if comments*.xml is absent)
  4) templates*.xml
  5) pages*.xml
  6) enhanced-sidebar.xml (if present)

When --add-default is set, these are included:
  - _shared/default-files*.xml (before files*.xml)
  - _shared/default-pages*.xml (before pages*.xml)

Notes:
- Run this script from the MediaWiki root directory.
- user.xml is intentionally ignored.
EOF
}

src=""
sfr=""
add_default=0
wiki_root=""
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

for arg in "$@"; do
  case "$arg" in
    --wiki-root=*)
      wiki_root="${arg#*=}"
      ;;
    --src=*)
      src="${arg#*=}"
      ;;
    --sfr=*)
      sfr="${arg}"
      ;;
    --add-default)
      add_default=1
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Error: unknown argument: $arg" >&2
      usage >&2
      exit 1
      ;;
  esac
done

if [[ -z "$wiki_root" ]]; then
  echo "Error: --wiki-root is required" >&2
  usage >&2
  exit 1
fi

if [[ -z "$src" ]]; then
  src="$script_dir"
fi

if [[ ! -d "$src" ]]; then
  echo "Error: --src directory does not exist: $src" >&2
  exit 1
fi

if [[ ! -d "$wiki_root" ]]; then
  echo "Error: --wiki-root directory does not exist: $wiki_root" >&2
  exit 1
fi

if [[ ! -f "$wiki_root/maintenance/importDump.php" ]]; then
  echo "Error: maintenance/importDump.php not found in wiki root: $wiki_root" >&2
  echo "Make sure you passed the correct wiki root directory." >&2
  exit 1
fi

if [[ ! -f "$wiki_root/extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php" ]]; then
  echo "Error: extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php not found in wiki root: $wiki_root" >&2
  echo "Make sure BlueSpiceDistributionConnector is installed and the wiki root path is correct." >&2
  exit 1
fi

collect_xml_files() {
  local source_dir="$1"
  local base="$2"
  local files=()
  local split_candidates=()
  local split_files=()

  if [[ -f "$source_dir/$base.xml" ]]; then
    files+=("$source_dir/$base.xml")
  fi

  shopt -s nullglob
  split_candidates=("$source_dir/$base"-*.xml)
  shopt -u nullglob

  for file in "${split_candidates[@]}"; do
    if [[ "$(basename "$file")" =~ ^${base}-[0-9]+\.xml$ ]]; then
      split_files+=("$file")
    fi
  done

  if (( ${#split_files[@]} > 0 )); then
    mapfile -t split_files < <(printf '%s\n' "${split_files[@]}" | sort -V)
    files+=("${split_files[@]}")
  fi

  if (( ${#files[@]} > 0 )); then
    printf "%q\n" "${files[@]}"
  fi
}

run_import_dump_file() {
  local file="$1"
  local args=()
  if [[ -n "$sfr" ]]; then
    args+=("$sfr")
  fi
  args+=("$file")
  ( cd "$wiki_root" && php maintenance/importDump.php "${args[@]}" )
}

run_import_files_file() {
  local file="$1"
  local args=()
  if [[ -n "$sfr" ]]; then
    args+=("$sfr")
  fi
  args+=("--src=$file")
  ( cd "$wiki_root" && php extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php "${args[@]}" )
}

run_group() {
  local source_dir="$1"
  local base="$2"
  local mode="$3"
  local required="$4"
  local files=()

  readarray -t files < <(collect_xml_files "$source_dir" "$base")

  if (( ${#files[@]} == 0 )); then
    if [[ "$required" == "required" ]]; then
      echo "Error: required file group missing: $base.xml or $base-<number>.xml" >&2
      exit 1
    fi
    echo "Note: no $base.xml or split variants found, skipping optional group."
    return 0
  fi

  for file in "${files[@]}"; do
    echo "==> Importing $base from $file"
    if [[ "$mode" == "files" ]]; then
      if ! run_import_files_file "$file"; then
        echo "Error: import failed for $file" >&2
        exit 1
      fi
    else
      if ! run_import_dump_file "$file"; then
        echo "Error: import failed for $file" >&2
        exit 1
      fi
    fi
  done
}

if [[ "$add_default" -eq 1 ]]; then
  shared_dir="$src/_shared"
  if [[ -d "$shared_dir" ]]; then
    echo "==> Importing shared default data from $shared_dir"
    run_group "$shared_dir" "default-files" "files" "optional"
    run_group "$shared_dir" "default-pages" "dump" "optional"
  fi
fi
run_group "$src" "files" "files" "required"
run_group "$src" "blogs" "dump" "required"

comment_files=()
readarray -t comment_files < <(collect_xml_files "$src" "comments")
if (( ${#comment_files[@]} > 0 )); then
  run_group "$src" "comments" "dump" "required"
else
  run_group "$src" "page-talk" "dump" "required"
  run_group "$src" "blog-talk" "dump" "required"
fi

run_group "$src" "templates" "dump" "required"
run_group "$src" "pages" "dump" "required"

sidebar_file="$src/enhanced-sidebar.xml"
if [[ -f "$sidebar_file" ]]; then
  echo "==> Importing sidebar from $sidebar_file"
  if ! run_import_dump_file "$sidebar_file"; then
    echo "Error: import failed for $sidebar_file" >&2
    exit 1
  fi
fi

if [[ -f "$src/user.xml" ]]; then
  echo "Note: user.xml exists at $src/user.xml and is intentionally ignored."
fi

echo "Import completed successfully."
