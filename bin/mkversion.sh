#!/bin/bash

set -euo pipefail

cd "$(dirname "$0")"

COMMIT_HASH="$(git rev-parse HEAD)"

case "${1:-}" in
    "--clean")
        printf 'dev-unversioned' > ../VERSION
        ;;
    "--from-env")
        printf "${GITHUB_REF_NAME:-dev-no-env-$COMMIT_HASH}" > ../VERSION
        ;;
    "")
        printf 'dev-%s' "$COMMIT_HASH" > ../VERSION
        ;;
    *)
        echo "Usage: $(basename "$0") [--clean|--from-env]" >&2
        exit 1
        ;;
esac