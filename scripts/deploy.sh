#!/usr/bin/env bash
# deploy.sh — production deploy for admin.jadidaring.com (singlepage)
#
# Usage:
#   bash scripts/deploy.sh [REF] [--plan] [--maintenance] [--force-recreate] [--restart-reverb]
#   DEPLOY_MAINTENANCE=1 bash scripts/deploy.sh
#
# REF defaults to "main". Run from /var/www/singlepage, or let GitHub Actions
# pipe this file from the target ref over SSH.
#
# Path A — assets / code only (true near-zero)
#   Chosen when the diff from the previous HEAD to the new ref does not touch
#   an image or container definition (Dockerfile*, compose files, docker/**,
#   docker-config/php/**, docker-config/scheduler/**, php.ini / php-fpm config).
#   docker-compose.green.yml is excluded: a normal `docker compose up` never
#   loads it. Nginx-only files (docker-config/nginx/**) stay on this path and
#   finish with `nginx -s reload`.
#   Does: fetch, fast-forward, Vite build (or keep public/build), composer
#   install in the already-running singlepage-app if composer.lock/json
#   changed, migrate --force only when migrations are pending, config/route/view
#   cache. Does not: artisan down, docker compose build, or recreate
#   singlepage / reverb / scheduler / nginx.
#   PHP-FPM reads bind-mounted code on the next request (this image does not
#   enable opcache). The long-running Reverb process keeps the code it loaded
#   at start. Pass --restart-reverb when a deploy must recycle Reverb; clients
#   disconnect. --force-recreate selects Path B instead.
#
# Path B — blue-green (image / container changes)
#   Builds singlepage:incoming from a side tree, starts singlepage-app-green
#   beside the live singlepage-app, and HTTP-checks it on 127.0.0.1 (not a
#   public port) before changing the live site. Public nginx
#   (singlepage-nginx, ${APP_PORT}) reloads onto the green PHP container, the
#   canonical singlepage-app is recreated on the new image, then nginx reloads
#   back. global-mysql is never created, stopped, or recreated.
#   WebSockets: singlepage-reverb is replaced once, after HTTP is on the new
#   app image. Connected clients drop and must reconnect. HTTP is not put in
#   artisan down unless maintenance mode is requested.
#   singlepage-nginx is reloaded, not recreated, unless a compose file other
#   than docker-compose.green.yml changed (new port or volume). That recreate
#   drops APP_PORT for a couple of seconds.
#   A few seconds of overlap: the live tree is fast-forwarded just before the
#   nginx reload, so the old PHP-FPM can execute new bind-mounted code until
#   the reload finishes. Assets and vendor are staged beforehand and renamed
#   into place in that same window.
#
# Maintenance (breaking schema changes)
#   DEPLOY_MAINTENANCE=1 or --maintenance runs `php artisan down` around
#   migrate + the switch, then `php artisan up`. Without it, additive
#   migrations run while the site stays up.
#
# One-time server notes (also in DEPLOY.md):
#   - Deploy user must be able to create ${DEPLOY_RELEASE_DIR:-$APP_DIR-release}
#     (a side checkout). It is removed when the deploy finishes.
#   - Path B borrows 127.0.0.1:${APP_PORT}+1 (or the next free port) for a
#     throwaway health nginx. Set DEPLOY_GREEN_PORT to pin it. Nothing new is
#     published on a public interface.
#   - No manual nginx edit. .deploy/nginx-upstream.conf is in git.
#
# Services this script may recreate: singlepage, reverb, scheduler, nginx
# Deliberately never touched:         global-mysql (external shared container)
set -euo pipefail

if [ "${BASH_VERSINFO[0]}" -lt 4 ]; then
    printf '[FATAL] bash 4 or newer is required\n' >&2
    exit 1
fi

APP_DIR="${DEPLOY_PATH:-/var/www/singlepage}"
APP_CONTAINER="singlepage-app"
NGINX_CONTAINER="singlepage-nginx"
REVERB_CONTAINER="singlepage-reverb"
SCHEDULER_CONTAINER="singlepage-scheduler"
GREEN_APP_CONTAINER="singlepage-app-green"
GREEN_NGINX_CONTAINER="singlepage-nginx-health"
GREEN_PROJECT="singlepage-green"
RELEASE_DIR="${DEPLOY_RELEASE_DIR:-${APP_DIR}-release}"
REF="main"
REF_SET=0
MAINTENANCE=0
MAINTENANCE_ACTIVE=0
FORCE_RECREATE=0
RESTART_REVERB=0
PLAN_ONLY=0
HEALTH_TIMEOUT="${DEPLOY_HEALTH_TIMEOUT:-90}"
DRAIN_SECONDS="${DEPLOY_DRAIN_SECONDS:-8}"
APP_PORT="8081"
MODE="assets"
TRAFFIC="blue"
UPSTREAM_PHP="singlepage-app"
UPSTREAM_REVERB="singlepage-reverb"
OLD_SHA=""
NEW_SHA=""
REMOTE_REF=""
RUN_DIR=""
DEPLOY_GREEN_PORT_SELECTED=""
CHANGED_FILES=()
TRIGGER_FILES=()
STAGE_DESTS=()
STAGE_INCOMING=()
STAGE_OUTGOING=()

usage() {
    cat <<'EOF'
Usage: bash scripts/deploy.sh [REF] [options]

REF is a branch or tag (default: main).

Options:
  --plan              Print Path A vs Path B and exit without changing the server
  --maintenance       artisan down around migrations and the container switch
  --force-recreate    Path B even if no image/container files changed
  --restart-reverb    Path A only: recreate Reverb (WebSocket clients reconnect)
  -h, --help          Show this help

Environment:
  DEPLOY_PATH            App directory (default /var/www/singlepage)
  DEPLOY_MAINTENANCE=1   Same as --maintenance
  DEPLOY_FORCE_RECREATE=1
  DEPLOY_RESTART_REVERB=1
  DEPLOY_RELEASE_DIR     Side tree for Path B (default: $DEPLOY_PATH-release)
  DEPLOY_GREEN_PORT      Localhost port for the throwaway green health check
  DEPLOY_DRAIN_SECONDS   Wait after an nginx reload before stopping the old PHP (default 8)
  DEPLOY_HEALTH_TIMEOUT  Seconds to wait for a container or /up (default 90)
EOF
}

log()  { printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"; }
step() { printf '\n━━━ %s\n' "$*"; }
fail() { printf '\n[FATAL] %s\n' "$*" >&2; exit 1; }

path_needs_container() {
    local p="$1"
    case "$p" in
        docker-compose.green.yml|docker-compose.green.yaml)
            return 1
            ;;
        Dockerfile|Dockerfile.*|.dockerignore|php.ini|php-fpm.conf)
            return 0
            ;;
        */Dockerfile|*/Dockerfile.*)
            return 0
            ;;
        docker-compose.yml|docker-compose.yaml|compose.yml|compose.yaml)
            return 0
            ;;
        docker-compose.*|compose.*)
            return 0
            ;;
        docker/*|docker-config/php/*|docker-config/scheduler/*|php-fpm.d/*)
            return 0
            ;;
        docker-config/*)
            case "$p" in
                docker-config/nginx/*) return 1 ;;
                *) return 0 ;;
            esac
            ;;
    esac
    return 1
}

path_needs_nginx() {
    case "$1" in
        docker-config/nginx/*|.deploy/nginx-upstream.conf) return 0 ;;
        *) return 1 ;;
    esac
}

# Prints "assets", "nginx", or "container". Container wins over nginx.
classify_mode() {
    local path mode="assets"
    for path in "$@"; do
        [ -n "$path" ] || continue
        if path_needs_container "$path"; then
            printf '%s\n' container
            return 0
        fi
        if path_needs_nginx "$path"; then
            mode="nginx"
        fi
    done
    printf '%s\n' "$mode"
}

path_label() {
    case "$MODE" in
        container) printf '%s\n' "B (blue-green)" ;;
        nginx) printf '%s\n' "A (code/assets + nginx reload)" ;;
        *) printf '%s\n' "A (code/assets)" ;;
    esac
}

parse_args() {
    if [ "${DEPLOY_MAINTENANCE:-0}" = "1" ]; then MAINTENANCE=1; fi
    if [ "${DEPLOY_FORCE_RECREATE:-0}" = "1" ]; then FORCE_RECREATE=1; fi
    if [ "${DEPLOY_RESTART_REVERB:-0}" = "1" ]; then RESTART_REVERB=1; fi

    while [ $# -gt 0 ]; do
        case "$1" in
            --maintenance) MAINTENANCE=1 ;;
            --force-recreate) FORCE_RECREATE=1 ;;
            --restart-reverb) RESTART_REVERB=1 ;;
            --plan) PLAN_ONLY=1 ;;
            -h|--help) usage; exit 0 ;;
            --) shift; break ;;
            -*) fail "Unknown option: $1" ;;
            *)
                if [ "$REF_SET" -eq 1 ]; then
                    fail "Unexpected argument: $1"
                fi
                REF="$1"
                REF_SET=1
                ;;
        esac
        shift
    done
}

require_uint() {
    local name="$1" value="$2"
    if ! [[ "$value" =~ ^[0-9]+$ ]]; then
        fail "$name=$value must be a non-negative integer"
    fi
}

acquire_lock() {
    mkdir -p "$APP_DIR/.deploy"
    exec 9>"$APP_DIR/.deploy/deploy.lock"
    if ! flock -n 9; then
        fail "Another deploy holds $APP_DIR/.deploy/deploy.lock"
    fi
}

dirty_guard() {
    if git diff --quiet && git diff --cached --quiet; then
        return 0
    fi
    git status --short >&2 || true
    if [ -e "$APP_DIR/.deploy/nginx-upstream.conf" ] && [ ! -L "$APP_DIR/.deploy/nginx-upstream.conf" ]; then
        fail "Working tree is dirty and .deploy/nginx-upstream.conf is not the committed symlink. A Path B deploy may still be serving singlepage-app-green. Inspect nginx and that container before resetting files."
    fi
    fail "Working tree has uncommitted local changes. Stash or commit them before deploying."
}

fetch_ref() {
    git fetch --prune --tags origin
}

resolve_ref() {
    if git rev-parse --verify "origin/${REF}" >/dev/null 2>&1; then
        REMOTE_REF="origin/${REF}"
    elif git rev-parse --verify "${REF}" >/dev/null 2>&1; then
        REMOTE_REF="${REF}"
    else
        fail "Ref '${REF}' not found on origin after fetch."
    fi
    NEW_SHA=$(git rev-parse "$REMOTE_REF")
    OLD_SHA=$(git rev-parse HEAD)
}

load_changed_files() {
    CHANGED_FILES=()
    local line
    if [ "$OLD_SHA" = "$NEW_SHA" ]; then
        return 0
    fi
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        CHANGED_FILES+=("$line")
    done < <(git diff --name-only --find-renames "$OLD_SHA" "$NEW_SHA")
}

collect_classification() {
    MODE="assets"
    TRIGGER_FILES=()
    local f
    if [ "${#CHANGED_FILES[@]}" -gt 0 ]; then
        for f in "${CHANGED_FILES[@]}"; do
            if path_needs_container "$f"; then
                TRIGGER_FILES+=("$f")
                MODE="container"
            elif path_needs_nginx "$f"; then
                TRIGGER_FILES+=("$f")
                if [ "$MODE" != "container" ]; then
                    MODE="nginx"
                fi
            fi
        done
    fi
    if [ "$FORCE_RECREATE" -eq 1 ]; then
        MODE="container"
        TRIGGER_FILES+=("(forced by --force-recreate / DEPLOY_FORCE_RECREATE=1)")
    fi
}

lockfile_changed() {
    local f
    if [ "${#CHANGED_FILES[@]}" -eq 0 ]; then
        return 1
    fi
    for f in "${CHANGED_FILES[@]}"; do
        case "$f" in
            composer.json|composer.lock) return 0 ;;
        esac
    done
    return 1
}

print_plan() {
    echo "Deploy plan"
    echo "  app:          $APP_DIR"
    echo "  ref:          $REF ($NEW_SHA)"
    echo "  current:      $OLD_SHA"
    echo "  path:         $(path_label)"
    echo "  maintenance:  $([ "$MAINTENANCE" -eq 1 ] && echo yes || echo no)"
    echo "  restart reverb: $([ "$RESTART_REVERB" -eq 1 ] && echo yes || echo no)"
    if [ "${#TRIGGER_FILES[@]}" -eq 0 ]; then
        echo "  triggers:     (none — code/assets only)"
    else
        echo "  triggers:"
        local f
        for f in "${TRIGGER_FILES[@]}"; do
            echo "    - $f"
        done
    fi
    echo "  changed files: ${#CHANGED_FILES[@]}"
}

load_app_port() {
    APP_PORT="8081"
    if [ -f "$APP_DIR/.env" ]; then
        local raw
        raw=$(grep -E '^APP_PORT=' "$APP_DIR/.env" | head -1 | cut -d= -f2- | tr -d "\"'[:space:]" || true)
        if [ -n "$raw" ]; then
            APP_PORT="$raw"
        fi
    fi
    require_uint APP_PORT "$APP_PORT"
}

require_docker() {
    command -v docker >/dev/null 2>&1 || fail "docker is not installed"
    docker info >/dev/null 2>&1 || fail "Cannot talk to the Docker daemon"
    command -v flock >/dev/null 2>&1 || fail "flock is required (util-linux)"
    command -v curl >/dev/null 2>&1 || fail "curl is required"
}

container_running() {
    [ "$(docker inspect -f '{{.State.Running}}' "$1" 2>/dev/null || true)" = "true" ]
}

wait_running() {
    local name="$1" elapsed=0 state=""
    while true; do
        state=$(docker inspect -f '{{.State.Running}}' "$name" 2>/dev/null || echo missing)
        if [ "$state" = "true" ]; then
            log "$name is Running"
            return 0
        fi
        if [ "$elapsed" -ge "$HEALTH_TIMEOUT" ]; then
            docker logs --tail 80 "$name" >&2 || true
            fail "$name did not reach Running within ${HEALTH_TIMEOUT}s (state=$state)"
        fi
        sleep 2
        elapsed=$((elapsed + 2))
    done
}

port_in_use() {
    local port="$1"
    if command -v ss >/dev/null 2>&1; then
        ss -ltn | grep -qE ":${port}([^0-9]|$)"
        return $?
    fi
    (echo >/dev/tcp/127.0.0.1/"$port") >/dev/null 2>&1
}

pick_green_port() {
    local port start end
    if [ -n "${DEPLOY_GREEN_PORT:-}" ]; then
        require_uint DEPLOY_GREEN_PORT "$DEPLOY_GREEN_PORT"
        if port_in_use "$DEPLOY_GREEN_PORT"; then
            fail "DEPLOY_GREEN_PORT=$DEPLOY_GREEN_PORT is already in use on this host"
        fi
        DEPLOY_GREEN_PORT_SELECTED="$DEPLOY_GREEN_PORT"
        return 0
    fi
    start=$((APP_PORT + 1))
    end=$((start + 20))
    port=$start
    while [ "$port" -le "$end" ]; do
        if ! port_in_use "$port"; then
            DEPLOY_GREEN_PORT_SELECTED="$port"
            log "Green health check will use 127.0.0.1:${port}"
            return 0
        fi
        port=$((port + 1))
    done
    fail "No free localhost port in ${start}-${end} for the green health check. Set DEPLOY_GREEN_PORT."
}

git_ff() {
    if [ "$OLD_SHA" = "$NEW_SHA" ]; then
        log "Already at ${NEW_SHA} — nothing to fast-forward"
        return 0
    fi
    git merge --ff-only "$REMOTE_REF" \
        || fail "Cannot fast-forward HEAD to $REMOTE_REF. The histories have diverged — resolve manually."
    log "Updated: $(git rev-parse --short HEAD) — $(git log -1 --format='%s')"
}

assert_safe_release_dir() {
    case "$RELEASE_DIR" in
        ""|/|/tmp|/var|/var/www|"$APP_DIR"|"$APP_DIR"/*)
            fail "Refusing to use DEPLOY_RELEASE_DIR=$RELEASE_DIR. Pick a writable directory outside the app tree."
            ;;
    esac
}

extract_release() {
    local sha="$1"
    assert_safe_release_dir
    if [ -e "$RELEASE_DIR" ]; then
        rm -rf "$RELEASE_DIR"
    fi
    mkdir -p "$RELEASE_DIR" \
        || fail "Cannot create $RELEASE_DIR. Set DEPLOY_RELEASE_DIR to a writable path outside $APP_DIR."
    git archive "$sha" | tar -x -C "$RELEASE_DIR"
    chmod -R a+rX "$RELEASE_DIR"
    if [ -d "$RELEASE_DIR/bootstrap/cache" ]; then
        chmod -R a+rwX "$RELEASE_DIR/bootstrap/cache"
    fi
    log "Extracted $(git rev-parse --short "$sha") to $RELEASE_DIR"
}

cleanup_release() {
    [ -n "${RELEASE_DIR:-}" ] || return 0
    [ -d "$RELEASE_DIR" ] || return 0
    case "$RELEASE_DIR" in
        ""|/|/tmp|/var|/var/www|"$APP_DIR"|"$APP_DIR"/*)
            log "Refusing to delete RELEASE_DIR=$RELEASE_DIR"
            return 0
            ;;
    esac
    if container_running "$GREEN_APP_CONTAINER" || docker inspect "$GREEN_APP_CONTAINER" >/dev/null 2>&1; then
        local src=""
        src=$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www"}}{{.Source}}{{end}}{{end}}' "$GREEN_APP_CONTAINER" 2>/dev/null || true)
        if [ "$src" = "$RELEASE_DIR" ]; then
            log "Not deleting $RELEASE_DIR; $GREEN_APP_CONTAINER still mounts it"
            return 0
        fi
    fi
    rm -rf "$RELEASE_DIR"
}

build_assets() {
    local root="$1"
    if command -v node >/dev/null 2>&1 && command -v npm >/dev/null 2>&1; then
        log "Node $(node -v) / npm $(npm -v) — building Vite assets in $root"
        (
            cd "$root"
            export CI=true NPM_CONFIG_UPDATE_NOTIFIER=false
            npm ci --no-audit --no-fund
            npm run build
        )
        return 0
    fi
    log "WARNING: node/npm not found on this host."
    log "  Pre-built assets in public/build/ will be used when present."
    if [ ! -d "$root/public/build" ] && [ -d "$APP_DIR/public/build" ]; then
        mkdir -p "$root/public"
        cp -a "$APP_DIR/public/build" "$root/public/build"
        log "Copied the live public/build into the release tree."
    fi
}

stage_dir() {
    local src="$1" dest="$2" incoming outgoing
    if [ ! -e "$src" ]; then
        log "Nothing to stage from $src"
        return 0
    fi
    incoming="${dest}.incoming.$$"
    outgoing="${dest}.outgoing.$$"
    rm -rf "$incoming"
    log "Staging $(basename "$dest") (live path stays in place until fast-forward)"
    cp -a "$src" "$incoming"
    STAGE_DESTS+=("$dest")
    STAGE_INCOMING+=("$incoming")
    STAGE_OUTGOING+=("$outgoing")
}

commit_staged() {
    local i dest incoming outgoing
    if [ "${#STAGE_DESTS[@]}" -eq 0 ]; then
        return 0
    fi
    for i in "${!STAGE_DESTS[@]}"; do
        dest="${STAGE_DESTS[$i]}"
        incoming="${STAGE_INCOMING[$i]}"
        outgoing="${STAGE_OUTGOING[$i]}"
        if [ -e "$dest" ] || [ -L "$dest" ]; then
            mv "$dest" "$outgoing"
        fi
        if ! mv "$incoming" "$dest"; then
            if [ -e "$outgoing" ]; then
                mv "$outgoing" "$dest" || true
            fi
            fail "Failed to publish $dest"
        fi
        rm -rf "$outgoing"
        log "Published $dest"
    done
    STAGE_DESTS=()
    STAGE_INCOMING=()
    STAGE_OUTGOING=()
}

rollback_staged() {
    local i dest incoming outgoing
    if [ "${#STAGE_DESTS[@]}" -eq 0 ]; then
        return 0
    fi
    for i in "${!STAGE_DESTS[@]}"; do
        dest="${STAGE_DESTS[$i]}"
        incoming="${STAGE_INCOMING[$i]}"
        outgoing="${STAGE_OUTGOING[$i]}"
        if [ ! -e "$dest" ] && [ -e "$outgoing" ]; then
            mv "$outgoing" "$dest" || true
        fi
        rm -rf "$incoming" || true
        if [ -e "$dest" ]; then
            rm -rf "$outgoing" || true
        fi
    done
}

copy_package_manifests() {
    local f
    mkdir -p "$APP_DIR/bootstrap/cache"
    for f in packages.php services.php; do
        if [ -f "$RELEASE_DIR/bootstrap/cache/$f" ]; then
            cp -a "$RELEASE_DIR/bootstrap/cache/$f" "$APP_DIR/bootstrap/cache/$f"
        fi
    done
}

composer_in_container() {
    local container="$1"
    log "composer install in $container"
    docker exec \
        -e COMPOSER_HOME=/tmp/composer-home \
        -e COMPOSER_MEMORY_LIMIT=-1 \
        "$container" composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --prefer-dist
}

composer_in_release() {
    log "composer install in the release tree (live vendor is not modified yet)"
    docker run --rm \
        --user "$(id -u):$(id -g)" \
        --network shared-services \
        --add-host=host.docker.internal:host-gateway \
        --entrypoint composer \
        -e COMPOSER_HOME=/tmp/composer-home \
        -e HOME=/tmp \
        -e COMPOSER_MEMORY_LIMIT=-1 \
        -v "$RELEASE_DIR:/var/www" \
        -v "$APP_DIR/.env:/var/www/.env:ro" \
        -w /var/www \
        singlepage:incoming \
        install --no-dev --optimize-autoloader --no-interaction --prefer-dist
    chmod -R a+rX "$RELEASE_DIR/vendor"
}

install_release_vendor() {
    if lockfile_changed || [ ! -d "$APP_DIR/vendor" ]; then
        composer_in_release
        return 0
    fi
    log "composer.lock unchanged — copying vendor into the release"
    cp -a "$APP_DIR/vendor" "$RELEASE_DIR/vendor"
    chmod -R a+rX "$RELEASE_DIR/vendor"
}

build_image() {
    log "Building image singlepage:incoming (running containers keep their current image)"
    docker build -t singlepage:incoming -f "$RELEASE_DIR/Dockerfile" "$RELEASE_DIR"
}

migrations_pending() {
    local container="$1" out
    if ! out=$(docker exec "$container" php artisan migrate:status --pending --no-ansi 2>&1); then
        log "WARNING: migrate:status --pending failed; will run migrate --force"
        printf '%s\n' "$out" >&2
        return 0
    fi
    if printf '%s\n' "$out" | grep -q 'Pending'; then
        return 0
    fi
    return 1
}

backup_database() {
    local container="$1"
    if ! command -v mysqldump >/dev/null 2>&1; then
        log "mysqldump not installed on this host — skipping backup."
        log "  To enable: sudo apt install default-mysql-client"
        return 0
    fi
    local DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
    DB_HOST=$(docker exec "$container" sh -c 'printf "%s" "$DB_HOST"' 2>/dev/null || true)
    DB_PORT=$(docker exec "$container" sh -c 'printf "%s" "${DB_PORT:-3306}"' 2>/dev/null || true)
    DB_DATABASE=$(docker exec "$container" sh -c 'printf "%s" "$DB_DATABASE"' 2>/dev/null || true)
    DB_USERNAME=$(docker exec "$container" sh -c 'printf "%s" "$DB_USERNAME"' 2>/dev/null || true)
    DB_PASSWORD=$(docker exec "$container" sh -c 'printf "%s" "$DB_PASSWORD"' 2>/dev/null || true)
    if [ -z "$DB_HOST" ] || [ -z "$DB_DATABASE" ]; then
        log "Could not read DB credentials from container — skipping backup."
        return 0
    fi
    local backup_dir backup_file
    backup_dir="${APP_DIR}/storage/backups"
    mkdir -p "$backup_dir"
    backup_file="${backup_dir}/pre-deploy-$(date +%Y%m%d-%H%M%S).sql.gz"
    log "Dumping $DB_DATABASE → $backup_file"
    if MYSQL_PWD="$DB_PASSWORD" mysqldump \
            -h "$DB_HOST" \
            -P "$DB_PORT" \
            -u "$DB_USERNAME" \
            --single-transaction \
            --skip-lock-tables \
            "$DB_DATABASE" \
        | gzip > "$backup_file"; then
        log "Backup saved ($(du -sh "$backup_file" | cut -f1))."
    else
        log "WARNING: mysqldump failed — continuing without backup."
        rm -f "$backup_file"
    fi
}

maybe_migrate() {
    local container="$1"
    if migrations_pending "$container"; then
        backup_database "$container"
        log "Running migrations in $container"
        docker exec "$container" php artisan migrate --force
    else
        log "No pending migrations"
    fi
}

maybe_down() {
    if [ "$MAINTENANCE" -ne 1 ]; then
        return 0
    fi
    if ! container_running "$APP_CONTAINER"; then
        log "Maintenance requested but $APP_CONTAINER is not running yet"
        return 0
    fi
    docker exec "$APP_CONTAINER" php artisan down --retry=15
    MAINTENANCE_ACTIVE=1
    log "Maintenance mode ON (breaking-schema deploy)"
}

maybe_up() {
    if [ "$MAINTENANCE_ACTIVE" -ne 1 ]; then
        return 0
    fi
    local c
    for c in "$APP_CONTAINER" "$GREEN_APP_CONTAINER"; do
        if container_running "$c"; then
            if docker exec "$c" php artisan up; then
                MAINTENANCE_ACTIVE=0
                log "Maintenance mode OFF"
                return 0
            fi
        fi
    done
    log "WARNING: could not lift maintenance mode"
    return 1
}

rebuild_caches() {
    local container="$1" strict="${2:-1}" skip_views="${3:-0}"
    log "Rebuilding framework caches in $container"
    if ! docker exec "$container" php artisan config:cache; then
        if [ "$strict" -eq 1 ]; then
            fail "config:cache failed in $container"
        fi
        log "WARNING: config:cache failed in $container"
        return 1
    fi
    if ! docker exec "$container" php artisan route:cache; then
        # routes/admin.php and routes/tenant.php register closure routes, which
        # Laravel cannot serialize. Drop a stale cache so the new files load.
        log "WARNING: route:cache failed (closure routes cannot be cached). Clearing the route cache."
        docker exec "$container" php artisan route:clear || true
    fi
    if [ "$skip_views" -eq 0 ]; then
        if ! docker exec "$container" php artisan view:cache; then
            if [ "$strict" -eq 1 ]; then
                fail "view:cache failed in $container"
            fi
            log "WARNING: view:cache failed in $container"
        fi
        # Green pre-checks skip this: queue:restart talks to the shared cache,
        # and storage:link on the release tree is not the live public/ directory.
        docker exec "$container" php artisan storage:link >/dev/null 2>&1 || true
        docker exec "$container" php artisan queue:restart >/dev/null 2>&1 || true
    fi
    return 0
}

write_upstream() {
    local php_host="$1" reverb_host="$2" dest tmp
    dest="$APP_DIR/.deploy/nginx-upstream.conf"
    tmp=$(mktemp)
    cat > "$tmp" <<EOF
# Runtime upstream written by scripts/deploy.sh.
# Restored to the git symlink once traffic is back on the canonical containers.
set \$php_upstream ${php_host}:9000;
set \$reverb_upstream ${reverb_host}:8080;
EOF
    mkdir -p "$APP_DIR/.deploy"
    mv -f "$tmp" "$dest"
    chmod 644 "$dest"
}

restore_upstream_symlink() {
    local dest="$APP_DIR/.deploy/nginx-upstream.conf"
    if [ "${UPSTREAM_PHP:-singlepage-app}" != "singlepage-app" ] || [ "${UPSTREAM_REVERB:-singlepage-reverb}" != "singlepage-reverb" ]; then
        log "Leaving $dest pointing at $UPSTREAM_PHP / $UPSTREAM_REVERB"
        return 0
    fi
    if [ ! -f "$APP_DIR/docker-config/nginx/upstream.default.conf" ]; then
        return 0
    fi
    if [ -L "$dest" ]; then
        return 0
    fi
    ln -sfn ../docker-config/nginx/upstream.default.conf "$dest"
}

switch_public_upstream() {
    local php_host="$1" reverb_host="$2"
    local prev_php="$UPSTREAM_PHP" prev_reverb="$UPSTREAM_REVERB"
    write_upstream "$php_host" "$reverb_host"
    if ! docker exec "$NGINX_CONTAINER" nginx -t; then
        write_upstream "$prev_php" "$prev_reverb"
        fail "nginx -t rejected upstream ${php_host}. Restored ${prev_php}."
    fi
    if ! docker exec "$NGINX_CONTAINER" nginx -s reload; then
        write_upstream "$prev_php" "$prev_reverb"
        docker exec "$NGINX_CONTAINER" nginx -s reload || true
        fail "nginx reload failed for upstream ${php_host}. Traffic left on ${prev_php}."
    fi
    UPSTREAM_PHP="$php_host"
    UPSTREAM_REVERB="$reverb_host"
    log "Public nginx upstream is now PHP=$php_host Reverb=$reverb_host"
}

reload_public_nginx() {
    docker exec "$NGINX_CONTAINER" nginx -t
    docker exec "$NGINX_CONTAINER" nginx -s reload
    log "Reloaded $NGINX_CONTAINER"
}

preflight_nginx() {
    local root="$1"
    [ -f "$root/docker-config/nginx/default.conf" ] || fail "Release is missing docker-config/nginx/default.conf"
    [ -e "$root/.deploy/nginx-upstream.conf" ] || fail "Release is missing .deploy/nginx-upstream.conf"
    log "Checking nginx syntax for the target revision (live nginx is not reloaded)"
    docker run --rm \
        -v "$root/docker-config/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro" \
        -v "$root:/var/www:ro" \
        nginx:alpine nginx -t
}

write_health_conf() {
    local php_host="$1"
    cat > "$RUN_DIR/nginx-health.conf" <<EOF
server {
    listen 80;
    index index.php index.html;
    root /var/www/public;
    client_max_body_size 50M;
    resolver 127.0.0.11 ipv6=off valid=2s;
    set \$php_upstream ${php_host}:9000;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php\$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\\.php)(/.+)\$;
        fastcgi_pass \$php_upstream;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_read_timeout 60;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }
}
EOF
    chmod 644 "$RUN_DIR/nginx-health.conf"
}

wait_http() {
    local url="$1" allow_maintenance="${2:-0}"
    local elapsed=0 code=""
    while [ "$elapsed" -lt "$HEALTH_TIMEOUT" ]; do
        code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 5 "$url" || true)
        if [ "$code" = "200" ]; then
            log "Health OK $url (HTTP 200)"
            return 0
        fi
        if [ "$allow_maintenance" -eq 1 ] && [ "$code" = "503" ]; then
            log "Health OK $url (HTTP 503 during maintenance)"
            return 0
        fi
        sleep 2
        elapsed=$((elapsed + 2))
    done
    log "Last HTTP status from $url: ${code:-none}"
    docker logs --tail 80 "$GREEN_APP_CONTAINER" >&2 || true
    docker logs --tail 40 "$GREEN_NGINX_CONTAINER" >&2 || true
    docker logs --tail 40 "$APP_CONTAINER" >&2 || true
    fail "Timed out waiting for $url"
}

prepare_green_compose() {
    git cat-file -e "${NEW_SHA}:docker-compose.green.yml" \
        || fail "Target revision has no docker-compose.green.yml, so Path B cannot start a green slot."
    git show "${NEW_SHA}:docker-compose.green.yml" > "$RUN_DIR/docker-compose.green.yml"
}

start_green() {
    [ -n "$DEPLOY_GREEN_PORT_SELECTED" ] || fail "Internal error: green health port was not chosen"
    export GREEN_APP_MOUNT="$1"
    export APP_DIR DEPLOY_GREEN_PORT HEALTH_NGINX_CONF SINGLEPAGE_IMAGE
    DEPLOY_GREEN_PORT="$DEPLOY_GREEN_PORT_SELECTED"
    HEALTH_NGINX_CONF="$RUN_DIR/nginx-health.conf"
    SINGLEPAGE_IMAGE="${SINGLEPAGE_IMAGE:-singlepage:incoming}"
    docker network inspect shared-services >/dev/null 2>&1 \
        || fail "Docker network shared-services is missing. The live stack uses it; this script will not create or alter it."
    docker compose \
        --project-directory "$APP_DIR" \
        --project-name "$GREEN_PROJECT" \
        -f "$RUN_DIR/docker-compose.green.yml" \
        up -d --force-recreate --remove-orphans
}

cleanup_green() {
    if [ -n "${RUN_DIR:-}" ] && [ -f "$RUN_DIR/docker-compose.green.yml" ]; then
        docker compose \
            --project-directory "$APP_DIR" \
            --project-name "$GREEN_PROJECT" \
            -f "$RUN_DIR/docker-compose.green.yml" \
            down --remove-orphans >/dev/null 2>&1 || true
    fi
    docker rm -f "$GREEN_NGINX_CONTAINER" "$GREEN_APP_CONTAINER" >/dev/null 2>&1 || true
}

refuse_leftover_green() {
    if docker inspect "$GREEN_APP_CONTAINER" >/dev/null 2>&1; then
        fail "$GREEN_APP_CONTAINER already exists. If a previous Path B deploy left HTTP on it, do not remove it until singlepage-app is healthy and nginx points at singlepage-app. If it is an abandoned pre-check, remove it with: docker rm -f $GREEN_APP_CONTAINER $GREEN_NGINX_CONTAINER"
    fi
    if docker inspect "$GREEN_NGINX_CONTAINER" >/dev/null 2>&1; then
        log "Removing leftover $GREEN_NGINX_CONTAINER"
        docker rm -f "$GREEN_NGINX_CONTAINER" >/dev/null
    fi
}

retarget_health_nginx() {
    local php_host="$1"
    write_health_conf "$php_host"
    docker exec "$GREEN_NGINX_CONTAINER" nginx -t
    docker exec "$GREEN_NGINX_CONTAINER" nginx -s reload
}

recreate_canonical_php() {
    log "Recreating $APP_CONTAINER on the new image while nginx still sends HTTP to green"
    docker tag singlepage:incoming singlepage
    docker compose up -d --no-deps --force-recreate singlepage
    wait_running "$APP_CONTAINER"
}

compose_file_changed() {
    local f
    if [ "${#CHANGED_FILES[@]}" -eq 0 ]; then
        return 1
    fi
    for f in "${CHANGED_FILES[@]}"; do
        case "$f" in
            docker-compose.green.yml|docker-compose.green.yaml)
                ;;
            docker-compose.yml|docker-compose.yaml|compose.yml|compose.yaml|docker-compose.*|compose.*)
                return 0
                ;;
        esac
    done
    return 1
}

recreate_public_nginx_if_compose_changed() {
    if ! compose_file_changed; then
        return 0
    fi
    log "Compose file changed. Recreating $NGINX_CONTAINER so new ports or volume mounts apply."
    log "HTTP on port ${APP_PORT} drops until nginx is listening again (usually a couple of seconds)."
    docker compose up -d --no-deps --force-recreate nginx
    wait_running "$NGINX_CONTAINER"
}

recreate_reverb_and_scheduler() {
    log "Recreating Reverb and the scheduler."
    log "WebSocket clients will disconnect briefly and must reconnect. HTTP stays up."
    docker tag singlepage:incoming singlepage
    docker compose up -d --no-deps --force-recreate reverb scheduler
    wait_running "$REVERB_CONTAINER"
    wait_running "$SCHEDULER_CONTAINER"
    # Flush Docker DNS so the recreated Reverb name is used immediately.
    reload_public_nginx
}

public_health() {
    local url="http://127.0.0.1:${APP_PORT}/" code
    log "Checking $url"
    code=$(curl -sS --max-time 15 -o /dev/null -w '%{http_code}' "$url" 2>/dev/null || echo "FAILED")
    case "$code" in
        200|301|302|303)
            log "Health check PASSED (HTTP $code)."
            ;;
        FAILED)
            log "WARNING: curl could not reach $url — check nginx / firewall."
            ;;
        *)
            log "WARNING: Health check returned HTTP $code — verify the app manually."
            ;;
    esac
    local up_code
    up_code=$(curl -sS --max-time 15 -o /dev/null -w '%{http_code}' "http://127.0.0.1:${APP_PORT}/up" 2>/dev/null || echo "FAILED")
    case "$up_code" in
        200) log "Health check /up PASSED." ;;
        503) log "Health check /up returned 503 (maintenance or boot failure)." ;;
        *) log "WARNING: /up returned ${up_code}." ;;
    esac
}

drain() {
    if [ "$DRAIN_SECONDS" -eq 0 ]; then
        return 0
    fi
    log "Draining in-flight requests for ${DRAIN_SECONDS}s before stopping the previous PHP container"
    sleep "$DRAIN_SECONDS"
}

require_env_file() {
    [ -f "$APP_DIR/.env" ] || fail "Missing $APP_DIR/.env"
}

cleanup_on_exit() {
    local status=$?
    if [ "$status" -ne 0 ]; then
        log "Deploy failed (exit $status)."
    fi
    if [ "${TRAFFIC:-blue}" = "green" ]; then
        log "HTTP is still on $GREEN_APP_CONTAINER. Leaving that container and $RELEASE_DIR in place."
        log "Do not delete them until $NGINX_CONTAINER is confirmed on singlepage-app and that container is healthy."
    else
        cleanup_green || true
        rollback_staged || true
        cleanup_release || true
        restore_upstream_symlink || true
    fi
    if [ "${MAINTENANCE_ACTIVE:-0}" -eq 1 ]; then
        log "Lifting maintenance mode"
        docker exec "$APP_CONTAINER" php artisan up >/dev/null 2>&1 \
            || docker exec "$GREEN_APP_CONTAINER" php artisan up >/dev/null 2>&1 \
            || true
    fi
    if [ -n "${RUN_DIR:-}" ] && [ -d "${RUN_DIR:-}" ]; then
        rm -rf "$RUN_DIR" || true
    fi
}

path_assets() {
    local reload_nginx="$1"
    step "Path A — refresh bind-mounted code without recreating containers"
    extract_release "$NEW_SHA"
    if [ "$reload_nginx" -eq 1 ]; then
        preflight_nginx "$RELEASE_DIR"
    fi
    build_assets "$RELEASE_DIR"
    if [ -d "$RELEASE_DIR/public/build" ]; then
        stage_dir "$RELEASE_DIR/public/build" "$APP_DIR/public/build"
    fi
    maybe_down
    git_ff
    commit_staged
    if lockfile_changed; then
        composer_in_container "$APP_CONTAINER"
    else
        log "composer.lock unchanged — skipping composer install"
    fi
    maybe_migrate "$APP_CONTAINER"
    rebuild_caches "$APP_CONTAINER" 1 0
    if [ "$reload_nginx" -eq 1 ]; then
        reload_public_nginx
    fi
    if [ "$RESTART_REVERB" -eq 1 ]; then
        step "Restart Reverb (requested)"
        recreate_reverb_only
    fi
    maybe_up
    public_health
}

recreate_reverb_only() {
    log "Recreating $REVERB_CONTAINER — WebSocket clients will disconnect and must reconnect."
    docker compose up -d --no-deps --force-recreate reverb
    wait_running "$REVERB_CONTAINER"
    reload_public_nginx
}

path_b() {
    step "Path B — blue-green PHP replace (global-mysql is not touched)"
    refuse_leftover_green
    require_env_file
    extract_release "$NEW_SHA"
    preflight_nginx "$RELEASE_DIR"
    build_image
    install_release_vendor
    build_assets "$RELEASE_DIR"
    prepare_green_compose
    pick_green_port
    write_health_conf "$GREEN_APP_CONTAINER"
    start_green "$RELEASE_DIR"
    wait_running "$GREEN_APP_CONTAINER"
    wait_running "$GREEN_NGINX_CONTAINER"
    # Give php-fpm a moment after the container is Running.
    sleep 2
    wait_http "http://127.0.0.1:${DEPLOY_GREEN_PORT_SELECTED}/up" 0
    rebuild_caches "$GREEN_APP_CONTAINER" 1 1
    wait_http "http://127.0.0.1:${DEPLOY_GREEN_PORT_SELECTED}/up" 0

    maybe_down
    maybe_migrate "$GREEN_APP_CONTAINER"

    if [ -d "$RELEASE_DIR/public/build" ]; then
        stage_dir "$RELEASE_DIR/public/build" "$APP_DIR/public/build"
    fi
    if lockfile_changed || [ ! -d "$APP_DIR/vendor" ]; then
        stage_dir "$RELEASE_DIR/vendor" "$APP_DIR/vendor"
    fi

    step "Fast-forward the live tree and switch nginx to green PHP"
    git_ff
    commit_staged
    copy_package_manifests
    if container_running "$APP_CONTAINER"; then
        rebuild_caches "$APP_CONTAINER" 0 1 || log "Cache refresh on the previous PHP container failed; switching to green."
    fi
    switch_public_upstream "$GREEN_APP_CONTAINER" "$REVERB_CONTAINER"
    TRAFFIC="green"
    drain

    step "Recreate canonical $APP_CONTAINER on the new image"
    recreate_canonical_php
    retarget_health_nginx "$APP_CONTAINER"
    local allow_maintenance=0
    if [ "$MAINTENANCE_ACTIVE" -eq 1 ]; then
        allow_maintenance=1
    fi
    wait_http "http://127.0.0.1:${DEPLOY_GREEN_PORT_SELECTED}/up" "$allow_maintenance"
    rebuild_caches "$APP_CONTAINER" 1 0

    step "Point nginx back at $APP_CONTAINER"
    switch_public_upstream "$APP_CONTAINER" "$REVERB_CONTAINER"
    TRAFFIC="blue"
    drain
    cleanup_green

    recreate_reverb_and_scheduler
    recreate_public_nginx_if_compose_changed
    maybe_up
    public_health
}

cold_start() {
    step "Cold start — $APP_CONTAINER or $NGINX_CONTAINER is not running"
    log "Blue-green is skipped because there is no live edge to preserve. global-mysql is not touched."
    require_env_file
    extract_release "$NEW_SHA"
    if [ "$MODE" = "nginx" ] || [ "$MODE" = "container" ]; then
        preflight_nginx "$RELEASE_DIR"
    fi
    if [ "$MODE" = "container" ]; then
        build_image
        install_release_vendor
    fi
    build_assets "$RELEASE_DIR"
    if [ -d "$RELEASE_DIR/public/build" ]; then
        stage_dir "$RELEASE_DIR/public/build" "$APP_DIR/public/build"
    fi
    if [ "$MODE" = "container" ] && { lockfile_changed || [ ! -d "$APP_DIR/vendor" ]; }; then
        stage_dir "$RELEASE_DIR/vendor" "$APP_DIR/vendor"
    fi
    git_ff
    if [ "$MODE" = "container" ]; then
        copy_package_manifests
    fi
    commit_staged
    if [ "$MODE" = "container" ]; then
        docker tag singlepage:incoming singlepage
        docker compose up -d --no-deps --force-recreate singlepage reverb scheduler nginx
    else
        docker compose up -d --no-deps singlepage reverb scheduler nginx
    fi
    wait_running "$APP_CONTAINER"
    wait_running "$NGINX_CONTAINER"
    sleep 2
    if [ "$MODE" != "container" ] && { lockfile_changed || [ ! -d "$APP_DIR/vendor" ]; }; then
        composer_in_container "$APP_CONTAINER"
    fi
    if [ "$MAINTENANCE" -eq 1 ]; then
        docker exec "$APP_CONTAINER" php artisan down --retry=15
        MAINTENANCE_ACTIVE=1
    fi
    maybe_migrate "$APP_CONTAINER"
    rebuild_caches "$APP_CONTAINER" 1 0
    if [ "$MODE" = "nginx" ]; then
        reload_public_nginx
    fi
    maybe_up
    public_health
}

finish_log() {
    step "Deploy complete"
    log "Path: $(path_label)"
    log "Ref: $REF → $(git rev-parse --short HEAD) — $(git log -1 --format='%s')"
    docker compose ps --format 'table {{.Name}}\t{{.Status}}' 2>/dev/null || true
}

main() {
    parse_args "$@"
    require_uint DEPLOY_HEALTH_TIMEOUT "$HEALTH_TIMEOUT"
    require_uint DEPLOY_DRAIN_SECONDS "$DRAIN_SECONDS"
    cd "$APP_DIR" || fail "Cannot cd to APP_DIR=$APP_DIR"
    load_app_port
    acquire_lock
    step "Git: check working tree"
    dirty_guard
    step "Git: fetch $REF"
    fetch_ref
    resolve_ref
    load_changed_files
    collect_classification
    log "Deploying from: $APP_DIR"
    log "Path selected: $(path_label)"
    if [ "$PLAN_ONLY" -eq 1 ]; then
        print_plan
        exit 0
    fi
    require_docker
    RUN_DIR=$(mktemp -d /tmp/singlepage-deploy.XXXXXX)
    chmod 755 "$RUN_DIR"
    trap cleanup_on_exit EXIT

    if ! container_running "$APP_CONTAINER" || ! container_running "$NGINX_CONTAINER"; then
        cold_start
    elif [ "$MODE" = "container" ]; then
        path_b
    elif [ "$MODE" = "nginx" ]; then
        path_assets 1
    else
        path_assets 0
    fi
    finish_log
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    main "$@"
fi
