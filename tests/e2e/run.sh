#!/usr/bin/env bash
# SPDX-License-Identifier: GPL-3.0-or-later
# SPDX-FileCopyrightText: 2026 Bodo Steffen
# Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt
#
# End-to-end tests of the plugin in a real Stud.IP in Docker.
#
#   tests/e2e/run.sh all 6.3      build or reuse the image, start, test, stop
#   tests/e2e/run.sh build 6.0    build the Stud.IP image only
#   tests/e2e/run.sh up 6.3       start an instance with the plugin from plugin/
#   tests/e2e/run.sh test 6.3     run the e2e suite against a started instance
#   tests/e2e/run.sh down 6.3     stop and remove the instance
#   tests/e2e/run.sh ref 6.3      print the pinned core sources
#
# The Stud.IP image is built from the official core sources with the
# Dockerfile that ships with the core (docker/studip/Dockerfile), pinned
# below. Environment:
#   E2E_STUDIP_IMAGE  use this existing image instead of building one
#   E2E_PORT          host port (default 8460 + minor version, e.g. 8463)
#   E2E_IMAGE_CACHE   directory with saved images (docker save), used by CI
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$HERE/../.." && pwd)"
STUDIP_GIT=https://gitlab.studip.de/studip/studip.git

die() { echo "$*" >&2; exit 1; }

# Pinned core sources. 6.2 has no release tag yet; 6.3 has neither tag nor
# branch, it is the 6.3.alpha state of the main branch.
core_ref() {
    case "$1" in
        6.0) echo v6.0.7 ;;
        6.1) echo v6.1.4 ;;
        6.2) echo f1b79f8d1e47413130d3c5d47196236daee90d0c ;;
        6.3) echo 4d9fc53c87d6b7a4909c85ddc71e3e1c4d40dd44 ;;
        *) die "Unknown Stud.IP version '$1' (use 6.0, 6.1, 6.2 or 6.3)." ;;
    esac
}

[ $# -ge 2 ] || die "Usage: $0 all|build|up|test|down|ref VERSION [phpunit args]"
CMD=$1 VERSION=$2
shift 2
REF=$(core_ref "$VERSION")
MINOR=${VERSION#6.}

export E2E_PORT=${E2E_PORT:-$((8460 + MINOR))}
export E2E_IMAGE=${E2E_STUDIP_IMAGE:-app-connect-e2e-studip:$VERSION}
export E2E_PLUGIN_DIR="$ROOT/plugin"
PROJECT="app-connect-e2e-${VERSION/./-}"
compose() { docker compose -p "$PROJECT" -f "$HERE/compose.yml" "$@"; }

# Builds the image unless one from the pinned sources exists (or is cached).
build_core() {
    [ -n "${E2E_STUDIP_IMAGE:-}" ] && return
    local label="{{index .Config.Labels \"app-connect.core-ref\"}}"
    [ "$(docker image inspect -f "$label" "$E2E_IMAGE" 2>/dev/null)" = "$REF" ] && return
    local cache="${E2E_IMAGE_CACHE:-}/studip-$VERSION-$REF.tar.gz"
    if [ -n "${E2E_IMAGE_CACHE:-}" ] && [ -f "$cache" ]; then
        gunzip -c "$cache" | docker load
        [ "$(docker image inspect -f "$label" "$E2E_IMAGE" 2>/dev/null)" = "$REF" ] && return
    fi
    echo "Building the Stud.IP $VERSION image from $REF (takes a while)"
    # The asset build of the core fails now and then, so it gets a second try.
    build_from_core || build_from_core
    if [ -n "${E2E_IMAGE_CACHE:-}" ]; then
        mkdir -p "$E2E_IMAGE_CACHE"
        docker save "$E2E_IMAGE" | gzip -1 > "$cache"
    fi
}

# On the main branch (6.3) the Dockerfile still calls the removed make target
# webpack-prod and uses Node 18, while the assets need Node 20 or later; those
# two lines are fixed on the fly. The other versions build unchanged.
build_from_core() {
    local dockerfile
    dockerfile=$(curl -fsSL "${STUDIP_GIT%.git}/-/raw/$REF/docker/studip/Dockerfile") \
        || die "Cannot fetch the core Dockerfile for $REF."
    if [ "$VERSION" = 6.3 ]; then
        dockerfile=$(printf '%s\n' "$dockerfile" | sed \
            -e 's/^FROM node:18 AS nodejs/FROM node:22 AS nodejs/' \
            -e 's/^RUN make webpack-prod/RUN make assets-prod/')
    fi
    printf '%s\n' "$dockerfile" \
        | docker build -t "$E2E_IMAGE" --label "app-connect.core-ref=$REF" -f - "$STUDIP_GIT#$REF"
}

up() {
    build_core
    compose up -d --wait --wait-timeout 600 || { compose logs web | tail -60; exit 1; }
    echo "Stud.IP $VERSION ready at http://localhost:$E2E_PORT/ and http://localhost:$E2E_PORT/studip/"
}

run_tests() {
    [ -d "$ROOT/plugin/vendor" ] || (cd "$ROOT/plugin" && composer install --no-interaction --quiet)
    local container
    container=$(compose ps -q web)
    [ -n "$container" ] || die "No running instance for Stud.IP $VERSION; start it with: $0 up $VERSION"
    (cd "$ROOT/plugin" && APP_CONNECT_E2E_URL="http://localhost:$E2E_PORT" \
        APP_CONNECT_E2E_CONTAINER="$container" vendor/bin/phpunit --testsuite e2e "$@")
}

case "$CMD" in
    ref) echo "$REF" ;;
    build) build_core ;;
    up) up ;;
    test) run_tests "$@" ;;
    down) compose down --volumes --remove-orphans ;;
    all)
        trap 'compose down --volumes --remove-orphans >/dev/null 2>&1 || true' EXIT
        up
        run_tests "$@"
        ;;
    *) die "Unknown command '$CMD'." ;;
esac
