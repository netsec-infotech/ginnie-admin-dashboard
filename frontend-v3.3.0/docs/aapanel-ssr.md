# aaPanel: Git deployment with SSR

This workflow builds a fresh release from Git, then replaces the single PM2 process
with that release. Failed installation/build leaves the existing process and files
untouched. The process replacement has a brief interruption; this is not a
zero-downtime deployment.
The existing static build and FTP commands remain available.

## Server setup

1. Install Nginx, Git, Node.js 22 and PM2 in aaPanel. Set the command-line Node
   version to 22 too. Confirm `node -v`, `npm -v` and `pm2 -v` in the deployment
   user's terminal. The script also needs Bash, tar and flock (util-linux).
2. Use one Unix deployment user for Git, builds, PM2 and webhook execution. Give
   that user ownership of the checkout and its sibling deployment directory.
   Different users have different PM2 process lists. Do not run two managers
   against port 3003.
3. Add that user's SSH public key as a read-only GitHub repository deploy key.
   Clone the repository (or use aaPanel's Pull Git project option):

   ```bash
   git clone git@github.com:Infinitietechnologies/hyperlocal-web.git /www/wwwroot/hyperlocal-web
   cd /www/wwwroot/hyperlocal-web
   cp .env.ssr.example .env.production.local
   ```

4. Edit `.env.production.local`: set the real backend and storefront URLs and any
   Firebase/payment public configuration overrides. The backend URL excludes
   `/api`. Keep `NEXT_PUBLIC_SSR=true`. Existing `.env` supplies fallback settings;
   never commit server secrets. Values prefixed `NEXT_PUBLIC_` are browser-visible.
   Use literal values, without variable interpolation. The build runner loads
   `.env.production.local`, `.env.local`, `.env.production`, then `.env`, without
   overriding variables already exported in the shell. Avoid conflicting shell
   settings. Each release receives `.env.production.local`; other ignored local
   environment files are not copied.

## First deployment

Commit and push these deployment files to the production branch first. Then run
from the checkout, as the deployment user:

```bash
DEPLOY_BRANCH=main bash scripts/deploy-aapanel.sh
pm2 status
curl --fail http://127.0.0.1:3003/ -o /dev/null
```

The script fetches the named branch directly; it does not pull or change the
checkout. Builds live in `/www/wwwroot/hyperlocal-web-deploy/releases/` by default.
Override `DEPLOY_ROOT` with an absolute path if needed. Git must have the committed
files: local uncommitted changes are intentionally excluded from a deployment.

`npm run build:ssr` forces SSR, runs check-only ESLint and the three asset/SEO
generators, then builds Next with Webpack for the PWA integration. It skips the
static-only `.htaccess` generator and `out/` SEO audit. The backend must be reachable
at build time. Some existing generators tolerate API failures, so inspect build
logs and verify generated metadata, robots.txt and sitemap.xml after deployment.

PM2 uses `ecosystem.config.cjs`, listens on `127.0.0.1:3003`, and starts in the new
release directory. The deploy script replaces the prior PM2 process after a
successful build so its code and `NEXT_PUBLIC_*` bundle cannot remain active. For a
different app name or port, edit that file and commit it
before deployment. Use this PM2 process rather than adding a second aaPanel
default Node process. Use `pm2 startup` and follow its printed system command, then
`pm2 save`, to enable reboot recovery for the deployment user.

## Domain and HTTPS

Point the domain A record at the server. In aaPanel create the domain site and
configure a reverse proxy to `http://127.0.0.1:3003`. Proxy the full site, including
`/_next/`; do not serve the Git checkout or `out/` as the storefront. Disable proxy
page caching for personalized SSR responses. Apply a Let's Encrypt certificate
and enable HTTPS. Port 3003 does not need public firewall access.

Configure the backend's allowed storefront origin and Firebase authorized domain
where applicable. Verify home/product pages, sign-in, cart, image loading,
`/robots.txt`, `/sitemap.xml`, and a direct refresh on a nested URL over HTTPS.

## Git webhook

In aaPanel's Git Manager/Webhook script configure the following, using your path
and branch. Run it as the same deployment user and ensure Node/PM2 are on its PATH:

```bash
DEPLOY_BRANCH=main bash /www/wwwroot/hyperlocal-web/scripts/deploy-aapanel.sh
```

Associate the script with the Git repository and configure its GitHub push webhook.
Keep webhook authentication enabled and use HTTPS with valid certificate
verification. The script fetches the production branch itself; no separate
destructive reset or pull is needed. Concurrent deployments are rejected by a lock;
retry a skipped delivery after the active deployment finishes.

The bootstrap script in the checkout is not automatically updated by deployment.
When changing that script, update the checkout explicitly with `git pull --ff-only`.
The application and PM2 configuration always come from the fetched revision.

## Failure and rollback

Check `pm2 logs hyperlocal-web --lines 100` and webhook logs. Build failure leaves
the current site running; startup failure after switching requires rollback.
Reapply a known-good release configuration, using its actual path:

```bash
pm2 startOrReload /www/wwwroot/hyperlocal-web-deploy/releases/KNOWN_GOOD_RELEASE/ecosystem.config.cjs --update-env
pm2 save
```

Verify the public site after rollback. Releases (including failed builds) are kept
for inspection; periodically remove only releases you no longer need, keeping the
active release and a known-good rollback. Each includes dependencies and may use
significant disk space. This script does not run live health checks or automatic
rollback and does not change the Laravel backend.

References: [aaPanel Node projects](https://www.aapanel.com/docs/Function/Node.html),
[aaPanel Git/webhooks](https://www.aapanel.com/docs/Function/Tutorial/create_for_git.html),
[Next.js deployment](https://nextjs.org/docs/app/getting-started/deploying).
