# Artiey Art Platform 3.0.4

## Cache and relationship consistency

- Statistics cache generations now advance when platform entity relationship metadata changes, including added, updated, and deleted post meta.
- Statistics cache generations now advance when a platform entity is deleted.
- Repeated invalidation hooks in a single request are coalesced to one generation bump.
- Page-cache integrations receive targeted post purges for saved/deleted/status-changed WordPress pages and Artiey entities where their public APIs are available.
- A stable `artiey_aet_purge_post_cache` action is emitted for custom host/CDN integrations.
- No automatic full-domain cache purge is performed for each entity save.

## Compatibility and verification

- Plugin header and runtime version are 3.0.4.
- Existing post types, relationship storage, Elementor layouts, and public URLs are unchanged.
- The included standalone PHP regression suite is run as part of this release check.

## Operational limitation

A WordPress plugin cannot invalidate a host-level reverse proxy or CDN unless the host exposes an integration/API. The new post-level action allows a site-specific integration; verify host/CDN purge behavior in the live environment.
