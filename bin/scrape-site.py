#!/usr/bin/env python3
"""Copy a client's current website into an export folder that `wp es import` loads into the new site.

    python3 -I bin/scrape-site.py https://oldsite.com export/
    # review export/map.csv (what each old page becomes), then on the new site:
    # rsync the folder to the server and run: wp es import ~/export --dry-run, then without --dry-run

What it reads (politely: one request at a time, a short pause between them):
- the page list: WordPress's REST API when the old site is WordPress, otherwise its XML sitemap,
  otherwise links followed from the home page (--limit, default 300 pages);
- for each page: the SEO title and description, the H1, the text in reading order (headings,
  paragraphs, lists) without the header, menus, footer, forms and pop-ups, FAQs (FAQPage structured
  data, <details>, Elementor and other accordions), and the photos with their alt text and the
  heading they sit under;
- blog posts (WordPress only, from the REST API): title, date, excerpt, categories, featured image,
  SEO title and description, and the body rebuilt as editor blocks;
- every photo, downloaded once and saved as WebP (max 2048px, quality 80, rotated, no EXIF/GPS:
  the same rule as bin/photos-to-webp.py; needs Pillow, otherwise the original file is kept).

What it writes:
- export/site.json   pages, posts and categories in one file (the importer's input)
- export/map.csv     one row per old page: old path, kind, new path, template, action. Edit it
                     before importing: kind is a guess (home, service, area, about, contact, legal,
                     blog, page), new path defaults to the old one (keep URLs), action is
                     import or skip. A changed path gets a 301 redirect from the old one.
- export/images/     the WebP photos, plus images.json (source URL -> file, alt, size)

Words are copied as they are: read the result, fix facts with the client, and keep the site's copy
rules (starter AGENTS.md > Rules) before launch.
"""

import argparse
import csv
import hashlib
import html
import io
import json
import re
import subprocess
import sys
import time
import xml.etree.ElementTree as ET
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urljoin, urlparse, urldefrag

UA = "Mozilla/5.0 (compatible; ElRoi site migration)"
PAUSE = 0.4
VOID = {"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "source", "track", "wbr"}
DROP_TAGS = {"script", "style", "noscript", "svg", "form", "nav", "header", "footer", "aside", "iframe",
             "button", "select", "textarea", "template", "dialog", "canvas", "video", "audio", "object"}
DROP_HINT = re.compile(r"(^|[\s_-])(menu|navbar|nav|site-header|site-footer|header|footer|cookie|consent|popup|modal|"
                       r"breadcrumbs?|sidebar|widget-area|share|social|skip-link|screen-reader|sr-only|visually-hidden|"
                       r"elementor-location-header|elementor-location-footer|offcanvas|drawer|newsletter)([\s_-]|$)", re.I)
SERVICE_WORDS = re.compile(r"roof|siding|kitchen|bath|remodel|renovat|paint|gutter|window|door|masonry|concrete|"
                           r"deck|patio|plumb|hvac|heating|cooling|electric|landscap|clean|repair|install|basement|"
                           r"carpent|floor|tile|drywall|excavat|paving|asphalt|fence|pool|demolition|construction|"
                           r"contractor|service|restoration|insulation|chimney|waterproof", re.I)
AREA_WORDS = re.compile(r"county|service-areas?|areas-we-serve|locations?|-nj$|-ny$|-ct$|-pa$|town", re.I)


# ---------------------------------------------------------------- fetching

def fetch(url, binary=False):
    """GET through curl (it uses the system's certificates; python.org builds often have none)."""
    time.sleep(PAUSE)
    r = subprocess.run(["curl", "-sL", "--compressed", "--max-time", "60", "-A", UA, "-w", "\n%{http_code}", url],
                       capture_output=True)
    body, _, code = r.stdout.rpartition(b"\n")
    try:
        status = int(code)
    except ValueError:
        status = 0
    if binary:
        return status, body
    return status, body.decode("utf-8", "replace")


def fetch_json(url):
    status, body = fetch(url)
    if status != 200:
        return None
    try:
        return json.loads(body)
    except ValueError:
        return None


# ---------------------------------------------------------------- a small DOM

class Node:
    __slots__ = ("tag", "attrs", "children", "parent")

    def __init__(self, tag, attrs=None, parent=None):
        self.tag, self.attrs, self.children, self.parent = tag, dict(attrs or {}), [], parent

    def cls(self):
        return (self.attrs.get("class") or "") + " " + (self.attrs.get("id") or "")

    def text(self):
        out = []
        for c in self.children:
            out.append(c if isinstance(c, str) else ("\n" if c.tag == "br" else c.text()))
        return "".join(out)

    def iter(self):
        for c in self.children:
            if isinstance(c, Node):
                yield c
                yield from c.iter()

    def find(self, pred):
        return next((n for n in self.iter() if pred(n)), None)


class Builder(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.root = Node("#root")
        self.cur = self.root

    def handle_starttag(self, tag, attrs):
        n = Node(tag, [(k, v or "") for k, v in attrs], self.cur)
        self.cur.children.append(n)
        if tag not in VOID:
            self.cur = n

    def handle_startendtag(self, tag, attrs):
        self.cur.children.append(Node(tag, [(k, v or "") for k, v in attrs], self.cur))

    def handle_endtag(self, tag):
        n = self.cur
        while n is not self.root and n.tag != tag:
            n = n.parent
        if n is not self.root:
            self.cur = n.parent

    def handle_data(self, data):
        self.cur.children.append(data)


def parse(doc):
    b = Builder()
    b.feed(doc)
    return b.root


def clean(s):
    s = html.unescape(s or "").replace("\xa0", " ")
    return re.sub(r"\s+", " ", s).strip()


# ---------------------------------------------------------------- page extraction

def meta(doc):
    def tag(pattern):
        m = re.search(pattern, doc, re.I | re.S)
        return clean(m.group(1)) if m else ""
    return {
        "seo_title": tag(r"<title[^>]*>(.*?)</title>"),
        "seo_description": tag(r'<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)') or
        tag(r'<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\']'),
        "og_image": tag(r'<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']*)'),
        "canonical": tag(r'<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']*)'),
    }


def jsonld_faqs(doc):
    faqs = []
    for block in re.findall(r'<script[^>]+application/ld\+json[^>]*>(.*?)</script>', doc, re.S | re.I):
        try:
            data = json.loads(block)
        except ValueError:
            continue
        stack = [data]
        while stack:
            d = stack.pop()
            if isinstance(d, list):
                stack.extend(d)
            elif isinstance(d, dict):
                if d.get("@type") == "FAQPage" or (isinstance(d.get("@type"), list) and "FAQPage" in d["@type"]):
                    for q in d.get("mainEntity") or []:
                        a = (q.get("acceptedAnswer") or {}).get("text", "")
                        if q.get("name") and a:
                            faqs.append({"q": clean(q["name"]), "a": clean(re.sub(r"<[^>]+>", " ", a))})
                stack.extend(v for v in d.values() if isinstance(v, (dict, list)))
    return faqs


def img_src(n, base):
    """The biggest real photo URL of an <img> (lazy-load attributes and srcset included)."""
    cands = []
    for key in ("data-srcset", "srcset", "data-lazy-srcset"):
        for part in (n.attrs.get(key) or "").split(","):
            bits = part.strip().split()
            if bits:
                w = int(bits[1][:-1]) if len(bits) > 1 and bits[1].endswith("w") and bits[1][:-1].isdigit() else 0
                cands.append((w, bits[0]))
    src = max(cands)[1] if cands else ""
    for key in ("data-src", "data-lazy-src", "data-orig-src", "src"):
        if not src and n.attrs.get(key) and not n.attrs[key].startswith("data:"):
            src = n.attrs[key]
    if not src or src.startswith("data:"):
        return ""
    src = urljoin(base, src)
    if re.search(r"\.svg(\?|$)|gravatar|pixel|spacer|blank\.gif|/emoji/", src, re.I):
        return ""
    for dim in ("width", "height"):
        v = n.attrs.get(dim, "")
        if v.isdigit() and int(v) < 120:
            return ""
    return re.sub(r"-\d+x\d+(\.\w+)$", r"\1", src)  # WordPress: full-size upload, not a thumbnail


def bg_images(node, base):
    out = []
    for n in [node, *node.iter()]:
        style = n.attrs.get("style", "") if isinstance(n, Node) else ""
        for u in re.findall(r"background(?:-image)?\s*:[^;]*url\(['\"]?([^'\")]+)", style):
            if not u.startswith("data:"):
                out.append(urljoin(base, u))
        settings = n.attrs.get("data-settings", "") if isinstance(n, Node) else ""
        for u in re.findall(r'"background_image":\{"url":"([^"]+)"', html.unescape(settings)):
            out.append(urljoin(base, u.replace("\\/", "/")))
    return out


def dropped(n):
    if n.tag in DROP_TAGS:
        return True
    if n.attrs.get("aria-hidden") == "true" or "hidden" in n.attrs or n.attrs.get("role") in ("navigation", "banner", "contentinfo", "dialog"):
        return True
    return bool(DROP_HINT.search(n.cls()))


def content_root(root):
    for pred in (lambda n: n.tag == "main", lambda n: n.attrs.get("role") == "main", lambda n: n.tag == "article",
                 lambda n: re.search(r"(^|\s)(entry-content|page-content|site-content|elementor-section-wrap)(\s|$)", n.cls() or "") is not None,
                 lambda n: n.attrs.get("id") in ("content", "main", "primary")):
        hit = root.find(pred)
        if hit:
            return hit
    return root.find(lambda n: n.tag == "body") or root


def extract(doc, url):
    root = parse(doc)
    page = {"url": url, **meta(doc), "h1": "", "intro": [], "sections": [], "faqs": jsonld_faqs(doc), "images": []}
    body = content_root(root)
    seen_text, seen_img = set(), set()
    sec = None  # current section
    card = None

    def add_img(src, alt):
        if not src or src in seen_img:
            return
        seen_img.add(src)
        page["images"].append({"src": src, "alt": clean(alt)})
        target = card if card is not None else sec
        if target is not None and not target.get("image"):
            target["image"] = src
        elif target is None and not page.get("hero_photo"):
            page["hero_photo"] = src

    def add_text(kind, text):
        nonlocal sec, card
        text = clean(text)
        if not text or text in seen_text:
            return
        seen_text.add(text)
        if kind == "p" and (len(text) < 25 and not re.search(r"[.!?]$", text)):
            return  # button labels, stray captions
        target = card if card is not None else sec
        if target is None:
            if kind == "li":
                page.setdefault("intro_bullets", []).append(text)
            else:
                page["intro"].append(text)
        elif kind == "li":
            target.setdefault("bullets", []).append(text)
        else:
            target.setdefault("paragraphs", []).append(text)

    faq_q = None

    def walk(n):
        nonlocal sec, card, faq_q
        if isinstance(n, str) or dropped(n):
            return
        t, c = n.tag, n.cls()
        # FAQs written as accordions or <details>
        if t == "summary" or re.search(r"elementor-tab-title|accordion-(title|header|button)|faq-(question|q)\b|toggle-title", c):
            faq_q = clean(n.text())
            return
        if faq_q and (t == "details" or re.search(r"elementor-tab-content|accordion-(content|body|panel)|faq-(answer|a)\b|toggle-content", c)):
            ans = clean(n.text())
            if ans and not any(f["q"] == faq_q for f in page["faqs"]):
                page["faqs"].append({"q": faq_q, "a": ans})
            faq_q = None
            seen_text.add(ans)
            return
        if t == "details":
            summ = n.find(lambda x: x.tag == "summary")
            if summ:
                q = clean(summ.text())
                summ.children = []
                a = clean(n.text())
                if q and a and not any(f["q"] == q for f in page["faqs"]):
                    page["faqs"].append({"q": q, "a": a})
                    seen_text.add(a)
                return
        if t == "h1":
            if not page["h1"]:
                page["h1"] = clean(n.text())
            return
        if t == "h2":
            head = clean(n.text())
            if head and head not in seen_text:
                seen_text.add(head)
                sec, card = {"heading": head}, None
                page["sections"].append(sec)
            return
        if t in ("h3", "h4", "h5"):
            head = clean(n.text())
            if head and head not in seen_text:
                seen_text.add(head)
                if sec is None:
                    sec = {"heading": ""}
                    page["sections"].append(sec)
                card = {"heading": head}
                sec.setdefault("cards", []).append(card)
            return
        if t == "p" or (t == "blockquote"):
            for im in [x for x in n.iter() if x.tag == "img"]:
                add_img(img_src(im, url), im.attrs.get("alt", ""))
            add_text("p", n.text())
            return
        if t == "li":
            add_text("li", n.text())
            return
        if t == "img":
            add_img(img_src(n, url), n.attrs.get("alt", ""))
            return
        for u in bg_images(Node("x", n.attrs), url):
            add_img(u, "")
        # loose text in a div (page builders): treat a text-only block as a paragraph
        if t == "div" and n.children and all(isinstance(ch, str) or ch.tag in ("span", "strong", "em", "b", "i", "a", "br") for ch in n.children):
            add_text("p", n.text())
            return
        for ch in n.children:
            walk(ch)

    hero_bgs = bg_images(body, url)[:1]
    for ch in body.children:
        walk(ch)
    if hero_bgs and not page.get("hero_photo"):
        page["hero_photo"] = hero_bgs[0]
        if hero_bgs[0] not in seen_img:
            page["images"].insert(0, {"src": hero_bgs[0], "alt": ""})
    if not page.get("hero_photo") and page["og_image"]:
        page["hero_photo"] = page["og_image"]
        page["images"].insert(0, {"src": page["og_image"], "alt": ""})
    # a card with only a heading is a sub-heading of its section's text, not a card
    for s in page["sections"]:
        cards = s.get("cards") or []
        if cards and all(not c.get("paragraphs") and not c.get("bullets") and not c.get("image") for c in cards):
            s.pop("cards")
            s.setdefault("bullets", []).extend(c["heading"] for c in cards)
    page["sections"] = [s for s in page["sections"] if s.get("heading") or s.get("paragraphs") or s.get("bullets") or s.get("cards")]
    # The text before the first heading: a short label above the H1 (eyebrow) and the introduction.
    intro = [t for t in page["intro"] if not re.search(r"★|\(\d{3}\)\s*\d{3}-\d{4}|opens in a new tab", t)]
    if intro and len(intro[0]) <= 60 and not re.search(r"[.!?]$", intro[0]):
        page["eyebrow"] = intro.pop(0)
    page["lede"] = next((t for t in intro if len(t) >= 60), intro[0] if intro else "")
    page["intro"] = intro
    page["words"] = sum(len(" ".join([*s.get("paragraphs", []), *s.get("bullets", [])]).split()) for s in page["sections"]) + len(" ".join(page["intro"]).split())
    return page


# ---------------------------------------------------------------- discovery

def same_site(u, host):
    h = urlparse(u).netloc.lower().removeprefix("www.")
    return h == host


def skip_url(u):
    return re.search(r"/(wp-admin|wp-login|wp-json|feed|tag|category|author|attachment|page/\d+|cart|checkout|my-account)(/|$)|"
                     r"\?|\.(jpe?g|png|gif|webp|svg|pdf|zip|xml|mp4|mov|css|js)$|/#", u, re.I)


def sitemap_urls(base, host):
    urls, todo, done = [], [], set()
    _, robots = fetch(urljoin(base, "/robots.txt"))
    todo += re.findall(r"(?im)^sitemap:\s*(\S+)", robots or "")
    todo += [urljoin(base, p) for p in ("/sitemap_index.xml", "/sitemap.xml", "/wp-sitemap.xml")]
    while todo and len(done) < 60:
        sm = todo.pop(0)
        if sm in done:
            continue
        done.add(sm)
        status, body = fetch(sm)
        if status != 200 or "<" not in body:
            continue
        try:
            tree = ET.fromstring(body.encode("utf-8", "replace"))
        except ET.ParseError:
            continue
        for loc in tree.iter():
            if not loc.tag.endswith("loc") or not loc.text:
                continue
            u = loc.text.strip()
            if u.endswith(".xml") or "sitemap" in u.rsplit("/", 1)[-1]:
                if re.search(r"(post|page|portfolio|project|service|location|wp-sitemap-posts)", u, re.I) or "sitemap_index" in sm or "wp-sitemap.xml" in sm or True:
                    if not re.search(r"(category|tag|author|attachment|taxonom|users)", u, re.I):
                        todo.append(u)
            elif same_site(u, host) and not skip_url(u):
                urls.append(urldefrag(u)[0])
        if urls and not todo:
            break
    return list(dict.fromkeys(urls))


def crawl_urls(base, host, limit):
    todo, seen, out = [base], set(), []
    while todo and len(out) < limit:
        u = todo.pop(0)
        if u in seen:
            continue
        seen.add(u)
        status, body = fetch(u)
        if status != 200:
            continue
        out.append(u)
        for href in re.findall(r'href=["\']([^"\'#]+)', body):
            v = urldefrag(urljoin(u, html.unescape(href)))[0]
            if same_site(v, host) and not skip_url(v) and v not in seen:
                todo.append(v)
    return out


# ---------------------------------------------------------------- WordPress posts

def top_level(content):
    out, i = [], 0
    tag_re = re.compile(r"<(/?)([a-zA-Z0-9]+)[^>]*?(/?)>")
    start = re.compile(r"<(p|h[1-6]|ul|ol|figure|blockquote|table|div|hr|img)\b")
    while True:
        m = start.search(content, i)
        if not m:
            break
        name, depth, j = m.group(1), 0, m.start()
        if name in ("hr", "img"):
            j = content.find(">", m.start()) + 1
        else:
            for t in tag_re.finditer(content, m.start()):
                if t.group(2).lower() != name:
                    continue
                if t.group(3):
                    j = t.end(); break
                depth += -1 if t.group(1) else 1
                if depth == 0:
                    j = t.end(); break
            else:
                j = len(content)
        out.append((name, content[m.start():j]))
        i = max(j, m.end())
    return out


def post_blocks(content, base, images):
    """Rendered post HTML -> block markup; photos become {"img": n} placeholders for the importer."""
    blocks = []
    for name, el in top_level(content):
        el = re.sub(r"<!--.*?-->", "", el, flags=re.S).strip()
        if name in ("p", "div") and not re.sub(r"<[^>]+>|&nbsp;|\s", "", el) and "<img" not in el:
            continue
        imgs = re.findall(r"<img[^>]*>", el)
        if name in ("figure", "img") or (name in ("p", "div") and imgs and not re.sub(r"<[^>]+>|&nbsp;|\s", "", el)):
            for tag in imgs:
                n = parse(tag).find(lambda x: x.tag == "img")
                src = img_src(n, base) if n else ""
                if src:
                    cap = re.search(r"<figcaption[^>]*>(.*?)</figcaption>", el, re.S)
                    images.append({"src": src, "alt": clean(n.attrs.get("alt", ""))})
                    blocks.append({"img": len(images) - 1, "caption": clean(re.sub(r"<[^>]+>", "", cap.group(1))) if cap else ""})
            continue
        if name == "p":
            blocks.append(f"<!-- wp:paragraph -->\n{el}\n<!-- /wp:paragraph -->")
        elif name[0] == "h" and name[1:].isdigit():
            lvl = max(2, int(name[1]))
            attrs = "" if lvl == 2 else f' {{"level":{lvl}}}'
            inner = re.sub(r"^<h\d[^>]*>|</h\d>$", "", el)
            blocks.append(f'<!-- wp:heading{attrs} -->\n<h{lvl} class="wp-block-heading">{inner}</h{lvl}>\n<!-- /wp:heading -->')
        elif name in ("ul", "ol"):
            items = re.findall(r"<li[^>]*>(.*?)</li>", el, re.S)
            inner = "".join(f"<!-- wp:list-item -->\n<li>{x.strip()}</li>\n<!-- /wp:list-item -->" for x in items)
            attrs = ' {"ordered":true}' if name == "ol" else ""
            blocks.append(f'<!-- wp:list{attrs} -->\n<{name} class="wp-block-list">{inner}</{name}>\n<!-- /wp:list -->')
        elif name == "blockquote":
            blocks.append(f'<!-- wp:quote -->\n<blockquote class="wp-block-quote">{re.sub(r"^<blockquote[^>]*>|</blockquote>$", "", el)}</blockquote>\n<!-- /wp:quote -->')
        elif name == "div":
            text = clean(re.sub(r"<[^>]+>", " ", el))
            if text:
                blocks.append(f"<!-- wp:paragraph -->\n<p>{html.escape(text, quote=False)}</p>\n<!-- /wp:paragraph -->")
        else:
            blocks.append(f"<!-- wp:html -->\n{el}\n<!-- /wp:html -->")
    return blocks


def wp_posts(base):
    posts, page = [], 1
    while True:
        batch = fetch_json(urljoin(base, f"/wp-json/wp/v2/posts?per_page=100&page={page}&_embed=wp:featuredmedia,wp:term"))
        if not isinstance(batch, list) or not batch:
            break
        posts += batch
        if len(batch) < 100:
            break
        page += 1
    cats = fetch_json(urljoin(base, "/wp-json/wp/v2/categories?per_page=100")) or []
    out = []
    for p in posts:
        _, head = fetch(p["link"])
        m = meta(head or "")
        images = []
        fm = ((p.get("_embedded") or {}).get("wp:featuredmedia") or [{}])[0] or {}
        terms = [t for g in (p.get("_embedded") or {}).get("wp:term", []) for t in g if t.get("taxonomy") == "category"]
        out.append({
            "url": p["link"], "slug": p["slug"], "title": clean(p["title"]["rendered"]),
            "date": p["date"], "modified": p.get("modified", p["date"]),
            "excerpt": clean(re.sub(r"<[^>]+>", " ", p["excerpt"]["rendered"])).removesuffix("[…]").removesuffix("[&hellip;]").strip(),
            "categories": [t["slug"] for t in terms if t["slug"] != "uncategorized"],
            "featured": {"src": fm.get("source_url", ""), "alt": fm.get("alt_text", "")} if fm.get("source_url") else None,
            "seo_title": m["seo_title"], "seo_description": m["seo_description"],
            "blocks": post_blocks(p["content"]["rendered"], p["link"], images),
            "images": images,
        })
        print(f"  post {p['slug']}: {len(out[-1]['blocks'])} blocks, {len(images)} photos", file=sys.stderr)
    return out, [{"slug": c["slug"], "name": clean(c["name"]), "description": clean(c.get("description", ""))}
                 for c in cats if c.get("count") and c["slug"] != "uncategorized"]


# ---------------------------------------------------------------- photos

def save_photo(data, dest, max_px=2048, limit=450 * 1024):
    try:
        from PIL import Image, ImageOps
    except ImportError:
        return None
    try:
        img = ImageOps.exif_transpose(Image.open(io.BytesIO(data)))
        if img.mode not in ("RGB", "RGBA"):
            img = img.convert("RGBA" if "A" in img.getbands() else "RGB")
        for px, q in ((max_px, 80), (1920, 74), (1920, 68), (1920, 62)):
            out = img.copy()
            out.thumbnail((px, px), Image.LANCZOS)
            out.save(dest, "WEBP", quality=q, method=6)
            if dest.stat().st_size <= limit:
                break
        return out.size
    except Exception:
        return None


def download_photos(urls, folder, alts):
    folder.mkdir(parents=True, exist_ok=True)
    index_file = folder / "images.json"
    index = json.loads(index_file.read_text()) if index_file.exists() else {}
    for u in urls:
        if u in index:
            continue
        status, data = fetch(u, binary=True)
        if status != 200 or len(data) < 1500:
            print(f"  photo {status}: {u}", file=sys.stderr)
            continue
        stem = re.sub(r"[^a-z0-9]+", "-", Path(urlparse(u).path).stem.lower()).strip("-")[:60] or "photo"
        name = f"{stem}-{hashlib.sha1(u.encode()).hexdigest()[:6]}"
        dest = folder / f"{name}.webp"
        size = save_photo(data, dest)
        if size is None:  # no Pillow, or not an image Pillow reads: keep the original file
            ext = Path(urlparse(u).path).suffix.lower() or ".jpg"
            dest = folder / f"{name}{ext}"
            dest.write_bytes(data)
            size = (0, 0)
        index[u] = {"file": dest.name, "alt": alts.get(u, ""), "width": size[0], "height": size[1], "bytes": dest.stat().st_size}
        index_file.write_text(json.dumps(index, indent=1))
    return index


# ---------------------------------------------------------------- kinds and the map

def guess_kind(path, page, front):
    slug = path.strip("/").split("/")[-1] if path.strip("/") else ""
    if path == "/" or (front and path == front):
        return "home"
    if re.search(r"contact|quote|estimate|appointment|book", slug):
        return "contact"
    if re.search(r"about|our-story|team|who-we-are", slug):
        return "about"
    if re.search(r"privacy|terms|accessibility|cookie|disclaimer|legal|sitemap", slug):
        return "legal"
    if re.search(r"^blog$|^news$|^articles$|^journal$", slug):
        return "blog"
    if AREA_WORDS.search(path) or re.search(r"\b(County|Township|Borough)\b|general contractor in ", page.get("h1", "") + " " + page.get("seo_title", "")):
        return "area"
    if SERVICE_WORDS.search(slug) or path.startswith("/services/"):
        return "service"
    return "page"


def page_title(p):
    """WordPress page title: the SEO title without its " | Brand" ending, else the H1."""
    t = re.split(r"\s+[|\u2013\u2014-]\s+(?=[^|\u2013\u2014-]*$)", p.get("seo_title") or "")[0].strip()
    return t if 3 <= len(t) <= 70 else (p.get("h1") or t or p["path"].strip("/").split("/")[-1].replace("-", " ").title())


TEMPLATES = {"home": "front-page", "service": "templates/service.php", "area": "templates/area.php",
             "contact": "templates/contact.php", "about": "templates/page.php", "legal": "templates/page.php",
             "page": "templates/page.php", "blog": "posts page"}


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("site")
    ap.add_argument("out", type=Path)
    ap.add_argument("--limit", type=int, default=300, help="most pages to read (default 300)")
    ap.add_argument("--no-photos", action="store_true", help="do not download photos")
    ap.add_argument("--no-posts", action="store_true", help="do not read blog posts")
    a = ap.parse_args()

    base = a.site.rstrip("/") + "/"
    if not base.startswith("http"):
        base = "https://" + base
    status, home = fetch(base)
    if status != 200:
        sys.exit(f"{base} answered {status}. Some hosts (SiteGround, Cloudflare) refuse scripts: ask the host or the "
                 "client to allow this computer, or export the content from the old site's admin instead.")
    host = urlparse(base).netloc.lower().removeprefix("www.")
    a.out.mkdir(parents=True, exist_ok=True)

    # page list
    wp_pages = None
    first = fetch_json(urljoin(base, "/wp-json/wp/v2/pages?per_page=100&_fields=link,slug,status"))
    if isinstance(first, list):
        wp_pages, n = list(first), 2
        while len(first) == 100:
            first = fetch_json(urljoin(base, f"/wp-json/wp/v2/pages?per_page=100&page={n}&_fields=link,slug,status")) or []
            wp_pages += first
            n += 1
    if wp_pages:
        urls, how = [p["link"] for p in wp_pages if same_site(p["link"], host)], "WordPress REST API"
    else:
        urls, how = sitemap_urls(base, host), "sitemap"
        if not urls:
            urls, how = crawl_urls(base, host, a.limit), "links from the home page"
    is_wp = wp_pages is not None or "/wp-content/" in home
    posts, cats = ([], [])
    if is_wp and not a.no_posts:
        print("Reading blog posts...", file=sys.stderr)
        posts, cats = wp_posts(base)
    post_urls = {urldefrag(p["url"])[0].rstrip("/") for p in posts}
    urls = [u for u in dict.fromkeys([base, *urls]) if u.rstrip("/") not in post_urls][: a.limit]
    print(f"{len(urls)} pages from the {how}; {len(posts)} posts.", file=sys.stderr)

    pages = []
    for u in urls:
        status, doc = fetch(u)
        if status != 200:
            print(f"  skip {status}: {u}", file=sys.stderr)
            continue
        p = extract(doc, u)
        path = urlparse(u).path or "/"
        p["path"] = path if path.endswith("/") else path + "/"
        pages.append(p)
        print(f"  page {p['path']}: {len(p['sections'])} sections, {len(p['faqs'])} FAQs, {len(p['images'])} photos, {p['words']} words", file=sys.stderr)

    # Parts repeated on many pages (estimate boxes, badge strips, financing bands, closing calls to
    # action) belong to the old site's layout, not to each page: drop them.
    if len(pages) >= 4:
        sig = lambda sec: (sec.get("heading") or "").lower() + "|" + " ".join(sec.get("paragraphs", [])[:1])[:120].lower()
        count = {}
        for p in pages:
            for k in {sig(sec) for sec in p["sections"]}:
                count[k] = count.get(k, 0) + 1
        common = {k for k, n in count.items() if n >= max(3, 0.3 * len(pages))}
        heads = {}
        for p in pages:
            for k in {(sec.get("heading") or "").lower() for sec in p["sections"]}:
                heads[k] = heads.get(k, 0) + 1
        common_heads = {k for k, n in heads.items() if k and n >= max(3, 0.5 * len(pages))}
        dropped_names = set()
        for p in pages:
            keep = []
            for sec in p["sections"]:
                if sig(sec) in common or (sec.get("heading") or "").lower() in common_heads:
                    dropped_names.add(sec.get("heading") or "(no heading)")
                else:
                    keep.append(sec)
            p["sections"] = keep
        if dropped_names:
            print("Left out, repeated on many pages: " + "; ".join(sorted(dropped_names)), file=sys.stderr)

    front = "/"
    for p in pages:
        p["kind"] = guess_kind(p["path"], p, front)

    photos = {}
    if not a.no_photos:
        alts, want = {}, []
        for item in pages + posts:
            for im in item.get("images", []):
                want.append(im["src"])
                alts.setdefault(im["src"], im.get("alt", ""))
            for key in ("hero_photo",):
                if item.get(key):
                    want.append(item[key])
            if item.get("featured"):
                want.append(item["featured"]["src"])
                alts.setdefault(item["featured"]["src"], item["featured"].get("alt", ""))
        want = list(dict.fromkeys(w for w in want if w))
        print(f"Downloading {len(want)} photos as WebP...", file=sys.stderr)
        photos = download_photos(want, a.out / "images", alts)

    (a.out / "site.json").write_text(json.dumps({
        "source": base, "scraped": time.strftime("%Y-%m-%d"), "wordpress": is_wp,
        "pages": pages, "posts": posts, "categories": cats,
    }, ensure_ascii=False, indent=1))
    map_file = a.out / "map.csv"
    old = {}
    if map_file.exists():  # keep edits made to an earlier map
        with open(map_file, newline="") as f:
            old = {r["old_path"]: r for r in csv.DictReader(f)}
    with open(map_file, "w", newline="") as f:
        w = csv.writer(f)
        w.writerow(["old_path", "kind", "new_path", "template", "action", "title", "words"])
        for p in sorted(pages, key=lambda p: (p["path"].count("/"), p["path"])):
            o = old.get(p["path"], {})
            kind = o.get("kind") or p["kind"]
            w.writerow([p["path"], kind, o.get("new_path") or p["path"], TEMPLATES.get(kind, "templates/page.php"),
                        o.get("action") or ("skip" if kind == "blog" else "import"), o.get("title") or page_title(p), p["words"]])
    size = sum(v.get("bytes", 0) for v in photos.values())
    print(f"\nDone: {len(pages)} pages, {len(posts)} posts, {len(photos)} photos ({size / 1e6:.1f} MB) in {a.out}/.\n"
          f"Next: review {map_file} (kind, new_path, action), then copy the folder to the server and run\n"
          f"  wp es import <folder> --dry-run", file=sys.stderr)


if __name__ == "__main__":
    main()
