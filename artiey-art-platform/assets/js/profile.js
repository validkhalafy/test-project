/* Artiey Elementor frontend: accessible tabs + resilient AJAX directory filters. */
(function () {
	'use strict';

	var tabSequence = 0;

	function directChildren(parent, className) {
		return Array.prototype.filter.call(parent.children || [], function (child) {
			return child.classList && child.classList.contains(className);
		});
	}

	function tabParts(box) {
		return {
			list: directChildren(box, 'aetp-tablist')[0] || null,
			buttons: directChildren(box, 'aetp-tablist')[0]
				? Array.prototype.filter.call(directChildren(box, 'aetp-tablist')[0].children, function (el) {
					return el.classList && el.classList.contains('aetp-tabbtn');
				})
				: [],
			panels: directChildren(box, 'aetp-tabpanel')
		};
	}

	function isNarrowTabsViewport() {
		return !!(window.matchMedia && window.matchMedia('(max-width: 767px)').matches);
	}

	function applyTabsOrientation(box) {
		if (!box) { return 'horizontal'; }
		var desktop = box.getAttribute('data-tabs-orientation') === 'vertical' ? 'vertical' : 'horizontal';
		var mobile = box.getAttribute('data-tabs-mobile-orientation') || 'horizontal';
		var orientation = isNarrowTabsViewport() && mobile !== 'same' ? (mobile === 'vertical' ? 'vertical' : 'horizontal') : desktop;
		box.classList.toggle('is-vertical', orientation === 'vertical');
		box.classList.toggle('is-horizontal', orientation !== 'vertical');
		var parts = tabParts(box);
		if (parts.list) { parts.list.setAttribute('aria-orientation', orientation); }
		return orientation;
	}

	function matchingPanel(parts, key) {
		return parts.panels.filter(function (panel) {
			return (panel.getAttribute('data-t') || '') === key;
		})[0] || null;
	}

	function activateTab(box, button, focusButton, userInitiated) {
		var parts = tabParts(box);
		if (!button || parts.buttons.indexOf(button) === -1) {
			return false;
		}
		var key = button.getAttribute('data-t') || '';
		var activePanel = matchingPanel(parts, key);
		// A control without a matching panel must never deactivate the visible content.
		if (!activePanel) {
			return false;
		}
		parts.buttons.forEach(function (candidate) {
			var active = candidate === button;
			candidate.classList.toggle('is-active', active);
			candidate.setAttribute('aria-selected', active ? 'true' : 'false');
			candidate.setAttribute('tabindex', active ? '0' : '-1');
		});
		parts.panels.forEach(function (panel) {
			var active = panel === activePanel;
			panel.classList.toggle('is-active', active);
			panel.classList.remove('aetp-tabpanel-animate-fade', 'aetp-tabpanel-animate-slide');
			panel.setAttribute('aria-hidden', active ? 'false' : 'true');
			// `hidden` gives a reliable state even when other Elementor/theme CSS overrides display.
			panel.hidden = !active;
			if (active && userInitiated) {
				var animation = box.getAttribute('data-tabs-animation') || 'none';
				if (animation === 'fade') { panel.classList.add('aetp-tabpanel-animate-fade'); }
				if (animation === 'slide') { panel.classList.add('aetp-tabpanel-animate-slide'); }
			}
		});
		if (focusButton) {
			button.focus();
		}
		if (userInitiated && box.getAttribute('data-tabs-url-hash') === 'yes' && window.history && window.history.replaceState && activePanel.id) {
			try { window.history.replaceState(null, '', window.location.pathname + window.location.search + '#' + activePanel.id); } catch (ignoreHash) { /* Some preview contexts disallow history updates. */ }
		}
		return true;
	}

	function initTabs(scope) {
		var root = scope || document;
		var boxes = [];
		if (root.matches && root.matches('[data-aetp-tabs]')) {
			boxes.push(root);
		}
		if (root.querySelectorAll) {
			Array.prototype.push.apply(boxes, root.querySelectorAll('[data-aetp-tabs]'));
		}
		boxes.forEach(function (box) {
			var parts = tabParts(box);
			if (!parts.buttons.length || !parts.panels.length) {
				return;
			}
			var validButtons = parts.buttons.filter(function (button) {
				return !!matchingPanel(parts, button.getAttribute('data-t') || '');
			});
			if (!validButtons.length) {
				return;
			}
			if (!box.id) {
				tabSequence += 1;
				box.id = 'aetp-tabs-auto-' + tabSequence;
			}
			parts.buttons.forEach(function (button, index) {
				var key = button.getAttribute('data-t') || String(index);
				var panel = matchingPanel(parts, key);
				button.setAttribute('role', 'tab');
				if (!button.id) {
					button.id = box.id + '-tab-' + key.replace(/[^a-z0-9_-]/gi, '-');
				}
				if (panel) {
					button.removeAttribute('aria-disabled');
					panel.setAttribute('role', 'tabpanel');
					if (!panel.id) {
						panel.id = box.id + '-panel-' + key.replace(/[^a-z0-9_-]/gi, '-');
					}
					button.setAttribute('aria-controls', panel.id);
					panel.setAttribute('aria-labelledby', button.id);
				} else {
					button.setAttribute('aria-disabled', 'true');
					button.setAttribute('tabindex', '-1');
				}
			});
			if (parts.list) {
				parts.list.setAttribute('role', 'tablist');
			}
			// Mark as enhanced only after controls and panels are connected.
			var alreadyEnhanced = box.classList.contains('js');
			box.classList.add('js');
			applyTabsOrientation(box);
			var active = null;
			// A repeated init (Elementor re-render, DOM mutation inside a panel) must never
			// throw the visitor back to the default/hash tab: keep the tab that is open now.
			if (alreadyEnhanced) {
				active = validButtons.filter(function (button) {
					return button.classList.contains('is-active');
				})[0] || null;
				if (active) {
					activateTab(box, active, false, false);
					return;
				}
			}
			if (box.getAttribute('data-tabs-url-hash') === 'yes' && window.location.hash) {
				var currentHash = window.location.hash.slice(1);
				try { currentHash = decodeURIComponent(currentHash); } catch (ignoreDecode) { /* Use raw hash when malformed. */ }
				active = validButtons.filter(function (button) {
					var panel = matchingPanel(parts, button.getAttribute('data-t') || '');
					return button.id === currentHash || (panel && panel.id === currentHash) || (button.getAttribute('data-t') || '') === currentHash;
				})[0] || null;
			}
			if (!active && box.getAttribute('data-tabs-default') === 'last') {
				active = validButtons[validButtons.length - 1];
			}
			if (!active) {
				active = validButtons.filter(function (button) {
					return button.classList.contains('is-active') || button.getAttribute('aria-selected') === 'true';
				})[0] || validButtons[0];
			}
			activateTab(box, active, false, false);
		});
	}

	function observeDynamicTabs() {
		if (!window.MutationObserver || !document.documentElement || document.documentElement._aetpTabsObserver) {
			return;
		}
		var observer = new MutationObserver(function (mutations) {
			var candidates = [];
			var seen = [];
			function add(box) {
				// Elementor may replace the tab buttons/panels inside an already-enhanced
				// container. Reconcile it too; initTabs preserves the active key when possible.
				if (box && seen.indexOf(box) === -1) {
					seen.push(box);
					candidates.push(box);
				}
			}
			mutations.forEach(function (mutation) {
				Array.prototype.forEach.call(mutation.addedNodes || [], function (node) {
					if (!node || node.nodeType !== 1) { return; }
					if (node.matches && node.matches('[data-aetp-tabs]')) { add(node); }
					if (node.querySelectorAll) {
						Array.prototype.forEach.call(node.querySelectorAll('[data-aetp-tabs]'), add);
					}
					if (node.closest) { add(node.closest('[data-aetp-tabs]')); }
				});
			});
			candidates.forEach(initTabs);
		});
		document.documentElement._aetpTabsObserver = observer;
		observer.observe(document.documentElement, { childList: true, subtree: true });
	}

	function bindElementorTabs() {
		if (!window.elementorFrontend || !window.elementorFrontend.hooks || bindElementorTabs.bound) {
			return;
		}
		bindElementorTabs.bound = true;
		try {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
				initTabs($scope && $scope[0] ? $scope[0] : document);
			});
		} catch (ignore) { /* MutationObserver remains the fallback for Elementor re-renders. */ }
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('.aetp-tabbtn') : null;
		if (!button) {
			return;
		}
		var box = button.closest('[data-aetp-tabs]');
		if (!box) {
			return;
		}
		// Elementor editor/preview can insert widgets after DOMContentLoaded.
		// Initialize on demand so a click never merely toggles classes on an unenhanced tab set.
		if (!box.classList.contains('js')) { initTabs(box); }
		activateTab(box, button, false, true);
	});

	document.addEventListener('keydown', function (event) {
		var button = event.target.closest ? event.target.closest('.aetp-tabbtn') : null;
		if (!button) { return; }
		var box = button.closest('[data-aetp-tabs]');
		if (!box) { return; }
		if (!box.classList.contains('js')) { initTabs(box); }
		var orientation = applyTabsOrientation(box);
		var allowedKeys = orientation === 'vertical' ? ['ArrowUp', 'ArrowDown', 'Home', 'End'] : ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
		if (allowedKeys.indexOf(event.key) === -1) { return; }
		var buttons = tabParts(box).buttons;
		var index = buttons.indexOf(button);
		if (index < 0 || !buttons.length) { return; }
		if (event.key === 'Home') {
			index = 0;
		} else if (event.key === 'End') {
			index = buttons.length - 1;
		} else {
			var forward = orientation === 'vertical' ? event.key === 'ArrowDown' : event.key === 'ArrowRight';
			var dir = window.getComputedStyle ? window.getComputedStyle(box).direction : 'ltr';
			if (orientation === 'horizontal' && dir === 'rtl') { forward = !forward; }
			index = forward ? (index + 1) % buttons.length : (index - 1 + buttons.length) % buttons.length;
		}
		event.preventDefault();
		activateTab(box, buttons[index], true, true);
	});

	window.addEventListener('resize', function () {
		document.querySelectorAll('[data-aetp-tabs].js').forEach(applyTabsOrientation);
	});

	/* ---------- Directory AJAX ---------- */
	function collect(form) {
		var values = [];
		var fd = new FormData(form);
		fd.forEach(function (value, key) {
			if (key.indexOf('aet_') === 0 && String(value) !== '') {
				values.push([key, String(value)]);
			}
		});
		return values;
	}

	function valuesFromUrl(url) {
		var values = [];
		try {
			new URL(url, window.location.href).searchParams.forEach(function (value, key) {
				if (key.indexOf('aet_') === 0 && value !== '') {
					values.push([key, value]);
				}
			});
		} catch (ignore) { /* invalid URLs are ignored; callers retain the current result */ }
		return values;
	}

	function encodePairs(pairs) {
		var query = new URLSearchParams();
		pairs.forEach(function (pair) {
			if (pair && pair.length > 1 && String(pair[1]) !== '') {
				query.append(String(pair[0]), String(pair[1]));
			}
		});
		return query.toString();
	}

	function updateHistory(pairs, replace) {
		if (!window.history || !window.history.pushState) {
			return;
		}
		var url = new URL(window.location.href);
		Array.from(url.searchParams.keys()).forEach(function (key) {
			if (key.indexOf('aet_') === 0) {
				url.searchParams.delete(key);
			}
		});
		pairs.forEach(function (pair) {
			url.searchParams.set(pair[0], pair[1]);
		});
		if (replace) {
			window.history.replaceState(null, '', url.toString());
		} else {
			window.history.pushState(null, '', url.toString());
		}
	}

	function liveStatus(root) {
		var status = root.querySelector('.aetd-live-status');
		if (!status) {
			status = document.createElement('span');
			status.className = 'aetd-live-status screen-reader-text';
			status.setAttribute('role', 'status');
			status.setAttribute('aria-live', 'polite');
			root.insertBefore(status, root.firstChild);
		}
		return status;
	}

	function load(root, pairs, replaceHistory) {
		var rest = root.getAttribute('data-rest') || '';
		var args = root.getAttribute('data-args') || '{}';
		if (!rest) {
			return Promise.resolve(false);
		}
		if (root._aetRequest && root._aetRequest.controller) {
			root._aetRequest.controller.abort();
		}
		root._aetSequence = (root._aetSequence || 0) + 1;
		var sequence = root._aetSequence;
		var controller = window.AbortController ? new AbortController() : null;
		root._aetRequest = { sequence: sequence, controller: controller };
		var status = liveStatus(root);
		status.textContent = 'در حال به‌روزرسانی نتایج…';
		root.setAttribute('data-busy', '1');
		root.setAttribute('aria-busy', 'true');
		root.classList.add('aetd-loading');

		var query = 'args=' + encodeURIComponent(args);
		var encodedPairs = encodePairs(pairs);
		if (encodedPairs) {
			query += '&' + encodedPairs;
		}
		var separator = rest.indexOf('?') > -1 ? '&' : '?';

		return fetch(rest + separator + query, {
			credentials: 'same-origin',
			signal: controller ? controller.signal : undefined,
			headers: { 'Accept': 'application/json' }
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				return response.json();
			})
			.then(function (data) {
				if (!root._aetRequest || root._aetRequest.sequence !== sequence) {
					return false;
				}
				var inner = root.querySelector('.aetd-inner');
				if (!inner || !data || typeof data.html !== 'string') {
					throw new Error('Invalid directory response');
				}
				inner.innerHTML = data.html;
				initTabs(inner);
				root.removeAttribute('data-error');
				status.textContent = 'نتایج به‌روزرسانی شدند.';
				updateHistory(pairs, replaceHistory);
				return true;
			})
			.catch(function (error) {
				if (error && error.name === 'AbortError') {
					return false;
				}
				if (!root._aetRequest || root._aetRequest.sequence !== sequence) {
					return false;
				}
				root.setAttribute('data-error', '1');
				status.textContent = 'به‌روزرسانی نتایج انجام نشد. اتصال را بررسی کنید و دوباره تلاش کنید.';
				if (window.console && typeof window.console.warn === 'function') {
					window.console.warn('Artiey directory AJAX failed.', error);
				}
				return false;
			})
			.then(function (result) {
				if (!root._aetRequest || root._aetRequest.sequence !== sequence) {
					return result;
				}
				root._aetRequest = null;
				root.removeAttribute('data-busy');
				root.removeAttribute('aria-busy');
				root.classList.remove('aetd-loading');
				return result;
			});
	}

	document.addEventListener('submit', function (event) {
		var form = event.target.closest ? event.target.closest('.aetd-filters') : null;
		if (!form) {
			return;
		}
		var root = form.closest('.aetd');
		if (!root || root.getAttribute('data-ajax') !== 'yes') {
			return;
		}
		event.preventDefault();
		load(root, collect(form), false);
	});

	document.addEventListener('change', function (event) {
		var form = event.target.closest ? event.target.closest('.aetd-filters') : null;
		if (!form || event.target.type === 'search') {
			return;
		}
		var root = form.closest('.aetd');
		if (root && root.getAttribute('data-ajax') === 'yes') {
			load(root, collect(form), false);
		}
	});

	document.addEventListener('input', function (event) {
		var target = event.target;
		if (!target || target.type !== 'search') {
			return;
		}
		var form = target.closest('.aetd-filters');
		var root = form ? form.closest('.aetd') : null;
		if (!root || root.getAttribute('data-ajax') !== 'yes') {
			return;
		}
		window.clearTimeout(form._aetSearchTimer);
		form._aetSearchTimer = window.setTimeout(function () {
			load(root, collect(form), true);
		}, 400);
	});

	document.addEventListener('click', function (event) {
		var link = event.target.closest ? event.target.closest('.aetd-pager a, .aetd-reset') : null;
		if (!link) {
			return;
		}
		var root = link.closest('.aetd');
		if (!root || root.getAttribute('data-ajax') !== 'yes') {
			return;
		}
		event.preventDefault();
		load(root, valuesFromUrl(link.href), false);
		if (root.scrollIntoView) {
			root.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	});

	window.addEventListener('popstate', function () {
		var pairs = valuesFromUrl(window.location.href);
		document.querySelectorAll('.aetd[data-ajax="yes"]').forEach(function (root) {
			load(root, pairs, true);
		});
	});

	function boot() {
		initTabs(document);
		observeDynamicTabs();
		bindElementorTabs();
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
	// Elementor may be initialized after this asset depending on optimization/defer settings.
	if (window.jQuery && window.jQuery.fn) {
		window.jQuery(window).on('elementor/frontend/init', bindElementorTabs);
	}
})();
