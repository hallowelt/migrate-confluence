#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'EOF'
Usage: ./src/Composer/_shell/wikiimport.sh --wiki-root=/path/to/wiki-root --wiki=<wiki-name> [--src=/path/to/result]

Runs the existing namespace import helper for the shared output and for every
namespace directory inside the selected wiki result directory.

Options:
  --wiki-root=PATH Path to the MediaWiki root directory
  --wiki=NAME      Target wiki name passed through to the namespace import helper
  --src=PATH       Path to the result root directory, defaults to <wiki-root>/result
  --add-default  Also import default-files*.xml and default-pages*.xml if present

Notes:
- Shared output is imported once from --src/<wiki>/_shared when present.
- Each namespace directory under --src/<wiki> is imported independently.
- This script expects the existing ./src/Composer/_shell/import.sh helper to be present.
EOF
}

src=""
wiki=""
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
    --wiki=*)
      wiki="${arg#*=}"
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
  src="$wiki_root/result"
fi

if [[ -z "$wiki" ]]; then
  echo "Error: --wiki is required" >&2
  usage >&2
  exit 1
fi

if [[ ! -d "$wiki_root" ]]; then
  echo "Error: --wiki-root directory does not exist: $wiki_root" >&2
  exit 1
fi

if [[ ! -d "$src" ]]; then
  echo "Error: --src directory does not exist: $src" >&2
  exit 1
fi

shared_dir="$src/$wiki/_shared"
wiki_dir="$src/$wiki"

if [[ -d "$shared_dir" ]]; then
  echo "==> Importing shared output from $shared_dir"
  shared_args=("--wiki-root=$wiki_root" "--src=$shared_dir" "--sfr=$wiki")
  if [[ $add_default -eq 1 ]]; then
    shared_args+=("--add-default")
  fi

  if ! "$script_dir/import.sh" "${shared_args[@]}"; then
    echo "Error: import failed for shared output $shared_dir" >&2
    exit 1
  fi
fi

if [[ ! -d "$wiki_dir" ]]; then
  echo "Error: wiki directory does not exist: $wiki_dir" >&2
  exit 1
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
  echo "==> Importing wiki '$wiki' namespace '$namespace_name' from $namespace_dir"

  args=("--wiki-root=$wiki_root" "--src=$namespace_dir" "--sfr=$wiki")

  if ! "$script_dir/import.sh" "${args[@]}"; then
    echo "Error: import failed for namespace directory $namespace_dir" >&2
    exit 1
  fi
done

echo "Wiki import completed for '$wiki'."