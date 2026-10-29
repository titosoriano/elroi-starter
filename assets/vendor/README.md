# Vendor scripts (motion layer)

Loaded only when the theme declares `add_theme_support( 'es-motion' )` (see `inc/motion.php`). Self-hosted on purpose: no CDN, the site keeps working if a third party goes down.

The files are not committed: `bin/vendor.sh` downloads them from npm at the pinned versions and checks them against `SHA256SUMS`. `bin/build.sh` runs it when the theme uses the motion layer, so the theme zip always contains them. Run it yourself after cloning to test locally.

| File | Version | License |
|---|---|---|
| `gsap.min.js` | GSAP 3.15.0 | GreenSock Standard "no charge" license, https://gsap.com/standard-license (free for commercial client sites) |
| `ScrollTrigger.min.js` | GSAP 3.15.0 | same as GSAP |
| `lenis.min.js` | Lenis 1.3.27 | MIT, `LICENSE-lenis.txt` |

To update: change the versions in `bin/vendor.sh` and `inc/motion.php`, delete the files, run `bin/vendor.sh`, and replace `SHA256SUMS` with the new checksums (`sha256sum *.js *.txt`).
