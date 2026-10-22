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
- Reveal animations use opacity and transform only (`data-es-reveal`); never hide content with `clip-path` or zero height.
- No em-dashes in copy we write; only real facts about the client.
- Bump `Version:` in `style.css` and `ES_VERSION` in `functions.php` on every deploy (cache busting).

## Structure

- `functions.php`: constants and includes.
- `inc/setup.php`: theme supports, menus (`primary`, `footer`), assets, head cleanup, notices when the plugin is missing (the front end shows `parts/needs-plugin.php` until it is active).
- `inc/fields.php`: Site Settings and every template's fields (`front-page`, `templates/service.php`, `templates/page.php`, `templates/contact.php`). Shared builders: `es_hero_fields()`, `es_sections_field()`, `es_faq_field()`, `es_cta_field()`.
- `inc/nav.php`: header menu with dropdowns and the mobile drawer, from the "Main menu" location.
- `inc/lead-form.php`: the contact form (`es_lead_form( $title, $text )`). Saves a private "Form messages" post, emails `form_email` (or the admin email), and optionally sends the lead to HighLevel and checks Cloudflare Turnstile. Server-only settings go in `wp-config.php`, never in the repo: `ELROI_GHL_TOKEN`, `ELROI_GHL_LOCATION`, optional `ELROI_GHL_PIPELINE`, `ELROI_GHL_STAGE`, `ELROI_GHL_MESSAGE_FIELD`; `ELROI_TURNSTILE_SITE`, `ELROI_TURNSTILE_SECRET`. HighLevel tags: `website-lead`, `website-request` (removed and re-added on each message so a "Tag added" workflow fires every time) and `service-<slug>`.
- `inc/seo.php`: JSON-LD: the business (type from Site Settings, same `@id` as Rank Math's Organization) on the home page, BreadcrumbList on inner pages, FAQPage where a page has FAQs. Titles, descriptions, Open Graph and sitemaps belong to Rank Math.
- `parts/`: `hero`, `sections`, `faq`, `cta`. `header.php`, `footer.php` (footer credit links to elroi.marketing).
- Templates: `front-page.php`, `templates/service.php`, `templates/page.php`, `templates/contact.php`; `page.php` (plain block-editor pages), `index.php` (blog/archives), `single.php`, `404.php`.
- `assets/css/tokens.css` (brand), `assets/css/site.css` (layout), `assets/js/site.js` (dropdowns, drawer, reveal, form notice focus).

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

`tests/run.php` checks that each designed page renders one `<h1>` without PHP errors, that saving each page's form unchanged leaves the HTML identical, that form messages are stored, and the schema. On the client's server, repeat the same idea: snapshot the pages' HTML before and after each deploy and compare. Check every page at 1440px and 390px.

## Deploy

`bin/build.sh` writes `dist/<theme-folder>.zip`. Upload it in Appearance > Themes > Add New > Upload (or through the site's MCP, as in the Nuvista project), with the version bumped.

## Client

Replace this section in the client's copy: business facts, server and MCP access, pending items.
