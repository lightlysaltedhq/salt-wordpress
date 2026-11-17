#!/usr/bin/env bash
# Self-test for bin/check-release-tag.mjs. Builds throwaway repos (a bare "origin"
# plus a clone) in a temp dir, pushes nothing real, and runs the gate against each case.
# Usage: bash bin/check-release-tag.test.sh   (needs node and git only)
set -u
script="$(cd "$(dirname "$0")" && pwd)/check-release-tag.mjs"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
export GIT_AUTHOR_NAME=t GIT_AUTHOR_EMAIL=t@example.com GIT_COMMITTER_NAME=t GIT_COMMITTER_EMAIL=t@example.com

git init -q --bare -b main "$tmp/origin.git"
git clone -q "$tmp/origin.git" "$tmp/work" 2>/dev/null
cd "$tmp/work" || exit 1
git checkout -q -b main

write() { # salt_version style_version
	printf '/*\nTheme Name: Salt\nVersion: %s\n*/\n' "$2" > style.css
	printf "<?php\ndefine( 'SALT_VERSION', '%s' );\n" "$1" > functions.php
}
commit() { git add -A && git commit -q -m "$1"; }

write 1.0.1 1.0.1; commit release-1.0.1
git tag v1.0.1
write 1.0.1 1.0.3; commit "style.css ahead"       # tag v1.0.3: style.css ok, SALT_VERSION stale
git tag v1.0.3
write 1.0.4 1.0.1; commit "constant ahead"        # tag v1.0.4: SALT_VERSION ok, style.css stale
git tag v1.0.4
# Annotated tag; WordPress-style ` * Version:` header; a commented-out define above the real one.
printf '/*\n * Theme Name: Salt\n * version: 1.0.5\n */\n' > style.css
printf "<?php\n// define( 'SALT_VERSION', '9.9.9' );\n * define( 'SALT_VERSION', '8.8.8' );\ndefine( 'SALT_VERSION', '1.0.5' );\n" > functions.php
commit "release-1.0.5 (comment forms)"
git tag -a v1.0.5 -m "release 1.0.5"
git push -q origin main
git fetch -q origin
# A fully consistent release commit that is never merged to main.
git checkout -q -b side
write 1.0.2 1.0.2; commit off-main
git tag v1.0.2
git checkout -q main

fail=0
run() { # description expected_exit tag
	echo "=== $1 (tag $3)"
	node "$script" "$3"; rc=$?
	if [ "$rc" -eq "$2" ]; then echo "--> exit $rc as expected"; else echo "--> exit $rc, EXPECTED $2"; fail=1; fi
	echo
}
run "correct tag on main"            0 v1.0.1
run "SALT_VERSION differs (style.css matches)" 1 v1.0.3
run "style.css Version differs (SALT_VERSION matches)" 1 v1.0.4
run "tag on a commit not on main"    1 v1.0.2
run "malformed tag name"             1 v1.0.1-rc1
run "missing tag"                    1 v9.9.9
run "annotated tag, ' * Version:' header, commented-out defines ignored" 0 v1.0.5

# A clone with no origin/main must fail closed.
git clone -q "$tmp/work" "$tmp/nomain" 2>/dev/null
git -C "$tmp/nomain" remote remove origin
cd "$tmp/nomain" || exit 1
run "no origin/main in the checkout (fails closed)" 1 v1.0.1
[ "$fail" -eq 0 ] && echo "ALL CASES PASSED" || { echo "SELF-TEST FAILED"; exit 1; }
