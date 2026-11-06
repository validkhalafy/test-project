/*! Artiey open calls: live deadline countdowns and "copy link". Vanilla JS, no dependencies. */
(function () {
	'use strict';
	var cfg = window.ArtieyCalls || {};
	var t = cfg.i18n || {};
	var FA = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
	function digits(s) { return cfg.fa ? String(s).replace(/[0-9]/g, function (d) { return FA[d]; }) : String(s); }

	function render(el) {
		var end = Date.parse(el.getAttribute('data-aet-deadline') || '');
		if (isNaN(end)) { return false; }
		var diff = end - Date.now();
		if (diff <= 0) {
			el.textContent = t.ended || '';
			el.classList.add('is-ended');
			return false;
		}
		var d = Math.floor(diff / 86400000), h = Math.floor((diff % 86400000) / 3600000),
			m = Math.floor((diff % 3600000) / 60000), s = Math.floor((diff % 60000) / 1000);
		var parts = [];
		if (d) { parts.push(digits(d) + ' ' + (t.day || 'd')); }
		parts.push(digits(h) + ' ' + (t.hour || 'h'));
		parts.push(digits(m) + ' ' + (t.minute || 'm'));
		if (!d) { parts.push(digits(s) + ' ' + (t.second || 's')); }
		el.textContent = parts.join(cfg.fa ? ' و ' : ' ') + ' ' + (t.left || '');
		el.classList.toggle('is-soon', diff < 3 * 86400000);
		return true;
	}

	function start() {
		var items = Array.prototype.slice.call(document.querySelectorAll('[data-aet-deadline]'));
		if (!items.length) { return; }
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var tick = function () {
			items = items.filter(render);
			if (items.length) { window.setTimeout(tick, reduce ? 60000 : 1000); }
		};
		tick();
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest && e.target.closest('[data-aet-copy]');
		if (!btn) { return; }
		var url = btn.getAttribute('data-aet-copy');
		var done = function () {
			var old = btn.textContent;
			btn.textContent = t.copied || 'OK';
			window.setTimeout(function () { btn.textContent = old; }, 1800);
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(url).then(done, function () { window.prompt('', url); });
		} else {
			window.prompt('', url);
		}
	});

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
	window.ArtieyCallsRefresh = start;
})();
