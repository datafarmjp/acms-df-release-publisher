#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  tools/sync-product-docs.sh CONFIG_JSON

Environment variables for optional SFTP sync:
  DF_PRODUCT_DOCS_SYNC_ENABLED=1
  DF_PRODUCT_DOCS_SYNC_HOST
  DF_PRODUCT_DOCS_SYNC_USER
  DF_PRODUCT_DOCS_THEME_REMOTE_PATH
  DF_PRODUCT_DOCS_SYNC_PORT optional, defaults to 22

Example remote path:
  /home/example/public_html/info.example.jp/themes/datafarm@member
USAGE
}

if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
  usage
  exit 0
fi

CONFIG_JSON="${1:-}"
if [ -z "$CONFIG_JSON" ]; then
  usage >&2
  exit 1
fi
if [ ! -f "$CONFIG_JSON" ]; then
  echo "Config JSON was not found: $CONFIG_JSON" >&2
  exit 1
fi

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PLUGINS_DIR="$(dirname "$ROOT_DIR")"
PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
if [ -z "$PHP_BIN" ] && [ -d /Applications/MAMP/bin/php ]; then
  PHP_BIN="$(find /Applications/MAMP/bin/php -path '*/bin/php' -type f | sort -V | tail -1 || true)"
fi
if [ -z "$PHP_BIN" ] || [ ! -x "$PHP_BIN" ]; then
  echo "php command was not found." >&2
  exit 1
fi

BASE_PATH="$("$PHP_BIN" -r '
$json = json_decode(file_get_contents($argv[1]), true);
$base = $json["df_release_docs"]["theme_base_path"] ?? "_df-product-docs";
$base = trim((string)$base, " \t\n\r\0\x0B/");
$parts = array_values(array_filter(explode("/", $base), static fn($part) => $part !== "" && $part !== "." && $part !== ".." && preg_match("/^[A-Za-z0-9_.-]+$/", $part)));
echo implode("/", $parts);
' "$CONFIG_JSON")"
if [ -z "$BASE_PATH" ]; then
  BASE_PATH="_df-product-docs"
fi

OUTPUT_DIR="/private/tmp/df-product-docs/$BASE_PATH"
rm -rf "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR"

"$PHP_BIN" "$ROOT_DIR/tools/generate-product-docs.php" \
  --config "$CONFIG_JSON" \
  --plugins-dir "$PLUGINS_DIR" \
  --output-dir "$OUTPUT_DIR"

echo "Product docs generated: $OUTPUT_DIR"

if [ "${DF_PRODUCT_DOCS_SYNC_ENABLED:-}" != "1" ]; then
  echo "Product docs sync skipped. Set DF_PRODUCT_DOCS_SYNC_ENABLED=1 to enable it."
  exit 0
fi

if ! command -v sftp >/dev/null 2>&1; then
  echo "sftp command was not found. Product docs were generated, but sync failed." >&2
  exit 1
fi

: "${DF_PRODUCT_DOCS_SYNC_HOST:?DF_PRODUCT_DOCS_SYNC_HOST is required when DF_PRODUCT_DOCS_SYNC_ENABLED=1}"
: "${DF_PRODUCT_DOCS_SYNC_USER:?DF_PRODUCT_DOCS_SYNC_USER is required when DF_PRODUCT_DOCS_SYNC_ENABLED=1}"
: "${DF_PRODUCT_DOCS_THEME_REMOTE_PATH:?DF_PRODUCT_DOCS_THEME_REMOTE_PATH is required when DF_PRODUCT_DOCS_SYNC_ENABLED=1}"

PORT="${DF_PRODUCT_DOCS_SYNC_PORT:-22}"
REMOTE_BASE="${DF_PRODUCT_DOCS_THEME_REMOTE_PATH%/}/$BASE_PATH"
BATCH_FILE="/private/tmp/df-product-docs-sftp.batch"

{
  echo "-mkdir $REMOTE_BASE"
  find "$OUTPUT_DIR" -mindepth 1 -type d | sort | while IFS= read -r dir; do
    rel="${dir#$OUTPUT_DIR/}"
    echo "-mkdir $REMOTE_BASE/$rel"
  done
  find "$OUTPUT_DIR" -type f | sort | while IFS= read -r file; do
    rel="${file#$OUTPUT_DIR/}"
    echo "put $file $REMOTE_BASE/$rel"
  done
} >"$BATCH_FILE"

if sftp -P "$PORT" -b "$BATCH_FILE" "$DF_PRODUCT_DOCS_SYNC_USER@$DF_PRODUCT_DOCS_SYNC_HOST"; then
  rm -f "$BATCH_FILE"
  echo "Product docs synced: $REMOTE_BASE"
  exit 0
fi

rm -f "$BATCH_FILE"
echo "Product docs sync failed." >&2
exit 1
