# Artiey Art Platform 2.32.17

## Elementor single and archive system

- Kept the four native public, REST-enabled WordPress content types (`artiey_artist`, `artiey_gallery`, `artiey_artwork`, `artiey_exhibition`) editable with Elementor and exposed custom fields to Elementor dynamic tags.
- Preserved `/artists/` as the canonical, ordinary WordPress page archive; it remains editable directly with Elementor and continues to use the Artiey artist-directory widget/shortcode. Artist singles remain canonical at `/artists/{slug}/`.
- Native gallery, artwork and exhibition archives remain native WordPress archives (`/galleries/`, `/artworks/`, `/exhibitions/` by default); Single/Archive design conditions are managed in Elementor Pro Theme Builder. The `artiey_style` taxonomy remains public and REST-enabled for style archive templates.
- Added an Elementor layout assignment center under **پلتفرم آرتی → قالب‌های صفحات Elementor**. It groups and assigns saved Elementor Library templates for all database-backed COA single/archive routes: artist, curator, gallery, exhibition, collector, artwork, certificate, directories, auction, calls, portfolio, shop and secondary market.
- Added the **محتوای صفحهٔ جاری Artiey** Elementor widget and `[artiey_route_content]` shortcode. It allows a custom layout to place the native privacy-aware page output at any point in the design. If a custom layout omits this widget, the trusted native page output is appended as a compatibility fallback.
- Added native single-page context detection using the active Elementor document/preview ID. Relationship widgets can inherit the current post type and slug for native WP singles as well as custom Art COA routes.
- Connected current context to artist biography, hierarchy, portfolio, auction timeline, shop and artwork-history widgets. Explicit IDs/slugs selected in a widget continue to take priority over automatic current-page detection.
- Artwork-history widget can now show an individual artwork's timeline on its single page or the current artist's work history on an artist profile. Shop widgets inherit the current artist/gallery filter when no explicit filter is selected.
- Redesigned the layout manager admin screen with grouped route cards, a library shortcut and a relationship guide.

## Relationship and privacy behavior

- Existing renderers remain the source of truth for entity records and relational queries; no duplicate relationship dataset is created.
- Artist relations include artworks, galleries, exhibitions, curators, auctions, certificates and provenance/history where data is actually registered.
- Gallery relations include artists, artworks, exhibitions, auctions and portfolio/shop content where records are linked.
- Exhibition/curator and auction/artwork relations use registered entity IDs/slugs and existing route helpers, not fuzzy name matching.
- Collector ownership is displayed only through existing public-safe renderers; private owner/buyer details are not exposed by the template assignment layer.
- If a custom Elementor template is not assigned, the existing native page output remains unchanged.

## Design setup

- For native CPT singles/archives, create Single/Archive templates in **Elementor → Theme Builder** and apply display conditions to the relevant post type. Elementor Pro is required for Theme Builder display conditions.
- Design `/artists/` by editing the canonical page with Elementor and placing the Artiey Directory widget where the artist listing should appear.
- For database-backed COA routes, create and publish a template in the Elementor Library, then assign it in **پلتفرم آرتی → قالب‌های صفحات Elementor**. Place **محتوای صفحهٔ جاری Artiey** where the existing route content should appear; use additional COA widgets for relationship modules.
- Older WP COA person profiles continue to render through the existing managed profile page and its Elementor-compatible shortcode/widget integration.
