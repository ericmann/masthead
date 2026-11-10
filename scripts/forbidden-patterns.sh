#!/usr/bin/env bash
# Structural checks for SPEC section 3 that are not line patterns.
# Usage: forbidden-patterns.sh [--root <dir>] [--self-test]
set -u

SELF="$(basename "${BASH_SOURCE[0]}")"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SELF_TEST_ONLY=0

while [ $# -gt 0 ]; do
	case "$1" in
		--root)
			ROOT="$(cd "$2" && pwd)"
			shift 2
			;;
		--self-test)
			SELF_TEST_ONLY=1
			shift
			;;
		*)
			echo "unknown argument: $1" >&2
			exit 2
			;;
	esac
done

SCRIPT_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/$SELF"

# Each check prints one "FAIL [id] path: message" line per violation.
# Arguments: root directory.

check_strict_types() {
	local root="$1" f
	[ -d "$root/src" ] || return 0
	while IFS= read -r f; do
		if ! grep -q 'declare(strict_types=1);' "$f"; then
			echo "FAIL [strict-types] ${f#"$root"/}: missing declare(strict_types=1);"
		fi
	done < <(find "$root/src" -name '*.php' | sort)
}

check_deps_empty() {
	local root="$1" out
	[ -f "$root/package.json" ] || return 0
	out="$(node -e '
		const p = JSON.parse(require("fs").readFileSync(process.argv[1], "utf8"));
		const d = p.dependencies;
		if (d !== undefined && Object.keys(d).length > 0) { console.log("non-empty"); }
	' "$root/package.json" 2>&1)"
	if [ -n "$out" ]; then
		echo "FAIL [deps-empty] package.json: dependencies must be {} (everything is a devDependency)"
	fi
}

check_lockfiles() {
	local root="$1" f
	for f in composer.lock package-lock.json; do
		if [ ! -f "$root/$f" ]; then
			echo "FAIL [lockfiles] $f: lockfile is missing"
		fi
	done
}

check_ability_permission() {
	local root="$1" f base
	[ -d "$root/src/Abilities" ] || return 0
	while IFS= read -r f; do
		base="$(basename "$f")"
		if [ "$base" = "Registrar.php" ] || [ "$base" = "Ability.php" ]; then
			continue
		fi
		if ! grep -q 'permission' "$f"; then
			echo "FAIL [ability-permission] ${f#"$root"/}: ability has no permission callback"
		fi
	done < <(find "$root/src/Abilities" -maxdepth 1 -name '*.php' | sort)
}

check_version_agree() {
	local root="$1" header const pkg
	header="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' "$root/masthead.php" 2>/dev/null | head -n 1)"
	const="$(sed -n "s/^const VERSION[[:space:]]*=[[:space:]]*'\([^']*\)';.*/\1/p" "$root/src/constants.php" 2>/dev/null | head -n 1)"
	pkg="$(node -e 'console.log(JSON.parse(require("fs").readFileSync(process.argv[1], "utf8")).version || "")' "$root/package.json" 2>/dev/null)"
	if [ -z "$header" ] || [ "$header" != "$const" ] || [ "$header" != "$pkg" ]; then
		echo "FAIL [version-agree] masthead.php: plugin header '$header', Masthead\\VERSION '$const', package.json '$pkg' must be equal"
	fi
}

check_no_gpg_bypass() {
	local root="$1" f d
	for d in .github scripts; do
		[ -d "$root/$d" ] || continue
		while IFS= read -r f; do
			[ "$f" = "$SCRIPT_PATH" ] && continue
			[ "$(basename "$f")" = "$SELF" ] && continue
			if grep -q 'no-gpg-sign' "$f"; then
				echo "FAIL [no-gpg-bypass] ${f#"$root"/}: bypasses commit signing"
			fi
		done < <(find "$root/$d" -type f | sort)
	done
	for f in package.json composer.json; do
		if [ -f "$root/$f" ] && grep -q 'no-gpg-sign' "$root/$f"; then
			echo "FAIL [no-gpg-bypass] $f: bypasses commit signing"
		fi
	done
}

CHECKS="strict-types deps-empty lockfiles ability-permission version-agree no-gpg-bypass"

run_check() {
	local id="$1" root="$2"
	"check_${id//-/_}" "$root"
}

make_good_tree() {
	local d="$1"
	mkdir -p "$d/src/Abilities" "$d/scripts" "$d/.github"
	printf '<?php\n/**\n * Version: 1.2.3\n */\n' > "$d/masthead.php"
	printf '<?php\ndeclare(strict_types=1);\nnamespace Masthead;\nconst VERSION = '"'"'1.2.3'"'"';\n' > "$d/src/constants.php"
	printf '<?php\ndeclare(strict_types=1);\n' > "$d/src/Plugin.php"
	printf '<?php\ndeclare(strict_types=1);\n// permission_callback\n' > "$d/src/Abilities/GetPlan.php"
	printf '<?php\ndeclare(strict_types=1);\n' > "$d/src/Abilities/Registrar.php"
	printf '{"name":"masthead","version":"1.2.3","dependencies":{}}\n' > "$d/package.json"
	printf '{}\n' > "$d/composer.lock"
	printf '{}\n' > "$d/package-lock.json"
	printf 'git commit -S\n' > "$d/scripts/release.sh"
}

make_bad_tree() {
	local id="$1" d="$2"
	make_good_tree "$d"
	case "$id" in
		strict-types) printf '<?php\n' > "$d/src/Plugin.php" ;;
		deps-empty) printf '{"name":"masthead","version":"1.2.3","dependencies":{"left-pad":"1.0.0"}}\n' > "$d/package.json" ;;
		lockfiles) rm "$d/package-lock.json" ;;
		ability-permission) printf '<?php\ndeclare(strict_types=1);\n' > "$d/src/Abilities/GetPlan.php" ;;
		version-agree) printf '{"name":"masthead","version":"9.9.9","dependencies":{}}\n' > "$d/package.json" ;;
		no-gpg-bypass) printf 'git commit --no-gpg-sign\n' > "$d/scripts/release.sh" ;;
	esac
}

self_test() {
	local tmp id good bad out failed=0
	tmp="$(mktemp -d)"
	good="$tmp/good"
	make_good_tree "$good"
	for id in $CHECKS; do
		out="$(run_check "$id" "$good")"
		if [ -n "$out" ]; then
			echo "SELF-TEST FAILED [$id]: flagged the good tree: $out"
			failed=1
		fi
		bad="$tmp/bad-$id"
		make_bad_tree "$id" "$bad"
		out="$(run_check "$id" "$bad")"
		if [ -z "$out" ]; then
			echo "SELF-TEST FAILED [$id]: missed its bad tree"
			failed=1
		fi
	done
	rm -rf "$tmp"
	return "$failed"
}

if ! self_test; then
	exit 1
fi
if [ "$SELF_TEST_ONLY" -eq 1 ]; then
	echo "forbidden-patterns: self-test ok"
	exit 0
fi

found=0
for id in $CHECKS; do
	out="$(run_check "$id" "$ROOT")"
	if [ -n "$out" ]; then
		echo "$out"
		found=1
	fi
done

if [ "$found" -ne 0 ]; then
	exit 1
fi
echo "forbidden-patterns: ok"
