#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'EOF'
Usage: ./wikiimport.sh --wiki-root=/path/to/wiki-root [--src=/path/to/result/<wiki>] [--sfr=<wiki-instance>]

Imports shared output and every namespace directory inside the selected wiki
result directory.

Options:
  --wiki-root=PATH Path to the MediaWiki root directory
  --src=PATH       Wiki result directory (defaults to this script's directory)
  --sfr=NAME       MediaWiki wiki instance passed to the import maintenance scripts
  --add-default    Also import default-files*.xml and default-pages*.xml from _shared

Notes:
- When --add-default is set, shared output is imported once from --src/_shared.
- Each namespace directory under --src is imported independently.
EOF
}

src=""
sfr=""
wiki_root=""
add_default=0
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
      sfr="$arg"
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

wiki_name="$(basename "${src%/}")"

if [[ ! -d "$wiki_root" ]]; then
  echo "Error: --wiki-root directory does not exist: $wiki_root" >&2
  exit 1
fi

if [[ ! -d "$src" ]]; then
  echo "Error: --src directory does not exist: $src" >&2
  exit 1
fi

wiki_dir="$src"
shared_dir="$wiki_dir/_shared"

if [[ ! -d "$wiki_dir" ]]; then
  echo "Error: wiki directory does not exist: $wiki_dir" >&2
  exit 1
fi

if [[ ! -f "$wiki_root/maintenance/importDump.php" ]]; then
  echo "Error: maintenance/importDump.php not found in wiki root: $wiki_root" >&2
  exit 1
fi

if [[ ! -f "$wiki_root/extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php" ]]; then
  echo "Error: extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php not found in wiki root: $wiki_root" >&2
  exit 1
fi

collect_xml_files() {
  local base_dir="$1"
  local base="$2"
  local files=()
  local split_candidates=()
  local split_files=()

  if [[ -f "$base_dir/$base.xml" ]]; then
    files+=("$base_dir/$base.xml")
  fi

  shopt -s nullglob
  split_candidates=("$base_dir/$base"-*.xml)
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
    printf '%s\n' "${files[@]}"
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

  mapfile -t files < <(collect_xml_files "$source_dir" "$base")
  if (( ${#files[@]} == 0 )); then
    if [[ "$required" == "required" ]]; then
      echo "Error: required file group missing in $source_dir: $base.xml or $base-<number>.xml" >&2
      return 1
    fi
    return 0
  fi

  for file in "${files[@]}"; do
    echo "==> Importing $base from $file"
    if [[ "$mode" == "files" ]]; then
      run_import_files_file "$file"
    else
      run_import_dump_file "$file"
    fi
  done
}

import_shared_directory() {
  local source_dir="$1"
  run_group "$source_dir" "default-files" "files" "optional"
  run_group "$source_dir" "default-pages" "dump" "optional"
}

import_namespace_directory() {
  local source_dir="$1"

  run_group "$source_dir" "files" "files" "required"
  run_group "$source_dir" "blogs" "dump" "required"

  local comment_files=()
  mapfile -t comment_files < <(collect_xml_files "$source_dir" "comments")
  if (( ${#comment_files[@]} > 0 )); then
    run_group "$source_dir" "comments" "dump" "required"
  else
    run_group "$source_dir" "page-talk" "dump" "required"
    run_group "$source_dir" "blog-talk" "dump" "required"
  fi

  run_group "$source_dir" "templates" "dump" "required"
  run_group "$source_dir" "pages" "dump" "required"
  run_group "$source_dir" "enhanced-sidebar" "dump" "optional"
}

if [[ "$add_default" -eq 1 && -d "$shared_dir" ]]; then
  echo "==> Importing shared output from $shared_dir"
  import_shared_directory "$shared_dir"
fi

sidebar_file="$wiki_dir/enhanced-sidebar.xml"
if [[ -f "$sidebar_file" ]]; then
  echo "==> Importing wiki sidebar from $sidebar_file"
  if ! run_import_dump_file "$sidebar_file"; then
    echo "Error: import failed for wiki sidebar $sidebar_file" >&2
    exit 1
  fi
fi

shopt -s nullglob
namespace_dirs=("$wiki_dir"/*/)
shopt -u nullglob

if (( ${#namespace_dirs[@]} == 0 )); then
  echo "Error: no namespace directories found in $wiki_dir" >&2
  exit 1
fi

for namespace_dir in "${namespace_dirs[@]}"; do
  namespace_name="$(basename "${namespace_dir%/}")"
  if [[ "$namespace_name" == "_shared" ]]; then
    continue
  fi
  echo "==> Importing wiki '$wiki_name' namespace '$namespace_name' from $namespace_dir"

  if ! import_namespace_directory "${namespace_dir%/}"; then
    echo "Error: import failed for namespace directory $namespace_dir" >&2
    exit 1
  fi
done

echo "Wiki import completed for '$wiki_name'."