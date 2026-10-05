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
# request; and that requests without a valid nonce are refused. Fails on any
# case that misbehaves or on new PHP errors from this plugin in debug.log. The
# plugin is activated for the suite if it is not already, and everything is put
# back afterwards, even after a failure.
#
# Needs: WP-CLI and Node with Playwright (PW_NODE_PATH, or a node_modules below).
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
echo "== shopper";  node "$HERE/frontend.js" || true
echo "== shop owner"; node "$HERE/admin.js" || true

NEW=$(tail -c +$((OFFSET + 1)) "$LOG" 2>/dev/null | grep -v 'PHP Deprecated' | grep 'wpheka-request-for-quote' || true)
if [ -n "$NEW" ]; then echo "$NEW" | cut -c1-300; echo "FAIL|debug.log clean -- new errors above" >> "$STATE/results"; else echo "PASS|debug.log clean" >> "$STATE/results"; fi

echo; echo "== results"
PASS=$(grep -c '^PASS|' "$STATE/results"); FAIL=$(grep -c '^FAIL|' "$STATE/results")
sed 's/^\([A-Z]*\)|/\1  /' "$STATE/results"
echo; echo "$PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
