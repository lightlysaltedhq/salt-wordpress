#!/usr/bin/env bash
# Self-test for bin/check-slug-stability.php (FLEET07). Builds a throwaway repo in a
# temp dir holding a copy of the gate and of the committed registry, tags the registry
# as release v6.0.0, then edits it and the style.css Version and runs the gate on each case.
# Usage: bash bin/check-slug-stability.test.sh   (needs php and git only)
set -u
root="$(cd "$(dirname "$0")/.." && pwd)"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
export GIT_AUTHOR_NAME=t GIT_AUTHOR_EMAIL=t@example.com GIT_COMMITTER_NAME=t GIT_COMMITTER_EMAIL=t@example.com

mkdir -p "$tmp/work/bin" "$tmp/work/docs/contracts"
cp "$root/bin/check-slug-stability.php" "$tmp/work/bin/"
cp "$root/docs/contracts/slug-registry.json" "$tmp/work/docs/contracts/"
cd "$tmp/work" || exit 1
git init -q -b main
registry=docs/contracts/slug-registry.json
cp "$registry" "$tmp/baseline.json"

version() { printf '/*\nTheme Name: Salt\nVersion: %s\n*/\n' "$1" > style.css; }
migrations() { printf '{ "migrations": [ %s ] }\n' "${1:-}" > docs/contracts/slug-migrations.json; }
reset() { cp "$tmp/baseline.json" "$registry"; migrations; }
# Rename a storage path in one scope of the registry: scope from to.
move() {
	php -r '$f = $argv[1]; $r = json_decode( file_get_contents( $f ), true );
		$r["paths"][ $argv[2] ] = array_map( fn( $p ) => $p === $argv[3] ? $argv[4] : $p, $r["paths"][ $argv[2] ] );
		file_put_contents( $f, json_encode( $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );' "$registry" "$@"
}
# Rename a path prefix (a group or repeater) in one scope: scope old_prefix new_prefix.
rename_prefix() {
	php -r '$f = $argv[1]; $r = json_decode( file_get_contents( $f ), true );
		$r["paths"][ $argv[2] ] = array_map( fn( $p ) => str_starts_with( $p, $argv[3] ) ? $argv[4] . substr( $p, strlen( $argv[3] ) ) : $p, $r["paths"][ $argv[2] ] );
		file_put_contents( $f, json_encode( $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );' "$registry" "$@"
}
# Drop a layout: its slug and its scope.
drop_layout() {
	php -r '$f = $argv[1]; $r = json_decode( file_get_contents( $f ), true );
		$r["layouts"] = array_values( array_diff( $r["layouts"], array( $argv[2] ) ) );
		unset( $r["paths"][ "group_salt_sections / " . $argv[2] ] );
		file_put_contents( $f, json_encode( $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );' "$registry" "$@"
}

version 6.0.0; migrations; git add -A; git commit -q -m release-6.0.0; git tag v6.0.0
git commit -q --allow-empty -m "work after the release" # HEAD is not the tag itself

fail=0
run() { # description expected_exit [text the output must contain]
	echo "=== $1"
	out="$(php bin/check-slug-stability.php 2>&1)"; rc=$?
	echo "$out"
	if [ "$rc" -ne "$2" ]; then echo "--> exit $rc, EXPECTED $2"; fail=1
	elif [ -n "${3:-}" ] && ! grep -qF -- "$3" <<<"$out"; then echo "--> output lacks: $3"; fail=1
	else echo "--> exit $rc as expected"; fi
	echo
}

reset; version 6.1.0
run "unchanged registry, minor bump" 0 "Slug stability OK against v6.0.0"

reset; version 6.1.0; move "group_salt_sections / hero" sections_N_heading sections_N_settings_heading
run "hero heading moved into settings, minor bump" 1 \
	"path:group_salt_sections / hero:sections_N_heading -> sections_N_settings_heading"
version 6.0.1
run "hero heading moved into settings, patch bump" 1 "sections_N_settings_heading"
version 7.0.0
run "hero heading moved into settings, major bump (listed)" 0 \
	"path:group_salt_sections / hero:sections_N_heading -> sections_N_settings_heading"

reset; version 6.1.0; move "group_salt_sections / hero" sections_N_settings_spacing sections_N_spacing
run "hero spacing moved out of settings, minor bump" 1 \
	"path:group_salt_sections / hero:sections_N_settings_spacing -> sections_N_spacing"

reset; version 6.1.0; rename_prefix "group_salt_sections / hero" sections_N_settings sections_N_style
run "hero settings group renamed (field name still used elsewhere), minor bump" 1 \
	"path:group_salt_sections / hero:sections_N_settings_tone -> sections_N_style_tone"

reset; version 6.1.0; move group_salt_options_contact contact_recipient contact_routes_N_contact_recipient
run "options field moved into a repeater, minor bump" 1 \
	"path:group_salt_options_contact:contact_recipient -> contact_routes_N_contact_recipient"

reset; version 6.1.0; move "group_salt_sections / hero" sections_N_heading sections_N_settings_heading
migrations '{ "slug": "path:group_salt_sections / hero:sections_N_heading", "version": "6.1.0", "replacement": "sections_N_settings_heading", "note": "test" }'
run "path move with a migration entry, minor bump" 0 "Slug stability OK"

reset; version 6.1.0; drop_layout faq
run "layout removed: reported once as the layout, not path by path" 1 "layout:faq"
grep -qF "path:group_salt_sections / faq" <<<"$out" && { echo "--> paths of a removed layout were listed"; fail=1; }

# A release registry from before paths were recorded: nothing to compare paths against.
reset; php -r '$f = $argv[1]; $r = json_decode( file_get_contents( $f ), true ); unset( $r["paths"] );
	file_put_contents( $f, json_encode( $r, JSON_PRETTY_PRINT ) );' "$registry"
version 6.0.0; git add -A; git commit -q -m "registry without paths"; git tag v6.0.1
git commit -q --allow-empty -m "work after the release"
reset; version 6.1.0
run "previous release has no paths (rebaseline)" 0 "Slug stability OK against v6.0.1"

[ "$fail" -eq 0 ] && echo "ALL CASES PASSED" || { echo "SELF-TEST FAILED"; exit 1; }
