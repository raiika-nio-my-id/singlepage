#!/usr/bin/env bash
# Classifier coverage for scripts/deploy.sh. No Docker and no git mutations.
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/.." && pwd)
# shellcheck disable=SC1091
source "$ROOT/scripts/deploy.sh"

failures=0

assert_mode() {
    local expect="$1"
    shift
    local got
    got=$(classify_mode "$@")
    if [ "$got" != "$expect" ]; then
        printf 'FAIL: [%s] → %s (expected %s)\n' "$*" "$got" "$expect" >&2
        failures=$((failures + 1))
    fi
}

assert_mode assets
assert_mode assets app/Http/Controllers/Foo.php resources/js/app.js resources/views/app.blade.php
assert_mode assets composer.json composer.lock database/migrations/2026_01_01_000000_example.php
assert_mode assets scripts/deploy.sh scripts/deploy-classify-test.sh .github/workflows/deploy.yml
assert_mode assets docker-compose.green.yml DEPLOY.md .gitignore
assert_mode assets public/index.php routes/web.php config/app.php

assert_mode nginx docker-config/nginx/default.conf
assert_mode nginx docker-config/nginx/upstream.default.conf
assert_mode nginx .deploy/nginx-upstream.conf
assert_mode nginx docker-config/nginx/default.conf app/Models/User.php composer.lock

assert_mode container Dockerfile
assert_mode container Dockerfile.php
assert_mode container services/foo/Dockerfile
assert_mode container .dockerignore
assert_mode container php.ini
assert_mode container php-fpm.conf
assert_mode container php-fpm.d/www.conf
assert_mode container docker-compose.yml
assert_mode container docker-compose.yaml
assert_mode container docker-compose.override.yml
assert_mode container docker-compose.prod.yml
assert_mode container compose.yml
assert_mode container compose.prod.yaml
assert_mode container docker/php/Dockerfile
assert_mode container docker/nginx/extra/site.conf
assert_mode container docker-config/php/custom.ini
assert_mode container docker-config/php/conf.d/opcache.ini
assert_mode container docker-config/scheduler/entrypoint.sh
assert_mode container docker-config/other/thing.txt
assert_mode container Dockerfile docker-config/nginx/default.conf app/Foo.php
assert_mode container docker-compose.green.yml Dockerfile

if ! grep -q 'include /var/www/.deploy/nginx-upstream.conf;' "$ROOT/docker-config/nginx/default.conf"; then
    echo 'FAIL: default.conf does not include the upstream file' >&2
    failures=$((failures + 1))
fi
# shellcheck disable=SC2016
if ! grep -q 'fastcgi_pass $php_upstream;' "$ROOT/docker-config/nginx/default.conf"; then
    # shellcheck disable=SC2016
    echo 'FAIL: default.conf does not proxy PHP through $php_upstream' >&2
    failures=$((failures + 1))
fi
# shellcheck disable=SC2016
if ! grep -q 'set $php_upstream singlepage-app:9000;' "$ROOT/docker-config/nginx/upstream.default.conf"; then
    echo 'FAIL: default upstream is not singlepage-app' >&2
    failures=$((failures + 1))
fi
# shellcheck disable=SC2016
if ! grep -q 'set $reverb_upstream singlepage-reverb:8080;' "$ROOT/docker-config/nginx/upstream.default.conf"; then
    echo 'FAIL: default reverb upstream is not singlepage-reverb' >&2
    failures=$((failures + 1))
fi
if [ ! -L "$ROOT/.deploy/nginx-upstream.conf" ]; then
    echo 'FAIL: .deploy/nginx-upstream.conf must be a symlink' >&2
    failures=$((failures + 1))
fi
if ! grep -q 'container_name: singlepage-app-green' "$ROOT/docker-compose.green.yml"; then
    echo 'FAIL: green compose file is missing singlepage-app-green' >&2
    failures=$((failures + 1))
fi
if grep -E '^[[:space:]]*(container_name: global-mysql|global-mysql:)' "$ROOT/docker-compose.green.yml"; then
    echo 'FAIL: green compose file must not define global-mysql' >&2
    failures=$((failures + 1))
fi
if grep -q 'artisan down' "$ROOT/scripts/deploy.sh" && ! grep -q 'DEPLOY_MAINTENANCE' "$ROOT/scripts/deploy.sh"; then
    echo 'FAIL: maintenance flag missing' >&2
    failures=$((failures + 1))
fi

help_out=$(bash "$ROOT/scripts/deploy.sh" --help)
if ! printf '%s\n' "$help_out" | grep -q -- '--maintenance'; then
    echo 'FAIL: --help does not mention --maintenance' >&2
    failures=$((failures + 1))
fi

CHANGED_FILES=(docker-compose.green.yml app/Foo.php)
if compose_file_changed; then
    echo 'FAIL: docker-compose.green.yml must not force an nginx recreate' >&2
    failures=$((failures + 1))
fi
CHANGED_FILES=(docker-compose.yml)
if ! compose_file_changed; then
    echo 'FAIL: docker-compose.yml should force an nginx recreate' >&2
    failures=$((failures + 1))
fi
# shellcheck disable=SC2034
CHANGED_FILES=()
if compose_file_changed; then
    echo 'FAIL: empty diff should not recreate nginx' >&2
    failures=$((failures + 1))
fi

if [ "$failures" -ne 0 ]; then
    printf '%s assertion(s) failed\n' "$failures" >&2
    exit 1
fi

echo "deploy classify tests passed"
