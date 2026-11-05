// Rank Math score of every page and post, from Rank Math's own analyzer, with no browser or login.
//
//   sh tests/rank-math/fetch.sh https://client.com          # once, and after Rank Math updates
//   wp es seo-papers --out=papers.json                     # on the site (or through Novamira run-wp-cli)
//   node tests/rank-math/score.js papers.json --min 80
//
// Each line: score, (score Rank Math stored in wp-admin), type and slug, focus keyword; under it the
// checks that lost points. Exits 1 when any item is under --min (default 80) or has no focus keyword.
// --json prints { slug: score } instead (to store the scores back), --content-ai keeps the paid-only
// Content AI check, which ElRoi Site Editor leaves out.
const { score, load } = require('./analyzer');
const args = process.argv.slice(2);
const file = args.find(a => !a.startsWith('--'));
const flag = n => args.includes('--' + n);
const opt = (n, d) => { const i = args.indexOf('--' + n); return i >= 0 && args[i + 1] ? args[i + 1] : d; };
if (!file) { console.error('Usage: node tests/rank-math/score.js papers.json [--min 80] [--json] [--content-ai]'); process.exit(2); }
const min = parseInt(opt('min', '80'), 10);

(async () => {
	const items = load(file);
	if (items.length && !(items[0].tests || []).length) {
		console.error('The export has no Rank Math tests: export from a site where Rank Math is active.');
		process.exit(2);
	}
	const json = {};
	let low = 0;
	for (const it of items) {
		if (!it.keyword) {
			low++;
			if (!flag('json')) console.log(`  -  ${it.type}/${it.slug}: no focus keyword`);
			continue;
		}
		const r = await score(it, it.keywords && it.keywords.length ? it.keywords : [it.keyword], { contentAi: flag('content-ai') });
		json[it.slug] = r.score;
		if (r.score < min) low++;
		if (flag('json')) continue;
		const stored = it.stored !== '' && it.stored != null && Number(it.stored) !== r.score ? ` (stored ${it.stored})` : '';
		console.log(`${String(r.score).padStart(3)}${stored}  ${it.type}/${it.slug}  [${it.keywords.join(', ')}]`);
		for (const m of r.misses) console.log(`       ${m.score}/${m.max} ${m.test}: ${m.text}`);
	}
	if (flag('json')) console.log(JSON.stringify(json, null, 1));
	else console.log(`\n${items.length - low} of ${items.length} at ${min} or more.`);
	process.exit(low ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
