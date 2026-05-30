#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:-0.3.3}"
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
if [ -z "$PHP_BIN" ] && [ -d /Applications/MAMP/bin/php ]; then
  PHP_BIN="$(find /Applications/MAMP/bin/php -path '*/bin/php' -type f | sort -V | tail -1 || true)"
fi

cd "$ROOT_DIR"

if [ -z "$PHP_BIN" ] || [ ! -x "$PHP_BIN" ]; then
  echo "php command was not found." >&2
  exit 1
fi

if ! grep -Fq "public const VERSION = '$VERSION';" ServiceProvider.php; then
  echo "ServiceProvider.php does not contain public const VERSION = '$VERSION';" >&2
  exit 1
fi

if [ ! -f RELEASE_MANIFEST.txt ]; then
  echo "RELEASE_MANIFEST.txt was not found." >&2
  exit 1
fi

while IFS= read -r path; do
  [ -n "$path" ] || continue
  case "$path" in
    \#*|@project*) continue ;;
  esac
  path="${path#./}"
  if [ ! -f "$path" ]; then
    echo "Manifest file is missing: $path" >&2
    exit 1
  fi
done < RELEASE_MANIFEST.txt

find . -path './.git' -prune -o -path './tools' -prune -o -name '*.php' -print0 \
  | xargs -0 -n 1 "$PHP_BIN" -l >/tmp/df_release_publisher_release_check.out 2>&1

rm -f /tmp/df_release_publisher_release_check.out
echo "release check passed"
