/* ElRoi Starter accessibility preferences (parts/a11y.php, inc/a11y.php), ported from Nuvista.
   Classes on <html>, saved in localStorage "es-a11y": boolean prefs become es-a11y-<key>, text
   size es-a11y-text-<n>, color es-a11y-color-<name>. The inline script in inc/a11y.php applies
   the saved classes before first paint. Changes fire an "es:a11y" event on document. */
(function () {
	'use strict';
	var doc = document.documentElement;
	var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var a11y = document.querySelector('[data-es-a11y]');
	if (a11y) {
		var a11yBtn = a11y.querySelector('[data-es-a11y-toggle]');
		var a11yPanel = a11y.querySelector('[data-es-a11y-panel]');
		var a11yLive = a11y.querySelector('[data-es-a11y-live]');
		var guide = document.querySelector('[data-es-a11y-guide]');
		var tip = document.querySelector('[data-es-a11y-tip]');
		var struct = document.querySelector('[data-es-a11y-struct]');
		var synth = window.speechSynthesis;
		var prefs = {};
		try { prefs = JSON.parse(localStorage.getItem('es-a11y')) || {}; } catch (e) { prefs = {}; }
		if (prefs.contrast === true && !prefs.color) prefs.color = 'contrast';
		delete prefs.contrast;
		var colors = ['dark', 'light', 'contrast', 'hsat', 'lsat', 'mono', 'protan', 'deutan', 'tritan'];
		var flags = [];
		a11y.querySelectorAll('[data-es-a11y-opt]').forEach(function (b) { flags.push(b.getAttribute('data-es-a11y-opt')); });

		var speak = function (text) {
			if (!synth || !prefs.speak) return;
			text = (text || '').replace(/\s+/g, ' ').trim();
			if (!text) return;
			synth.cancel();
			var u = new SpeechSynthesisUtterance(text.slice(0, 600));
			u.lang = doc.lang || 'en-US';
			synth.speak(u);
		};
		var profileOn = function (p) {
			return Object.keys(p).every(function (k) { return prefs[k] === p[k]; });
		};

		var apply = function () {
			['125', '150', '200'].forEach(function (t) { doc.classList.toggle('es-a11y-text-' + t, prefs.text === t); });
			colors.forEach(function (c) { doc.classList.toggle('es-a11y-color-' + c, prefs.color === c); });
			flags.forEach(function (k) { doc.classList.toggle('es-a11y-' + k, !!prefs[k]); });
			reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches || !!prefs.motion;
			if (reduce || prefs.noimg || prefs.color === 'light') document.querySelectorAll('video').forEach(function (v) { v.pause(); });
			document.dispatchEvent(new CustomEvent('es:a11y', { detail: { prefs: prefs, reduce: reduce } }));
			if (!prefs.speak && synth) synth.cancel();
			if (!prefs.magnifier && !prefs.tooltips) tip.classList.remove('is-on');
			a11y.querySelectorAll('[data-es-a11y-text]').forEach(function (b) {
				b.setAttribute('aria-pressed', (prefs.text || '') === b.getAttribute('data-es-a11y-text') ? 'true' : 'false');
			});
			a11y.querySelectorAll('[data-es-a11y-color]').forEach(function (b) {
				b.setAttribute('aria-pressed', prefs.color === b.getAttribute('data-es-a11y-color') ? 'true' : 'false');
			});
			a11y.querySelectorAll('[data-es-a11y-opt]').forEach(function (b) {
				b.setAttribute('aria-pressed', prefs[b.getAttribute('data-es-a11y-opt')] ? 'true' : 'false');
			});
			a11y.querySelectorAll('[data-es-a11y-profile]').forEach(function (b) {
				b.setAttribute('aria-pressed', profileOn(JSON.parse(b.getAttribute('data-es-a11y-profile'))) ? 'true' : 'false');
			});
			try { localStorage.setItem('es-a11y', JSON.stringify(prefs)); } catch (e) {}
		};
		var openPanel = function (open) {
			a11yBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
			a11yPanel.hidden = !open;
			if (open) a11yPanel.querySelector('button').focus();
		};
		var announce = function (b, on) {
			a11yLive.textContent = b.querySelector('.es-a11y__opt-name').textContent + (on ? ' on' : ' off');
		};

		a11yBtn.addEventListener('click', function () { openPanel(a11yPanel.hidden); });
		a11y.querySelector('[data-es-a11y-close]').addEventListener('click', function () { openPanel(false); a11yBtn.focus(); });
		a11y.querySelectorAll('[data-es-a11y-text]').forEach(function (b) {
			b.addEventListener('click', function () { prefs.text = b.getAttribute('data-es-a11y-text'); apply(); });
		});
		a11y.querySelectorAll('[data-es-a11y-color]').forEach(function (b) {
			b.addEventListener('click', function () {
				var c = b.getAttribute('data-es-a11y-color');
				prefs.color = prefs.color === c ? '' : c;
				apply();
				announce(b, !!prefs.color);
			});
		});
		a11y.querySelectorAll('[data-es-a11y-opt]').forEach(function (b) {
			b.addEventListener('click', function () {
				var k = b.getAttribute('data-es-a11y-opt');
				prefs[k] = !prefs[k];
				apply();
				announce(b, prefs[k]);
				if (k === 'speak' && prefs.speak) speak('Read aloud on. Click any text, or tab to a link, to hear it.');
			});
		});
		a11y.querySelectorAll('[data-es-a11y-profile]').forEach(function (b) {
			b.addEventListener('click', function () {
				var p = JSON.parse(b.getAttribute('data-es-a11y-profile'));
				var on = !profileOn(p);
				Object.keys(p).forEach(function (k) {
					if (on) prefs[k] = p[k];
					else delete prefs[k];
				});
				apply();
				announce(b, on);
			});
		});
		a11y.querySelector('[data-es-a11y-reset]').addEventListener('click', function () {
			prefs = {};
			apply();
			a11yLive.textContent = 'All accessibility options reset';
		});
		a11yPanel.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { e.stopPropagation(); openPanel(false); a11yBtn.focus(); }
		});
		document.addEventListener('click', function (e) {
			if (!a11yPanel.hidden && !a11y.contains(e.target) && !(struct && struct.contains(e.target))) openPanel(false);
		});

		// Read aloud needs the Speech Synthesis API.
		if (!synth) {
			var speakBtn = a11y.querySelector('[data-es-a11y-opt="speak"]');
			if (speakBtn) {
				speakBtn.disabled = true;
				speakBtn.querySelector('.es-a11y__opt-desc').textContent = 'Not supported in this browser';
			}
			delete prefs.speak;
		}

		// Read aloud: speak text that is clicked, and the name of whatever gets keyboard focus.
		var textSel = 'p, li, h1, h2, h3, h4, h5, h6, dt, dd, td, th, blockquote, figcaption, label, summary, a, button';
		document.addEventListener('click', function (e) {
			if (!prefs.speak || a11y.contains(e.target)) return;
			var el = e.target.closest(textSel);
			if (el) speak(el.getAttribute('aria-label') || el.innerText);
		}, true);
		document.addEventListener('focusin', function (e) {
			if (!prefs.speak || e.target === document.body) return;
			var el = e.target;
			var name = el.getAttribute('aria-label') || el.innerText || el.alt || '';
			if (!name && el.labels && el.labels[0]) name = el.labels[0].innerText;
			if (!name) name = el.getAttribute('placeholder') || el.value || '';
			speak(name);
		});

		// Reading guide follows the pointer; magnifier and image descriptions share one tooltip.
		var placeTip = function (e) {
			var x = Math.min(e.clientX + 18, window.innerWidth - tip.offsetWidth - 12);
			var y = e.clientY + 24;
			if (y + tip.offsetHeight > window.innerHeight - 12) y = e.clientY - tip.offsetHeight - 16;
			tip.style.left = Math.max(12, x) + 'px';
			tip.style.top = Math.max(12, y) + 'px';
		};
		document.addEventListener('mousemove', function (e) {
			if (prefs.guide) guide.style.transform = 'translateY(' + (e.clientY + 14) + 'px)';
			if (!prefs.magnifier && !prefs.tooltips) return;
			var text = '';
			var big = false;
			var t = e.target;
			if (prefs.tooltips && t.tagName === 'IMG' && t.alt) {
				text = t.alt;
			} else if (prefs.magnifier && !a11y.contains(t)) {
				var el = t.closest(textSel + ', span, input, textarea');
				if (el) { text = el.value || el.getAttribute('aria-label') || el.innerText || ''; big = true; }
			}
			text = text.replace(/\s+/g, ' ').trim();
			if (text) {
				if (tip.textContent !== text.slice(0, 240)) tip.textContent = text.slice(0, 240);
				tip.classList.toggle('is-big', big);
				tip.classList.add('is-on');
				placeTip(e);
			} else {
				tip.classList.remove('is-on');
			}
		}, { passive: true });
		document.addEventListener('mouseleave', function () { tip.classList.remove('is-on'); });

		// Page structure: lists the headings, regions and links, and moves focus to the one picked.
		if (struct && struct.showModal) {
			var label = function (el) { return (el.getAttribute('aria-label') || el.innerText || '').replace(/\s+/g, ' ').trim(); };
			var item = function (list, tag, text, target) {
				var li = document.createElement('li');
				var b = document.createElement('button');
				b.type = 'button';
				var s = document.createElement('span');
				s.textContent = tag;
				b.appendChild(s);
				b.appendChild(document.createTextNode(text));
				b.addEventListener('click', function () {
					struct.close();
					if (!target.matches('a, button, input, select, textarea, [tabindex]')) target.setAttribute('tabindex', '-1');
					target.scrollIntoView({ block: 'start', behavior: reduce ? 'auto' : 'smooth' });
					target.focus({ preventScroll: true });
				});
				li.appendChild(b);
				list.appendChild(li);
			};
			var build = function () {
				var lists = {};
				struct.querySelectorAll('[data-es-a11y-struct-list]').forEach(function (ul) { ul.textContent = ''; lists[ul.getAttribute('data-es-a11y-struct-list')] = ul; });
				var outside = function (el) { return !el.closest('[data-es-a11y], [data-es-a11y-struct], [hidden], [inert]') && el.getClientRects().length; };
				document.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(function (h) {
					if (!outside(h) || !label(h)) return;
					var li = lists.headings.children.length;
					item(lists.headings, h.tagName, label(h), h);
					lists.headings.children[li].style.paddingLeft = (parseInt(h.tagName.charAt(1), 10) - 1) * 14 + 'px';
				});
				var names = { HEADER: 'Header', NAV: 'Navigation', MAIN: 'Main content', FOOTER: 'Footer', ASIDE: 'Sidebar', FORM: 'Form' };
				document.querySelectorAll('header, nav, main, footer, aside, form[aria-label], form[aria-labelledby], section[aria-labelledby], section[aria-label]').forEach(function (el) {
					if (!outside(el)) return;
					var name = el.getAttribute('aria-label');
					var by = el.getAttribute('aria-labelledby');
					if (!name && by && document.getElementById(by)) name = label(document.getElementById(by));
					item(lists.landmarks, names[el.tagName] || 'Section', name || names[el.tagName] || '', el);
				});
				var seen = {};
				document.querySelectorAll('a[href]').forEach(function (a) {
					var text = label(a) || (a.querySelector('img') ? a.querySelector('img').alt : '');
					if (!outside(a) || !text || seen[text + a.href]) return;
					seen[text + a.href] = true;
					item(lists.links, 'Link', text, a);
				});
			};
			a11y.querySelector('[data-es-a11y-struct-open]').addEventListener('click', function () {
				build();
				openPanel(false);
				struct.showModal();
			});
			struct.querySelector('[data-es-a11y-struct-close]').addEventListener('click', function () { struct.close(); });
		} else {
			a11y.querySelector('[data-es-a11y-struct-open]').hidden = true;
		}

		apply();
	}

})();
