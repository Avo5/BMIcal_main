#!/usr/bin/env bash
set -e

echo "Running php -l on project PHP files..."
files=$(git ls-files '*.php' || find . -type f -name '*.php')
errors=0
for f in $files; do
  if php -l "$f" > /dev/null 2>&1; then
    echo "OK: $f"
  else
    echo "SYNTAX ERROR: $f"
    php -l "$f" || true
    errors=1
  fi
done

if [ "$errors" -ne 0 ]; then
  echo "One or more files failed lint." >&2
  exit 2
fi

echo "PHP lint passed."
