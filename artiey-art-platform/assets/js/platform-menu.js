(function () {
    'use strict';

    function getMenuKey(anchor) {
        try {
            var url = new URL(anchor.href, window.location.href);
            var page = url.searchParams.get('page');
            if (page) return 'page:' + page;

            var taxonomy = url.searchParams.get('taxonomy');
            if (taxonomy) return 'taxonomy:' + taxonomy;

            var postType = url.searchParams.get('post_type');
            var filename = url.pathname.split('/').pop();
            if (postType && filename === 'edit.php') return 'post_type:' + postType;
            if (postType && filename === 'post-new.php') return 'new_post_type:' + postType;

            var pairs = Array.from(url.searchParams.entries());
            pairs.sort(function (a, b) {
                if (a[0] !== b[0]) return a[0].localeCompare(b[0]);
                return a[1].localeCompare(b[1]);
            });
            var query = pairs.map(function (pair) {
                return encodeURIComponent(pair[0]) + '=' + encodeURIComponent(pair[1]);
            }).join('&');
            return 'url:' + filename + (query ? '?' + query : '');
        } catch (error) {
            return '';
        }
    }

    function inferSection(key) {
        if (/^(post_type|new_post_type|taxonomy):/.test(key)) return 'content';
        if (key === 'page:wp-art-coa-auctions' || key === 'page:wp-art-coa-calls') return 'market';
        if (/^page:(wp-art-coa|wp-coa-pro)(-|$)/.test(key)) return 'certificates';
        if (key === 'page:artiey-aet-settings') return 'settings';
        return 'other';
    }

    function initialise() {
        var config = window.ArtieyPlatformMenu;
        if (!config || !config.rootId) return;

        var root = document.getElementById(config.rootId);
        var submenu = root ? root.querySelector(':scope > ul.wp-submenu') : null;
        if (!submenu || submenu.dataset.artieyOrganized === '1') return;
        submenu.dataset.artieyOrganized = '1';

        var entries = Array.from(submenu.children).filter(function (li) {
            return li.tagName === 'LI' && li.querySelector(':scope > a');
        });
        var sectionMap = config.sectionMap || {};
        var sections = config.sectionLabels || {};
        var currentSection = '';
        entries.forEach(function (li) {
            var anchor = li.querySelector(':scope > a');
            var key = getMenuKey(anchor);
            var section = sectionMap[key] || inferSection(key);
            if (key === config.dashboardKey || section === 'dashboard') {
                li.dataset.artieyMenuSection = 'dashboard';
                return;
            }
            if (!sections[section]) section = 'other';
            li.dataset.artieyMenuSection = section;
            if (li.classList.contains('current') || anchor.classList.contains('current') || li.querySelector('.current')) {
                currentSection = section;
            }
        });

        var groups = {};
        entries.forEach(function (li) {
            var section = li.dataset.artieyMenuSection;
            if (!section || section === 'dashboard') return;
            if (!groups[section]) groups[section] = [];
            groups[section].push(li);
        });

        var lastSection = '';
        entries.forEach(function (li) {
            var section = li.dataset.artieyMenuSection;
            if (!section || section === 'dashboard') {
                lastSection = '';
                return;
            }
            if (section !== lastSection) {
                var heading = document.createElement('li');
                heading.className = 'artiey-menu-section-heading';
                heading.dataset.artieySectionHeading = section;

                var toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'artiey-menu-section-toggle';

                var title = document.createElement('span');
                title.className = 'artiey-menu-section-title';
                title.textContent = sections[section] || 'سایر ابزارهای آرتی';

                var caret = document.createElement('span');
                caret.className = 'artiey-menu-section-caret';
                caret.setAttribute('aria-hidden', 'true');
                caret.textContent = '⌄';

                toggle.appendChild(title);
                toggle.appendChild(caret);
                heading.appendChild(toggle);
                submenu.insertBefore(heading, li);
                lastSection = section;
            }
        });

        Object.keys(groups).forEach(function (section) {
            var heading = submenu.querySelector('[data-artiey-section-heading="' + section + '"]');
            if (!heading) return;
            var button = heading.querySelector('button');
            var collapsed = section !== 'content' && section !== currentSection;

            function setCollapsed(nextState) {
                collapsed = Boolean(nextState);
                button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                heading.classList.toggle('is-collapsed', collapsed);
                var caret = button.querySelector('.artiey-menu-section-caret');
                if (caret) caret.textContent = collapsed ? '›' : '⌄';
                groups[section].forEach(function (li) {
                    li.style.display = collapsed ? 'none' : '';
                });
            }

            button.addEventListener('click', function (event) {
                event.preventDefault();
                setCollapsed(!collapsed);
            });
            setCollapsed(collapsed);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
})();
