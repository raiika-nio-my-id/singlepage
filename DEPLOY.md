# Deployment Guide – admin.jadidaring.com

This project uses a one-button deploy:
a **GitHub Actions workflow** (manual trigger) that SSH-es into the production
server and runs `scripts/deploy.sh`.

---

## Quick reference

| What | Command / URL |
|---|---|
| Press the deploy button | GitHub → Actions → **Deploy to Production** → Run workflow |
| Run manually on server | `cd /var/www/singlepage && bash scripts/deploy.sh` |
| Deploy a specific branch | `bash scripts/deploy.sh feature/my-branch` |
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

## Deploying

**Automatic:** every push (or merged PR) to `main` triggers the workflow
automatically — no button to press. Docs-only commits (`*.md`) are skipped.

**Manual override:** go to GitHub → **Actions** → **Deploy to Production** →
**Run workflow**, optionally specify a different branch or tag.

In both cases the workflow will:

1. SSH into the server.
2. Run `scripts/deploy.sh` which will:
   - Fail loudly if the working tree has local changes.
   - Fast-forward `git pull` to `origin/main` (or the chosen ref).
   - Build frontend assets (if Node is installed).
   - Put the app in maintenance mode (`php artisan down`).
   - Rebuild and restart Docker containers (`singlepage`, `reverb`,
     `scheduler`, `nginx`). **`global-mysql` is never touched.**
   - Run `composer install --no-dev`, migrations, and cache rebuilds inside
     the `singlepage-app` container.
   - Bring the app back online (`php artisan up`).
   - Perform a basic HTTP health check.

A failed deploy leaves the app in **maintenance mode**. The EXIT trap always
calls `php artisan up`, so even on script error the app comes back –
but verify manually if a deploy fails.

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

## Environment variables that control the script

| Variable | Default | Effect |
|---|---|---|
| `DEPLOY_PATH` | `/var/www/singlepage` | App directory on the server |
| `$1` (first argument) | `main` | Git ref (branch or tag) to deploy |

The script reads `APP_PORT` from the server's `.env` file for the health check
(default `8081` if not set).
