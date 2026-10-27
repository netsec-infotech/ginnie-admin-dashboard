#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

# Run as the same Unix user that owns the PM2 process and deployment folders.
repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
deploy_root="${DEPLOY_ROOT:-${repo_dir}-deploy}"
deploy_branch="${DEPLOY_BRANCH:-main}"
env_file="$repo_dir/.env.production.local"

for command in git node npm pm2 flock tar; do
  command -v "$command" >/dev/null || { echo "Missing command: $command" >&2; exit 1; }
done
[[ -f "$env_file" ]] || { echo "Create $env_file from .env.ssr.example first." >&2; exit 1; }
mkdir -p "$deploy_root/releases"
deploy_root="$(cd "$deploy_root" && pwd)"
exec 9>"$deploy_root/deploy.lock"
flock -n 9 || { echo "Another deployment is running." >&2; exit 1; }

git -C "$repo_dir" check-ref-format "refs/heads/$deploy_branch" >/dev/null
git -C "$repo_dir" fetch origin "refs/heads/$deploy_branch"
revision="$(git -C "$repo_dir" rev-parse FETCH_HEAD)"
release_dir="$(mktemp -d "$deploy_root/releases/$(date -u +%Y%m%dT%H%M%SZ)-${revision:0:12}-XXXXXX")"
echo "Building revision $revision in $release_dir"
git -C "$repo_dir" archive "$revision" | tar -x -C "$release_dir"
cp "$env_file" "$release_dir/.env.production.local"
chmod 600 "$release_dir/.env.production.local"

cd "$release_dir"
npm ci --include=dev
npm run build:ssr

# Only touch the running process once installation and the build have succeeded.
# Deleting by app name is deliberate: `startOrReload` can retain the old release's
# cwd/script path, leaving its bundled NEXT_PUBLIC_* values live after a deploy.
# Fork mode has a brief restart window; old releases remain available for rollback.
pm2 delete hyperlocal-web 9>&- || true
pm2 start "$release_dir/ecosystem.config.cjs" --update-env 9>&-
pm2 save 9>&-
printf '%s\n' "$release_dir" > "$deploy_root/latest-built-release.txt"
echo "PM2 updated. Check pm2 status and the public URL before declaring deployment healthy."
