#!/bin/sh
# Build dist/<folder>.zip of the theme for upload (tests and dev files left out).
set -e
cd "$(dirname "$0")/.."
NAME=$(basename "$PWD")
find . -name '*.php' -not -path './dist/*' -exec php -l {} \; | grep -v 'No syntax' || true
# The motion layer's libraries are downloaded, not committed: fetch them when the theme uses it.
if grep -rls "add_theme_support( 'es-motion'" functions.php inc templates 2>/dev/null | grep -qv 'inc/motion.php'; then
	sh bin/vendor.sh
fi
rm -rf dist && mkdir -p "dist/$NAME"
cp -R style.css functions.php *.php inc parts templates assets screenshot.png "dist/$NAME/" 2>/dev/null || true
(cd dist && zip -rq "$NAME.zip" "$NAME" -x '*.DS_Store')
echo "Built dist/$NAME.zip ($(sed -n 's/^Version: *//p' style.css))"
