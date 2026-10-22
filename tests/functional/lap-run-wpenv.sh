#!/usr/bin/env bash
#
# Laptop only: the same steps as run.sh, against a wp-env store that mounts this plugin.
# run.sh drives WordPress through a local WP-CLI at WP_PATH; here every WP-CLI call goes into
# the wp-env cli container instead. Not part of the suite.
#
#   LAP_CLI=<cli container> NODE_PATH=C:/path/to/node_modules bash tests/functional/lap-run-wpenv.sh
#
# What is adapted, and nothing else:
# - `wp --path=WP_PATH ...` becomes `docker exec <cli container> wp ...`, with RFQ_STATE passed
#   in. The state folder lives in the store's wp-content/uploads, which the laptop and the
#   container both see (wp-env mounts its WordPress folder).
# - frontend.js and admin.js run with `node -r lap-wpenv-shim.cjs`, which sends their `wp`
#   calls to the same container and uses the installed Chrome.
# - wp-content/mu-plugins is created in the store if missing (setup.php writes its mail capture there).
# The order of steps, the files run and the result counting are run.sh's.
set -uo pipefail
export MSYS_NO_PATHCONV=1

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CLI="${LAP_CLI:?set LAP_CLI to the wp-env cli container that mounts this plugin}"
WP_HOST_ROOT="$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html"}}{{.Source}}{{end}}{{end}}' "$CLI" | tr '\\' '/')"
[ -n "$WP_HOST_ROOT" ] || { echo "cannot find the store's WordPress folder"; exit 2; }
STATE_NAME="lap-rfq-state-$$"
STATE="$WP_HOST_ROOT/wp-content/uploads/$STATE_NAME"
CSTATE="/var/www/html/wp-content/uploads/$STATE_NAME"
mkdir -p "$STATE"
PLUGINS_HOST="$(cygpath -m "$(cd "$HERE/../../.." && pwd)")"
export RFQ_STATE="$(cygpath -m "$STATE")" LAP_CLI="$CLI" LAP_PLUGINS_HOST="$PLUGINS_HOST" LAP_STATE_CONTAINER="$CSTATE" WP_CLI=wp WP_PATH=/var/www/html
[ -n "${NODE_PATH:-}" ] || { echo "set NODE_PATH to a node_modules with playwright (Windows path)"; exit 2; }

CDIR="/var/www/html/wp-content/plugins/wpheka-request-for-quote/tests/functional"
wpx() { docker exec -e RFQ_STATE="$CSTATE" -w /var/www/html "$CLI" wp "$@"; }
wpe() { wpx eval-file "$CDIR/$(basename "$1")" "${@:2}" 2>&1 | grep -v -e 'Deprecated' -e '^$' -e 'Undefined array key 1'; }

run_node() {
  local script="$1"; shift
  node -r "$(cygpath -m "$HERE/lap-wpenv-shim.cjs")" "$(cygpath -m "$HERE/$script")" "$@"
  local code=$?
  if [ "$code" -ne 0 ]; then
    echo "  FAIL|$script exited with code $code"
    echo "FAIL|$script exited with code $code" >> "$STATE/results"
  fi
  if ! grep -qxF "PASS|$script: ran to the end" "$STATE/results" 2>/dev/null; then
    echo "  FAIL|$script: ran to the end -- the script stopped before its last case"
    echo "FAIL|$script: ran to the end -- the script stopped before its last case" >> "$STATE/results"
  fi
}

cleanup() {
  wpe "$HERE/cleanup.php" | sed 's/^/  /'
  rm -rf "$STATE"
}
CLEANED=0
on_exit() { [ "$CLEANED" = 1 ] && return; CLEANED=1; cleanup; }
trap on_exit EXIT
trap 'on_exit; exit 130' INT TERM HUP

docker exec "$CLI" sh -c 'mkdir -p /var/www/html/wp-content/mu-plugins'
OFFSET=$(docker exec "$CLI" sh -c 'wc -c < /var/www/html/wp-content/debug.log 2>/dev/null || echo 0' | tr -d '\r')

echo "== setup";    wpe "$HERE/setup.php" | sed 's/^/  /'
[ -f "$STATE/ids.json" ] || { echo "setup failed"; exit 2; }
echo "== shopper";  run_node frontend.js
echo "== shop owner"; run_node admin.js
echo "== stored requests, handlers, privacy, uninstall (requests.php)"
wpe "$HERE/requests.php" | sed 's/^/  /'
if ! grep -qxF "PASS|requests: ran to the end" "$STATE/results" 2>/dev/null; then
  echo "  FAIL|requests.php: ran to the end -- it stopped before its last case"
  echo "FAIL|requests.php: ran to the end -- it stopped before its last case" >> "$STATE/results"
fi

NEW=$(docker exec "$CLI" sh -c "tail -c +$((OFFSET + 1)) /var/www/html/wp-content/debug.log 2>/dev/null" | grep -v 'PHP Deprecated' | grep 'wpheka-request-for-quote' || true)
if [ -n "$NEW" ]; then echo "$NEW" | cut -c1-300; echo "FAIL|debug.log clean -- new errors above" >> "$STATE/results"; else echo "PASS|debug.log clean" >> "$STATE/results"; fi

echo; echo "== results"
PASS=$(grep -c '^PASS|' "$STATE/results"); FAIL=$(grep -c '^FAIL|' "$STATE/results")
sed 's/^\([A-Z]*\)|/\1  /' "$STATE/results"
echo; echo "$PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
