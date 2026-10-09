#!/usr/bin/env bash
# Proves the application switcher (K31; app/switcher.php os_my_applications(), shared/app-switcher.php, the header):
#   1. standalone (config/.env with OS_ENABLED empty, under php -S): the header carries no switcher;
#   2. in process, against the REAL kernel on this host with this checkout's OS_APPLICATION_TOKEN (a read): the feed for the
#      first super-admin arrives, is cached in the session, the partial renders the Helpdesk button (this is not the Help Desk),
#      one row per application with ProcessCore marked current and not linked, the launcher and the OS at the foot, every link
#      a full navigation on OS_LAUNCHER_URL; a feed in which this application is the Help Desk hides the button; no feed renders nothing.
set -u
cd "$(dirname "$0")/.."
PORT=$(grep -E '^APP_INTERNAL_PORT=' config/.env | cut -d= -f2); DB=$(grep -E '^DB_NAME=' config/.env | cut -d= -f2)
case "$DB" in *_dev|*_prove*|processcore_w[0-9]) ;; *) echo "refusing $DB: only a scratch database" >&2; exit 2;; esac
pass=0; fail=0; ok() { pass=$((pass+1)); echo "ok   $1"; }; bad() { fail=$((fail+1)); echo "FAIL $1"; }; check(){ if eval "$2"; then ok "$1"; else bad "$1"; fi; }
php -l app/switcher.php >/dev/null && php -l app/views/shared/app-switcher.php >/dev/null && ok "lint" || bad "lint"
check "the layout renders the switcher before the dark-mode toggle" "grep -B1 'dark-light-theme' app/views/layout.php | grep -q \"view('shared/app-switcher.php')\""
echo "== 1. standalone"
php -S 127.0.0.1:$PORT -t html html/_router.php >/tmp/pc-switcher-php.log 2>&1 & PHP_PID=$!; sleep 1.5
B="http://127.0.0.1:$PORT"; J=$(mktemp)
t=$(curl -s -b $J -c $J $B/login | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -b $J -c $J -X POST --data-urlencode "csrf_token=$t" --data-urlencode "email=owner@processcore.test" --data-urlencode "password=owner-dev-password" --data-urlencode "next=/" $B/login
check "a standalone shell has no Helpdesk button and no applications menu" "! curl -s -b $J $B/purchase-orders/ | grep -q 'header-apps-toggle\|header-helpdesk-btn'"
check "no PHP warning" "! grep -qiE 'PHP (Warning|Notice|Fatal)' /tmp/pc-switcher-php.log"
kill $PHP_PID 2>/dev/null; rm -f $J
echo "== 2. against the kernel, in process"
out=$(php scripts/prove-switcher.php 2>&1); rc=$?
echo "$out" | grep -E '^(ok|FAIL)' | while read -r l; do echo "$l"; done
p2=$(echo "$out" | grep -c '^ok'); f2=$(echo "$out" | grep -c '^FAIL'); pass=$((pass+p2)); fail=$((fail+f2))
if [ $rc -ne 0 ] || [ $p2 -lt 10 ] || echo "$out" | grep -q 'Exception\|Fatal'; then bad "the in-process half stopped early (exit $rc, $p2 ok lines): $(echo "$out" | grep -m1 'Exception\|Fatal\|FAIL' | cut -c1-160)"; fi
echo "== $pass passed, $fail failed"; [ $fail -eq 0 ]
