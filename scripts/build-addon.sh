#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CS_DIR="$ROOT_DIR/app"
ADDON_ID="branding_text"
ADDON_XML="$CS_DIR/app/addons/$ADDON_ID/addon.xml"
OUT_DIR="$ROOT_DIR/addon"
NO_BUMP=0
if [[ "${1:-}" == "--no-bump" ]]; then
  NO_BUMP=1
fi

if [[ ! -f "$ADDON_XML" ]]; then
  echo "ERROR: addon.xml not found: $ADDON_XML" >&2
  exit 1
fi

mkdir -p "$OUT_DIR"

get_version() {
  # extract <version>...</version>
  sed -nE 's/.*<version>([^<]+)<\/version>.*/\1/p' "$ADDON_XML" | head -n 1
}

inc_patch() {
  local v="$1"
  if [[ ! "$v" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
    echo "0.0.1"
    return
  fi
  local major="${BASH_REMATCH[1]}"
  local minor="${BASH_REMATCH[2]}"
  local patch="${BASH_REMATCH[3]}"
  patch=$((patch + 1))
  echo "$major.$minor.$patch"
}

set_version_in_xml() {
  local new_v="$1"
  # replace first occurrence of <version>...</version>
  perl -0777 -i -pe 's/<version>[^<]*<\/version>/<version>'"$new_v"'<\/version>/s' "$ADDON_XML"
}

CUR_VER="$(get_version)"
if [[ "$NO_BUMP" -eq 1 ]]; then
  NEXT_VER="$CUR_VER"
else
  NEXT_VER="$(inc_patch "$CUR_VER")"
  set_version_in_xml "$NEXT_VER"
fi

OUT_ZIP="$OUT_DIR/${ADDON_ID}-${NEXT_VER}.zip"

TMP_DIR="$(mktemp -d)"
cleanup() {
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

# Build addon package structure relative to CS-Cart root
copy_dir_if_exists() {
  local src="$1"
  local dst="$2"
  if [[ -d "$src" ]]; then
    mkdir -p "$dst"
    cp -a "$src/." "$dst/"
  fi
}

copy_file_if_exists() {
  local src="$1"
  local dst="$2"
  if [[ -f "$src" ]]; then
    mkdir -p "$(dirname "$dst")"
    cp -a "$src" "$dst"
  fi
}

mkdir -p "$TMP_DIR/app/addons/$ADDON_ID"
mkdir -p "$TMP_DIR/js/addons/$ADDON_ID"

# Theme templates (responsive)
mkdir -p "$TMP_DIR/design/themes/responsive/templates/addons/$ADDON_ID"

# Potential future/optional paths (копируем если есть)
mkdir -p "$TMP_DIR/design/themes/responsive/css/addons/$ADDON_ID"
mkdir -p "$TMP_DIR/design/themes/responsive/media/images/addons/$ADDON_ID"

# Copy addon code
copy_dir_if_exists "$CS_DIR/app/addons/$ADDON_ID" "$TMP_DIR/app/addons/$ADDON_ID"

# Copy templates/styles/media
copy_dir_if_exists "$CS_DIR/design/themes/responsive/templates/addons/$ADDON_ID" "$TMP_DIR/design/themes/responsive/templates/addons/$ADDON_ID"
copy_dir_if_exists "$CS_DIR/design/themes/responsive/css/addons/$ADDON_ID" "$TMP_DIR/design/themes/responsive/css/addons/$ADDON_ID"
copy_dir_if_exists "$CS_DIR/design/themes/responsive/media/images/addons/$ADDON_ID" "$TMP_DIR/design/themes/responsive/media/images/addons/$ADDON_ID"

# Copy JS assets
copy_dir_if_exists "$CS_DIR/js/addons/$ADDON_ID" "$TMP_DIR/js/addons/$ADDON_ID"

# Create zip (overwrite)
rm -f "$OUT_ZIP"

if command -v zip >/dev/null 2>&1; then
  (
    cd "$TMP_DIR"
    zip -r -q "$OUT_ZIP" app design js
  )
else
  if ! command -v python3 >/dev/null 2>&1; then
    echo "ERROR: neither 'zip' nor 'python3' is available to create archive" >&2
    exit 1
  fi

  (
    cd "$TMP_DIR"
    OUT_ZIP="$OUT_ZIP" python3 - <<'PY'
import os
import zipfile

out_zip = os.environ['OUT_ZIP']

def add_dir(zf, root):
    for base, dirs, files in os.walk(root):
        for fn in files:
            p = os.path.join(base, fn)
            arc = os.path.relpath(p, '.')
            zf.write(p, arc)

with zipfile.ZipFile(out_zip, 'w', compression=zipfile.ZIP_DEFLATED) as zf:
    for d in ('app', 'design', 'js'):
        if os.path.isdir(d):
            add_dir(zf, d)
PY
  )
fi

# Keep only last 3 versioned archives
ls -1 "$OUT_DIR"/${ADDON_ID}-*.zip 2>/dev/null | sort -V | head -n -3 | xargs -r rm -f

echo "OK: built $OUT_ZIP"
echo "Version: $CUR_VER -> $NEXT_VER"
