#!/usr/bin/env bash
#
# Full functional suite for WPHEKA Request For Quote. Run before every release:
#
#   bash tests/functional/run.sh
#
# Drives the store in headless Chromium as a guest and as the shop owner: the
# Add to quote button with the price and cart hidden, on product and shop pages;
# adding, updating and removing in the quote list; sending the request and the
# email the shop receives; every display setting; the Settings page; the review
# request; that other plugins' notices stay off the plugin page; the 1.9.0 page
# (settings, Requests tab, stats) and stored requests
# (requests.php: handlers, privacy, uninstall); and that requests without a valid
# nonce are refused. Fails on any
# case that misbehaves or on new PHP errors from this plugin in debug.log. The
# plugin is activated for the suite if it is not already, and everything is put
# back afterwards, even after a failure.
#
# Needs: WP-CLI and Node with Playwright (PW_NODE_PATH, or a node_modules below).
# Optional: WP_CLI="php /path/to/wp-cli.phar" when node cannot start `wp`, and
# PW_CHANNEL=chrome to use the installed Google Chrome.
set -uo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP_PATH="${WP_PATH:-/Applications/MAMP/htdocs/wpheka-plugins}"
STATE="$(mktemp -d)"
export RFQ_STATE="$STATE" WP_PATH

for candidate in "${PW_NODE_PATH:-}" \
    "$WP_PATH/wp-content/plugins/wc-moneris-payment-gateway/node_modules" \
    "$WP_PATH/wp-content/plugins/wc-moneris-payment-gateway-pro/node_modules"; do
  if [ -n "$candidate" ] && [ -d "$candidate/playwright" ]; then export NODE_PATH="$candidate"; break; fi
done
[ -n "${NODE_PATH:-}" ] || { echo "Playwright not found; set PW_NODE_PATH"; exit 2; }

wpe() { wp --path="$WP_PATH" eval-file "$@" 2>&1 | grep -v -e 'Deprecated' -e '^$' -e 'Undefined array key 1'; }

# Runs one browser script. Never "|| true" a test step: a non-zero exit is a
# FAIL, and so is a script that stopped before its final "ran to the end" case
# (an unsettled promise lets node exit 0 halfway through).
run_node() {
  local script="$1"; shift
  node "$HERE/$script" "$@"
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
# Clean up on every way out: normal exit, Ctrl-C, kill, a closed terminal.
CLEANED=0
on_exit() { [ "$CLEANED" = 1 ] && return; CLEANED=1; cleanup; }
trap on_exit EXIT
trap 'on_exit; exit 130' INT TERM HUP

LOG="$WP_PATH/wp-content/debug.log"
OFFSET=$(wc -c < "$LOG" 2>/dev/null || echo 0)

echo "== setup";    wpe "$HERE/setup.php" | sed 's/^/  /'
[ -f "$STATE/ids.json" ] || { echo "setup failed"; exit 2; }
echo "== shopper";  run_node frontend.js
echo "== shop owner"; run_node admin.js
echo "== stored requests, handlers, privacy, uninstall (requests.php)"
wpe "$HERE/requests.php" | sed 's/^/  /'
# Same guard as run_node: a fatal error or an early exit stops it before its last case.
if ! grep -qxF "PASS|requests: ran to the end" "$STATE/results" 2>/dev/null; then
  echo "  FAIL|requests.php: ran to the end -- it stopped before its last case"
  echo "FAIL|requests.php: ran to the end -- it stopped before its last case" >> "$STATE/results"
fi

# grep finds nothing in a clean log and exits 1; "|| true" only reads the log here.
NEW=$(tail -c +$((OFFSET + 1)) "$LOG" 2>/dev/null | grep -v 'PHP Deprecated' | grep 'wpheka-request-for-quote' || true)
if [ -n "$NEW" ]; then echo "$NEW" | cut -c1-300; echo "FAIL|debug.log clean -- new errors above" >> "$STATE/results"; else echo "PASS|debug.log clean" >> "$STATE/results"; fi

echo; echo "== results"
PASS=$(grep -c '^PASS|' "$STATE/results"); FAIL=$(grep -c '^FAIL|' "$STATE/results")
sed 's/^\([A-Z]*\)|/\1  /' "$STATE/results"
echo; echo "$PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
