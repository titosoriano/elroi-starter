/* ElRoi Starter: dropdowns, mobile menu, reveal on scroll, video players, form notice focus. No dependencies. */
(function () {
	'use strict';
	document.documentElement.classList.add('es-js');

	// Dropdowns: click or Enter/Space to open, Escape or click outside to close.
	var drops = document.querySelectorAll('[data-es-dropdown]');
	function closeAll(except) {
		drops.forEach(function (d) {
			if (d === except) return;
			var b = d.querySelector('button');
			b.setAttribute('aria-expanded', 'false');
			d.querySelector('ul').hidden = true;
		});
	}
	drops.forEach(function (d) {
		var b = d.querySelector('button');
		var ul = d.querySelector('ul');
		b.addEventListener('click', function () {
			var open = b.getAttribute('aria-expanded') === 'true';
			closeAll(d);
			b.setAttribute('aria-expanded', String(!open));
			ul.hidden = open;
		});
		d.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { closeAll(); b.focus(); }
		});
		d.addEventListener('focusout', function (e) {
			if (!d.contains(e.relatedTarget)) closeAll();
		});
	});
	document.addEventListener('click', function (e) {
		if (!e.target.closest('[data-es-dropdown]')) closeAll();
	});

	// Mobile drawer: everything behind it is inert while open.
	var drawer = document.querySelector('[data-es-drawer]');
	var toggle = document.querySelector('[data-es-menu]');
	var behind = ['main', '.es-footer', '.es-header'].map(function (s) { return document.querySelector(s); }).filter(Boolean);
	function setDrawer(open) {
		if (!drawer) return;
		drawer.hidden = !open;
		toggle.setAttribute('aria-expanded', String(open));
		document.body.classList.toggle('es-locked', open);
		behind.forEach(function (el) { el.inert = open; });
		(open ? drawer.querySelector('[data-es-menu-close]') : toggle).focus();
	}
	if (drawer && toggle) {
		toggle.addEventListener('click', function () { setDrawer(true); });
		drawer.querySelector('[data-es-menu-close]').addEventListener('click', function () { setDrawer(false); });
		drawer.addEventListener('click', function (e) { if (e.target === drawer) setDrawer(false); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !drawer.hidden) setDrawer(false); });
		drawer.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { setDrawer(false); }); });
	}

	// Reveal on scroll; anything already in view, or without IntersectionObserver, shows at once.
	var items = document.querySelectorAll('[data-es-reveal]');
	if ('IntersectionObserver' in window) {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
			});
		}, { rootMargin: '0px 0px -8% 0px' });
		items.forEach(function (el) { io.observe(el); });
	} else {
		items.forEach(function (el) { el.classList.add('is-in'); });
	}
	window.setTimeout(function () {
		items.forEach(function (el) {
			if (el.getBoundingClientRect().top < window.innerHeight) el.classList.add('is-in');
		});
	}, 1200);

	// Videos: the cover becomes the YouTube/Vimeo player only when pressed.
	document.querySelectorAll('[data-es-video]').forEach(function (a) {
		a.addEventListener('click', function (e) {
			e.preventDefault();
			var f = document.createElement('iframe');
			var src = a.getAttribute('data-es-video');
			f.src = src + (src.indexOf('?') < 0 ? '?' : '&') + 'autoplay=1';
			f.title = a.getAttribute('data-es-video-title') || 'Video';
			f.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
			f.allowFullscreen = true;
			f.className = 'es-video__frame';
			a.replaceWith(f);
			f.focus();
		});
	});

	// After sending the form, move focus to its message so screen readers announce it.
	var notice = document.querySelector('[data-es-notice]');
	if (notice) notice.focus();

	// Key events for Google Analytics, when Site Kit or another tag defines gtag or dataLayer
	// (nothing is sent otherwise): calls, emails, WhatsApp, estimate buttons and sent forms. Mark
	// them as key events in GA4 > Admin > Events. window.esTrack( name, params ) sends a child theme's own.
	function track(name, params) {
		params = params || {};
		if (typeof window.gtag === 'function') window.gtag('event', name, params);
		else if (window.dataLayer && window.dataLayer.push) window.dataLayer.push(Object.assign({ event: name }, params));
	}
	window.esTrack = track;
	document.addEventListener('click', function (e) {
		var a = e.target.closest ? e.target.closest('a[href]') : null;
		if (!a) return;
		var href = a.getAttribute('href') || '';
		if (/^tel:/i.test(href)) track('click_call', { link_url: href });
		else if (/^mailto:/i.test(href)) track('click_email', { link_url: href });
		else if (/(^https?:\/\/)?(wa\.me|api\.whatsapp\.com)\//i.test(href)) track('click_whatsapp', { link_url: href });
		else if (/#lead-form$/.test(href)) track('click_quote', { link_text: (a.textContent || '').trim().slice(0, 100) });
	});
	// A sent form comes back with ?lead=sent: count it once, then drop the flag so a reload does not count it again.
	if (window.URLSearchParams && window.history && history.replaceState) {
		var q = new URLSearchParams(location.search);
		if (q.get('lead') === 'sent') {
			track('form_submit', { form_id: 'lead-form' });
			q.delete('lead');
			history.replaceState(null, '', location.pathname + (q.toString() ? '?' + q.toString() : '') + location.hash);
		}
	}
})();
