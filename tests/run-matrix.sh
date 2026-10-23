#!/usr/bin/env bash
# Runs the whole suite under every installed PHP version and prints a summary.
#   tests/run-matrix.sh                # default matrix
#   PHP_VERSIONS="5.6 8.5" tests/run-matrix.sh
#   tests/run-matrix.sh --filter=email # extra args go to tests/run.php
set -u
cd "$(dirname "$0")/.."

VERSIONS="${PHP_VERSIONS:-5.6 8.3 8.4 8.5}"
declare -a SUMMARY
status=0

for v in $VERSIONS; do
	bin="php$v"
	if ! command -v "$bin" >/dev/null 2>&1; then
		SUMMARY+=("$v  SKIPPED (php$v not installed)")
		continue
	fi
	echo "=============== PHP $v ==============="
	# xdebug adds multi-line stack traces to every diagnostic
	out=$("$bin" -d xdebug.mode=off -d memory_limit=512M tests/run.php "$@" 2>&1)
	code=$?
	echo "$out"
	last=$(echo "$out" | grep -E "^PHP [0-9.]+.*passed" | tail -1)
	if [ $code -eq 0 ]; then
		SUMMARY+=("$v  OK    ${last#*: }")
	else
		SUMMARY+=("$v  FAIL  ${last#*: }")
		status=1
	fi
done

echo
echo "=============== SUMMARY ==============="
for line in "${SUMMARY[@]}"; do echo "$line"; done
exit $status
