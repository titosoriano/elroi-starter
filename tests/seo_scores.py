"""Rank Math score check: open every published page and post in wp-admin, let Rank Math analyze it,
and fail when any scores under the minimum (default 80). Run it before handing a site over and
after every content change that matters for SEO.

    pip install playwright && python3 -m playwright install chromium
    # Local Playground (signs in through /es-tests/login.php):
    python3 tests/seo_scores.py --site http://127.0.0.1:9401 --playground
    # A client site (an administrator or editor account; never commit the password):
    ES_WP_USER=... ES_WP_PASS=... python3 tests/seo_scores.py --site https://example.com --min 80

Each line shows the score, the focus keyword and the failed checks, so you know what to fix.
A page without a focus keyword counts as failed: Rank Math scores against the keyword.
"""
import argparse
import asyncio
import json
import os
import sys

from playwright.async_api import async_playwright

READ = """() => {
  const rm = window.rankMathEditor;
  if (!rm || !rm.resultManager) return null;
  const store = window.wp && wp.data && wp.data.select('rank-math');
  const keyword = ((store && store.getKeywords ? store.getKeywords() : '') || '').split(',')[0].trim();
  const ui = document.querySelector('.misc-pub-section.rank-math-seo-score, .rank-math-toolbar-score, .seo-score');
  const m = ui ? ui.textContent.match(/(\\d+)\\s*\\/\\s*100/) : null;
  const res = keyword && rm.resultManager.getResult ? rm.resultManager.getResult(keyword) : null;
  const tests = (res && res.results) || {};
  const failed = Object.keys(tests).filter(k => tests[k] && tests[k].hasScore && !tests[k].hasScore());
  return { score: m ? parseInt(m[1], 10) : rm.resultManager.getScore(keyword), keyword, failed };
}"""


async def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--site", required=True)
    ap.add_argument("--min", type=int, default=80)
    ap.add_argument("--playground", action="store_true", help="sign in through /es-tests/login.php")
    a = ap.parse_args()
    site = a.site.rstrip("/")

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        ctx = await browser.new_context(viewport={"width": 1400, "height": 1000})
        page = await ctx.new_page()
        if a.playground:
            await page.goto(site + "/es-tests/login.php")
        else:
            await page.goto(site + "/wp-login.php")
            await page.fill("#user_login", os.environ["ES_WP_USER"])
            await page.fill("#user_pass", os.environ["ES_WP_PASS"])
            await page.click("#wp-submit")
            await page.wait_for_load_state("domcontentloaded")

        items = []
        for kind in ("pages", "posts"):
            r = await ctx.request.get(f"{site}/?rest_route=/wp/v2/{kind}&per_page=100&status=publish&_fields=id,link,title")
            for it in await r.json():
                items.append((it["id"], it["title"]["rendered"], it["link"]))

        low = 0
        for pid, title, link in items:
            await page.goto(f"{site}/wp-admin/post.php?post={pid}&action=edit", wait_until="domcontentloaded")
            try:
                await page.wait_for_function("window.rankMathEditor && window.rankMathEditor.resultManager", timeout=30000)
            except Exception:
                print(f"  ?  {title}: Rank Math did not load on the edit screen")
                low += 1
                continue
            await page.wait_for_timeout(6000)  # the page preview and the analysis
            res = await page.evaluate(READ)
            ok = res and res["keyword"] and res["score"] >= a.min
            low += 0 if ok else 1
            kw = res["keyword"] if res and res["keyword"] else "(no focus keyword)"
            print(f"{'ok ' if ok else 'LOW'} {res['score'] if res else '?':>3}  {title}  [{kw}]  {link}")
            if res and not ok and res["failed"]:
                print("      failed: " + ", ".join(res["failed"]))
        await browser.close()
    print(json.dumps({"checked": len(items), "below": low, "min": a.min}))
    sys.exit(1 if low else 0)


asyncio.run(main())
