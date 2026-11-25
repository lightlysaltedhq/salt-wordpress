#!/usr/bin/env bash
# The real-ACF harness: builds a throwaway WordPress with the salt theme and real ACF Pro,
# checks the theme registers the pinned salt-contract emitter's field groups, seeds and reads back every
# contract fixture, checks storage paths, drives the classic editor in a real browser, prints
# a pass/fail report and removes everything it made. See tests/real-acf/README.md.
#
# usage: tests/real-acf/run.sh [--php 8.2|8.5|<binary>] [--no-browser] [--keep] [--offline] [--report <file>] [--conformance|--limits]
# --offline skips the check that the pinned WordPress is the latest release (reported as not checked).
# --conformance builds the site, keeps it up and runs salt-conformance against it instead of the checks:
#   the adapter sends the contract's view and page cases here (tests/contract-adapter/README.md).
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"

PHP_CHOICE="${SALT_HARNESS_PHP:-php}"
KEEP=0
BROWSER=1
OFFLINE=0
REPORT=""
CONFORMANCE=0
LIMITS=0

usage() { sed -n '2,10p' "$0" | sed 's/^# \{0,1\}//'; }

while [ $# -gt 0 ]; do
	case "$1" in
		--php) PHP_CHOICE="${2:?--php needs a version or binary}"; shift 2 ;;
		--no-browser) BROWSER=0; shift ;;
		--keep) KEEP=1; shift ;;
		--offline) OFFLINE=1; shift ;;
		--report) REPORT="${2:?--report needs a file}"; shift 2 ;;
		--conformance) CONFORMANCE=1; BROWSER=0; shift ;;
		--limits) LIMITS=1; shift ;;
		-h|--help) usage; exit 0 ;;
		*) echo "unknown option: $1" >&2; usage >&2; exit 2 ;;
	esac
done

die() { echo "SETUP FAILED: $*" >&2; exit 2; }

# ── PHP ─────────────────────────────────────────────────────────────────────────────────────────
php_minor() { "$1" -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null || true; }
if [[ "$PHP_CHOICE" =~ ^[0-9]+\.[0-9]+$ ]]; then
	PHP=""
	for candidate in "php$PHP_CHOICE" "/opt/homebrew/opt/php@$PHP_CHOICE/bin/php" "/usr/local/opt/php@$PHP_CHOICE/bin/php" "/usr/bin/php$PHP_CHOICE" php; do
		if command -v "$candidate" >/dev/null 2>&1 && [ "$(php_minor "$candidate")" = "$PHP_CHOICE" ]; then
			PHP="$(command -v "$candidate")"
			break
		fi
	done
	[ -n "$PHP" ] || die "no PHP $PHP_CHOICE binary found; pass --php <path>"
else
	PHP="$(command -v "$PHP_CHOICE" || true)"
	[ -n "$PHP" ] || die "PHP binary $PHP_CHOICE not found"
fi
"$PHP" -r 'exit(extension_loaded("mysqli") ? 0 : 1);' || die "$PHP has no mysqli extension"
PHP_VERSION="$("$PHP" -r 'echo PHP_VERSION;')"

# ── ACF Pro: a Composer-installed copy, or the zip (hash-checked) ───────────────────────────────
ACF_VERSION="${SALT_ACF_VERSION:-6.8.11}"
ACF_COMPOSER_COPY="$HERE/acf-composer/vendor/wpengine/advanced-custom-fields-pro"
if [ -z "${SALT_ACF_DIR:-}" ] && [ -z "${SALT_ACF_ZIP:-}" ] && [ -f "$ACF_COMPOSER_COPY/acf.php" ]; then
	SALT_ACF_DIR="$ACF_COMPOSER_COPY"
fi
if [ -n "${SALT_ACF_DIR:-}" ]; then
	[ -f "$SALT_ACF_DIR/acf.php" ] || die "SALT_ACF_DIR=$SALT_ACF_DIR has no acf.php"
	ACF_SOURCE="Composer copy at $SALT_ACF_DIR"
else
	ACF_ZIP="${SALT_ACF_ZIP:-/Users/Shared/salt-harness/acf-pro-6.8.11.zip}"
	ACF_SHA256="${SALT_ACF_SHA256:-b383a6c059b14554bf5e011722da9ea5ca174a66af6b7a977d4324041d5e4376}"
	[ -f "$ACF_ZIP" ] || die "no ACF Pro zip at $ACF_ZIP (set SALT_ACF_ZIP, or SALT_ACF_DIR for a Composer copy)"
	if command -v sha256sum >/dev/null 2>&1; then
		actual="$(sha256sum "$ACF_ZIP" | cut -d' ' -f1)"
	else
		actual="$(shasum -a 256 "$ACF_ZIP" | cut -d' ' -f1)"
	fi
	[ "$actual" = "$ACF_SHA256" ] || die "ACF Pro zip SHA-256 is $actual, expected $ACF_SHA256"
	ACF_SOURCE="zip $ACF_ZIP (SHA-256 verified)"
fi

# ── Dependencies (the harness's own; the theme's install is never touched) ─────────────────────
[ -f "$REPO/node_modules/@lightlysaltedhq/salt-contract/package.json" ] || die "run npm ci at the repository root first (the pinned salt-contract)"
WP_CORE="$HERE/vendor/roots/wordpress-no-content"
# The WordPress this run uses is the one composer.lock pins: install it when it is missing or differs.
WP_PINNED="$("$PHP" -r 'foreach (json_decode(file_get_contents($argv[1]), true)["packages"] as $p) { if ($p["name"] === "roots/wordpress-no-content") { echo $p["version"]; } }' "$HERE/composer.lock")"
[ -n "$WP_PINNED" ] || die "tests/real-acf/composer.lock pins no roots/wordpress-no-content"
wp_installed() { sed -n "s/^\$wp_version = '\(.*\)';/\1/p" "$WP_CORE/wp-includes/version.php" 2>/dev/null || true; }
if [ ! -f "$WP_CORE/wp-load.php" ] || [ "$(wp_installed)" != "$WP_PINNED" ]; then
	composer install --working-dir="$HERE" --no-interaction --no-progress --quiet || die "composer install in tests/real-acf failed"
fi
[ "$(wp_installed)" = "$WP_PINNED" ] || die "WordPress in tests/real-acf/vendor is $(wp_installed), expected $WP_PINNED (composer.lock)"
if [ "$BROWSER" = 1 ]; then
	[ -d "$HERE/node_modules/playwright" ] || npm ci --prefix "$HERE" --no-audit --no-fund --silent || die "npm ci in tests/real-acf failed"
	(cd "$HERE" && npx --no-install playwright install chromium-headless-shell >/dev/null) || die "could not install Playwright's Chromium"
fi

# ── The throwaway site ──────────────────────────────────────────────────────────────────────────
TMP="$(mktemp -d "${TMPDIR:-/tmp}/salt-harness.XXXXXX")"
WP="$TMP/wp"
DB="salt_harness_$(LC_ALL=C tr -dc 'a-z0-9' </dev/urandom | head -c 12 || true)"
SERVER_PID=""
DB_CREATED=0

if [ -z "${SALT_HARNESS_DB_HOST:-}" ]; then
	if [ -S /tmp/mysql.sock ]; then SALT_HARNESS_DB_HOST="localhost:/tmp/mysql.sock"; else SALT_HARNESS_DB_HOST="localhost"; fi
fi
export SALT_HARNESS_DB_HOST
export SALT_HARNESS_DB_USER="${SALT_HARNESS_DB_USER:-$(id -un)}"
export SALT_HARNESS_DB_PASSWORD="${SALT_HARNESS_DB_PASSWORD:-}"

# The server runs in its own process group (job control gives a background job one), so the
# group holds the PHP_CLI_SERVER_WORKERS children too; stopping the group stops them all and
# touches nothing another run started.
start_server() {
	set -m
	PHP_CLI_SERVER_WORKERS=4 "$@" >"$TMP/server.log" 2>&1 &
	SERVER_PID=$!
	set +m
}
stop_server() {
	[ -n "$SERVER_PID" ] || return 0
	kill -TERM -- "-$SERVER_PID" 2>/dev/null || true
	for _ in 1 2 3 4 5 6 7 8 9 10; do kill -0 -- "-$SERVER_PID" 2>/dev/null || break; sleep 0.2; done
	kill -KILL -- "-$SERVER_PID" 2>/dev/null || true
	wait "$SERVER_PID" 2>/dev/null || true
	SERVER_PID=""
}

cleanup() {
	local status=$?
	stop_server
	if [ "$KEEP" = 1 ]; then
		echo "kept: site $WP, database $DB (drop it with: $PHP $HERE/php/db.php drop $DB)"
	else
		if [ "$DB_CREATED" = 1 ]; then
			if "$PHP" "$HERE/php/db.php" drop "$DB"; then echo "cleanup: database $DB dropped"; else echo "cleanup: could not drop database $DB" >&2; fi
		fi
		rm -rf "$TMP" && echo "cleanup: $TMP removed"
	fi
	exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT TERM

PORT="$("$PHP" -r '$s = stream_socket_server("tcp://127.0.0.1:0"); $n = stream_socket_get_name($s, false); echo substr($n, strrpos($n, ":") + 1);')"
SERVER="http://127.0.0.1:$PORT"
URL="$SERVER"
# A conformance run's site has a fixed address, so its links, and so its report, are the same
# on every run; requests reach the server at SERVER and name the site in their Host header.
[ "$CONFORMANCE" = 0 ] || URL="http://salt-harness.test"

cp -R "$WP_CORE" "$WP"
mkdir -p "$WP/wp-content/themes" "$WP/wp-content/plugins" "$WP/wp-content/mu-plugins" "$WP/wp-content/uploads"
ln -s "$REPO" "$WP/wp-content/themes/salt"
cp "$HERE"/site/*.php "$WP/wp-content/mu-plugins/"
if [ -n "${SALT_ACF_DIR:-}" ]; then
	cp -R "$SALT_ACF_DIR" "$WP/wp-content/plugins/advanced-custom-fields-pro"
else
	unzip -q "$ACF_ZIP" -d "$WP/wp-content/plugins"
fi
ACF_HEADER_VERSION="$(sed -n 's/^ \* Version: *//p' "$WP/wp-content/plugins/advanced-custom-fields-pro/acf.php" | head -1 | tr -d '[:space:]')"
[ "$ACF_HEADER_VERSION" = "$ACF_VERSION" ] || die "ACF Pro is $ACF_HEADER_VERSION, expected $ACF_VERSION"

ACF_JSON="$TMP/salt-sections.acf.json"
EMIT_LINE="$(node "$HERE/emit.mjs" "$ACF_JSON")" || die "the emitter failed"

salt() { "$PHP" -r 'echo bin2hex(random_bytes(32));'; }
cat >"$WP/wp-config.php" <<PHP
<?php
// Generated by tests/real-acf/run.sh for one throwaway run.
define( 'DB_NAME', '$DB' );
define( 'DB_USER', getenv( 'SALT_HARNESS_DB_USER' ) );
define( 'DB_PASSWORD', getenv( 'SALT_HARNESS_DB_PASSWORD' ) );
define( 'DB_HOST', getenv( 'SALT_HARNESS_DB_HOST' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'AUTH_KEY', '$(salt)' );
define( 'SECURE_AUTH_KEY', '$(salt)' );
define( 'LOGGED_IN_KEY', '$(salt)' );
define( 'NONCE_KEY', '$(salt)' );
define( 'AUTH_SALT', '$(salt)' );
define( 'SECURE_AUTH_SALT', '$(salt)' );
define( 'LOGGED_IN_SALT', '$(salt)' );
define( 'NONCE_SALT', '$(salt)' );
\$table_prefix = 'wp_';
define( 'WP_HOME', '$URL' );
define( 'WP_SITEURL', '$URL' );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', '$TMP/debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISABLE_WP_CRON', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'FS_METHOD', 'direct' );
define( 'SALT_HARNESS_ACF_JSON', '$ACF_JSON' );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
PHP

export SALT_HARNESS_WP="$WP"
export SALT_HARNESS_URL="$URL"
export SALT_HARNESS_SERVER="$SERVER"
export SALT_HARNESS_DB="$DB"
export SALT_HARNESS_LOG="$TMP/debug.log"
export SALT_HARNESS_LOCK="$TMP/view.lock"
SALT_HARNESS_ADMIN_PASS="$("$PHP" -r 'echo bin2hex(random_bytes(12));')"
export SALT_HARNESS_ADMIN_PASS
SALT_HARNESS_EDITOR_PASS="$("$PHP" -r 'echo bin2hex(random_bytes(12));')"
export SALT_HARNESS_EDITOR_PASS
export SALT_HARNESS_ACF_VERSION="$ACF_VERSION"
export SALT_HARNESS_ACF_JSON="$ACF_JSON"
export SALT_HARNESS_FIXTURES="$REPO/node_modules/@lightlysaltedhq/salt-contract/fixtures"
export SALT_HARNESS_STATE="$TMP/state.json"
export SALT_HARNESS_PHP_BIN="$PHP"
touch "$TMP/debug.log"

# ── Phases ──────────────────────────────────────────────────────────────────────────────────────
OUT="$TMP/report.txt"
: >"$OUT"
PHASES=()

# phase <name> <command...>: runs the command, then checks debug.log for anything it added.
phase() {
	local name="$1"; shift
	local from status=0
	from="$(wc -c <"$TMP/debug.log" | tr -d ' ')"
	echo "── $name" | tee -a "$OUT"
	"$@" 2>&1 | tee -a "$OUT" || status=$?
	"$PHP" "$HERE/php/notices.php" "$TMP/debug.log" "$from" "$name" 2>&1 | tee -a "$OUT" || status=1
	if [ "$status" = 0 ]; then PHASES+=("PASS  $name"); else PHASES+=("FAIL  $name"); fi
}

{
	echo "Salt real-ACF harness"
	echo "  PHP        $PHP_VERSION ($PHP)"
	echo "  WordPress  $(sed -n "s/^\$wp_version = '\(.*\)';/\1/p" "$WP/wp-includes/version.php")"
	echo "  ACF Pro    $ACF_HEADER_VERSION from $ACF_SOURCE"
	echo "  contract   $EMIT_LINE"
	echo "  database   $DB on $SALT_HARNESS_DB_HOST"
	echo "  site       $URL in $TMP"
	[ "$CONFORMANCE" = 0 ] || echo "  mode       conformance (server $SERVER)"
} | tee -a "$OUT"

# Clients run the latest WordPress: a pinned release that has fallen behind fails the run.
if [ "$OFFLINE" = 1 ]; then
	echo "── WordPress is the latest release" | tee -a "$OUT"
	echo "SKIP STALENESS NOT CHECKED (--offline): WordPress $WP_PINNED was not compared with the latest release" | tee -a "$OUT"
	PHASES+=("SKIP  WordPress is the latest release: STALENESS NOT CHECKED (--offline)")
else
	echo "── WordPress is the latest release" | tee -a "$OUT"
	if "$PHP" "$HERE/php/latest.php" "$WP_PINNED" 2>&1 | tee -a "$OUT"; [ "${PIPESTATUS[0]}" = 0 ]; then
		PHASES+=("PASS  WordPress is the latest release")
	else
		PHASES+=("FAIL  WordPress is the latest release")
	fi
fi

"$PHP" "$HERE/php/db.php" create "$DB" || die "could not create database $DB"
DB_CREATED=1

phase "install" "$PHP" "$HERE/php/install.php"

# The conformance run: the site serves the contract's view and page cases while salt-conformance runs.
# Its timeout is per case and includes the wait for view.php's lock (the cases take the one site in
# turn), so it is ten minutes, not the runner's default minute. The report goes to report/ like any
# run's, and is copied to the committed baseline only when the whole run conforms.
conformance() {
	(cd "$REPO" && PATH="$(dirname "$PHP"):$PATH" npm run --silent conformance:contract -- --timeout 600000 >"$TMP/conformance.md") || { cat "$TMP/conformance.md"; return 1; }
	mkdir -p "$REPO/tests/contract-adapter/baseline"
	cp "$REPO/tests/contract-adapter/report/conformance.json" "$REPO/tests/contract-adapter/report/conformance.md" "$REPO/tests/contract-adapter/baseline/"
	echo "PASS the report is copied to tests/contract-adapter/baseline/"
}
if [ "$CONFORMANCE" = 1 ]; then
	phase "the site for the view and page cases" "$PHP" "$HERE/php/view.php" setup
	start_server "$PHP" -S "127.0.0.1:$PORT" -t "$WP"
	for _ in $(seq 1 50); do
		curl -fs -o /dev/null "$SERVER/wp-login.php" && break
		sleep 0.2
	done
	phase "salt-conformance with the view and page cases on this site (tests/contract-adapter/baseline)" conformance
else
# Every check below is the harness's own run; --conformance runs none of them.
if [ "$LIMITS" = 0 ]; then
phase "theme's field groups" "$PHP" "$HERE/php/theme-groups.php"

# The theme registers the generated JSON itself (inc/acf.php); the contract phase checks it.
phase "contract field shape: register, seed, read back, storage" "$PHP" "$HERE/php/contract.php"
phase "the theme renders it (resolvers on real ACF values)" "$PHP" "$HERE/php/render.php"
# After the render, so the posts it makes are not in the page's FAQ and locations queries.
phase "collection fields: seed every post type, read back, storage" "$PHP" "$HERE/php/collections.php"
fi

# The site's own server, for the single templates over HTTP and the browser.
# PHP's default max_input_vars (1000) truncates the re-save of a page holding every fixture
# case; browser.mjs reports how many variables the form posts against this limit.
export SALT_HARNESS_MAX_INPUT_VARS="${SALT_HARNESS_MAX_INPUT_VARS:-20000}"
# A browser save of that page also outlasts the default 30-second max_execution_time.
start_server "$PHP" -d max_input_vars="$SALT_HARNESS_MAX_INPUT_VARS" -d max_execution_time=600 -S "127.0.0.1:$PORT" -t "$WP" "$HERE/php/router.php"
for _ in $(seq 1 50); do
	curl -fs -o /dev/null "$URL/wp-login.php" && break
	sleep 0.2
done

if [ "$LIMITS" = 0 ]; then
phase "single templates over HTTP (the contract's service, case-study and post fixtures)" "$PHP" "$HERE/php/singles.php"
phase "tabs and listing with real ACF rows and paged routes" "$PHP" "$HERE/php/sections.php"
if [ "$BROWSER" = 1 ]; then
	phase "tabs keyboard behaviour and listing rendering in Chromium (no script)" node "$HERE/sections.mjs"
	phase "contact form over real requests in Chromium, with and without script (CSP on)" node "$HERE/contact.mjs"
	phase "the theme's pages under its CSP in Chromium" node "$HERE/csp.mjs"
	phase "no style the CSP refuses on the representative pages, signed out and in (Chromium)" node "$HERE/inline-styles.mjs"
	phase "editor in a real browser (Chromium)" node "$HERE/browser.mjs"
else
	PHASES+=("SKIP  editor in a real browser (--no-browser)")
fi
# Last: these tests switch the site to pretty permalinks and enable its public sitemap.
phase "public profiles, native credits, permissions and picker over HTTP" "$PHP" "$HERE/php/profiles.php"
if [ "$BROWSER" = 1 ] || [ -n "${SALT_HARNESS_SCREENSHOTS:-}" ]; then
	phase "profile editorial form in Chromium" node "$HERE/profile-screenshots.mjs"
fi
fi

# Last: the limits fixtures add published services and topics to this site.
phase "limits and targets: outside writes" "$PHP" "$HERE/php/limits.php"
if [ "$BROWSER" = 1 ]; then
	phase "limits and targets: editor" node "$HERE/limits.mjs"
fi
fi

echo | tee -a "$OUT"
echo "Summary (PHP $PHP_VERSION, ACF Pro $ACF_HEADER_VERSION, WordPress $(sed -n "s/^\$wp_version = '\(.*\)';/\1/p" "$WP/wp-includes/version.php"))" | tee -a "$OUT"
for p in "${PHASES[@]}"; do echo "  $p" | tee -a "$OUT"; done
FAILS="$(grep -c '^FAIL' "$OUT" || true)"
PASSES="$(grep -c '^PASS' "$OUT" || true)"
echo "  checks: $PASSES passed, $FAILS failed" | tee -a "$OUT"
[ -z "$REPORT" ] || cp "$OUT" "$REPORT"

if printf '%s\n' "${PHASES[@]}" | grep -q '^FAIL'; then
	echo "RESULT: FAIL"
	exit 1
fi
echo "RESULT: PASS"
