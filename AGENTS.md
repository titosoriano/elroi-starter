# ElRoi Starter: guide for agents

ElRoi Starter is [ElRoi Marketing](https://elroi.marketing/)'s base theme for custom client websites. It is a classic PHP theme (no parent theme, no page builder) built for the **ElRoi Site Editor** plugin (`titosoriano/elroi-site-editor`): every word and picture a client may change is a registered field, edited in wp-admin, while the design stays in code.

Each client site starts as a copy of this repository ("Use this template" on GitHub), in a **private** repository named after the client (e.g. `acme-website`), because it will hold the client's copy, facts and server notes.

## Starting a client site

1. Create the client repo from this template and clone it. Rename nothing in the code at first; the `es_` prefix and `elroi-starter` text domain can stay.
2. Update `style.css` (Theme Name, Description) and replace this file's "Client" section below with the client's facts, server, and deploy steps.
3. **Brand:** edit only `assets/css/tokens.css`: colors (check WCAG AA contrast for every pair), fonts (self-host in `assets/fonts/` with `@font-face`; no Google Fonts link), radius. Add the logo in Appearance > Customize > Site Identity.
4. **Design the pages** (home, services, about, contact, and any new page types). For each new page type, add a template in `templates/` that composes `parts/` and register its fields in `inc/fields.php`. Copy the client's real text into each field's `default`.
5. On the server: install and activate ElRoi Site Editor (latest release zip), then the theme. Create pages, assign templates, set the home page and posts page (Settings > Reading), build the Main menu (Appearance > Menus, location "Main menu"; second-level items become the dropdown).
6. Fill in Site Settings (Business, Contact, Numbers, Contact form, Social).
7. Verify (below), then create the client's user with the **Editor** role.

## Rules

- **Never hard-code client copy, photos or contact facts** in templates. Register a field (with the designed copy as `default`) or use a Site Setting (`es_setting( 'phone' )`, or `{phone}` inside any text field). Fixed UI words (Menu, Send, Skip to content) may stay in code.
- Print values only through `elroi_text()`, `elroi_heading()`, `elroi_paragraphs()`, `elroi_link()`, `elroi_image()`, `es_button()`. Never echo a raw value.
- One `<h1>` per page. Headings in order. Every image has alt text (the plugin requires it); decorative images use `alt=""`.
- Accessibility target WCAG 2.2 AA: keep the skip link, visible focus, `prefers-reduced-motion`, inert background while the drawer is open, labels on every form field, rem font sizes. Never add an accessibility overlay widget.
- Animations may use any technique (opacity, transform, clip-path, filters, pinned or scrubbed scenes, smooth scroll), as long as: the page is complete and usable without JavaScript (hidden start states are set by JavaScript, never by CSS alone; `data-es-reveal` is the one CSS exception and has its own fallback); `prefers-reduced-motion: reduce` shows everything with no movement; nothing stays hidden if a script fails to load; and libraries are self-hosted, never from a CDN. Simple sites use `data-es-reveal`; cinematic sites turn on the motion layer (see Motion).
- No em-dashes in copy we write; only real facts about the client.
- Bump `Version:` in `style.css` and `ES_VERSION` in `functions.php` on every deploy (cache busting).

## Structure

- `functions.php`: constants and includes.
- `inc/setup.php`: theme supports, menus (`primary`, `footer`), assets, head cleanup, notices when the plugin is missing (the front end shows `parts/needs-plugin.php` until it is active).
- `inc/fields.php`: Site Settings and every template's fields (`front-page`, `templates/service.php`, `templates/page.php`, `templates/contact.php`). Shared builders: `es_hero_fields()`, `es_sections_field()`, `es_faq_field()`, `es_cta_field()`, `es_video_field()` (inc/video.php).
- `inc/nav.php`: header menu with dropdowns and the mobile drawer, from the "Main menu" location.
- `inc/lead-form.php`: the contact form (`es_lead_form( $title, $text )`). Saves a private "Form messages" post, emails `form_email` (or the admin email), and optionally sends the lead to HighLevel and checks Cloudflare Turnstile. Server-only settings go in `wp-config.php`, never in the repo: `ELROI_GHL_TOKEN`, `ELROI_GHL_LOCATION`, optional `ELROI_GHL_PIPELINE`, `ELROI_GHL_STAGE`, `ELROI_GHL_MESSAGE_FIELD`; `ELROI_TURNSTILE_SITE`, `ELROI_TURNSTILE_SECRET`. HighLevel tags: `website-lead`, `website-request` (removed and re-added on each message so a "Tag added" workflow fires every time) and `service-<slug>`.
- `inc/form-connect.php`: **forms are designed first, connected last.** The built-in form keeps every message under Form messages while the site is built. Form messages > **Connect a form** switches every `es_lead_form()` to a pasted CRM embed code or form shortcode, inside the same designed box (title, text, `#lead-form` anchor); switching back keeps the code. Tools > Site Health warns while messages only stay in wp-admin (no CRM form, HighLevel or SMTP plugin). Never hard-code a CRM embed in a template.
- `inc/video.php` + `parts/video.php`: `es_video_field()` (title, YouTube/Vimeo/MP4 link, description, cover, date). The cover loads the player only when pressed (youtube-nocookie). Every value with a `video_url` in a page's fields becomes a VideoObject in the schema, with no extra wiring; a video without a cover (Vimeo, MP4) gets no schema because Google requires one. Background loops are decoration, not videos: never give them a `video_url` field.
- `inc/toc.php`: posts with two or more H2s get an "On this page" list and heading ids, unless the post already has a table of contents block. Rank Math's TOC check is told the site has one (`es_toc` filter turns both off).
- `inc/images.php`: new uploads get WebP resized copies when the server supports it (originals kept; older photos need "Regenerate thumbnails"). Pass `'sizes' => es_sizes( 'full'|'wide'|'half'|'third'|'card' )` to every `elroi_image()` so phones download a small copy; the first photo of a page gets `loading="eager"` and `fetchpriority="high"`, every other one stays lazy.
- `inc/motion.php`: the optional motion layer (off by default; see Motion).
- `inc/seo.php`: JSON-LD: the business (type, description, phone, email, address, service areas and social profiles from Site Settings), BreadcrumbList on inner pages, FAQPage where a page has FAQs. With Rank Math's Schema module on, all of it goes into Rank Math's single graph: the business completes Rank Math's Organization node (same `@id`), and our BreadcrumbList is only added when Rank Math's breadcrumbs are off. Without Rank Math the theme prints its own graph. Titles, descriptions, Open Graph and sitemaps belong to Rank Math.
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

Check every animated page with JavaScript off, with reduced motion on, at 1440px and 390px.

## SEO (Rank Math)

Needs ElRoi Site Editor 1.1+, which makes Rank Math score each designed page as visitors see it (live while editing), leaves the paid-only Content AI check out, adds field photos to the sitemap and makes site search read the fields. The theme's part:

- Everything a visitor reads is inside `<main>`; one `<h1>`; headings never skip a level; decorative duplicates (split words, marquee copies) carry `aria-hidden="true"`.
- Paragraph and FAQ answer fields use `'rich' => true` and print with `elroi_paragraphs( $list, '', true )` / `es_rich()`, so clients can add `[internal links](/page/)`. FAQ answers in the structured data use `es_plain()`.
- Every field that labels a button or link has `'link_text' => true`; the form then warns about "Learn more"-style words.
- Fill in Site Settings > Business, including **Towns and areas you serve** (Google's `areaServed`), and set Rank Math > Titles & Meta > Local SEO to "Organization" so the business details merge into Rank Math's own entry.
- One focus keyword per page, never two pages on the same keyword: keep the map in the client repo (`seo/keyword-map.md`: keyword, page, pages linking to it, score). Put the keyword in the SEO title, the meta description, the H1 or first paragraph, one image's alt text, and link to the page from at least one other page.
- Rank Math wants 600+ words per page: give designed pages a real text section (sections, FAQs) rather than padding.
- Before handover and after content changes, run the score check: every page and post must score 80 or more.

```bash
pip install playwright && python3 -m playwright install chromium
python3 tests/seo_scores.py --site http://127.0.0.1:9401 --playground            # local
ES_WP_USER=... ES_WP_PASS=... python3 tests/seo_scores.py --site https://client.com  # live (never commit the password)
```

## Verify

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

`bin/build.sh` writes `dist/<theme-folder>.zip`. Upload it in Appearance > Themes > Add New > Upload (or through the site's MCP, as in the Nuvista project), with the version bumped.

## Client

Replace this section in the client's copy: business facts, server and MCP access, pending items.
