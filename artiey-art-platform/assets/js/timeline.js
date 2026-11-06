/* Artiey Exhibition Timeline – previous / next navigation (no page reload). */
(function () {
	'use strict';

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.aet-nav-btn') : null;
		if (!btn) {
			return;
		}
		var root = btn.closest('.aet');
		if (!root || root.getAttribute('data-busy')) {
			return;
		}

		var step = parseInt(btn.getAttribute('data-step'), 10) || 0;
		var offset = (parseInt(root.getAttribute('data-offset'), 10) || 0) + step;
		var rest = root.getAttribute('data-rest') || '';
		var args = root.getAttribute('data-args') || '{}';
		var sep = rest.indexOf('?') > -1 ? '&' : '?';
		var url = rest + sep + 'offset=' + encodeURIComponent(offset) + '&args=' + encodeURIComponent(args);

		root.setAttribute('data-busy', '1');
		root.classList.add('aet-loading');

		fetch(url, { credentials: 'same-origin' })
			.then(function (r) {
				if (!r.ok) {
					throw new Error('HTTP ' + r.status);
				}
				return r.json();
			})
			.then(function (data) {
				var body = root.querySelector('.aet-body');
				if (body && data && typeof data.html === 'string') {
					body.innerHTML = data.html;
					root.setAttribute('data-offset', String(offset));
				}
			})
			.catch(function () {
				/* keep the current view on error */
			})
			.then(function () {
				root.removeAttribute('data-busy');
				root.classList.remove('aet-loading');
			});
	});
})();
