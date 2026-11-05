/* ElRoi Starter motion layer: GSAP + ScrollTrigger, with Lenis as the only smooth-scroll engine.
 * Loaded only when the theme declares add_theme_support( 'es-motion' ).
 *
 * Safety rules (see AGENTS.md > Motion):
 *  - The page is complete without this file. Every hidden start state is set here, never in CSS.
 *  - Under prefers-reduced-motion: reduce nothing is hidden or moved and Lenis stays off.
 *  - If GSAP fails to load, nothing runs and nothing is hidden.
 *
 * Declarative hooks:
 *  data-es-split[="0.2"]   heading words rise one by one (value = delay in s); "now" = on load
 *  data-es-rise[="0.2"]    fade and rise when it enters the screen (value = delay in s)
 *  data-es-parallax="12"   drifts inside its parent while scrolling (value = strength in %)
 *  data-es-shutter         wipes open from the bottom when it enters (clip-path); frames that
 *                          enter together cascade. Its first <img> settles from a slight zoom.
 *  data-es-inview-play     a muted <video> plays only while on screen
 *
 * Custom scenes, in the client's own script (enqueued with 'es-motion' as a dependency):
 *  ESMotion.scene(function (m) {            // m = { gsap, ScrollTrigger, lenis }
 *    var tl = m.gsap.timeline({ scrollTrigger: { trigger: '.x', scrub: true, pin: true } });
 *    return function () { tl.kill(); };     // optional cleanup
 *  });
 * Scenes never run under reduced motion, so write them to start from the finished state.
 */
(function () {
	'use strict';

	var cfg = window.ESMotionConfig || { smooth: true, duration: 1.15 };
	// The accessibility panel's "Stop animations" (es-a11y-motion on <html>) counts as reduced motion.
	var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('es-a11y-motion');
	var gsap = window.gsap;
	var ST = window.ScrollTrigger;
	var ok = !reduced && !!gsap && !!ST;
	var lenis = null;

	var api = {
		enabled: ok,
		reduced: reduced,
		gsap: gsap,
		ScrollTrigger: ST,
		lenis: null,
		/** Run fn( { gsap, ScrollTrigger, lenis } ) when motion is allowed. */
		scene: function (fn) {
			if (!ok) return;
			try {
				fn({ gsap: gsap, ScrollTrigger: ST, lenis: lenis });
			} catch (e) {
				if (window.console) console.error('ESMotion scene failed', e);
			}
			refreshSoon();
		},
		/** Scroll to a target (element, selector or number) through Lenis when it runs. */
		scrollTo: function (target, opts) {
			if (lenis) return lenis.scrollTo(target, opts || {});
			var el = typeof target === 'string' ? document.querySelector(target) : target;
			if (typeof target === 'number') window.scrollTo({ top: target, behavior: reduced ? 'auto' : 'smooth' });
			else if (el) el.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth' });
		}
	};
	window.ESMotion = api;

	// Videos play only on screen, also as a bandwidth saver. Under reduced motion they stay paused.
	if (!reduced && 'IntersectionObserver' in window) {
		var vio = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				var v = en.target;
				if (en.isIntersecting) { var p = v.play(); if (p && p.catch) p.catch(function () {}); }
				else v.pause();
			});
		}, { rootMargin: '200px 0px' });
		document.querySelectorAll('video[data-es-inview-play]').forEach(function (v) { vio.observe(v); });
	}

	if (!ok) return;
	gsap.registerPlugin(ST);
	ST.config({ ignoreMobileResize: true });

	// Smooth scroll, driven by the GSAP ticker so ScrollTrigger and Lenis share one clock.
	if (cfg.smooth && window.Lenis) {
		lenis = new window.Lenis({ duration: cfg.duration || 1.15, smoothWheel: true, anchors: true, autoRaf: false });
		lenis.on('scroll', ST.update);
		gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
		gsap.ticker.lagSmoothing(0);
		api.lenis = lenis;
	}

	// Measure again whenever lazy images or fonts change the page height.
	var timer = 0;
	var lastH = 0;
	function refresh() { ST.sort(); ST.refresh(); lastH = document.body.scrollHeight; }
	function refreshSoon() { window.clearTimeout(timer); timer = window.setTimeout(refresh, 200); }
	window.addEventListener('load', refresh);
	if (document.fonts && document.fonts.ready) document.fonts.ready.then(refresh);
	if ('ResizeObserver' in window) {
		new ResizeObserver(function () {
			if (Math.abs(document.body.scrollHeight - lastH) > 2) refreshSoon();
		}).observe(document.body);
	}

	function num(v, d) { var n = parseFloat(v); return isNaN(n) ? d : n; }

	// Split a heading's text into word masks, keeping inner elements (em, a, br) intact.
	function split(el) {
		var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
		var nodes = [];
		var words = [];
		while (walker.nextNode()) nodes.push(walker.currentNode);
		nodes.forEach(function (n) {
			var frag = document.createDocumentFragment();
			n.textContent.split(/(\s+)/).forEach(function (part) {
				if (!part) return;
				if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(part)); return; }
				var outer = document.createElement('span');
				var inner = document.createElement('span');
				outer.className = 'es-w';
				inner.className = 'es-w__in';
				inner.textContent = part;
				outer.appendChild(inner);
				frag.appendChild(outer);
				words.push(inner);
			});
			n.parentNode.replaceChild(frag, n);
		});
		return words;
	}

	document.querySelectorAll('[data-es-split]').forEach(function (el) {
		var v = el.getAttribute('data-es-split');
		var words = split(el);
		gsap.from(words, {
			yPercent: 110,
			opacity: 0,
			duration: 1.1,
			ease: 'expo.out',
			stagger: 0.06,
			delay: v === 'now' ? 0.1 : num(v, 0),
			scrollTrigger: v === 'now' ? undefined : { trigger: el, start: 'clamp(top 85%)', once: true }
		});
	});

	document.querySelectorAll('[data-es-rise]').forEach(function (el) {
		gsap.from(el, {
			y: 40,
			opacity: 0,
			duration: 1.2,
			ease: 'power3.out',
			delay: num(el.getAttribute('data-es-rise'), 0),
			scrollTrigger: { trigger: el, start: 'clamp(top 88%)', once: true }
		});
	});

	document.querySelectorAll('[data-es-parallax]').forEach(function (el) {
		var amt = num(el.getAttribute('data-es-parallax'), 12);
		var scale = 1.05 + (amt * 2.4) / 100; // big enough that the drift never shows an edge
		gsap.fromTo(el, { yPercent: -amt, scale: scale }, {
			yPercent: amt,
			scale: scale,
			ease: 'none',
			scrollTrigger: { trigger: el.parentElement, start: 'top bottom', end: 'bottom top', scrub: true }
		});
	});

	var frames = Array.prototype.slice.call(document.querySelectorAll('[data-es-shutter]'));
	if (frames.length) {
		gsap.set(frames, { clipPath: 'inset(100% 0% 0% 0%)' });
		ST.batch(frames, {
			start: 'clamp(top 92%)',
			once: true,
			onEnter: function (batch) {
				gsap.to(batch, { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.1, ease: 'expo.inOut', stagger: 0.06, clearProps: 'clipPath' });
				var imgs = batch.map(function (f) { return f.querySelector('img'); }).filter(function (i) { return i && !i.hasAttribute('data-es-parallax'); });
				if (imgs.length) gsap.from(imgs, { scale: 1.12, duration: 1.4, ease: 'expo.out', stagger: 0.06, clearProps: 'scale' });
			}
		});
	}

	refreshSoon();
})();
