#!/usr/bin/env bash

# Imports the migration output of a single namespace directory into a MediaWiki
# installation.
#
# Expected layout of the migration output:
#   result/<namespace>/{files,blogs,page-talk,blog-talk,templates,pages}.xml
#   result/_shared/{default-files,default-pages}.xml
#
# The files in "result/_shared" are only imported when --add-default is set.

set -euo pipefail

usage() {
  cat <<'EOF'
Usage: ./spaceimport.sh --wiki-root=/path/to/wiki-root [--src=/path/to/result/<namespace>] [--add-default] [--dry] [--sfr=<wiki-instance>]

Runs MediaWiki imports for a single namespace directory of the migration result.
Supports both single-file output (e.g. pages.xml) and split output
(e.g. pages-00000001.xml, pages-00000002.xml, ...).

Options:
  --wiki-root=PATH  Path to the MediaWiki root directory
  --src=PATH        Namespace directory to import (defaults to this script's directory)
  --add-default     Also import default-files*.xml and default-pages*.xml from <src>/../_shared
  --dry             Dry run, only print the import commands instead of running them
  --sfr=NAME        MediaWiki wiki instance passed to the import maintenance scripts

Import order:
  1) _shared/default-files*.xml  (only with --add-default)
  2) _shared/default-pages*.xml  (only with --add-default)
  3) files*.xml
  4) templates*.xml
  5) blogs*.xml
  6) pages*.xml
  7) page-talk*.xml
  8) blog-talk*.xml
  9) enhanced-sidebar*.xml

Notes:
- Only pages*.xml is mandatory, all other groups are skipped when missing.
- user.xml is intentionally ignored.
EOF
}

# --- Argument parsing --------------------------------------------------------

src=""
sfr=""
add_default=0
dry=0
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
      # Passed through unchanged, the maintenance scripts expect "--sfr=<name>"
      sfr="${arg}"
      ;;
    --add-default)
      add_default=1
      ;;
    --dry)
      dry=1
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

# Without --src the script assumes it lives inside the namespace directory.
if [[ -z "$src" ]]; then
  src="$script_dir"
fi
src="${src%/}"

if [[ ! -d "$src" ]]; then
  echo "Error: --src directory does not exist: $src" >&2
  exit 1
fi

# "_shared" is a sibling of the namespace directory: result/_shared
shared_dir="$(cd "$src/.." && pwd)/_shared"

if [[ "$add_default" -eq 1 && ! -d "$shared_dir" ]]; then
  echo "Warning: --add-default is set but the shared directory does not exist: $shared_dir" >&2
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

# Lists all XML files belonging to one group, in import order:
# "<base>.xml" first, followed by the numbered split files "<base>-<number>.xml".
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
  if (( dry == 1 )); then
    echo "DRY RUN: cd $wiki_root && php maintenance/importDump.php ${args[*]}"
    return 0
  fi
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
  if (( dry == 1 )); then
    echo "DRY RUN: cd $wiki_root && php extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php ${args[*]}"
    return 0
  fi
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
      exit 1
    fi
    echo "Note: no $base.xml found in $source_dir, skipping."
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

# Imports a group from the shared directory, but only with --add-default.
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

# --- Import ------------------------------------------------------------------

echo "==> Importing namespace directory $src"

# Media first, so that pages referencing them already find their files.
run_default_group "default-files" "files"
# Default pages are imported before the migrated pages, so migrated
# content wins in case of a title collision.
run_default_group "default-pages" "dump"

run_group "$src" "files" "files" "optional"
run_group "$src" "templates" "dump" "optional"
run_group "$src" "blogs" "dump" "optional"
run_group "$src" "pages" "dump" "required"
run_group "$src" "page-talk" "dump" "optional"
run_group "$src" "blog-talk" "dump" "optional"
run_group "$src" "enhanced-sidebar" "dump" "optional"

if [[ -f "$src/user.xml" ]]; then
  echo "Note: user.xml exists at $src/user.xml and is intentionally ignored."
fi

if (( dry == 1 )); then
  echo "Dry run completed, no data was imported."
else
  echo "Import completed successfully."
fi
