/*
 * Medical College Manager — college header & footer (1.32). Vanilla, no
 * dependencies, deferred. Dropdown keyboard support + edge flip, mobile panel
 * (focus trap, Escape, scroll lock), static / sticky / smart / shrink,
 * admin-bar offset, back-to-top. Settings arrive as data-* on the header.
 */
(function () {
	'use strict';
	/* Skip link: themes name the content area differently. When there is no
	   #content, point it at the page's main area instead. */
	var skip = document.querySelector('.mcm-hf-skip');
	if (skip && !document.getElementById('content')) {
		var target = document.querySelector('main, [role="main"], .mcm-dept, .mcm-archive, .site-main');
		if (target) {
			if (!target.id) { target.id = 'mcm-hf-content'; }
			skip.setAttribute('href', '#' + target.id);
		}
	}
	if (skip) {
		skip.addEventListener('click', function () {
			var t = document.getElementById(skip.getAttribute('href').slice(1));
			if (t) { if (!t.hasAttribute('tabindex')) { t.setAttribute('tabindex', '-1'); } t.focus(); }
		});
	}

	var root = document.documentElement;
	// Enable colour transitions only after the first paint (see the CSS).
	window.requestAnimationFrame(function () { window.requestAnimationFrame(function () { root.classList.add('mcm-hf-ready'); }); });
	var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* ---------- Back to top (part of the footer; works without the header) ---------- */
	var top = document.querySelector('.mcm-hf-totop');
	if (top) {
		window.addEventListener('scroll', function () { top.classList.toggle('is-on', window.scrollY > 600); }, { passive: true });
		top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); });
	}

	/* ---------- Reveal footer: only when the page is long enough and the footer fits ---------- */
	var reveal = document.querySelector('[data-mcm-reveal]');
	if (reveal) {
		var revealFoot = reveal.querySelector('.mcm-hf-footer');
		var fitReveal = function () {
			reveal.classList.remove('is-reveal');
			reveal.style.removeProperty('--mcm-hf-reveal-h');
			var fh = revealFoot ? revealFoot.offsetHeight : 0;
			var longPage = document.documentElement.scrollHeight > window.innerHeight + fh * 1.5;
			if (!reduce && fh && longPage && fh < window.innerHeight * 0.85) {
				reveal.style.setProperty('--mcm-hf-reveal-h', fh + 'px');
				reveal.classList.add('is-reveal');
			}
		};
		fitReveal();
		window.addEventListener('resize', fitReveal);
		window.addEventListener('load', fitReveal);
	}

	/* ---------- Header search (a <details>): focus the field, Escape / outside click close it ---------- */
	document.querySelectorAll('.mcm-hf-search').forEach(function (d) {
		d.addEventListener('toggle', function () { if (d.open) { var i = d.querySelector('input'); if (i) { i.focus(); } } });
		d.addEventListener('keydown', function (e) { if (e.key === 'Escape' && d.open) { d.open = false; d.querySelector('summary').focus(); } });
	});
	document.addEventListener('click', function (e) {
		document.querySelectorAll('.mcm-hf-search[open]').forEach(function (d) { if (!d.contains(e.target)) { d.open = false; } });
	});

	var header = document.querySelector('.mcm-hf-header');
	if (!header) { return; }
	var isMobile = function () { return header.classList.contains('mcm-hf-collapsed'); }; // below the breakpoint OR menu does not fit

	/* ---------- Dropdowns ---------- */
	function setOpen(li, open) {
		li.classList.toggle('is-open', open);
		var btn = li.querySelector(':scope > .mcm-hf-sub-toggle');
		if (btn) { btn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
		var sub = li.querySelector(':scope > .sub-menu');
		if (open && sub && !li.closest('.mcm-hf-acc')) { flip(sub); }
		if (!open) { li.querySelectorAll('.is-open').forEach(function (x) { setOpen(x, false); }); }
	}
	function flip(sub) {
		sub.classList.remove('mcm-hf-flip');
		sub.parentNode.classList.remove('mcm-hf-flip-parent');
		var r = sub.getBoundingClientRect();
		if (r.right > window.innerWidth - 8) { sub.classList.add('mcm-hf-flip'); sub.parentNode.classList.add('mcm-hf-flip-parent'); }
	}
	function closeSiblings(li) {
		Array.prototype.forEach.call(li.parentNode.children, function (s) { if (s !== li && s.classList.contains('is-open')) { setOpen(s, false); } });
	}
	document.querySelectorAll('.mcm-hf-menu, .mcm-hf-acc').forEach(function (menu) {
		menu.addEventListener('click', function (e) {
			var btn = e.target.closest('.mcm-hf-sub-toggle');
			if (!btn) { return; }
			var li = btn.parentNode;
			var open = !li.classList.contains('is-open');
			if (open) { closeSiblings(li); }
			setOpen(li, open);
		});
		menu.addEventListener('keydown', function (e) {
			var li = e.target.closest('li');
			if (!li) { return; }
			var sub = li.querySelector(':scope > .sub-menu');
			if (e.key === 'Escape') {
				var openLi = e.target.closest('li.is-open') || li.parentNode.closest('li.is-open');
				if (openLi) { setOpen(openLi, false); openLi.querySelector('a').focus(); e.preventDefault(); }
			} else if (e.key === 'ArrowDown' && sub && !menu.classList.contains('mcm-hf-acc') && !li.parentNode.classList.contains('sub-menu')) {
				closeSiblings(li); setOpen(li, true); sub.querySelector('a').focus(); e.preventDefault();
			} else if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && li.parentNode.classList.contains('sub-menu')) {
				var items = Array.prototype.slice.call(li.parentNode.children);
				var i = items.indexOf(li) + (e.key === 'ArrowDown' ? 1 : -1);
				if (items[i]) { items[i].querySelector('a').focus(); e.preventDefault(); }
			} else if (e.key === 'ArrowRight' && sub && li.parentNode.classList.contains('sub-menu')) {
				setOpen(li, true); sub.querySelector('a').focus(); e.preventDefault();
			} else if (e.key === 'ArrowLeft' && li.parentNode.classList.contains('sub-menu') && li.parentNode.parentNode.closest('.sub-menu')) {
				var parent = li.parentNode.parentNode; setOpen(parent, false); parent.querySelector('a').focus(); e.preventDefault();
			}
		});
	});
	/* Close desktop dropdowns when focus or a click leaves the menu. */
	document.addEventListener('click', function (e) { if (!e.target.closest('.mcm-hf-menu')) { document.querySelectorAll('.mcm-hf-menu .is-open').forEach(function (x) { setOpen(x, false); }); } });
	document.querySelectorAll('.mcm-hf-menu > li').forEach(function (li) {
		li.addEventListener('focusout', function (e) { if (!li.contains(e.relatedTarget)) { setOpen(li, false); } });
		li.addEventListener('mouseenter', function () { var s = li.querySelectorAll('.sub-menu'); s.forEach(function (x) { x.parentNode.addEventListener('mouseenter', function () { flip(x); }, { once: true }); }); });
	});

	/* ---------- Mobile panel ---------- */
	var panel = document.getElementById('mcm-hf-panel');
	var burger = document.querySelector('.mcm-hf-burger');
	var lastFocus = null;
	/* Only what can take focus now: links inside collapsed accordion branches are display:none. */
	function focusables() { return Array.prototype.filter.call(panel.querySelectorAll('a[href], button:not([disabled])'), function (el) { return el.getClientRects().length > 0; }); }
	function openPanel() {
		lastFocus = document.activeElement;
		panel.hidden = false;
		void panel.offsetWidth; // Flush styles so the slide-in transition runs from the closed state.
		panel.classList.add('is-open'); // Visible now, so focus() below is not ignored.
		root.classList.add('mcm-hf-locked');
		burger.setAttribute('aria-expanded', 'true');
		panel.querySelector('.mcm-hf-panel-close').focus();
	}
	function closePanel() {
		panel.classList.remove('is-open');
		root.classList.remove('mcm-hf-locked');
		burger.setAttribute('aria-expanded', 'false');
		setTimeout(function () { if (!panel.classList.contains('is-open')) { panel.hidden = true; } }, reduce ? 0 : 260);
		if (lastFocus) { lastFocus.focus(); }
	}
	if (panel && burger) {
		burger.addEventListener('click', openPanel);
		panel.querySelector('.mcm-hf-panel-close').addEventListener('click', closePanel);
		panel.querySelector('.mcm-hf-panel-backdrop').addEventListener('click', closePanel);
		panel.addEventListener('keydown', function (e) {
			if (e.defaultPrevented) { return; } // Escape already closed an open accordion branch.
			if (e.key === 'Escape') { closePanel(); return; }
			if (e.key !== 'Tab') { return; }
			var f = focusables(); var first = f[0]; var last = f[f.length - 1];
			if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
			else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
		});
		window.addEventListener('resize', function () { if (window.mcmHfFit) { window.mcmHfFit(); } /* update collapsed state first */ if (!isMobile() && panel.classList.contains('is-open')) { closePanel(); } });
		// Belt and braces for the trap: browsers differ in what Tab visits (Safari skips links by default),
		// so if focus ever lands outside the open panel, bring it back to the first control.
		document.addEventListener('focusin', function (e) { if (panel.classList.contains('is-open') && !panel.contains(e.target)) { focusables()[0].focus(); } });
	}

	/* ---------- Admin bar offset ---------- */
	function adminBar() {
		var ab = document.getElementById('wpadminbar');
		var h = ab && getComputedStyle(ab).position === 'fixed' ? ab.offsetHeight : 0;
		root.style.setProperty('--mcm-hf-adminbar', h + 'px');
		return h;
	}

	/* ---------- Sticky modes ---------- */
	var spacer = document.querySelector('.mcm-hf-spacer');
	var lastY = window.scrollY;
	var startH = 0;
	function currentMode() { var m = header.getAttribute('data-mode') || 'static'; return isMobile() ? (header.getAttribute('data-mode-mobile') || m) : m; }
	function measure() {
		// is-hidden is a transform (no effect on the height): keep it, so a resize or a
		// font load never pops a smart-sticky header back while the page is still.
		header.classList.remove('is-fixed', 'is-stuck');
		if (spacer) { spacer.classList.remove('is-on'); spacer.style.height = ''; }
		startH = header.offsetHeight;
	}
	function onScroll() {
		var m = currentMode();
		var y = window.scrollY;
		if (m === 'static') { return; }
		var stuck = y > 4;
		header.classList.toggle('is-fixed', true);
		header.classList.toggle('is-stuck', stuck);
		if (spacer) { spacer.classList.add('is-on'); spacer.style.height = startH + 'px'; }
		if (m === 'smart' && y !== lastY) { header.classList.toggle('is-hidden', y > startH && y > lastY); } // No movement (resize, font load): keep the state.
		var tb = header.querySelector('.mcm-hf-topbar');
		header.style.setProperty('--mcm-hf-topbar-h', (tb ? tb.offsetHeight : 0) + 'px');
		/* Anchor targets are never hidden behind the fixed header. */
		root.style.setProperty('--mcm-hf-scroll-pad', (header.offsetHeight + adminBar() + 8) + 'px');
		lastY = y;
	}
	function setup() { if (window.mcmHfFit) { window.mcmHfFit(); } adminBar(); measure(); onScroll(); }
	if (document.fonts && document.fonts.ready) { document.fonts.ready.then(setup); }
	window.addEventListener('scroll', function () { window.requestAnimationFrame(onScroll); }, { passive: true });
	window.addEventListener('resize', setup);
	setup();

}());
