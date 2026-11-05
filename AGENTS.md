# ElRoi Starter: guide for agents

ElRoi Starter is [ElRoi Marketing](https://elroi.marketing/)'s base theme for custom client websites. It is a classic PHP theme (no parent theme, no page builder) built for the **ElRoi Site Editor** plugin (`titosoriano/elroi-site-editor`): every word and picture a client may change is a registered field, edited in wp-admin, while the design stays in code.

Each client site is a **child theme** of this one, in a **private** repository named after the client (e.g. `acme-website`), because it holds the client's copy, facts and server notes. The starter stays installed on every client site as the parent theme and updates itself from this repository's releases, so every improvement made here reaches every client with one Update click. Never copy the starter into a client repository (older client sites that were copies, like Dead Center Media before theme 2.0, were converted to child themes).

## Starting a client site

1. `sh bin/new-client.sh acme-website "Acme Roofing"` creates `../acme-website/`, a child theme (`Template: elroi-starter`) with `style.css`, `functions.php`, `inc/fields.php`, `assets/css/tokens.css`, `assets/css/client.css`, `bin/build.sh` and its own `AGENTS.md`. Push it to a new private repository. It also adds `tests/` (generic checks: every designed page renders one `<h1>` inside `<main>` without PHP errors, images have alt text, saving each form unchanged changes nothing) and `.github/workflows/checks.yml`, which runs them on GitHub on every push with the latest plugin and starter. Add the client's own checks to `tests/run.php`.
2. **Brand:** edit only the child's `assets/css/tokens.css` (it replaces the starter's): colors (check WCAG AA contrast for every pair), fonts (self-host in the child's `assets/fonts/` with `@font-face`; no Google Fonts link), radius. Extra styles go in `assets/css/client.css`. A site with an entirely different design declares `add_theme_support( 'es-own-design' )` and enqueues its own CSS and JS instead (the starter then loads neither tokens.css, site.css nor site.js).
3. **Client facts and copy:** set Site Settings defaults through the `es_settings` filter; register page fields in the child's `inc/fields.php` on `after_setup_theme` at priority **20** (after the starter's 10), so a template registered again replaces the starter's. The starter's builders (`es_hero_fields()`, `es_sections_field()`...) are available.
4. **Design the pages:** to change a starter template, copy that one file (`front-page.php`, `templates/service.php`, `header.php`, `parts/hero.php` via `get_template_part`...) into the child and edit it there; WordPress uses the child's copy. New page types go in the child's `templates/`. `add_theme_support( 'es-parent-templates', array( 'templates/contact.php' ) )` hides the starter templates the client does not use from the Template menu. Never copy or edit the starter's `inc/` files in a child: functions there cannot be redeclared. A change every client would want belongs in the starter (release it, then update the sites); a client-only change uses a filter (`es_settings`, `es_business_schema`, `es_toc`, `es_sizes`...) or a copied template. Add a filter to the starter when a child needs one that does not exist yet. In child files use `ES_CHILD_DIR` / `ES_CHILD_URI` for the child's own assets (`ES_DIR` / `ES_URI` are the starter).
5. On the server: install and activate ElRoi Site Editor (latest release zip), install **ElRoi Starter** (latest release zip, `elroi-starter.zip`; do not activate it), then upload and activate the child theme. Create pages, assign templates, set the home page and posts page (Settings > Reading), build the Main menu (Appearance > Menus, location "Main menu"; second-level items become the dropdown). Right after ElRoi Site Editor is active, **check the recommended plugins** (its `elroi/recommended-plugins` ability through the site's MCP, or Plugins > ElRoi recommended) and tell the user which are missing or inactive and why, before anything else; install them with the user's OK and follow each setup note.
6. Fill in Site Settings (Business, Contact, Numbers, Contact form, Social).
7. Backups: UpdraftPlus to Google Drive or Dropbox, daily, keeping at least 7 copies (Tools > Site Health checks this).
8. Speed: on LiteSpeed hosts (Hostinger), once LiteSpeed Cache is active, Tools > Site Health > **Speed settings** > Apply the recommended settings (`es_speed_apply()` through the MCP; `es_speed_status()` shows what differs). Fonts are self-hosted in the child.
9. Verify (below), then create the client's user with the **Editor** role.

**Converting an older client site that is a full copy of the starter** (as Dead Center Media was): remove every file the starter provides from the client theme, keep only its templates, assets, fields and client-only code (moved onto starter filters), set `Template: elroi-starter` in its style.css, and test it in Playground next to the starter: every page's HTML must match the old theme's except asset paths and body classes. On the live site: backup check, install ElRoi Starter, swap the client folder in one step, then `update_option( 'template', 'elroi-starter' )` (the stylesheet and its theme mods stay the same). WordPress keeps reading the old `template` option until it changes, and without it every page fails. Check each page renders, and switch both back if not.

## Rules

- **Never hard-code client copy, photos or contact facts** in templates. Register a field (with the designed copy as `default`) or use a Site Setting (`es_setting( 'phone' )`, or `{phone}` inside any text field). Fixed UI words (Menu, Send, Skip to content) may stay in code.
- Print values only through `elroi_text()`, `elroi_heading()`, `elroi_paragraphs()`, `elroi_link()`, `elroi_image()`, `es_button()`. Never echo a raw value.
- One `<h1>` per page. Headings in order. Every image has alt text (the plugin requires it); decorative images use `alt=""`.
- Accessibility target WCAG 2.2 AA: keep the skip link, visible focus, `prefers-reduced-motion`, inert background while the drawer is open, labels on every form field, rem font sizes. Never add an accessibility overlay widget.
- Animations may use any technique (opacity, transform, clip-path, filters, pinned or scrubbed scenes, smooth scroll), as long as: the page is complete and usable without JavaScript (hidden start states are set by JavaScript, never by CSS alone; `data-es-reveal` is the one CSS exception and has its own fallback); `prefers-reduced-motion: reduce` shows everything with no movement; nothing stays hidden if a script fails to load; and libraries are self-hosted, never from a CDN. Simple sites use `data-es-reveal`; cinematic sites turn on the motion layer (see Motion).
- No em-dashes in copy we write; only real facts about the client.
- Bump `Version:` in `style.css` and `ES_VERSION` in `functions.php` on every starter release (the release workflow refuses mismatched versions); a child bumps only its own `style.css` Version (its files are cache-busted with both versions).
- **Before a big change on a live site** (switching or converting a theme, a starter or plugin update with structural changes, a redesign, a plugin swap): check `es_backup_status()` through the site's MCP (or Tools > Site Health). If the last backup is older than a day or anything is flagged, run UpdraftPlus > Backup Now and wait for it to finish. Snapshot every page's HTML before and compare after. For redesigns and anything that cannot be undone by re-uploading the previous zip, use the host's staging copy first (Hostinger: hPanel > Websites > WordPress > Staging), then push it live.

## Structure

- `functions.php`: constants and includes.
- `inc/setup.php`: theme supports, menus (`primary`, `footer`), assets, head cleanup, notices when the plugin is missing (the front end shows `parts/needs-plugin.php` until it is active).
- `inc/fields.php`: Site Settings (through the `es_settings` filter) and every template's fields (`front-page`, `templates/service.php`, `templates/page.php`, `templates/contact.php`). Shared builders: `es_hero_fields()`, `es_sections_field()`, `es_faq_field()`, `es_cta_field()`, `es_video_field()` (inc/video.php).
- `inc/nav.php`: header menu with dropdowns and the mobile drawer, from the "Main menu" location. A third menu level shows its parent as a small label. **Mega menu** (opt in: `add_theme_support( 'es-mega-menu' )`, for clients with many services or towns): a top-level item whose children have children opens a full-width panel; second-level items (custom links with URL `#`) are column headings, third-level items are links; the top item's menu Description is the panel introduction, and each link's Description and its page's featured image (or the photo of a CSS class `es-img-<attachment ID>`) show in the preview on hover or focus. More than 11 links in a column make the panel dense (compact columns, no preview); the mobile menu shows the same groups, long ones in two columns. Opens on click, Enter or Space, and on hover with a short delay; Escape closes it.
- `inc/a11y.php`, `parts/a11y.php`, `assets/js/a11y.js`, `assets/css/a11y.css`: **accessibility preferences panel** (opt in per client: `add_theme_support( 'es-a11y-panel' )`), ported from Nuvista: profiles, text size, readable and dyslexia fonts, color modes and color-blindness corrections, reading guide, magnifier, read aloud, image descriptions, hide images, big cursor, stop animations (the motion layer treats it as reduced motion), strong focus and a page structure dialog. Settings stay in the visitor's browser and become `<html>` classes before first paint; never give an element a class `es-a11y-<pref key>` (it would get that preference's styles). Its footer links to a published page with the slug `accessibility` (`es_a11y_statement_url`). It is built in-house, loads nothing from other companies and does not change the page's code, so it is not the overlay the rule below forbids; it is still a convenience, never a reason to call a site compliant. Decide with the client before turning it on.
- `inc/lead-form.php`: the contact form (`es_lead_form( $title, $text )`). Saves a private "Form messages" post, emails `form_email` (or the admin email), and optionally sends the lead to HighLevel and checks Cloudflare Turnstile. Server-only settings go in `wp-config.php`, never in the repo: `ELROI_GHL_TOKEN`, `ELROI_GHL_LOCATION`, optional `ELROI_GHL_PIPELINE`, `ELROI_GHL_STAGE`, `ELROI_GHL_MESSAGE_FIELD`; `ELROI_TURNSTILE_SITE`, `ELROI_TURNSTILE_SECRET`. HighLevel tags: `website-lead`, `website-request` (removed and re-added on each message so a "Tag added" workflow fires every time) and `service-<slug>`.
- `inc/form-connect.php`: **forms are designed first, connected last.** The built-in form keeps every message under Form messages while the site is built. Form messages > **Connect a form** switches every `es_lead_form()` to a pasted CRM embed code or form shortcode, inside the same designed box (title, text, `#lead-form` anchor); switching back keeps the code. Tools > Site Health warns while messages only stay in wp-admin (no CRM form, HighLevel or SMTP plugin). Never hard-code a CRM embed in a template.
- `inc/video.php` + `parts/video.php`: `es_video_field()` (title, YouTube/Vimeo/MP4 link, description, cover, date). The cover loads the player only when pressed (youtube-nocookie). Every value with a `video_url` in a page's fields becomes a VideoObject in the schema, with no extra wiring; a video without a cover (Vimeo, MP4) gets no schema because Google requires one. Background loops are decoration, not videos: never give them a `video_url` field.
- `inc/inlinks.php`: in-text links. Site Settings > In-text links maps words to pages (rows in priority order; `es_link_map` filter for child themes); a natural mention in a body paragraph of a page (`es_paragraphs()`, which designed templates use instead of `elroi_paragraphs()`) or in a post's paragraphs and list items links to its page. At most 5 per page and 2 per paragraph, each page once, never the page itself, a page the text already links to, headings, buttons or paragraphs under 80 characters (`es_inlink_limits`). No rows, no links.
- `inc/areas.php` and `templates/area.php` ("Service area page"): one template for a service areas hub, its regions (counties) and their towns. The kind comes from the page tree: put towns under their region and regions under the hub; nothing else to set. The hub shows region cards with town chips, a region an A to Z town index, a town the other towns of its region; regions and towns list the service pages. Each prints a local business node serving that place (City for a town, AdministrativeArea for a region), with `aggregateRating` and a visible "Rated 4.9 out of 5 from 73 Google reviews" line when `es_google_rating()` has data (the free "Widgets for Google Reviews" plugin by Trustindex syncs it; `es_google_rating` filter; never type a rating by hand: Google calls self-marked ratings ineligible for stars, though it showed them for Nuvista's old site). Site Settings > Contact > Other offices are listed in the footer and become `department` nodes of the business. Service area lines can start with `City:`, `County:` or `State:` (a name with "County" is understood alone) so `areaServed` says what kind of place each is.
- `parts/embed.php`: the service template's "Widget (optional)" section, from an `embed` field (ElRoi Site Editor 1.5+: only addresses on allowed hosts, pasted embed codes accepted). The frame is a size container and the height a share of its width (`cqi`) with a minimum in pixels, because widgets like GAF's color explorer get taller as they get wider: measure the widget at 390, 600 and 1200px wide before choosing.
- `inc/seo-papers.php`: `wp es seo-papers` exports what Rank Math's analyzer needs for every page and post (the printed title and description, the focus keywords, the visible text of designed pages, the featured image), for `tests/rank-math/score.js`.
- `inc/blog.php`: blog pages hold whole rows of the 3-column grid: page 1 is the newest post as a feature plus 9 cards, later pages and archives 12 (`es_blog_page_sizes`; later blog pages use an offset and the page count is corrected, so no post sits alone on a last page). Posts show reading time and "Updated <date>" when changed on a later day, "Keep reading" lists 3 posts (same category first, `es_related_posts()`), and cards come from `es_post_card()`. Rank Math's BlogPosting credits the business, not the WordPress user (`es_post_author_business`).
- `inc/toc.php`: posts with two or more H2s get an "On this page" list and heading ids, unless the post already has a table of contents block. Rank Math's TOC check is told the site has one (`es_toc` filter turns both off).
- `inc/images.php`: new uploads get WebP resized copies when the server supports it (originals kept; older photos need "Regenerate thumbnails", or `wp media regenerate <ids> --skip-delete`, which keeps the old JPEG sizes that post bodies may link to). The site icon stays PNG/JPEG (iOS ignores WebP touch icons), and Rank Math's share images (`og:image`, `twitter:image`) point to the original upload, since some apps show no link preview for WebP. Pass `'sizes' => es_sizes( 'full'|'wide'|'half'|'third'|'card' )` to every `elroi_image()` so phones download a small copy; the first photo of a page gets `loading="eager"` and `fetchpriority="high"`, every other one stays lazy.
- `inc/motion.php`: the optional motion layer (off by default; with `'load' => 'auto'` only on pages that use it; see Motion).
- `inc/first-paint.php`: nothing in `<head>` waits for a file (see Speed below).
- `inc/speed.php`: speed settings that keep the design and animations working: `es_speed_status()`, `es_speed_apply()` and the Site Health "Speed settings" test (LiteSpeed Cache settings, pages kept fresh in browsers, Google Fonts added by plugins), and `data-no-defer` / `data-no-optimize` on the motion scripts so LiteSpeed never delays or re-bundles them.
- `inc/updater.php`: updates from this repository's GitHub releases (asset `elroi-starter.zip`). Only a theme in the `elroi-starter` folder updates itself; optional `ES_GITHUB_TOKEN` in wp-config.php for a higher GitHub rate limit.
- `inc/backups.php`: `es_backup_status()` and the Tools > Site Health backup check (UpdraftPlus: a finished backup in the last 8 days, stored off-site, at least 3 copies kept; the ElRoi default is daily and 7).
- `bin/new-client.sh`: creates a client child theme (see Starting a client site). `bin/child/` holds the files it copies into every child (generic `tests/` and the GitHub checks workflow).
- `inc/seo.php`: JSON-LD (child themes extend the business node with the `es_business_schema` filter): the business (type, description, phone, email, address, service areas and social profiles from Site Settings), BreadcrumbList on inner pages, FAQPage where a page has FAQs, and Service on service pages (`es_service_templates` lists the templates; name from the H1, type from the short name, provider the business, area the service areas; `es_service_schema` filter). Without Rank Math the theme's own graph also has a WebPage with `datePublished` and `dateModified` (Rank Math prints its own). Inner page heroes show "Updated <date>" (`es_show_updated`, false hides it): search engines and AI answers weigh freshness. With Rank Math's Schema module on, all of it goes into Rank Math's single graph: the business completes Rank Math's Organization node (same `@id`), and our BreadcrumbList is only added when Rank Math's breadcrumbs are off. Without Rank Math the theme prints its own graph. Titles, descriptions, Open Graph and sitemaps belong to Rank Math.
- `parts/`: `hero`, `sections`, `faq`, `cta`, `video`. `header.php`, `footer.php` (footer credit links to elroi.marketing).
- Templates: `front-page.php`, `templates/service.php`, `templates/page.php`, `templates/contact.php`; `page.php` (plain block-editor pages), `index.php` (blog/archives), `single.php`, `404.php`.
- `assets/css/tokens.css` (brand), `assets/css/site.css` (layout), `assets/js/site.js` (dropdowns, drawer, reveal, video players, form notice focus), `assets/js/motion.js` + `assets/css/motion.css` (motion layer), `assets/vendor/` (GSAP, ScrollTrigger, Lenis: downloaded by `bin/vendor.sh` at pinned versions with checksums, not committed).

## Motion

The starter ships an optional motion layer for cinematic sites: GSAP + ScrollTrigger, with Lenis as the only smooth-scroll engine, self-hosted in `assets/vendor/`. It is off by default, so simple sites load none of it. Turn it on in the client theme:

```php
add_action( 'after_setup_theme', fn() => add_theme_support( 'es-motion' ) );                              // all on
add_action( 'after_setup_theme', fn() => add_theme_support( 'es-motion', array( 'smooth' => false ) ) ); // no smooth scroll
```

Declarative hooks (markup only, no code):

| Attribute | Effect |
|---|---|
| `data-es-split` (`="0.2"` delay, `="now"` on load) | Heading words rise one by one. Inner `<em>` and links are kept. |
| `data-es-rise` (`="0.2"` delay) | Fades and rises when it enters the screen. |
| `data-es-parallax="12"` | Drifts inside its parent while scrolling (strength in %); the parent must clip (`overflow: hidden`). |
| `data-es-shutter` | The frame wipes open from the bottom (clip-path); frames entering together cascade; its image settles from a slight zoom. |
| `data-es-inview-play` | A muted `<video>` plays only while on screen. |

Custom scenes (pinned sections, scrubbed timelines, cursors) go in the client's own script, enqueued with `es-motion` as a dependency, through `ESMotion.scene( fn )`. `fn` receives `{ gsap, ScrollTrigger, lenis }` and runs only when motion is allowed, so markup must already show the finished state. Use `ESMotion.scrollTo( target )` for "back to top" and in-page links. Never pin an element that the CMS output wraps or re-renders; pin a wrapper the template owns.

The libraries are not in the repository: run `bin/vendor.sh` after cloning to test locally (`bin/build.sh` runs it for the zip). If they are missing, the site still works with no motion and wp-admin shows a notice.

**Load only where used.** New sites use `add_theme_support( 'es-motion', array( 'load' => 'auto' ) )`: the libraries (about 45 KB compressed) load only on pages whose HTML uses a motion hook (the attributes above, a plain `data-es-motion` attribute, or the child's own attributes listed in `'markers'`, e.g. `array( 'data-reveal', 'data-focus-pull' )`). Elsewhere `es-motion` is a tiny stand-in, so a client script that depends on it still runs: `ESMotion.scene()` does nothing and `ESMotion.scrollTo()` scrolls natively. Write client scripts to work without GSAP (guard `window.gsap`), enqueue them in the footer, and list every attribute your scenes look for in `'markers'`. Without `'load'`, the layer loads on every page (older child themes keep working unchanged). Filter `es_motion_used` decides per page.

Check every animated page with JavaScript off, with reduced motion on, at 1440px and 390px.

## Speed

- **Inline stylesheets.** `tokens.css`, `site.css`, the child's `client.css` and the motion CSS print inside the page as `<style id="<handle>-inline-file">` instead of `<link>`, so the first paint waits for no CSS file. The copy is minified (comments and indentation removed), relative `url()`s become absolute **without `?ver`** (fonts then match their preload links and never download twice), and it is cached in a transient until the file changes. Keep editing the files as before. Files over 100 KB stay links (`es_inline_max_bytes`). `es_inline_styles` lists the handles: a child adds its own (an `es-own-design` site lists its stylesheet) or a small plugin stylesheet such as a cookie banner's; return `array()` to turn it off. On Nuvista this took the home page from 91 to 94 on mobile Lighthouse; the cost is the CSS's size in every page's HTML.
- **Preloads.** `add_theme_support( 'es-preload', array( 'fonts' => array( 'assets/fonts/body-400.woff2' ), 'hosts' => array( 'https://cdn.example.com' ) ) )` preloads the fonts used above the fold (child theme first) and preconnects to third-party hosts the page needs (a reviews widget, a font CDN). Only preload fonts the first screen shows; each one competes with the hero photo. Per page, `es_preload` adds links, e.g. `es_preload_image( $id, 'full', es_sizes( 'full' ) )` for an image the browser cannot find early: a video poster or a CSS background. A photo printed with `fetchpriority="high"` needs none.
- **Video heroes:** give the poster the video's own size (e.g. 1280x720) on every screen. Chrome counts an image's largest-paint size by its real pixels, so a smaller phone poster makes the video, painted after load, the LCP (3.5 s instead of 2 s on Nuvista).
- Measure with `npx lighthouse <url> --only-categories=performance --form-factor=mobile` (Google's PageSpeed API is often over quota without a key). Behind a page cache or CDN the first visit after a purge is a miss: test twice.

## Analytics

Google Analytics comes from Site Kit (recommended plugin). `assets/js/site.js` sends the key events through whatever tag is on the page (`gtag` or `dataLayer`; nothing without one): `click_call`, `click_email`, `click_whatsapp`, `click_quote` (any link to `#lead-form`) and `form_submit` (once, on the `?lead=sent` return; the flag is then removed so a reload does not count twice). Mark them as key events in GA4 > Admin > Events. A child theme sends its own with `window.esTrack( name, params )`. For clients who run ads in the EU or California, add a consent plugin that supports Google Consent Mode and set Site Kit's consent mode on; the theme itself loads no tracking.

## SEO (Rank Math)

Needs ElRoi Site Editor 1.1+, which makes Rank Math score each designed page as visitors see it (live while editing), leaves the paid-only Content AI check out, adds field photos to the sitemap and makes site search read the fields. The theme's part:

- Everything a visitor reads is inside `<main>`; one `<h1>`; headings never skip a level; decorative duplicates (split words, marquee copies) carry `aria-hidden="true"`.
- Paragraph and FAQ answer fields use `'rich' => true` and print with `elroi_paragraphs( $list, '', true )` / `es_rich()`, so clients can add `[internal links](/page/)`. FAQ answers in the structured data use `es_plain()`.
- Every field that labels a button or link has `'link_text' => true`; the form then warns about "Learn more"-style words.
- Fill in Site Settings > Business, including **Towns and areas you serve** (Google's `areaServed`), and set Rank Math > Titles & Meta > Local SEO to "Organization" so the business details merge into Rank Math's own entry.
- The page title holds the focus keyword phrase (Rank Math checks it against the title); give the page a **Short name** (ElRoi Site Editor 1.4+, sidebar box) so menus and breadcrumbs stay short. Templates print the title as an `<h1>` only through `es_page_title()`, which keeps the full phrase.
- One focus keyword per page, never two pages on the same keyword: keep the map in the client repo (`seo/keyword-map.md`: keyword, page, pages linking to it, score). Put the keyword in the SEO title, the meta description, the H1 or first paragraph, one image's alt text, and link to the page from at least one other page.
- Rank Math wants 600+ words per page: give designed pages a real text section (sections, FAQs) rather than padding.
- Fill in Site Settings > In-text links: each service or key page with the words a reader would use for it (longest first), most important pages first. Rank Math counts the links, and search engines learn which page answers which search.
- Before handover and after content or code changes, run the score check: every page and post must score 80 or more.

What brought every indexed Nuvista page and post to 83 or more (its blog posts had computed 55 to 82):
- SEO title of 60 characters or fewer with the focus keyword near the start, a number and a positive or power word ("7 Reliable ...", "Easy ... From 4.99%").
- The keyword word for word in the page title (Short name for menus), the meta description, one H2, the first paragraph and one image's alt text (only where the alt stays true).
- Keyword density between 1% and 2.5%. Rank Math counts every focus keyword (the first is the main one, the rest secondary): `tests/rank-math/secsearch.js` finds the one or two secondary phrases the page already uses that bring it into the band. Too many mentions push it over 2.5% and lose the test.
- One link to another site (a manufacturer, a licence lookup, a reviews profile) and in-text links to other pages.
- Leave the slug alone on a live page: the keyword-in-URL points are not worth a broken link.
- What still costs points is usually length (under 600 words for pages, 2,500 for full marks) and the keyword in the slug; fix real gaps, never pad.

The score check runs Rank Math's own `analyzer.js` in Node on an export, with no browser or password, and gives the same numbers as wp-admin (checked on Nuvista: 5 of 5 pages identical):

```bash
sh tests/rank-math/fetch.sh https://client.com             # once, and after Rank Math updates
wp es seo-papers --out=papers.json                        # on the site, or Novamira run-wp-cli with --out under wp-content/uploads (download it, then delete it)
node tests/rank-math/score.js papers.json --min 80        # each page's score and the checks that lost points; exits 1 under the minimum
```

Locally, Playground serves the same export at `/es-tests/rank-math/papers.php` (Rank Math must be installed there). `tests/seo_scores.py` does the same check through wp-admin in a browser, when no shell or Novamira access exists:

```bash
pip install playwright && python3 -m playwright install chromium
ES_WP_USER=... ES_WP_PASS=... python3 tests/seo_scores.py --site https://client.com  # never commit the password
```

## Verify

**On GitHub, on every push and pull request:** `.github/workflows/checks.yml` lints the PHP and runs `tests/run.php` on a fresh WordPress Playground with the plugin's main branch; the run summary lists any failed check. Releases run the same checks first. Client child themes get the same workflow from `bin/new-client.sh`. Keep every repository green, and add a check for every rule the user cares about. The tests load pages over HTTP from inside Playground, so the workflows pin `--workers=3`: private repositories get 2-core runners, where Playground's default is one worker and the run deadlocks until the job timeout.


Local, on WordPress Playground (Node 22; Playground fails on Node 26), with the plugin checked out next to this repo:

```bash
P=../elroi-site-editor
mise exec node@22 -- npx -y @wp-playground/cli@3.1.56 server --port=9401 --login --blueprint=tests/blueprint.json \
  --mount="$P:/wordpress/wp-content/plugins/elroi-site-editor" \
  --mount="$PWD:/wordpress/wp-content/themes/elroi-starter" \
  --mount="$PWD/tests:/wordpress/es-tests"
curl -s http://127.0.0.1:9401/es-tests/run.php    # every check must pass
open http://127.0.0.1:9401/es-tests/login.php     # local admin
```

`tests/run.php` checks that each designed page renders one `<h1>` without PHP errors, keeps its h1 and words inside `<main>`, never skips a heading level, gives every image an alt attribute and has no "Learn more"-style links; that saving each page's form unchanged leaves the HTML identical; that a form message sent by a visitor is stored; the schema (business with service areas, FAQPage with plain answers, breadcrumbs, VideoObject); the CRM form swap and its Site Health check; the post table of contents; and WebP uploads. On the client's server, repeat the same idea: snapshot the pages' HTML before and after each deploy and compare. Check every page at 1440px and 390px.

## Deploy

**Starter releases publish themselves.** Bump `Version:` in `style.css` and `ES_VERSION` in `functions.php`, push to `main`, and `.github/workflows/release.yml` runs the checks (a version that fails them is never published), lints the PHP, builds `dist/elroi-starter.zip` with `bin/build.sh --with-motion` (the motion libraries are always included, checked against `assets/vendor/SHA256SUMS`, because child themes rely on them) and publishes release `vX.Y.Z` with the zip attached. Within a few hours (or at once with Dashboard > Updates > Check again) every client site shows "New version available" for ElRoi Starter. Never attach zips by hand; if a release lacks its zip, run the workflow from the Actions tab. Before updating a live client: backup check above, then compare every page's HTML before and after.

A **client child theme** deploys with its own `bin/build.sh` (`dist/<folder>.zip`, uploaded in Appearance > Themes > Add New > Upload or through the site's MCP), with its Version bumped.

## Client

Replace this section in the client's copy: business facts, server and MCP access, pending items.
