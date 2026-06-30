#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'EOF'
Usage: ./src/Composer/_shell/import.sh --src=/path/to/result/<namespace>

Runs MediaWiki imports from a result namespace directory in this order:
  1) files.xml
  2) blogs.xml
  3) comments.xml
  4) templates.xml
  5) pages.xml

Notes:
- Run this script from the MediaWiki root directory.
- user.xml is intentionally ignored.
EOF
}

src=""

for arg in "$@"; do
  case "$arg" in
    --src=*)
      src="${arg#*=}"
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

run_required() {
  local label="$1"
  local file="$2"
  shift 2

  if [[ ! -f "$file" ]]; then
    echo "Error: required file missing for $label: $file" >&2
    exit 1
  fi

  echo "==> Importing $label from $file"
  if ! "$@"; then
    echo "Error: import failed for $label" >&2
    exit 1
  fi
}

run_required "files.xml" "$src/files.xml" \
  php extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php --src="$src/files.xml"

run_required "blogs.xml" "$src/blogs.xml" \
  php maintenance/importDump.php "$src/blogs.xml"

run_required "comments.xml" "$src/comments.xml" \
  php maintenance/importDump.php "$src/comments.xml"

run_required "templates.xml" "$src/templates.xml" \
  php maintenance/importDump.php "$src/templates.xml"

run_required "pages.xml" "$src/pages.xml" \
  php maintenance/importDump.php "$src/pages.xml"

if [[ -f "$src/user.xml" ]]; then
  echo "Note: user.xml exists at $src/user.xml and is intentionally ignored."
fi

echo "Import completed successfully."