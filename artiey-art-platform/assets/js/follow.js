/*!
 * Artiey follow buttons — live follow / unfollow without reloading the page.
 * Works with full-page caches: after load, the current state and a fresh nonce are requested once
 * for every subject on the page; every button and counter of the same subject stays in sync.
 * Without JavaScript the buttons fall back to a normal form POST (logged-in) or the login link.
 */
(function () {
	'use strict';

	var cfg = window.ArtieyFollow || {};
	var i18n = cfg.i18n || {};
	var state = { loggedIn: null, nonce: '', ready: null, busy: {} };
	var FA = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

	function digits(text) {
		text = String(text);
		return cfg.fa ? text.replace(/[0-9]/g, function (d) { return FA[d]; }).replace(/\./g, '٫') : text;
	}

	function compact(n) {
		n = Math.max(0, parseInt(n, 10) || 0);
		var out;
		if (n >= 1000000) {
			out = (Math.round(n / 100000) / 10) + (cfg.fa ? ' ' : '') + (i18n.million || 'M');
		} else if (n >= 1000) {
			out = (Math.round(n / 100) / 10) + (cfg.fa ? ' ' : '') + (i18n.thousand || 'K');
		} else {
			out = String(n);
		}
		return digits(out);
	}

	function post(action, data) {
		var body = new URLSearchParams();
		body.append('action', action);
		Object.keys(data).forEach(function (k) {
			var v = data[k];
			if (Array.isArray(v)) {
				v.forEach(function (item) { body.append(k + '[]', item); });
			} else {
				body.append(k, v);
			}
		});
		return fetch(cfg.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (res) {
			return res.json().catch(function () { return { success: false, data: { code: 'error' } }; }).then(function (json) {
				json.status = res.status;
				return json;
			});
		});
	}

	function widgets(subject) {
		return Array.prototype.slice.call(document.querySelectorAll('[data-aet-follow="' + subject + '"]'));
	}

	function counters(subject) {
		var list = [];
		document.querySelectorAll('[data-aet-follow="' + subject + '"] [data-aet-follow-count], [data-aet-followers="' + subject + '"] .aet-followers-stat:first-child [data-aet-follow-count]').forEach(function (el) {
			if (list.indexOf(el) === -1) { list.push(el); }
		});
		return list;
	}

	function paint(subject, s) {
		widgets(subject).forEach(function (box) {
			var btn = box.querySelector('[data-aet-follow-btn]');
			box.setAttribute('data-following', s.following ? '1' : '0');
			if (!btn) { return; }
			btn.classList.toggle('is-following', !!s.following);
			btn.setAttribute('aria-pressed', s.following ? 'true' : 'false');
			var label = btn.querySelector('.aet-follow-label');
			if (label) {
				label.textContent = s.following ? (btn.getAttribute('data-label-following') || i18n.following) : (btn.getAttribute('data-label-follow') || i18n.follow);
			}
			var input = box.querySelector('input[name="follow_action"]');
			if (input) { input.value = s.following ? 'unfollow' : 'follow'; }
			if (s.own) {
				box.setAttribute('data-own', '1');
				btn.setAttribute('aria-disabled', 'true');
				btn.classList.add('is-own');
			}
		});
		if (typeof s.count === 'number') {
			counters(subject).forEach(function (el) {
				el.setAttribute('data-aet-follow-count', String(s.count));
				el.textContent = s.countText ? s.countText : compact(s.count);
			});
		}
	}

	function announce(box, text) {
		var live = box && box.querySelector('.aet-follow-status');
		if (live) {
			live.textContent = '';
			window.setTimeout(function () { live.textContent = text; }, 30);
		}
	}

	function collect() {
		var subjects = [];
		document.querySelectorAll('[data-aet-follow]').forEach(function (el) {
			var s = el.getAttribute('data-aet-follow');
			if (s && subjects.indexOf(s) === -1) { subjects.push(s); }
		});
		return subjects;
	}

	function refresh() {
		var subjects = collect();
		if (!subjects.length || !cfg.ajax) {
			state.ready = Promise.resolve();
			return state.ready;
		}
		state.ready = post('artiey_follow_state', { subjects: subjects.slice(0, 60) }).then(function (json) {
			if (!json || !json.success || !json.data) { return; }
			state.loggedIn = !!json.data.loggedIn;
			state.nonce = json.data.nonce || '';
			var map = json.data.states || {};
			Object.keys(map).forEach(function (subject) { paint(subject, map[subject]); });
		}).catch(function () { /* keep server-rendered state */ });
		return state.ready;
	}

	function toggle(box, btn, event) {
		var subject = box.getAttribute('data-aet-follow');
		if (box.getAttribute('data-own') === '1') {
			event.preventDefault();
			announce(box, i18n.self || '');
			return;
		}
		// Logged out (or state unknown yet on a guest link): follow the login link normally.
		if (state.loggedIn === false || (state.loggedIn === null && btn.tagName === 'A')) {
			return;
		}
		event.preventDefault();
		if (state.busy[subject]) { return; }
		state.busy[subject] = true;
		var wasFollowing = box.getAttribute('data-following') === '1';
		var countEl = box.querySelector('[data-aet-follow-count]');
		var before = countEl ? parseInt(countEl.getAttribute('data-aet-follow-count'), 10) || 0 : null;
		// Optimistic update; rolled back on failure.
		paint(subject, { following: !wasFollowing, count: before === null ? undefined : Math.max(0, before + (wasFollowing ? -1 : 1)) });
		widgets(subject).forEach(function (b) { b.classList.add('is-busy'); });

		var send = function (retry) {
			return post('artiey_follow_toggle', {
				subject: subject,
				follow_action: wasFollowing ? 'unfollow' : 'follow',
				nonce: state.nonce
			}).then(function (json) {
				if (json && json.success) {
					paint(subject, json.data);
					announce(box, json.data.following ? (i18n.followed || '') : (i18n.unfollowed || ''));
					return;
				}
				var data = (json && json.data) || {};
				if (data.code === 'nonce' && data.nonce && retry) {
					state.nonce = data.nonce;
					return send(false);
				}
				if (data.code === 'login') {
					window.location.href = data.login || cfg.login;
					return;
				}
				paint(subject, { following: wasFollowing, count: before === null ? undefined : before });
				if (typeof data.count === 'number') { paint(subject, data); }
				announce(box, data.code === 'rate' ? i18n.rate : (data.code === 'self' ? i18n.self : i18n.error));
			});
		};

		(state.ready || Promise.resolve()).then(function () {
			if (state.loggedIn === false) {
				window.location.href = btn.getAttribute('href') || cfg.login;
				return;
			}
			return send(true);
		}).catch(function () {
			paint(subject, { following: wasFollowing, count: before === null ? undefined : before });
			announce(box, i18n.error);
		}).then(function () {
			state.busy[subject] = false;
			widgets(subject).forEach(function (b) { b.classList.remove('is-busy'); });
		});
	}

	document.addEventListener('click', function (event) {
		var btn = event.target.closest && event.target.closest('[data-aet-follow-btn]');
		if (!btn) { return; }
		var box = btn.closest('[data-aet-follow]');
		if (box) { toggle(box, btn, event); }
	});

	// Hover / focus on a followed button offers "unfollow".
	function hover(event, on) {
		var btn = event.target.closest && event.target.closest('[data-aet-follow-btn].is-following');
		if (!btn) { return; }
		var label = btn.querySelector('.aet-follow-label');
		if (label) {
			label.textContent = on ? (btn.getAttribute('data-label-unfollow') || i18n.unfollow) : (btn.getAttribute('data-label-following') || i18n.following);
		}
	}
	document.addEventListener('mouseover', function (e) { hover(e, true); });
	document.addEventListener('mouseout', function (e) { hover(e, false); });
	document.addEventListener('focusin', function (e) { hover(e, true); });
	document.addEventListener('focusout', function (e) { hover(e, false); });

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', refresh);
	} else {
		refresh();
	}
	// Elementor editor / popups / AJAX-loaded content can add new buttons later.
	window.ArtieyFollowRefresh = refresh;
	window.addEventListener('elementor/frontend/init', function () {
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			['artiey_follow', 'artiey_curator', 'artiey_gallery_profile'].forEach(function (name) {
				window.elementorFrontend.hooks.addAction('frontend/element_ready/' + name + '.default', refresh);
			});
		}
	});
})();
