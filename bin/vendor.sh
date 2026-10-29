#!/bin/sh
# Download the motion layer's libraries (GSAP, ScrollTrigger, Lenis) into assets/vendor/ at the
# pinned versions and check them against assets/vendor/SHA256SUMS. Needed only when the theme
# turns on add_theme_support( 'es-motion' ); bin/build.sh runs it then. Files already present
# are kept; delete them to download again after changing a version.
set -e
cd "$(dirname "$0")/.."
GSAP=3.15.0
LENIS=1.3.27
D=assets/vendor

fetch() { # package, version, file inside the package, saved name
	[ -s "$D/$4" ] && return 0
	curl -fsSL "https://registry.npmjs.org/$1/-/$1-$2.tgz" | tar -xzO "package/$3" | sed '/sourceMappingURL/d' > "$D/$4.tmp"
	mv "$D/$4.tmp" "$D/$4"
}
fetch gsap "$GSAP" dist/gsap.min.js gsap.min.js
fetch gsap "$GSAP" dist/ScrollTrigger.min.js ScrollTrigger.min.js
fetch lenis "$LENIS" dist/lenis.min.js lenis.min.js
fetch lenis "$LENIS" LICENSE LICENSE-lenis.txt

if command -v sha256sum >/dev/null 2>&1; then SUM="sha256sum"; else SUM="shasum -a 256"; fi
(cd "$D" && $SUM -c SHA256SUMS >/dev/null) || { echo "assets/vendor: checksum mismatch. Delete the files and run bin/vendor.sh again, or update SHA256SUMS after a version change." >&2; exit 1; }
echo "assets/vendor ready (GSAP $GSAP, Lenis $LENIS)"
