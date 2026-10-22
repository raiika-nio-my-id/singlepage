#!/usr/bin/env bash
# deploy.sh – Production deploy for admin.jadidaring.com (singlepage)
#
# Usage:
#   bash scripts/deploy.sh [REF]
#
# REF defaults to "main". Run this from /var/www/singlepage on the server,
# or let the GitHub Actions workflow invoke it.
#
# Services managed by this script:  singlepage  reverb  scheduler  nginx
# Deliberately NOT touched:         global-mysql  (external shared container)
set -euo pipefail

# ─── Tunables ────────────────────────────────────────────────────────────────
APP_DIR="${DEPLOY_PATH:-/var/www/singlepage}"
APP_CONTAINER="singlepage-app"
REF="${1:-main}"
HEALTH_TIMEOUT=90   # seconds to wait for singlepage-app to become Running
# Services in this compose project (global-mysql is external – not listed).
COMPOSE_SERVICES="singlepage reverb scheduler nginx"

# ─── Logging helpers ─────────────────────────────────────────────────────────
log()  { printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"; }
step() { printf '\n━━━ %s\n' "$*"; }
fail() { printf '\n[FATAL] %s\n' "$*" >&2; exit 1; }

# ─── Guard: must run from the app directory ───────────────────────────────────
cd "$APP_DIR" || fail "Cannot cd to APP_DIR=$APP_DIR"
log "Deploying from: $APP_DIR"

# ─── STEP 1 – Verify working tree is clean ───────────────────────────────────
step "1/10  Git: check working tree"
if ! git diff --quiet || ! git diff --cached --quiet; then
    fail "Working tree has uncommitted local changes. Stash or commit them before deploying."
fi

# ─── STEP 2 – Fast-forward to origin/<REF> ───────────────────────────────────
step "2/10  Git: fetch and fast-forward to $REF"
git fetch --tags origin
# Prefer a remote branch; fall back to a tag.
if git rev-parse --verify "origin/${REF}" >/dev/null 2>&1; then
    REMOTE_REF="origin/${REF}"
elif git rev-parse --verify "${REF}" >/dev/null 2>&1; then
    REMOTE_REF="${REF}"
else
    fail "Ref '${REF}' not found on origin after fetch."
fi
REMOTE_SHA=$(git rev-parse "$REMOTE_REF")
LOCAL_SHA=$(git rev-parse HEAD)
if [ "$LOCAL_SHA" = "$REMOTE_SHA" ]; then
    log "Already at ${REMOTE_SHA} – nothing to pull."
else
    git merge --ff-only "$REMOTE_REF" \
        || fail "Cannot fast-forward HEAD to $REMOTE_REF. The histories have diverged – resolve manually."
    log "Updated: $(git rev-parse --short HEAD) – $(git log -1 --format='%s')"
fi

# ─── STEP 3 – Build frontend assets (optional – requires Node on server) ──────
step "3/10  Build frontend assets"
if command -v node >/dev/null 2>&1 && command -v npm >/dev/null 2>&1; then
    log "Node $(node -v) / npm $(npm -v) detected – building Vite assets."
    npm ci --silent
    npm run build
    log "Assets built successfully."
else
    log "WARNING: node/npm not found on this host."
    log "  Pre-built assets in public/build/ will be used."
    log "  To fix: install Node.js on the server (e.g. nvm or nodesource) and re-run."
fi

# ─── STEP 4 – Put app into maintenance mode (if container is already running) ─
step "4/10  Enable maintenance mode"
MAINTENANCE_ACTIVE=0

# The maintenance flag lives in storage/framework/down which is on the
# bind-mounted volume, so it persists across container restarts.
artisan_down() {
    if docker exec "$APP_CONTAINER" php artisan down --retry=15; then
        MAINTENANCE_ACTIVE=1
        log "Maintenance mode ON."
    else
        log "WARNING: artisan down failed (non-fatal; continuing)."
    fi
}
if docker inspect "$APP_CONTAINER" >/dev/null 2>&1 \
    && [ "$(docker inspect -f '{{.State.Running}}' "$APP_CONTAINER")" = "true" ]; then
    artisan_down
else
    log "Container $APP_CONTAINER is not currently running; maintenance mode will be applied after start."
fi

# Trap: always lift maintenance mode on script exit (success or failure).
_cleanup() {
    if [ "$MAINTENANCE_ACTIVE" -eq 1 ]; then
        log "[cleanup] Lifting maintenance mode..."
        docker exec "$APP_CONTAINER" php artisan up 2>/dev/null || true
    fi
}
trap _cleanup EXIT

# ─── STEP 5 – Rebuild image & restart app containers ─────────────────────────
step "5/10  docker compose: build + up"
log "Building image (picks up Dockerfile / PHP-ext / config changes)..."
docker compose build

log "Starting services: $COMPOSE_SERVICES"
log "(global-mysql is external and will not be affected)"
# shellcheck disable=SC2086
docker compose up -d $COMPOSE_SERVICES
log "Containers restarted."

# ─── STEP 6 – Wait for singlepage-app to be Running ─────────────────────────
step "6/10  Wait for $APP_CONTAINER"
ELAPSED=0
until [ "$(docker inspect -f '{{.State.Running}}' "$APP_CONTAINER" 2>/dev/null)" = "true" ]; do
    if [ "$ELAPSED" -ge "$HEALTH_TIMEOUT" ]; then
        fail "Container $APP_CONTAINER did not reach Running state within ${HEALTH_TIMEOUT}s."
    fi
    sleep 3
    ELAPSED=$((ELAPSED + 3))
done
log "Container is Running. Waiting 4 s for PHP-FPM to initialise..."
sleep 4

# If maintenance mode could not be set before restart, apply it now.
if [ "$MAINTENANCE_ACTIVE" -eq 0 ]; then
    artisan_down
fi

# ─── STEP 7 – Composer install ───────────────────────────────────────────────
step "7/10  composer install"
# COMPOSER_HOME is redirected to /tmp inside the container so that Composer's
# cache/config files (.cache/, .config/, .local/) are never written into
# /var/www (the bind-mounted app dir). Without this, those dirs end up tracked
# by git and fail the dirty-tree check on every subsequent deploy.
docker exec \
    -e COMPOSER_HOME=/tmp/composer-home \
    "$APP_CONTAINER" composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist
log "Composer dependencies installed."

# ─── STEP 8 – Optional: mysqldump backup before migrations ───────────────────
step "8/10  Pre-migration backup (optional)"
# Requires mysqldump on the HOST (apt install default-mysql-client).
# Credentials are read from the running container's environment variables;
# no credentials are stored in this script.
if command -v mysqldump >/dev/null 2>&1; then
    DB_HOST=$(docker exec "$APP_CONTAINER" sh -c 'printf "%s" "$DB_HOST"' 2>/dev/null || true)
    DB_PORT=$(docker exec "$APP_CONTAINER" sh -c 'printf "%s" "${DB_PORT:-3306}"' 2>/dev/null || true)
    DB_DATABASE=$(docker exec "$APP_CONTAINER" sh -c 'printf "%s" "$DB_DATABASE"' 2>/dev/null || true)
    DB_USERNAME=$(docker exec "$APP_CONTAINER" sh -c 'printf "%s" "$DB_USERNAME"' 2>/dev/null || true)
    DB_PASSWORD=$(docker exec "$APP_CONTAINER" sh -c 'printf "%s" "$DB_PASSWORD"' 2>/dev/null || true)

    if [ -n "$DB_HOST" ] && [ -n "$DB_DATABASE" ]; then
        BACKUP_DIR="${APP_DIR}/storage/backups"
        mkdir -p "$BACKUP_DIR"
        BACKUP_FILE="${BACKUP_DIR}/pre-deploy-$(date +%Y%m%d-%H%M%S).sql.gz"
        log "Dumping $DB_DATABASE → $BACKUP_FILE ..."
        # MYSQL_PWD avoids passing the password on the command line (shellcheck SC2086).
        if MYSQL_PWD="$DB_PASSWORD" mysqldump \
                -h "$DB_HOST" \
                -P "$DB_PORT" \
                -u "$DB_USERNAME" \
                --single-transaction \
                --skip-lock-tables \
                "$DB_DATABASE" \
            | gzip > "$BACKUP_FILE"; then
            log "Backup saved ($(du -sh "$BACKUP_FILE" | cut -f1))."
        else
            log "WARNING: mysqldump failed – continuing without backup."
            rm -f "$BACKUP_FILE"
        fi
    else
        log "Could not read DB credentials from container – skipping backup."
    fi
else
    log "mysqldump not installed on this host – skipping backup."
    log "  To enable: sudo apt install default-mysql-client"
fi

# ─── STEP 9 – Migrate, cache, link ───────────────────────────────────────────
step "9/10  Artisan: migrate, cache, storage:link"
log "Running migrations..."
docker exec "$APP_CONTAINER" php artisan migrate --force

log "Rebuilding framework caches..."
docker exec "$APP_CONTAINER" php artisan config:cache
docker exec "$APP_CONTAINER" php artisan route:cache
docker exec "$APP_CONTAINER" php artisan view:cache

log "Ensuring public storage symlink..."
docker exec "$APP_CONTAINER" php artisan storage:link 2>&1 \
    | grep -v "already exists" || true  # idempotent

log "Signalling queue workers to restart (no-op if none are running)..."
docker exec "$APP_CONTAINER" php artisan queue:restart 2>/dev/null || true

# ─── Lift maintenance mode (success path) ────────────────────────────────────
log "Lifting maintenance mode..."
docker exec "$APP_CONTAINER" php artisan up
MAINTENANCE_ACTIVE=0   # disarm trap
trap - EXIT

# ─── STEP 10 – Health check ──────────────────────────────────────────────────
step "10/10  Health check"
APP_PORT=""
if [ -f "${APP_DIR}/.env" ]; then
    APP_PORT=$(grep -E '^APP_PORT=' "${APP_DIR}/.env" | head -1 \
        | cut -d= -f2 | tr -d '"' | tr -d "'" | tr -d '[:space:]')
fi
APP_PORT="${APP_PORT:-8081}"
log "Checking http://127.0.0.1:${APP_PORT}/ ..."
HTTP_CODE=$(curl -sf --max-time 15 -o /dev/null -w '%{http_code}' \
    "http://127.0.0.1:${APP_PORT}/" 2>/dev/null || echo "FAILED")
case "$HTTP_CODE" in
    200|301|302|303)
        log "Health check PASSED (HTTP $HTTP_CODE)."
        ;;
    FAILED)
        log "WARNING: curl could not reach http://127.0.0.1:${APP_PORT}/ – check nginx / firewall."
        ;;
    *)
        log "WARNING: Health check returned HTTP $HTTP_CODE – verify the app manually."
        ;;
esac

step "Deploy complete"
log "Ref: $REF → $(git rev-parse --short HEAD) – $(git log -1 --format='%s')"
log "Containers: $(docker compose ps --format 'table {{.Name}}\t{{.Status}}' 2>/dev/null | tail -n +2 | tr '\n' ' ')"
