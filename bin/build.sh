#!/usr/bin/env bash
#
# Build a clean WordPress.org release directory for Book Surfer.
#
#   bin/build.sh                 build to ../book-surfer-build (default)
#   bin/build.sh /path/to/dest   build somewhere else (must be outside the repo)
#   bin/build.sh --check [dest]  only verify an existing build
#
# Steps:
#   1. Copy the repo to DEST, excluding every pattern in .distignore
#      (rsync when installed, otherwise a tar pipe; same result).
#   2. Normalise text files to LF (Windows checkouts may carry CRLF).
#   3. Verify: php -l on every PHP file, no file over 1 MB, nothing from
#      .distignore present, no dotfiles, no source maps, entry file and
#      readme.txt at the top level, plugin Version == readme Stable tag.
#   4. Print the file tree and total size.
#
# Exits non-zero if any check fails. Never builds inside the repo.

set -euo pipefail

SLUG="book-surfer"
ENTRY="$SLUG.php"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

CHECK_ONLY=0
if [ "${1:-}" = "--check" ]; then
	CHECK_ONLY=1
	shift
fi

DEST="${1:-$ROOT/../$SLUG-build}"
mkdir -p "$(dirname "$DEST")"
DEST="$(cd "$(dirname "$DEST")" && pwd)/$(basename "$DEST")"

case "$DEST" in
	"$ROOT" | "$ROOT"/*)
		echo "error: destination $DEST is inside the repo; build outside it" >&2
		exit 2
		;;
esac

# --- exclude list -------------------------------------------------------------
# Strip comments, blank lines and stray CRs so both rsync and tar accept it.
EXCLUDES="$(mktemp)"
trap 'rm -f "$EXCLUDES"' EXIT
tr -d '\r' < "$ROOT/.distignore" | sed -e '/^[[:space:]]*#/d' -e '/^[[:space:]]*$/d' > "$EXCLUDES"

# --- copy ---------------------------------------------------------------------
if [ "$CHECK_ONLY" -eq 0 ]; then
	if [ -e "$DEST" ]; then
		if [ ! -f "$DEST/$ENTRY" ] && [ -n "$(ls -A "$DEST" 2> /dev/null)" ]; then
			echo "error: $DEST exists and does not look like a previous build; refusing to delete it" >&2
			exit 2
		fi
		rm -rf "$DEST"
	fi
	mkdir -p "$DEST"

	if command -v rsync > /dev/null 2>&1; then
		echo "Copying with rsync ..."
		rsync -a --exclude-from="$EXCLUDES" "$ROOT/" "$DEST/"
	else
		echo "Copying with tar (rsync not installed) ..."
		( cd "$ROOT" && tar -cf - -X "$EXCLUDES" . ) | ( cd "$DEST" && tar -xf - )
	fi

	# --- normalise line endings ---------------------------------------------------
	echo "Normalising text files to LF ..."
	find "$DEST" -type f \( -name '*.php' -o -name '*.txt' -o -name '*.md' -o -name '*.json' \
		-o -name '*.css' -o -name '*.js' -o -name '*.pot' -o -name '*.po' \) -print0 |
	while IFS= read -r -d '' f; do
		tr -d '\r' < "$f" > "$f.lf" && mv "$f.lf" "$f"
	done
fi

# --- verify -------------------------------------------------------------------
fail=0
ok()  { printf '  ok    %s\n' "$*"; }
bad() { printf '  FAIL  %s\n' "$*"; fail=1; }

echo
echo "Verifying $DEST"

[ -f "$DEST/$ENTRY" ]     && ok "$ENTRY at top level"     || bad "$ENTRY missing at top level"
[ -f "$DEST/readme.txt" ] && ok "readme.txt at top level" || bad "readme.txt missing at top level"
[ ! -d "$DEST/$SLUG" ]    && ok "no nested $SLUG/ folder" || bad "nested $SLUG/ folder inside the build"

php_total=0
php_bad=0
while IFS= read -r -d '' f; do
	php_total=$((php_total + 1))
	if ! out="$(php -l "$f" 2>&1)"; then
		php_bad=$((php_bad + 1))
		printf '        %s\n' "$out"
	fi
done < <(find "$DEST" -type f -name '*.php' -print0)
[ "$php_bad" -eq 0 ] && ok "php -l clean on $php_total PHP files" || bad "$php_bad PHP file(s) with syntax errors"

big="$(find "$DEST" -type f -size +1M)"
[ -z "$big" ] && ok "no file over 1 MB" || bad "file(s) over 1 MB: $big"

leaks=""
while IFS= read -r pat; do
	case "$pat" in
		*/*)
			[ -e "$DEST/$pat" ] && leaks="$leaks $pat"
			;;
		*)
			hit="$(find "$DEST" -mindepth 1 -name "$pat" -print -quit)"
			[ -n "$hit" ] && leaks="$leaks ${hit#"$DEST"/}"
			;;
	esac
done < "$EXCLUDES"
[ -z "$leaks" ] && ok "nothing from .distignore present" || bad "excluded item(s) present:$leaks"

dots="$(find "$DEST" -mindepth 1 -name '.*' -print)"
[ -z "$dots" ] && ok "no dotfiles" || bad "dotfile(s) present: $dots"

[ ! -e "$DEST/.git" ] && [ ! -e "$DEST/node_modules" ] && ok "no .git or node_modules" || bad ".git or node_modules present"

if command -v file > /dev/null 2>&1; then
	crlf=""
	while IFS= read -r -d '' f; do
		if file "$f" | grep -q CRLF; then crlf="$crlf ${f#"$DEST"/}"; fi
	done < <(find "$DEST" -type f -print0)
	[ -z "$crlf" ] && ok "all text files use LF" || bad "CRLF file(s):$crlf"
fi

hv="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "$DEST/$ENTRY" | head -n1 | tr -d '\r ')"
st="$(sed -n 's/^Stable tag:[[:space:]]*//p' "$DEST/readme.txt" | head -n1 | tr -d '\r ')"
if [ -n "$hv" ] && [ "$hv" = "$st" ]; then
	ok "Version $hv == Stable tag $st"
else
	bad "Version '$hv' != Stable tag '$st'"
fi

# --- report -------------------------------------------------------------------
echo
echo "Build tree ($DEST):"
( cd "$DEST" && find . -mindepth 1 | sort | sed 's|^\./|  |' )
echo
files="$(find "$DEST" -type f | wc -l | tr -d ' ')"
kb="$(du -sk "$DEST" | cut -f1)"
echo "Files: $files   Total size: ${kb} KB"

if [ "$fail" -ne 0 ]; then
	echo
	echo "BUILD FAILED verification." >&2
	exit 1
fi
echo "Build OK."
