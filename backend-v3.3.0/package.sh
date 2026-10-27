#!/bin/bash
# Builds the shippable Laravel application: allow-listed code + a freshly
# installed production vendor/ + an empty storage skeleton. Run from the project root:
#   ./package.sh              interactive
#   ./package.sh --defaults   no prompts, use the defaults shown below

set -e
cd "$(dirname "$0")"

OUT="codecanyon-package"

# Only these root entries ship. Anything new in the project root is excluded
# until it is added here on purpose.
INCLUDE=(
  app artisan bootstrap composer.json composer.lock config database
  public resources routes
  .env.example .htaccess README.md
)

# Pruned from inside the allow-listed trees: build-machine runtime residue.
# Unanchored on purpose — with several rsync sources a leading / matches nothing.
PRUNE=(
  .DS_Store
  public/storage
  storage/debugbar
  storage/dev
  'storage/app/public/*'
  'storage/logs/*.log'
  storage/installed
  storage/app/license.json
  storage/app/license.json.backup
  storage/app/installer-progress.json
  storage/app/installer-progress.lock
  'storage/framework/cache/data/*'
  'storage/framework/sessions/*'
  'storage/framework/views/*'
  'bootstrap/cache/*.php'
)

env_value() { sed -nE "s/^$1=[\"']?(.*[^\"'])[\"']?$/\1/p" .env.example | head -1; }

set_env() {
  local file=$1 key=$2 value=$3
  case "$value" in *' '*) value="\"$value\"";; esac
  if grep -qE "^$key=" "$file"; then
    sed -i.bak -E "s|^$key=.*|$key=$value|" "$file"
  else
    printf '\n%s=%s\n' "$key" "$value" >> "$file"
  fi
  rm -f "$file.bak"
}

ask() {
  local prompt=$1 default=$2 answer=""
  if [ "$INTERACTIVE" = 1 ]; then read -r -p "$prompt [$default]: " answer; fi
  echo "${answer:-$default}"
}

INTERACTIVE=${INTERACTIVE:-$([ -t 0 ] && echo 1 || echo 0)}
[ "$1" = "--defaults" ] && INTERACTIVE=0

APP_NAME=$(ask "App name" "$(env_value APP_NAME)")
APP_VERSION=$(ask "Version" "$(env_value APP_VERSION)")
case "$(ask "APP_ENV — production (p) / local (l)" "p")" in
  l|L|local) APP_ENV=local; APP_DEBUG=true;;
  *)         APP_ENV=production; APP_DEBUG=false;;
esac
MAKE_ZIP=$(ask "Create the zip afterwards? (y/n)" "y")

for entry in "${INCLUDE[@]}"; do
  [ -e "$entry" ] || { echo "FAIL — allow-listed entry missing: $entry"; exit 1; }
done

RSYNC_ARGS=()
for p in "${PRUNE[@]}"; do RSYNC_ARGS+=(--exclude "$p"); done

rm -rf "$OUT"
mkdir -p "$OUT"
rsync -a "${RSYNC_ARGS[@]}" "${INCLUDE[@]}" "$OUT/"

BUILD_DIR=$(mktemp -d)
trap 'rm -rf "$BUILD_DIR"' EXIT
cp composer.json composer.lock "$BUILD_DIR/"
[ ! -d packages ] || cp -R packages "$BUILD_DIR/"
cp -R app database "$BUILD_DIR/"
php composer.phar --no-plugins validate --no-check-publish
php composer.phar install --working-dir="$BUILD_DIR" --no-dev --prefer-dist --no-interaction --no-scripts --no-plugins --optimize-autoloader
mv "$BUILD_DIR/vendor" "$OUT/vendor"

# storage/ ships with the same folder structure as this project — only runtime
# residue is pruned above. These dirs must exist even when empty, or Laravel
# fails to boot.
for d in app/private app/public framework/cache/data framework/sessions \
         framework/testing framework/views logs; do
  mkdir -p "$OUT/storage/$d"
done
mkdir -p "$OUT/bootstrap/cache"

# Laravel needs a .env at boot, before the installer route is reachable, so the
# buyer gets one seeded from .env.example. The installer then rewrites the DB
# block and APP_URL into it. Both files get the same edits, so the "shipped .env
# is the example verbatim" check below still holds.
cp "$OUT/.env.example" "$OUT/.env"
APP_KEY=""
for f in "$OUT/.env" "$OUT/.env.example"; do
  set_env "$f" APP_NAME "$APP_NAME"
  set_env "$f" APP_ENV "$APP_ENV"
  set_env "$f" APP_VERSION "$APP_VERSION"
  set_env "$f" APP_DEBUG "$APP_DEBUG"
  set_env "$f" APP_KEY "$APP_KEY"
done

fail=0

# The isolated production vendor build may contain package fixtures and CA bundles.
leaked=$(find "$OUT" -path "$OUT/vendor" -prune -o \
  \( -name '*.pem' -o -name '*.key' -o -name '*serviceAccount*.json' \) -print 2>/dev/null)
if [ -n "$leaked" ]; then echo "FAIL — secrets in package:"; echo "$leaked"; fail=1; fi

# The shipped .env must be the example verbatim — never this machine's real one.
if ! cmp -s "$OUT/.env" "$OUT/.env.example"; then
  echo "FAIL — .env is not a copy of .env.example (real credentials?)"; fail=1
fi

for key in APP_NAME APP_ENV APP_VERSION APP_KEY APP_DEBUG; do
  grep -qE "^$key=" "$OUT/.env" || { echo "FAIL — $key missing from shipped .env"; fail=1; }
done
if [ "$APP_ENV" = production ] && ! grep -qE '^APP_DEBUG=false$' "$OUT/.env"; then
  echo "FAIL — APP_DEBUG is not false in a production package"; fail=1
fi

debug=$(grep -rnE '(^|[^a-zA-Z_>$])(dd|dump|ray|var_dump)\(' \
  "$OUT/app" "$OUT/routes" "$OUT/resources/views" 2>/dev/null | head)
if [ -n "$debug" ]; then echo "FAIL — debug calls in shipped code:"; echo "$debug"; fail=1; fi

# A leftover marker makes the buyer's installer skip itself.
[ -e "$OUT/storage/installed" ] && { echo "FAIL — storage/installed present"; fail=1; }

# This symlink points at an absolute path on the build machine.
[ -L "$OUT/public/storage" ] && { echo "FAIL — public/storage symlink shipped"; fail=1; }

residue=$(find "$OUT/storage" \( -name '*.log' -o -path '*/debugbar/*' \
  -o -path '*/framework/sessions/*' -o -path '*/framework/views/*' \) -type f 2>/dev/null)
if [ -n "$residue" ]; then echo "FAIL — runtime residue in storage:"; echo "$residue" | head; fail=1; fi

[ -e "$OUT/storage/dev" ] && { echo "FAIL — storage/dev shipped"; fail=1; }

uploads=$(find "$OUT/storage/app/public" -type f 2>/dev/null)
if [ -n "$uploads" ]; then
  echo "FAIL — storage/app/public must be empty, found:"; echo "$uploads" | head; fail=1
fi

for d in storage/app/public storage/framework/views storage/logs bootstrap/cache; do
  [ -d "$OUT/$d" ] || { echo "FAIL — missing $d"; fail=1; }
done

for f in vendor/autoload.php public/index.php artisan composer.json .env.example \
         app config database routes resources; do
  [ -e "$OUT/$f" ] || { echo "FAIL — missing $f"; fail=1; }
done

[ "$fail" -eq 0 ] || { echo; echo "Package NOT safe to ship."; exit 1; }

slug=$(echo "$APP_NAME" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-|-$//g')
if [ "$MAKE_ZIP" = y ]; then
  zip_name="$slug-v$APP_VERSION.zip"
  rm -f "$zip_name"
  zip -rqX "$zip_name" "$OUT"
  echo "$zip_name — $(du -sh "$zip_name" | cut -f1)"
fi

echo "$OUT — $(du -sh "$OUT" | cut -f1), $(find "$OUT" -type f | wc -l | tr -d ' ') files. $APP_NAME v$APP_VERSION ($APP_ENV). Checks passed."
