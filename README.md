# ElRoi Starter

**The base theme for every custom website by [ElRoi Marketing](https://elroi.marketing/).**

A lean, hand-coded WordPress theme made to work with [ElRoi Site Editor](https://github.com/titosoriano/elroi-site-editor): the design lives in code, and every word and picture the client may change is a simple form in wp-admin. No page builder, no parent theme.

![Home page](docs/home.png)

## What is included

- **Page templates:** home, service page, general page (about, policies), contact page, blog, single post and 404.
- **Editable content everywhere:** hero, services, about with key facts, reviews, sections with checklists and cards, FAQs and a call to action, all registered with ElRoi Site Editor and filled with placeholder copy.
- **Site Settings:** business name and type, phone, email, address, opening hours, numbers shown on the site, the services in the contact form, and social profiles.
- **Lead form:** saved in WordPress, emailed, and optionally sent to HighLevel (contact, tags, note and opportunity) with a Cloudflare Turnstile spam check. Keys live in `wp-config.php`, never in the code.
- **Local SEO:** business, breadcrumb and FAQ structured data, ready to sit next to Rank Math.
- **Accessible by default:** skip link, visible focus, keyboard dropdowns, a mobile menu that locks the page behind it, labelled form fields, reduced-motion support.
- **One file to brand it:** colors, fonts and radius in `assets/css/tokens.css`.

![Service page](docs/service.png)

## Starting a client website

1. Click **Use this template** to create the client's private repository.
2. Brand it in `assets/css/tokens.css`, then design and register the client's pages (see [`AGENTS.md`](AGENTS.md)).
3. On the client's WordPress: install [ElRoi Site Editor](https://github.com/titosoriano/elroi-site-editor/releases/latest), then upload the theme zip from `bin/build.sh`.
4. Create the pages, menu and Site Settings, then give the client an **Editor** account.

[`AGENTS.md`](AGENTS.md) is the full guide for developers and AI agents: structure, rules, local testing on WordPress Playground, and deployment.

## Requirements

WordPress 6.5+, PHP 8.1+, and the ElRoi Site Editor plugin.

## License

GPL-2.0-or-later. Made by [ElRoi Marketing](https://elroi.marketing/).
