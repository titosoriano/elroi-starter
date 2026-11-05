// Loads Rank Math's own analyzer.js (downloaded by fetch.sh) in Node and scores one item from
// `wp es seo-papers`. Used by score.js and secsearch.js.
const fs = require('fs'), path = require('path'), vm = require('vm');

const vendor = path.join(__dirname, 'vendor');
if (!fs.existsSync(path.join(vendor, 'analyzer.js'))) {
	console.error('Rank Math analyzer missing: run  sh tests/rank-math/fetch.sh https://a-site-with-rank-math  first.');
	process.exit(2);
}
globalThis.window = globalThis; globalThis.self = globalThis;
const jq = function () { return { on() {}, each() {} }; };
jq.ajax = () => ({ done() { return this; }, fail() { return this; } }); jq.each = () => {};
globalThis.jQuery = jq;
globalThis.wp = {};
globalThis.rankMath = { assessor: { hasTOCPlugin: true, diacritics: {}, focusKeywordLink: '', powerWords: [], primaryTaxonomy: false }, postType: 'page', objectType: 'post', objectID: 0, noFollowDomains: [], noFollowExcludeDomains: [], noFollowExternalLinks: false, localeFull: 'en_US', parentDomain: '', links: {}, security: '', ajaxurl: '' };
for (const f of ['lodash.min.js', 'hooks.min.js', 'i18n.min.js', 'autop.min.js', 'wordcount.min.js', 'url.min.js', 'analyzer.js']) {
	vm.runInThisContext(fs.readFileSync(path.join(vendor, f), 'utf8'), { filename: f });
	if (f === 'lodash.min.js') globalThis.lodash = globalThis._;
}
const { Analyzer, Paper, ResultManager, Researcher } = window.rankMathAnalyzer;

/**
 * Score an item with the given keywords (first one is the focus keyword).
 * Returns { score, misses: [{ test, score, max, text }] }.
 */
async function score(item, keywords, opts = {}) {
	rankMath.assessor.powerWords = item.power_words || [];
	rankMath.postType = item.type || 'page';
	rankMath.parentDomain = item.site || '';
	const paper = new Paper('', { locale: 'en_US' });
	paper.setTitle(item.title || ''); paper.setDescription(item.description || ''); paper.setText(item.content || '');
	paper.setKeyword(keywords[0]); paper.setKeywords(keywords); paper.setPermalink(item.slug || ''); paper.setUrl(item.url || '');
	paper.setThumbnail(item.thumb || ''); paper.setThumbnailAltText(item.thumb_alt || '');
	const tests = (item.tests || []).filter(t => opts.contentAi || t !== 'hasContentAI');
	const results = await new Analyzer({ researcher: new Researcher(), analyses: tests }).analyze(paper);
	const rm = new ResultManager(); rm.update(keywords[0], results, true);
	const misses = Object.entries(results)
		.filter(([, r]) => r.getScore() < r.getMaxScore())
		.map(([test, r]) => ({ test, score: r.getScore(), max: r.getMaxScore(), text: String(r.text || '').replace(/<[^>]+>/g, '') }));
	return { score: rm.getScore(keywords[0]), misses };
}

function load(file) {
	const raw = fs.readFileSync(file, 'utf8');
	return JSON.parse(raw.slice(raw.indexOf('[')));
}

module.exports = { score, load };
