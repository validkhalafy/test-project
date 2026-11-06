/* Artiey Art Platform – prefill the COA issuance form from an artwork and link the new certificate to it. */
(function () {
	'use strict';

	var d = window.aetPrefill;
	if (!d) {
		return;
	}

	function fire(el) {
		var ev;
		try {
			ev = new Event('change', { bubbles: true });
		} catch (e) {
			ev = document.createEvent('Event');
			ev.initEvent('change', true, true);
		}
		el.dispatchEvent(ev);
	}

	function run() {
		var form = null;
		document.querySelectorAll('form').forEach(function (f) {
			var a = f.querySelector('input[name="action"][value="wp_coa_create_certificate"]');
			if (a) {
				form = f;
			}
		});
		if (!form) {
			return;
		}

		// 1) Selects first (the COA page listens to change on the artist select).
		Object.keys(d.selects || {}).forEach(function (id) {
			var el = document.getElementById(id);
			var v = parseInt(d.selects[id], 10);
			if (el && v > 0) {
				for (var i = 0; i < el.options.length; i++) {
					if (String(el.options[i].value) === String(v)) {
						el.value = String(v);
						fire(el);
						break;
					}
				}
			}
		});

		// 2) Text fields (do not wipe fields the selects already filled).
		Object.keys(d.fields || {}).forEach(function (id) {
			var el = document.getElementById(id);
			var v = d.fields[id];
			if (el && v) {
				el.value = v;
			}
		});

		// 3) Image.
		if (d.imageId) {
			var img = document.getElementById('artwork_image_id');
			var prev = document.getElementById('wp-coa-artwork-image-preview');
			var rm = document.getElementById('wp-coa-remove-artwork-image');
			if (img) {
				img.value = String(d.imageId);
			}
			if (prev && d.imageUrl) {
				prev.innerHTML = '';
				var i = document.createElement('img');
				i.src = d.imageUrl;
				i.alt = '';
				i.style.cssText = 'display:block;max-width:320px;max-height:260px;width:auto;height:auto;border:1px solid #dcdcde;padding:4px;background:#fff';
				prev.appendChild(i);
			}
			if (rm) {
				rm.hidden = false;
			}
		}

		// 4) Link the certificate to this artwork on submit.
		var hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = 'aet_artwork_id';
		hidden.value = String(d.artworkId);
		form.appendChild(hidden);

		// 5) Notice.
		var h1 = document.querySelector('.wrap h1');
		if (h1 && d.banner) {
			var n = document.createElement('div');
			n.className = 'notice notice-info inline';
			var p = document.createElement('p');
			p.textContent = d.banner + ' ';
			if (d.editUrl) {
				var a = document.createElement('a');
				a.href = d.editUrl;
				a.textContent = 'بازگشت به ویرایش اثر';
				p.appendChild(a);
			}
			n.appendChild(p);
			h1.parentNode.insertBefore(n, h1.nextSibling);
		}
		form.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}
})();
