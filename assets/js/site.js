/* ElRoi Starter: dropdowns, mobile menu, reveal on scroll, form notice focus. No dependencies. */
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

	// After sending the form, move focus to its message so screen readers announce it.
	var notice = document.querySelector('[data-es-notice]');
	if (notice) notice.focus();
})();
