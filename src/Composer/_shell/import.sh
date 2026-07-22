#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'EOF'
Usage: ./src/Composer/_shell/import.sh --src=/path/to/result/<namespace>

Runs MediaWiki imports from a result namespace directory.
Supports both single-file output (e.g. pages.xml) and split output
(e.g. pages-00000001.xml, pages-00000002.xml, ...).

Options:
  --add-default    Also import default-files*.xml, default-pages*.xml and enhanced-sidebar.xml
  --sfr=WIKI       Import into the WIKI wiki

Import order:
  1) files*.xml
  2) blogs*.xml
  3) comments*.xml (or page-talk*.xml + blog-talk*.xml if comments*.xml is absent)
  4) templates*.xml
  5) pages*.xml
  6) enhanced-sidebar.xml (if present and --add-default is set)

When --add-default is set, these are included:
  - default-files*.xml (before files*.xml)
  - default-pages*.xml (before pages*.xml)

Notes:
- Run this script from the MediaWiki root directory.
- user.xml is intentionally ignored.
EOF
}

src=""
sfr=""
add_default=0

for arg in "$@"; do
  case "$arg" in
    --src=*)
      src="${arg#*=}"
      ;;
    --sfr=*)
      # the flag has the same name for the PHP scripts, so we keep the whole
      # argument including the --sfr= part
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

if [[ -z "$src" ]]; then
  echo "Error: --src is required" >&2
  usage >&2
  exit 1
fi

if [[ ! -d "$src" ]]; then
  echo "Error: --src directory does not exist: $src" >&2
  exit 1
fi

# Require execution from MediaWiki root.
if [[ ! -f "maintenance/importDump.php" ]]; then
  echo "Error: maintenance/importDump.php not found in current directory." >&2
  echo "Run this script from the MediaWiki root." >&2
  exit 1
fi

if [[ ! -f "extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php" ]]; then
  echo "Error: extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php not found." >&2
  echo "Make sure BlueSpiceDistributionConnector is installed and run from wiki root." >&2
  exit 1
fi

collect_xml_files() {
  local base="$1"
  local files=()
  local split_candidates=()
  local split_files=()

  if [[ -f "$src/$base.xml" ]]; then
    files+=("$src/$base.xml")
  fi

  shopt -s nullglob
  split_candidates=("$src/$base"-*.xml)
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

  printf "%q\n" "${files[@]}"
}

run_import_dump_file() {
  local file="$1"
  local args=()
  if [[ $sfr ]]; then
    args+=("$sfr")
  fi
  args+=("$file")
  php maintenance/importDump.php "${args[@]}"
}

run_import_files_file() {
  local file="$1"
  local args=()
  if [[ $sfr ]]; then
    args+=("$sfr")
  fi
  args+=("--src=$file")
  php extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php "${args[@]}"
}

run_group() {
  local base="$1"
  local mode="$2"
  local required="$3"
  local files=()

  readarray -t files < <(collect_xml_files "$base")

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
  run_group "default-files" "files" "optional"
fi
run_group "files" "files" "required"
run_group "blogs" "dump" "required"

comment_files=()
collect_xml_files "comments" comment_files
if (( ${#comment_files[@]} > 0 )); then
  run_group "comments" "dump" "required"
else
  run_group "page-talk" "dump" "required"
  run_group "blog-talk" "dump" "required"
fi

run_group "templates" "dump" "required"
if [[ "$add_default" -eq 1 ]]; then
  run_group "default-pages" "dump" "optional"
fi
run_group "pages" "dump" "required"

sidebar_file="$src/enhanced-sidebar.xml"
if [[ "$add_default" -eq 1 ]] && [[ -f "$sidebar_file" ]]; then
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
