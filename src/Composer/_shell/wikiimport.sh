#!/usr/bin/env bash

# Imports the migration output of one wiki into a MediaWiki installation.
#
# Expected layout of the migration output:
#   result/<wiki-name>/<namespace>/{files,blogs,page-talk,blog-talk,templates,pages}.xml
#   result/<wiki-name>/_shared/{default-files,default-pages}.xml
#
# The files in "_shared" are only imported when --add-default is set.

set -euo pipefail

usage() {
  cat <<'EOF'
Usage: ./wikiimport.sh --wiki-root=/path/to/wiki-root [--src=/path/to/result/<wiki-name>] [--add-default] [--sfr=<wiki-instance>]

Imports every namespace directory inside the selected wiki result directory.
Supports both single-file output (e.g. pages.xml) and split output
(e.g. pages-00000001.xml, pages-00000002.xml, ...).

Options:
  --wiki-root=PATH  Path to the MediaWiki root directory
  --src=PATH        Wiki result directory (defaults to this script's directory)
  --add-default     Also import default-files*.xml and default-pages*.xml from <src>/_shared
  --sfr=NAME        MediaWiki wiki instance passed to the import maintenance scripts

Import order per namespace directory:
  1) _shared/default-files*.xml  (only with --add-default, once per wiki)
  2) files*.xml
  3) blogs*.xml
  4) page-talk*.xml
  5) blog-talk*.xml
  6) templates*.xml
  7) _shared/default-pages*.xml  (only with --add-default, once per wiki)
  8) pages*.xml
  9) enhanced-sidebar*.xml

Notes:
- Only pages*.xml is mandatory, all other groups are skipped when missing.
- user.xml is intentionally ignored.
EOF
}

# --- Argument parsing --------------------------------------------------------

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
      # Passed through unchanged, the maintenance scripts expect "--sfr=<name>"
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

# Without --src the script assumes it lives inside the wiki result directory.
if [[ -z "$src" ]]; then
  src="$script_dir"
fi
src="${src%/}"

wiki_name="$(basename "$src")"
shared_dir="$src/_shared"

# --- Validation --------------------------------------------------------------

if [[ ! -d "$wiki_root" ]]; then
  echo "Error: --wiki-root directory does not exist: $wiki_root" >&2
  exit 1
fi

if [[ ! -d "$src" ]]; then
  echo "Error: --src directory does not exist: $src" >&2
  exit 1
fi

if [[ "$add_default" -eq 1 && ! -d "$shared_dir" ]]; then
  echo "Warning: --add-default is set but the shared directory does not exist: $shared_dir" >&2
fi

if [[ ! -f "$wiki_root/maintenance/importDump.php" ]]; then
  echo "Error: maintenance/importDump.php not found in wiki root: $wiki_root" >&2
  exit 1
fi

if [[ ! -f "$wiki_root/extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php" ]]; then
  echo "Error: extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php not found in wiki root: $wiki_root" >&2
  exit 1
fi

# --- Helpers -----------------------------------------------------------------

# Lists all XML files belonging to one group, in import order:
# "<base>.xml" first, followed by the numbered split files "<base>-<number>.xml".
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

# Imports a wiki page XML dump.
run_import_dump_file() {
  local file="$1"
  local args=()
  if [[ -n "$sfr" ]]; then
    args+=("$sfr")
  fi
  args+=("$file")
  ( cd "$wiki_root" && php maintenance/importDump.php "${args[@]}" )
}

# Imports a file (media) XML dump.
run_import_files_file() {
  local file="$1"
  local args=()
  if [[ -n "$sfr" ]]; then
    args+=("$sfr")
  fi
  args+=("--src=$file")
  ( cd "$wiki_root" && php extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php "${args[@]}" )
}

# Imports one group of XML files.
#   $1 directory, $2 file base name, $3 mode ("files"|"dump"), $4 "required"|"optional"
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
    echo "Note: no $base.xml found in $source_dir, skipping."
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

# Imports a group from <src>/_shared, but only with --add-default.
run_default_group() {
  local base="$1"
  local mode="$2"

  if (( add_default == 0 )); then
    return 0
  fi

  if [[ ! -d "$shared_dir" ]]; then
    echo "Warning: shared directory not found, skipping $base: $shared_dir" >&2
    return 0
  fi

  run_group "$shared_dir" "$base" "$mode" "optional"
}

# Imports all XML groups of a single namespace directory.
import_namespace_directory() {
  local source_dir="$1"

  run_group "$source_dir" "files" "files" "optional"
  run_group "$source_dir" "blogs" "dump" "optional"
  run_group "$source_dir" "page-talk" "dump" "optional"
  run_group "$source_dir" "blog-talk" "dump" "optional"
  run_group "$source_dir" "templates" "dump" "optional"
  run_group "$source_dir" "pages" "dump" "required"
  run_group "$source_dir" "enhanced-sidebar" "dump" "optional"

  if [[ -f "$source_dir/user.xml" ]]; then
    echo "Note: user.xml exists at $source_dir/user.xml and is intentionally ignored."
  fi
}

# --- Import ------------------------------------------------------------------

# Default media are imported once per wiki, before any namespace content, so
# that pages referencing them already find their files.
run_default_group "default-files" "files"

# The wiki wide sidebar lives next to the namespace directories.
sidebar_file="$src/enhanced-sidebar.xml"
if [[ -f "$sidebar_file" ]]; then
  echo "==> Importing wiki sidebar from $sidebar_file"
  if ! run_import_dump_file "$sidebar_file"; then
    echo "Error: import failed for wiki sidebar $sidebar_file" >&2
    exit 1
  fi
fi

# Default pages are imported before the migrated pages, so migrated content
# wins in case of a title collision.
run_default_group "default-pages" "dump"

shopt -s nullglob
namespace_dirs=("$src"/*/)
shopt -u nullglob

if (( ${#namespace_dirs[@]} == 0 )); then
  echo "Error: no namespace directories found in $src" >&2
  exit 1
fi

for namespace_dir in "${namespace_dirs[@]}"; do
  namespace_dir="${namespace_dir%/}"
  namespace_name="$(basename "$namespace_dir")"

  # "_shared" is not a namespace, it is handled by run_default_group.
  if [[ "$namespace_name" == "_shared" ]]; then
    continue
  fi

  echo "==> Importing wiki '$wiki_name' namespace '$namespace_name' from $namespace_dir"
  if ! import_namespace_directory "$namespace_dir"; then
    echo "Error: import failed for namespace directory $namespace_dir" >&2
    exit 1
  fi
done

echo "Wiki import completed for '$wiki_name'."