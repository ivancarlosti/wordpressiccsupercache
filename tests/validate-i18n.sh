#!/bin/sh
#
# Guards the bundled catalogs against a translated plugin name.
#
# "ICC.gg WordPress Super Cache" is a brand: it must read exactly like that in
# every locale, both as a standalone string (admin heading, plugin list, update
# notices) and inside longer messages (update banner, settings footer).
# Translating it produces names such as "Súper caché de WordPress de ICC.gg" or
# "ICC.gg WordPress 超级缓存", which is what this guard prevents.
#
# Usage: sh tests/validate-i18n.sh [path-to-plugin-root]

set -eu

cd "${1:-.}"

brand='ICC.gg WordPress Super Cache'
found=0
status=0

for po in languages/*.po; do
	[ -f "$po" ] || continue
	found=$((found + 1))

	if ! output=$(awk -v brand="$brand" -v file="$po" '
		function payload(line) {
			sub(/^[A-Za-z_0-9]*[[:space:]]*/, "", line)
			sub(/^"/, "", line)
			sub(/"$/, "", line)
			return line
		}
		function check() {
			if (msgid == "" || msgstr == "") {
				return
			}
			if (msgid == brand) {
				if (msgstr != brand) {
					printf "::error file=%s::the plugin name must be translated as \"%s\", found \"%s\".\n", file, brand, msgstr
					bad = 1
				}
			} else if (index(msgid, brand) > 0 && index(msgstr, brand) == 0) {
				printf "::error file=%s::the plugin name \"%s\" must stay verbatim inside longer strings, found \"%s\".\n", file, brand, msgstr
				bad = 1
			}
		}
		BEGIN {
			msgid = ""
			msgstr = ""
			mode = ""
			bad = 0
		}
		/^msgid / {
			check()
			msgid = payload($0)
			msgstr = ""
			mode = "id"
			next
		}
		/^msgstr / {
			msgstr = payload($0)
			mode = "str"
			next
		}
		/^"/ {
			if (mode == "id") {
				msgid = msgid payload($0)
			} else if (mode == "str") {
				msgstr = msgstr payload($0)
			}
			next
		}
		/^$/ {
			check()
			msgid = ""
			msgstr = ""
			mode = ""
			next
		}
		{
			mode = ""
		}
		END {
			check()
			exit bad
		}
	' "$po"); then
		printf '%s\n' "$output" >&2
		status=1
	fi
done

if [ "$found" -eq 0 ]; then
	echo "::error::No catalogs found in languages/." >&2
	exit 1
fi

if [ "$status" -ne 0 ]; then
	exit 1
fi

echo "✅ The plugin name is untranslated in ${found} catalogs."
