// Which secondary keywords to add: Rank Math counts every focus keyword in its density test, so the
// right one or two extra phrases bring a page into the 1% to 2.5% band without touching the text.
// Add a "pool" array of candidate phrases (words the page really uses) to items in papers.json; this
// tries every set of up to 3 and prints the best score, the keywords to enter and what still misses.
//
//   node tests/rank-math/secsearch.js papers.json
const { score, load } = require('./analyzer');

(async () => {
	for (const it of load(process.argv[2]).filter(i => i.keyword && Array.isArray(i.pool))) {
		const pool = it.pool, sets = [[]];
		pool.forEach((a, i) => {
			sets.push([a]);
			pool.slice(i + 1).forEach((b, j) => {
				sets.push([a, b]);
				pool.slice(i + j + 2).forEach(c => sets.push([a, b, c]));
			});
		});
		let best = null;
		for (const set of sets) {
			const r = await score(it, [it.keyword, ...set]);
			if (!best || r.score > best.score || (r.score === best.score && set.length < best.set.length)) best = { set, ...r };
		}
		const miss = best.misses.map(m => `${m.test} ${m.score}/${m.max}` + (m.test === 'keywordDensity' ? ` ${(m.text.match(/[\d.]+%?/) || [''])[0]}` : ''));
		console.log(`${String(best.score).padStart(3)}  ${it.slug}  ${JSON.stringify([it.keyword, ...best.set].join(', '))}  | ${miss.join(', ')}`);
	}
})().catch(e => { console.error(e); process.exit(2); });
