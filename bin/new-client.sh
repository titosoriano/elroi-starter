#!/bin/sh
# Start a client website as a child theme of ElRoi Starter:
#   sh bin/new-client.sh acme-roofing "Acme Roofing" [../]
# creates ../acme-roofing/ (push it to a new private repository named after the client). The child
# holds only the client's brand, fields and any templates it changes; the form, SEO, images,
# motion layer and updates come from the starter, so starter releases reach the client by
# updating "ElRoi Starter" in wp-admin. See AGENTS.md > Starting a client site.
set -e
cd "$(dirname "$0")/.."
SLUG="$1"; NAME="$2"; DEST="${3:-..}/$SLUG"
[ -n "$SLUG" ] && [ -n "$NAME" ] || { echo 'Usage: sh bin/new-client.sh <folder-slug> "<Client Name>" [parent dir]'; exit 1; }
echo "$SLUG" | grep -Eq '^[a-z0-9-]+$' || { echo "Folder slug: lowercase letters, numbers and dashes only."; exit 1; }
[ ! -e "$DEST" ] || { echo "$DEST already exists."; exit 1; }
PREFIX=$(echo "$SLUG" | tr '-' '_')
mkdir -p "$DEST/assets/css" "$DEST/assets/fonts" "$DEST/inc" "$DEST/templates" "$DEST/bin"
cp assets/css/tokens.css "$DEST/assets/css/tokens.css"
cp screenshot.png "$DEST/screenshot.png"
cp LICENSE "$DEST/LICENSE"

cat > "$DEST/style.css" <<CSS
/*
Theme Name:   $NAME
Template:     elroi-starter
Author:       ElRoi Marketing
Author URI:   https://elroi.marketing/
Description:  $NAME website by ElRoi Marketing, built on ElRoi Starter (install and keep ElRoi Starter updated; do not delete it).
Version:      1.0.0
Requires at least: 6.5
Requires PHP: 8.1
License:      GPL-2.0-or-later
Text Domain:  $SLUG
*/

/* Brand: assets/css/tokens.css (replaces the starter's). Extra styles: assets/css/client.css. */
CSS

cat > "$DEST/functions.php" <<PHP
<?php
/**
 * $NAME: child theme of ElRoi Starter. Only this client's brand, fields and changed templates
 * live here. Never copy starter files in to edit them: use the starter's filters, or copy a
 * template file (front-page.php, templates/*.php, header.php...) here to replace it.
 *
 * @package ${PREFIX}
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/inc/fields.php';

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'custom-logo' );
		// Cinematic sites: add_theme_support( 'es-motion' ); (see the starter's AGENTS.md > Motion).
		// Hide starter page templates this site does not use, e.g. keep only the contact template:
		// add_theme_support( 'es-parent-templates', array( 'templates/contact.php' ) );
	},
	11
);
PHP

cat > "$DEST/inc/fields.php" <<PHP
<?php
/**
 * $NAME: Site Settings defaults and page fields. The starter registers its own first (priority
 * 10); everything here runs after it, so registering a template again replaces the starter's.
 *
 * @package ${PREFIX}
 */

defined( 'ABSPATH' ) || exit;

// The client's real facts as Site Settings defaults.
add_filter(
	'es_settings',
	function ( \$s ) {
		\$s['sections']['business']['fields']['company']['default'] = '$NAME';
		return \$s;
	}
);

add_action(
	'after_setup_theme',
	function () {
		if ( ! function_exists( 'elroi_register_template' ) ) {
			return;
		}
		// Example: the home page with this client's copy as defaults.
		// elroi_register_template( 'front-page', array( 'label' => 'Home page', 'fields' => array( 'hero' => es_hero_fields( '...' ) ) ) );
	},
	20
);
PHP

cat > "$DEST/assets/css/client.css" <<CSS
/* $NAME: styles beyond the brand tokens. Loads after the starter's site.css. */
CSS

cat > "$DEST/bin/build.sh" <<'SH'
#!/bin/sh
# Build dist/<folder>.zip of this child theme (ElRoi Starter must be installed on the site too).
set -e
cd "$(dirname "$0")/.."
NAME=$(basename "$PWD")
ERR=$(find . -name '*.php' -not -path './dist/*' -exec php -l {} \; 2>&1 | grep -v 'No syntax' || true)
[ -z "$ERR" ] || { echo "$ERR"; exit 1; }
rm -rf dist && mkdir -p "dist/$NAME"
cp -R style.css functions.php *.php inc templates assets screenshot.png "dist/$NAME/" 2>/dev/null || true
(cd dist && zip -rq "$NAME.zip" "$NAME" -x '*.DS_Store')
echo "Built dist/$NAME.zip ($(sed -n 's/^Version: *//p' style.css))"
SH
chmod +x "$DEST/bin/build.sh"

printf 'dist/\n.DS_Store\n' > "$DEST/.gitignore"

cat > "$DEST/AGENTS.md" <<MD
# $NAME website: guide for agents

Child theme of **ElRoi Starter** (\`titosoriano/elroi-starter\`, public) with the **ElRoi Site Editor**
plugin. Read the starter's AGENTS.md first: its rules, fields, SEO and motion apply here.

- This repository holds only what is specific to $NAME: \`assets/css/tokens.css\` (brand),
  \`assets/css/client.css\`, \`inc/fields.php\` (Site Settings defaults through \`es_settings\`, page
  fields registered at priority 20), and any template copied here to replace the starter's.
- Never edit or copy the starter's \`inc/\` files. A change every client would want goes into the
  starter (then update ElRoi Starter on each site); a change only $NAME wants goes here, through a
  starter filter (\`es_settings\`, \`es_business_schema\`, \`es_toc\`...) or a replaced template.
- Bump \`Version:\` in style.css on every deploy. Deploy: \`bin/build.sh\`, upload the zip; the
  starter must already be installed (Appearance > Themes shows it as the parent).
- Before a big change on the live site: check \`es_backup_status()\` (or Tools > Site Health) and take
  a fresh UpdraftPlus backup; try redesigns on the host's staging copy first.

## Client

Business facts, server and MCP access, pending items.
MD
echo "Created $DEST (child theme of ElRoi Starter). Next: brand tokens.css, then design and register pages."
