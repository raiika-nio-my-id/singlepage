# Deployment Guide – admin.jadidaring.com

Pushes to `main` deploy themselves. GitHub Actions SSHes into the production
server and runs `scripts/deploy.sh`. The script picks one of two paths:

- **Path A** — bind-mounted code, assets, composer, and additive migrations.
  No maintenance page and no container rebuild.
- **Path B** — a second PHP container is health-checked, then nginx reloads
  onto it, before the canonical `singlepage-app` container is replaced.
  WebSocket clients disconnect when Reverb is replaced. HTTP does not.

`global-mysql` is external and is never stopped or recreated.

---

## Quick reference

| What | Command / URL |
|---|---|
| Automatic deploy | Push (or merge) to `main` |
| Press the deploy button | GitHub → Actions → **Deploy to Production** → Run workflow |
| Run manually on server | `cd /var/www/singlepage && bash scripts/deploy.sh` |
| See which path would run | `bash scripts/deploy.sh --plan` |
| Deploy a specific branch | `bash scripts/deploy.sh feature/my-branch` |
| Breaking schema change | `bash scripts/deploy.sh --maintenance` |
| Force a container replace | `bash scripts/deploy.sh --force-recreate` |
| Recreate Reverb only | `bash scripts/deploy.sh --restart-reverb` |
| Roll back | `bash scripts/deploy.sh <previous-git-tag-or-sha>` |

---

## One-time setup

### 1  Create a dedicated deploy SSH key

Run this **locally** (or on any machine with ssh-keygen). Use an empty
passphrase so GitHub Actions can use the key unattended.

```bash
ssh-keygen -t ed25519 -C "deploy@jadidaring-singlepage" \
           -f ~/.ssh/jadidaring_deploy
```

Two files are created:

| File | Purpose |
|---|---|
| `~/.ssh/jadidaring_deploy` | **Private key** → goes into the `DEPLOY_SSH_KEY` GitHub secret |
| `~/.ssh/jadidaring_deploy.pub` | **Public key** → appended to `authorized_keys` on the server |

### 2  Add the public key to the server

Log into the production server as `jadidaring` and append the public key:

```bash
cat ~/.ssh/jadidaring_deploy.pub | ssh jadidaring@<SERVER_IP> \
  'cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys'
```

The `jadidaring` user must be able to run `docker` and `docker compose`
commands without `sudo` (i.e., a member of the `docker` group).

### 3  Collect the server's SSH host-key fingerprint

Run this **on the server** (as any user with read access to `/etc/ssh`):

```bash
ssh-keygen -l -f /etc/ssh/ssh_host_ed25519_key.pub | awk '{print $2}'
```

The output is a single line like `SHA256:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`.
That full string (including the `SHA256:` prefix) is the value of the
`DEPLOY_HOST_FINGERPRINT` secret.

### 4  Add GitHub repository secrets

In GitHub → **Settings → Secrets and variables → Actions**, add:

| Secret name | Required | Value |
|---|---|---|
| `DEPLOY_HOST` | ✅ | IP or hostname of the server |
| `DEPLOY_USER` | ✅ | SSH username (`jadidaring`) |
| `DEPLOY_SSH_KEY` | ✅ | Full content of `~/.ssh/jadidaring_deploy` (the **private** key) |
| `DEPLOY_HOST_FINGERPRINT` | ✅ | Output of `ssh-keygen -l -f /etc/ssh/ssh_host_ed25519_key.pub \| awk '{print $2}'` on the server |
| `DEPLOY_PORT` | optional | SSH port if not 22 |
| `DEPLOY_PATH` | optional | App directory if not `/var/www/singlepage` |

### 5  Ensure the server can pull from GitHub

The server needs to `git fetch origin` from this repository.
The simplest option is a **read-only GitHub deploy key**:

1. GitHub → **Settings → Deploy keys → Add deploy key**
2. Paste the **public key** of a new key (separate from the deploy SSH key above)
   or the same key if you are comfortable reusing it. Check **Allow read
   access** only.
3. On the server, confirm the remote is set correctly:
   ```bash
   cd /var/www/singlepage
   git remote -v   # should show git@github.com:<org>/<repo>.git
   git fetch origin main   # must succeed
   ```

### 6  (Optional) Install node/npm on the server for asset builds

The deploy script calls `npm ci && npm run build` to compile Vite assets if
Node is present. If Node is not available, pre-built assets in `public/build/`
are used (they must have been committed to the repo or built separately).

```bash
# On the server – install Node.js via nvm (recommended) or nodesource
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
source ~/.bashrc
nvm install --lts
node -v && npm -v
```

### 7  (Optional) Enable pre-migration database backups

The script will create a gzip'd mysqldump of `singlepage_db` before each
migration if `mysqldump` is available on the server:

```bash
sudo apt install default-mysql-client
```

Backup files are written to `storage/backups/` on the server (this directory
is git-ignored). Credentials are read from the running container's environment –
nothing is hard-coded in the script.

---

## How a deploy chooses a path

`scripts/deploy.sh` diffs the server's current HEAD against the target ref.

**Path A** when none of these changed:

- `Dockerfile`, `Dockerfile.*`, `.dockerignore`
- `docker-compose.yml` / `compose.yml` and override files
  (`docker-compose.green.yml` does **not** count — plain `docker compose up`
  never loads it)
- `docker/**`, `docker-config/php/**`, `docker-config/scheduler/**`
- `php.ini`, `php-fpm.conf`, `php-fpm.d/**`

Anything else (PHP, Blade, JS, CSS, `composer.lock`, migrations, config) stays
on Path A. If a path looks like it might require a new image and is not in
the list above, the script treats it as Path B.

Nginx files under `docker-config/nginx/**` stay on Path A and end with
`nginx -s reload` (graceful; existing requests finish on the old workers).

**Path B** when an image or container definition changed, or when
`--force-recreate` / `DEPLOY_FORCE_RECREATE=1` is set.

Only one deploy runs at a time (GitHub Actions concurrency group
`production-deploy`, plus a `flock` on the server).

### Path A

1. Refuse to run if the git working tree is dirty.
2. `git fetch` and fast-forward to the target ref.
3. Build Vite assets in a side directory when `node` and `npm` exist, then
   rename `public/build` into place. Without Node, `public/build` already on
   the server is kept.
4. `composer install --no-dev` **inside the running `singlepage-app`** only if
   `composer.json` or `composer.lock` changed.
5. `php artisan migrate --force` only when `migrate:status --pending` shows
   work. Additive migrations do **not** enable maintenance mode.
6. Rebuild config, route, and view caches in the running container.

PHP-FPM serves the new bind-mounted files on the next request. This image
does not enable opcache. **Reverb is a long-running process and keeps the
code it loaded at boot.** Pass `--restart-reverb` when a deploy must recycle
it. Clients disconnect and reconnect. `--force-recreate` runs Path B instead.

### Path B

1. Same dirty-tree guard and fetch. The live checkout is **not** moved yet.
2. Extract the target revision to `/var/www/singlepage-release` (override with
   `DEPLOY_RELEASE_DIR`), build `singlepage:incoming`, install PHP
   dependencies and Vite assets there.
3. Start `singlepage-app-green` on the Docker network `shared-services`,
   with the live `.env` and `storage/` mounted in. A throwaway nginx on
   `127.0.0.1` (usually port 8082) proxies to that container.
4. HTTP `GET /up` must return 200 before the live site changes. Failure stops
   here: production is still on the old commit.
5. Run pending migrations from the green container (shared MySQL, including
   `global-mysql` — the container is not restarted). `--maintenance` turns on
   `php artisan down` first.
6. Fast-forward `/var/www/singlepage` and swap in the staged `public/build`
   (and `vendor/` when the lockfile changed).
7. Reload `singlepage-nginx` so PHP goes to `singlepage-app-green`. Reverb
   stays on `singlepage-reverb` for this step. In-flight requests drain
   (`DEPLOY_DRAIN_SECONDS`, default 8).
8. Recreate canonical `singlepage-app` on the new image, health-check it, and
   reload nginx back onto `singlepage-app`.
9. Stop the green container. Recreate `singlepage-reverb` and
   `singlepage-scheduler`.
10. If a compose file other than `docker-compose.green.yml` changed, recreate
    `singlepage-nginx` so new host ports or volume mounts apply. That step
    drops `${APP_PORT}` for about as long as the container takes to start.
    Image-only changes leave the existing nginx process in place and reload it.

Step 9 is the WebSocket blip. HTTP is already on the new app image and is
not put in maintenance mode unless `--maintenance` was set. There is a short
overlap just before the first nginx reload where the old PHP-FPM can see the
new bind-mounted code; the green container was already proven against that
code.

If a step fails **after** nginx is pointing at green, the script leaves
`singlepage-app-green` and the release directory in place and exits non-zero.
Do not delete them until `singlepage-nginx` is back on `singlepage-app`.

### Maintenance mode

```bash
bash scripts/deploy.sh --maintenance
DEPLOY_MAINTENANCE=1 bash scripts/deploy.sh
```

On the GitHub **Run workflow** form, enable **artisan down for a breaking
schema change**. Use this for destructive schema changes. The script runs
`php artisan down --retry=15` and always tries `php artisan up` before it
exits, including when the deploy fails.

Additive migrations should not use this flag.

## Deploying

**Automatic:** every push (or merged PR) to `main` triggers the workflow.
Docs-only commits (`*.md`) are skipped.

**Manual override:** GitHub → **Actions** → **Deploy to Production** →
**Run workflow**. You can pick another branch or tag, force maintenance
mode, force Path B, or recreate Reverb.

The workflow SSHes in, fetches, and runs the `scripts/deploy.sh` from the
target ref (not whatever copy happens to be on disk). It refuses a dirty
working tree, then follows Path A or Path B above. **`global-mysql` is never
touched.**

A failed Path A deploy does not leave the site in maintenance mode unless
`--maintenance` was requested. A failed Path B deploy before the nginx switch
leaves the old containers serving the old commit.

---

## Manual deploy on the server

```bash
ssh jadidaring@<SERVER_IP>
cd /var/www/singlepage

# Deploy main:
bash scripts/deploy.sh

# Deploy a specific branch or tag:
bash scripts/deploy.sh release/2.1
bash scripts/deploy.sh v2.1.0
```

---

## Rolling back

### Roll back to a specific commit / tag

```bash
ssh jadidaring@<SERVER_IP>
cd /var/www/singlepage

# Identify the commit you want (the deploy log prints the short SHA):
git log --oneline -10

# Check it out manually and redeploy
git checkout <previous-sha-or-tag>
bash scripts/deploy.sh <previous-sha-or-tag>
```

> **Note:** `deploy.sh` requires the working tree to be clean and the ref to
> be reachable from `origin`. For a tagged release the tag must exist on the
> remote. For an arbitrary SHA, check it out manually before running the
> script.

### Restore from database backup

Pre-migration backups are stored in `storage/backups/` on the server (if
`mysqldump` was available at deploy time):

```bash
ls -lt /var/www/singlepage/storage/backups/
# e.g. pre-deploy-20260101-120000.sql.gz

# Restore (WARNING: destructive):
zcat /var/www/singlepage/storage/backups/pre-deploy-<timestamp>.sql.gz \
  | docker exec -i global-mysql mysql \
      -u root -p<ROOT_PASSWORD> singlepage_db
```

---

## One-time check before the first near-zero deploy

Nothing in nginx or DNS has to be edited by hand. The public edge stays
`singlepage-nginx` on `APP_PORT` (8081 unless `.env` says otherwise). The
upstream file is the tracked symlink `.deploy/nginx-upstream.conf`.

Confirm these once on the server:

1. The deploy user can create `/var/www/singlepage-release` (a sibling of the
   app). Path B extracts the target revision there and deletes it at the end.
   It needs enough free disk for a second checkout, `vendor/`, and
   `node_modules/`. If `/var/www` is not writable, set `DEPLOY_RELEASE_DIR`
   to another directory **outside** the git checkout, preferably on the same
   disk.
2. `127.0.0.1:8082` (or `APP_PORT + 1`) is free during deploys. The script
   binds a health-check nginx there only for Path B and removes it afterward.
   If that port is taken it tries the next 20 ports. Set `DEPLOY_GREEN_PORT`
   to pin one.
3. `flock` is installed (`util-linux`, already standard on Ubuntu) and the
   user can run `docker` and `docker compose`.
4. The Docker network `shared-services` already exists (the live stack uses
   it). The script will not create it and will not attach `global-mysql`.

The first deploy that contains the new nginx config reloads `singlepage-nginx`.
That reload is graceful. It does not recreate the container and does not open
a second public port.

## Environment variables that control the script

| Variable | Default | Effect |
|---|---|---|
| `DEPLOY_PATH` | `/var/www/singlepage` | App directory on the server |
| `DEPLOY_MAINTENANCE` | unset | `1` = `php artisan down` for this deploy |
| `DEPLOY_FORCE_RECREATE` | unset | `1` = Path B even if no image files changed |
| `DEPLOY_RESTART_REVERB` | unset | `1` = recreate Reverb after Path A (clients reconnect) |
| `DEPLOY_RELEASE_DIR` | `$DEPLOY_PATH-release` | Side tree used while preparing a deploy |
| `DEPLOY_GREEN_PORT` | first free port at `APP_PORT+1` | Localhost-only health check during Path B |
| `DEPLOY_DRAIN_SECONDS` | `8` | Pause after an nginx reload before stopping the previous PHP container |
| `DEPLOY_HEALTH_TIMEOUT` | `90` | How long to wait for a container or `/up` |
| `$1` (first argument) | `main` | Git ref (branch or tag) to deploy |

The script reads `APP_PORT` from the server's `.env` file for the public
health check (default `8081` if not set).

## WebSockets

Path A does not restart Reverb. Path B replaces `singlepage-reverb` once,
after HTTP is on the new PHP image. Connected game clients drop and must
reconnect. The public nginx process stays up across that replace; only the
Reverb container changes.
