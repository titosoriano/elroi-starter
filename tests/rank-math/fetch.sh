#!/bin/sh
# Downloads Rank Math's analyzer.js and the WordPress scripts it needs from a site running Rank Math
# (a client site, or a local Playground with Rank Math installed) into vendor/ (not committed).
# Usage: sh tests/rank-math/fetch.sh https://client.com
# Run it again after Rank Math updates, so the scores follow its current rules.
set -e
[ -n "$1" ] || { echo "Usage: sh $0 https://site-with-rank-math"; exit 1; }
B=${1%/}
cd "$(dirname "$0")"
mkdir -p vendor
get() { curl -fsS -A 'Mozilla/5.0 (ElRoi score check)' -o "vendor/$2" "$B/$1" || { echo "Could not download $B/$1"; exit 1; }; sleep 1; }
get wp-content/plugins/seo-by-rank-math/assets/admin/js/analyzer.js analyzer.js
get wp-includes/js/dist/vendor/lodash.min.js lodash.min.js
for f in hooks i18n autop wordcount url; do get "wp-includes/js/dist/$f.min.js" "$f.min.js"; done
grep -q rankMathAnalyzer vendor/analyzer.js || { echo 'vendor/analyzer.js is not Rank Math'"'"'s analyzer (is Rank Math active on that site?)'; exit 1; }
echo "Rank Math analyzer ready in $(pwd)/vendor"
