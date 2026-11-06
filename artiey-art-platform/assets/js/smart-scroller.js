/* Artiey smart scroller: flat scroller + 3D coverflow / cylinder. Dependency free. */
(function () {
	'use strict';

	var reduceQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
	var has3d = !!(window.CSS && window.CSS.supports && window.CSS.supports('transform-style', 'preserve-3d'));

	function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
	function make(tag, cls, attrs) {
		var n = document.createElement(tag);
		if (cls) { n.className = cls; }
		for (var k in (attrs || {})) { if (Object.prototype.hasOwnProperty.call(attrs, k)) { n.setAttribute(k, attrs[k]); } }
		return n;
	}

	function Scroller(root) {
		var cfg = {};
		try { cfg = JSON.parse(root.getAttribute('data-aetss') || '{}'); } catch (e) { cfg = {}; }
		var stage = root.querySelector('.aetss-stage');
		var track = root.querySelector('.aetss-track');
		if (!stage || !track) { return; }
		var slides = Array.prototype.slice.call(track.children);
		var n = slides.length;
		if (!n) { return; }

		var prevBtn = root.querySelector('.aetss-prev'), nextBtn = root.querySelector('.aetss-next');
		var dotsBox = root.querySelector('.aetss-dots'), live = root.querySelector('.aetss-live');
		var modeBtn = root.querySelector('.aetss-mode');
		var rtl = window.getComputedStyle(root).direction === 'rtl';
		var dir = rtl ? -1 : 1;
		var wanted = cfg.mode && cfg.mode !== 'flat' ? cfg.mode : 'coverflow'; // the 3D style used when 3D is on
		var userMode = cfg.mode || 'flat';                                      // flat | coverflow | cylinder (visitor may switch)
		var active = clamp(parseInt(cfg.start, 10) || 0, 0, n - 1);
		var frac = 0, dragging = false, moved = 0, timer = null, paused = false, visible = true, wheelAt = 0, raf = 0;
		var small = window.matchMedia ? window.matchMedia('(max-width: 600px)') : { matches: false };

		function reduce() { return reduceQuery.matches; }
		function forcedFlat() { return !has3d || reduce() || n < 3 || (small.matches && !cfg.mobile3d); }
		function mode() { return forcedFlat() ? 'flat' : userMode; }
		function is3d() { return mode() !== 'flat'; }
		function wrap(i) { return cfg.loop ? ((i % n) + n) % n : clamp(i, 0, n - 1); }

		/* ---------- state classes ---------- */
		function applyClasses() {
			var m = mode();
			root.classList.remove('aetss--flat', 'aetss--coverflow', 'aetss--cylinder');
			root.classList.add('aetss--' + m, 'aetss-ready');
			root.classList.toggle('aetss--forced-flat', forcedFlat());
			if (modeBtn) { modeBtn.setAttribute('aria-pressed', m === 'flat' ? 'false' : 'true'); }
		}

		/* ---------- 3D transforms ---------- */
		function slideWidth() { return slides[0].offsetWidth || 300; }
		function measureHeight() {
			var h = 0;
			slides.forEach(function (s) { h = Math.max(h, s.offsetHeight); });
			if (h) { track.style.height = h + 'px'; }
		}
		function place() {
			var m = mode();
			if (m === 'flat') {
				track.style.height = ''; track.style.transform = '';
				slides.forEach(function (s) { s.style.transform = ''; s.style.opacity = ''; s.style.zIndex = ''; s.style.visibility = ''; s.style.pointerEvents = ''; });
				return;
			}
			var W = slideWidth(), pos = active + frac, R = 0;
			if (m === 'cylinder') { R = Math.max(W * 0.9, (W / 2) / Math.tan(Math.PI / n) * 1.05); track.style.transform = 'translateZ(' + (-R).toFixed(1) + 'px)'; }
			else { track.style.transform = ''; }
			slides.forEach(function (s, i) {
				var off = i - pos;
				if (cfg.loop) { off = ((off + n / 2) % n + n) % n - n / 2; }
				var a = Math.abs(off), sg = off < 0 ? -1 : 1, t, op, z, vis = true;
				if (m === 'coverflow') {
					var spacing = W * (cfg.spread || 0.55);
					var x = (Math.min(a, 1) * spacing + Math.max(0, a - 1) * spacing * 0.55) * sg * dir;
					var ry = -sg * dir * Math.min(a, 1) * (cfg.angle || 42);
					var tz = -Math.min(a, 3) * (cfg.depth || 260) * 0.6;
					var sc = 1 - Math.min(a, 3) * 0.07;
					t = 'translate3d(' + x.toFixed(1) + 'px,0,' + tz.toFixed(1) + 'px) rotateY(' + ry.toFixed(2) + 'deg) scale(' + sc.toFixed(3) + ')';
					op = a > 4 ? 0 : 1 - Math.max(0, a - 1) * 0.22; z = 1000 - Math.round(a * 10); vis = a <= 4.2;
				} else {
					var ang = off * (360 / n) * dir;
					t = 'rotateY(' + ang.toFixed(2) + 'deg) translateZ(' + R.toFixed(1) + 'px)';
					var facing = Math.cos(ang * Math.PI / 180);
					op = clamp(0.25 + facing * 0.75, 0.12, 1); z = 0; vis = facing > -0.35;
				}
				s.style.transform = t; s.style.opacity = String(Math.max(0, op).toFixed(3));
				s.style.zIndex = String(z); s.style.visibility = vis ? '' : 'hidden';
				s.style.pointerEvents = a < 1.5 ? '' : 'none';
				s.setAttribute('aria-hidden', a < 0.5 ? 'false' : 'true');
				var focusables = s.querySelectorAll('a[href],button');
				for (var k = 0; k < focusables.length; k++) {
					if (focusables[k].classList.contains('aetss-media')) { continue; }
					if (a < 0.5) { focusables[k].removeAttribute('tabindex'); } else { focusables[k].setAttribute('tabindex', '-1'); }
				}
			});
		}

		function updateUi(announce) {
			if (prevBtn) { prevBtn.disabled = !cfg.loop && active <= 0; }
			if (nextBtn) { nextBtn.disabled = !cfg.loop && active >= n - 1; }
			if (dotsBox) { for (var i = 0; i < dotsBox.children.length; i++) { dotsBox.children[i].setAttribute('aria-selected', i === active ? 'true' : 'false'); } }
			if (announce && live) {
				var t = slides[active].querySelector('.aetss-title');
				live.textContent = (t ? t.textContent + ' — ' : '') + (active + 1) + ' / ' + n;
			}
		}

		/* ---------- flat scrolling ---------- */
		function centerDelta(el) {
			var r = el.getBoundingClientRect(), s = stage.getBoundingClientRect();
			return (r.left + r.width / 2) - (s.left + stage.clientWidth / 2);
		}
		function flatScrollTo(i) {
			var d = centerDelta(slides[i]);
			if (stage.scrollBy) { stage.scrollBy({ left: d, behavior: reduce() ? 'auto' : 'smooth' }); } else { stage.scrollLeft += d; }
		}
		function nearestFlat() {
			var best = 0, bd = Infinity;
			slides.forEach(function (s, i) { var d = Math.abs(centerDelta(s)); if (d < bd) { bd = d; best = i; } });
			return best;
		}

		/* ---------- navigation ---------- */
		function goTo(i, announce) {
			active = wrap(i); frac = 0;
			if (is3d()) { place(); } else { flatScrollTo(active); }
			updateUi(announce !== false);
		}
		function step(d) {
			var target = active + d;
			if (!cfg.loop && (target < 0 || target > n - 1)) { return; }
			goTo(target);
		}

		/* ---------- interactions ---------- */
		function onPointerDown(e) {
			if (!is3d() || !cfg.drag || (e.button && e.button !== 0)) { return; }
			if (e.target.closest && e.target.closest('.aetss-nav, .aetss-mode')) { return; }
			dragging = true; moved = 0; root.classList.add('aetss-dragging');
			var startX = e.clientX, spacing = slideWidth() * (mode() === 'cylinder' ? 0.9 : (cfg.spread || 0.55)) || 200, lastX = startX, lastT = Date.now(), v = 0;
			function move(ev) {
				var dx = ev.clientX - startX; moved = Math.max(moved, Math.abs(dx));
				var now = Date.now(); v = (ev.clientX - lastX) / Math.max(1, now - lastT); lastX = ev.clientX; lastT = now;
				frac = clamp(-dx * dir / spacing, -1.5, 1.5);
				if (!raf) { raf = window.requestAnimationFrame(function () { raf = 0; place(); }); }
			}
			function up() {
				document.removeEventListener('pointermove', move); document.removeEventListener('pointerup', up); document.removeEventListener('pointercancel', up);
				dragging = false; root.classList.remove('aetss-dragging');
				var bias = clamp(-v * dir * 0.6, -0.6, 0.6);
				var stepN = Math.round(frac + bias);
				active = wrap(active + stepN); frac = 0; place(); updateUi(true);
			}
			document.addEventListener('pointermove', move); document.addEventListener('pointerup', up); document.addEventListener('pointercancel', up);
		}
		function onClickCapture(e) {
			if (!is3d()) { return; }
			if (moved > 6) { e.preventDefault(); e.stopPropagation(); moved = 0; return; }
			var li = e.target.closest ? e.target.closest('.aetss-slide') : null;
			if (!li) { return; }
			var i = slides.indexOf(li);
			if (i > -1 && i !== active) { e.preventDefault(); e.stopPropagation(); goTo(i); } // side slides focus first; only the centre slide navigates
		}
		function onKey(e) {
			var k = e.key;
			if (k === 'ArrowRight') { e.preventDefault(); step(rtl ? -1 : 1); }
			else if (k === 'ArrowLeft') { e.preventDefault(); step(rtl ? 1 : -1); }
			else if (k === 'Home') { e.preventDefault(); goTo(0); }
			else if (k === 'End') { e.preventDefault(); goTo(n - 1); }
		}
		function onWheel(e) {
			if (!cfg.wheel || !is3d() || Math.abs(e.deltaX) <= Math.abs(e.deltaY)) { return; }
			e.preventDefault();
			var now = Date.now(); if (now - wheelAt < 380) { return; }
			wheelAt = now; step((e.deltaX > 0 ? 1 : -1) * dir);
		}

		/* ---------- autoplay ---------- */
		function tick() { if (!paused && visible && !document.hidden && !dragging) { step(1); if (!cfg.loop && active >= n - 1) { stopAuto(); } } }
		function startAuto() { if (cfg.autoplay > 0 && !reduce() && !timer) { timer = window.setInterval(tick, cfg.autoplay); } }
		function stopAuto() { if (timer) { window.clearInterval(timer); timer = null; } }

		/* ---------- mode switching ---------- */
		function refresh() {
			var wasFlat = !root.classList.contains('aetss--coverflow') && !root.classList.contains('aetss--cylinder');
			if (is3d() && wasFlat) { active = nearestFlat(); }
			applyClasses();
			if (is3d()) { measureHeight(); place(); } else { place(); }
			updateUi(false);
			if (reduce()) { stopAuto(); }
		}
		function setUserMode(m) { userMode = m; if (m !== 'flat') { wanted = m; } refresh(); if (!is3d()) { flatScrollTo(active); } }

		/* ---------- build ---------- */
		if (dotsBox && n <= 12) {
			for (var i = 0; i < n; i++) {
				(function (idx) {
					var b = make('button', 'aetss-dot', { type: 'button', role: 'tab', 'aria-label': 'اسلاید ' + (idx + 1) });
					b.addEventListener('click', function () { goTo(idx); });
					dotsBox.appendChild(b);
				})(i);
			}
		}
		if (prevBtn) { prevBtn.addEventListener('click', function () { step(-1); }); }
		if (nextBtn) { nextBtn.addEventListener('click', function () { step(1); }); }
		if (modeBtn) { modeBtn.addEventListener('click', function () { setUserMode(is3d() ? 'flat' : wanted); }); }
		stage.addEventListener('pointerdown', onPointerDown);
		stage.addEventListener('keydown', onKey);
		stage.addEventListener('wheel', onWheel, { passive: false });
		track.addEventListener('click', onClickCapture, true);
		stage.addEventListener('scroll', function () {
			if (is3d()) { return; }
			if (!raf) { raf = window.requestAnimationFrame(function () { raf = 0; var a = nearestFlat(); if (a !== active) { active = a; updateUi(false); } }); }
		}, { passive: true });
		['mouseenter', 'focusin', 'touchstart'].forEach(function (ev) { root.addEventListener(ev, function () { paused = true; }, { passive: true }); });
		['mouseleave', 'focusout'].forEach(function (ev) { root.addEventListener(ev, function () { paused = false; }); });
		if (reduceQuery.addEventListener) { reduceQuery.addEventListener('change', refresh); }
		if (small.addEventListener) { small.addEventListener('change', refresh); }
		if ('ResizeObserver' in window) { new window.ResizeObserver(function () { if (is3d()) { measureHeight(); place(); } }).observe(root); }
		else { window.addEventListener('resize', function () { if (is3d()) { measureHeight(); place(); } }); }
		if ('IntersectionObserver' in window) { new window.IntersectionObserver(function (en) { visible = en[0].isIntersecting; }, { threshold: 0.2 }).observe(root); }

		refresh();
		if (!is3d() && active > 0) { flatScrollTo(active); }
		startAuto();

		root.__aetss = { goTo: goTo, step: step, setMode: setUserMode, state: function () { return { active: active, mode: mode() }; } };
	}

	function init(scope) {
		var roots = (scope || document).querySelectorAll('.aetss:not(.aetss-ready)');
		Array.prototype.forEach.call(roots, function (root) { if (!root.__aetss) { Scroller(root); } });
	}

	window.ArtieySmartScroller = { init: init };
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { init(); }); } else { init(); }
	window.addEventListener('elementor/frontend/init', function () {
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/artiey_smart_scroller.default', function ($el) { init($el && $el[0] ? $el[0] : undefined); });
		}
	});
})();
