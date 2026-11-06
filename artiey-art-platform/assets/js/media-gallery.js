/* Artiey media gallery: scroller + accessible lightbox with video playback. No dependencies. */
(function () {
	'use strict';

	var EMBED_OK = /^https:\/\/(www\.youtube-nocookie\.com\/embed\/[A-Za-z0-9_-]{11}|player\.vimeo\.com\/video\/\d{5,12}|www\.aparat\.com\/video\/video\/embed\/videohash\/[A-Za-z0-9]{3,20}\/vt\/frame)(\?|$)/;
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var lb = null, items = [], index = 0, opener = null;

	function isHttp(u) { return typeof u === 'string' && /^https?:\/\//i.test(u); }
	function isRtl(node) { return window.getComputedStyle(node).direction === 'rtl'; }
	function make(tag, cls, attrs) {
		var n = document.createElement(tag);
		if (cls) { n.className = cls; }
		for (var k in (attrs || {})) { if (Object.prototype.hasOwnProperty.call(attrs, k)) { n.setAttribute(k, attrs[k]); } }
		return n;
	}

	/* ------------------------------ Lightbox ------------------------------ */
	function buildLightbox() {
		var box = make('div', 'aetmg-lb', { role: 'dialog', 'aria-modal': 'true', 'aria-label': 'نمایش رسانه', hidden: '' });
		box.innerHTML =
			'<div class="aetmg-lb-backdrop" data-close="1"></div>' +
			'<div class="aetmg-lb-stage" aria-live="polite"></div>' +
			'<button type="button" class="aetmg-lb-btn aetmg-lb-close" aria-label="بستن">&times;</button>' +
			'<button type="button" class="aetmg-lb-btn aetmg-lb-prev" aria-label="قبلی">&#8249;</button>' +
			'<button type="button" class="aetmg-lb-btn aetmg-lb-next" aria-label="بعدی">&#8250;</button>' +
			'<p class="aetmg-lb-cap"></p>';
		document.body.appendChild(box);
		box.addEventListener('click', function (e) {
			var t = e.target;
			if (t.getAttribute && t.getAttribute('data-close')) { closeLb(); }
			else if (t.closest && t.closest('.aetmg-lb-close')) { closeLb(); }
			else if (t.closest && t.closest('.aetmg-lb-prev')) { go(-1); }
			else if (t.closest && t.closest('.aetmg-lb-next')) { go(1); }
		});
		var sx = null;
		var stage = box.querySelector('.aetmg-lb-stage');
		stage.addEventListener('pointerdown', function (e) { sx = e.clientX; });
		stage.addEventListener('pointerup', function (e) {
			if (sx === null) { return; }
			var dx = e.clientX - sx; sx = null;
			if (Math.abs(dx) > 60) { go((dx < 0) === isRtl(box) ? -1 : 1); }
		});
		return box;
	}

	function stageContent(it) {
		var wrap = make('div', 'aetmg-lb-media');
		if (it.t === 'video') {
			if (it.p === 'file' && isHttp(it.f)) {
				var v = make('video', '', { controls: '', playsinline: '', autoplay: '', preload: 'metadata' });
				if (isHttp(it.o)) { v.setAttribute('poster', it.o); }
				var s = make('source', '', { src: it.f, type: it.m || 'video/mp4' });
				v.appendChild(s); wrap.appendChild(v);
			} else if (EMBED_OK.test(it.e || '')) {
				var src = it.e + (it.e.indexOf('?') > -1 ? '&' : '?') + 'autoplay=1';
				var f = make('iframe', '', {
					src: src, title: it.c || it.a || 'ویدیو', allowfullscreen: '', referrerpolicy: 'strict-origin-when-cross-origin',
					allow: 'autoplay; fullscreen; picture-in-picture; encrypted-media'
				});
				wrap.appendChild(f);
				if (isHttp(it.w)) {
					var a = make('a', 'aetmg-lb-ext', { href: it.w, target: '_blank', rel: 'noopener noreferrer' });
					a.textContent = 'باز کردن در سایت ارائه‌دهنده'; wrap.appendChild(a);
				}
			}
		} else if (isHttp(it.s)) {
			wrap.appendChild(make('img', '', { src: it.s, alt: it.a || '' }));
		}
		return wrap;
	}

	function show(i) {
		index = (i + items.length) % items.length;
		var it = items[index], stage = lb.querySelector('.aetmg-lb-stage');
		stage.innerHTML = '';
		stage.appendChild(stageContent(it));
		lb.querySelector('.aetmg-lb-cap').textContent = it.c || '';
		var many = items.length > 1;
		lb.querySelector('.aetmg-lb-prev').style.display = many ? '' : 'none';
		lb.querySelector('.aetmg-lb-next').style.display = many ? '' : 'none';
	}
	function go(step) { if (items.length > 1) { show(index + step); } }

	function openLb(list, i, from) {
		if (!lb) { lb = buildLightbox(); }
		items = list; opener = from || null;
		lb.hidden = false;
		document.documentElement.classList.add('aetmg-lb-open');
		show(i);
		lb.querySelector('.aetmg-lb-close').focus();
		document.addEventListener('keydown', onKey, true);
	}
	function closeLb() {
		if (!lb || lb.hidden) { return; }
		lb.querySelector('.aetmg-lb-stage').innerHTML = ''; // removing the node stops any playing video
		lb.hidden = true;
		document.documentElement.classList.remove('aetmg-lb-open');
		document.removeEventListener('keydown', onKey, true);
		if (opener && opener.focus) { opener.focus(); }
	}
	function onKey(e) {
		if (!lb || lb.hidden) { return; }
		var rtl = isRtl(lb);
		if (e.key === 'Escape') { e.preventDefault(); closeLb(); }
		else if (e.key === 'ArrowRight') { e.preventDefault(); go(rtl ? -1 : 1); }
		else if (e.key === 'ArrowLeft') { e.preventDefault(); go(rtl ? 1 : -1); }
		else if (e.key === 'Tab') {
			var f = lb.querySelectorAll('button:not([style*="display: none"]), a[href], iframe, video');
			if (!f.length) { return; }
			var first = f[0], last = f[f.length - 1];
			if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
			else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
		}
	}

	/* ------------------------------ Scroller ------------------------------ */
	function initScroller(root, cfg) {
		var vp = root.querySelector('.aetmg-viewport'), track = root.querySelector('.aetmg-track');
		if (!vp || !track) { return; }
		var prev = root.querySelector('.aetmg-prev'), next = root.querySelector('.aetmg-next'), dots = root.querySelector('.aetmg-dots');
		var rtl = isRtl(root), timer = null, paused = false;

		function pos() { return Math.abs(vp.scrollLeft); }
		function maxPos() { return Math.max(0, vp.scrollWidth - vp.clientWidth); }
		function stepPx() {
			var it = track.children[0]; if (!it) { return vp.clientWidth; }
			var gap = parseFloat(window.getComputedStyle(track).columnGap || window.getComputedStyle(track).gap) || 0;
			return it.getBoundingClientRect().width + gap;
		}
		function scrollToPos(p) {
			var left = rtl ? -p : p;
			if (vp.scrollTo) { vp.scrollTo({ left: left, behavior: reduce ? 'auto' : 'smooth' }); } else { vp.scrollLeft = left; }
		}
		function pages() { return Math.max(1, Math.ceil(vp.scrollWidth / Math.max(1, vp.clientWidth))); }
		function update() {
			if (prev) { prev.disabled = !cfg.loop && pos() <= 2; }
			if (next) { next.disabled = !cfg.loop && pos() >= maxPos() - 2; }
			if (dots) {
				var n = pages(), cur = maxPos() ? Math.round(pos() / maxPos() * (n - 1)) : 0;
				if (dots.children.length !== n) {
					dots.innerHTML = '';
					for (var i = 0; i < n; i++) {
						(function (idx) {
							var b = make('button', 'aetmg-dot', { type: 'button', role: 'tab', 'aria-label': 'صفحه ' + (idx + 1) });
							b.addEventListener('click', function () { scrollToPos(maxPos() * idx / Math.max(1, n - 1)); });
							dots.appendChild(b);
						})(i);
					}
				}
				for (var j = 0; j < dots.children.length; j++) { dots.children[j].setAttribute('aria-selected', j === cur ? 'true' : 'false'); }
			}
		}
		function move(dir) {
			var p = pos();
			if (dir > 0 && p >= maxPos() - 2) { if (cfg.loop) { scrollToPos(0); } return; }
			if (dir < 0 && p <= 2) { if (cfg.loop) { scrollToPos(maxPos()); } return; }
			scrollToPos(Math.max(0, Math.min(maxPos(), p + dir * stepPx())));
		}
		if (prev) { prev.addEventListener('click', function () { move(-1); }); }
		if (next) { next.addEventListener('click', function () { move(1); }); }
		vp.addEventListener('scroll', function () { window.requestAnimationFrame(update); }, { passive: true });
		vp.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') { e.preventDefault(); move(rtl ? -1 : 1); }
			else if (e.key === 'ArrowLeft') { e.preventDefault(); move(rtl ? 1 : -1); }
		});
		window.addEventListener('resize', update);
		update();

		var visible = true;
		function tick() { if (!paused && visible && !document.hidden) { move(1); } }
		function startAuto() { if (cfg.autoplay > 0 && !reduce && !timer) { timer = window.setInterval(tick, cfg.autoplay); } }
		if (cfg.autoplay > 0 && !reduce) {
			['mouseenter', 'focusin', 'touchstart', 'pointerdown'].forEach(function (ev) { root.addEventListener(ev, function () { paused = true; }, { passive: true }); });
			['mouseleave', 'focusout'].forEach(function (ev) { root.addEventListener(ev, function () { paused = false; }); });
			if ('IntersectionObserver' in window) {
				new IntersectionObserver(function (en) { visible = en[0].isIntersecting; }, { threshold: 0.2 }).observe(root);
			}
			startAuto();
		}
	}

	/* ------------------------------ Boot ------------------------------ */
	function init(scope) {
		var roots = (scope || document).querySelectorAll('.aetmg:not([data-ready])');
		Array.prototype.forEach.call(roots, function (root) {
			root.setAttribute('data-ready', '1');
			var cfg = {}, list = [];
			try { cfg = JSON.parse(root.getAttribute('data-aetmg') || '{}'); } catch (e) { cfg = {}; }
			var data = root.querySelector('.aetmg-data');
			try { list = data ? JSON.parse(data.textContent || '[]') : []; } catch (e) { list = []; }
			if (root.classList.contains('aetmg--scroller')) { initScroller(root, cfg); }
			root.addEventListener('click', function (e) {
				var btn = e.target.closest ? e.target.closest('.aetmg-open[data-index]') : null;
				if (!btn || !root.contains(btn)) { return; }
				e.preventDefault();
				openLb(list, parseInt(btn.getAttribute('data-index'), 10) || 0, btn);
			});
		});
	}

	window.ArtieyMediaGallery = { init: init };
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { init(); }); } else { init(); }
	window.addEventListener('elementor/frontend/init', function () {
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/artiey_media_gallery.default', function ($el) { init($el && $el[0] ? $el[0] : undefined); });
		}
	});
})();
