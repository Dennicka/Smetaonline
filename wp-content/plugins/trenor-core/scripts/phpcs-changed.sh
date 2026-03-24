#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
REPO_ROOT="$(cd "${PLUGIN_DIR}/../../.." && pwd)"

BASE_REF="${PHPCS_BASE_REF:-origin/main}"
if ! git -C "${REPO_ROOT}" rev-parse --verify --quiet "${BASE_REF}" >/dev/null; then
  BASE_REF="HEAD~1"
fi

mapfile -t CHANGED_FILES < <(
  git -C "${REPO_ROOT}" diff --name-only --diff-filter=ACMR "${BASE_REF}"...HEAD -- '*.php' \
    | grep '^wp-content/plugins/trenor-core/' \
    | sed 's#^wp-content/plugins/trenor-core/##' \
    | grep -v '^vendor/' || true
)

if [ "${#CHANGED_FILES[@]}" -eq 0 ]; then
  echo "No changed PHP files for phpcs check."
  exit 0
fi

cd "${PLUGIN_DIR}"
printf 'Running PHPCS on changed files (%s):\n' "${#CHANGED_FILES[@]}"
printf ' - %s\n' "${CHANGED_FILES[@]}"

./vendor/bin/phpcs --standard=phpcs.xml.dist "${CHANGED_FILES[@]}"
