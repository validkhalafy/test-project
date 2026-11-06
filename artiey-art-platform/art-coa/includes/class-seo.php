<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Seo
{
    public static function init(): void
    {
        add_action('wp_head', [self::class, 'output_meta'], 1);
        add_filter('document_title_parts', [self::class, 'title_parts']);
        add_action('wp_head', [self::class, 'json_ld'], 5);
    }

    public static function is_coa_page(): bool
    {
        return (bool) get_query_var('art_coa_type');
    }

    public static function title_parts(array $parts): array
    {
        $type = get_query_var('art_coa_type');
        $slug = sanitize_title((string) get_query_var('art_coa_slug'));
        if (!$type) {
            return $parts;
        }

        $title = match ($type) {
            'artist' => self::entity_title('artists', $slug, 'display_name', 'هنرمند'),
            'curator' => self::entity_title('curators', $slug, 'display_name', 'کیوریتور'),
            'gallery' => self::entity_title('galleries', $slug, 'name', 'گالری'),
            'exhibition' => self::entity_title('exhibitions', $slug, 'title', 'نمایشگاه'),
            'artwork' => self::entity_title('artworks', $slug, 'title', 'اثر'),
            'collector' => self::entity_title('collectors', $slug, 'display_name', 'مجموعه‌دار'),
            'certificate' => 'گواهی اصالت ' . $slug,
            'artists_list' => 'هنرمندان',
            'curators_list' => 'کیوریتورها',
            'galleries_list' => 'گالری‌ها',
            'exhibitions_list' => 'نمایشگاه‌ها',
            'artworks_list' => 'آثار هنری',
            default => $parts['title'] ?? 'Art COA',
        };

        $parts['title'] = $title;
        $parts['site'] = get_bloginfo('name');
        return $parts;
    }

    private static function entity_title(string $table, string $slug, string $field, string $prefix): string
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table($table) . ' WHERE slug = %s LIMIT 1',
            $slug
        ));
        if (!$row) {
            return $prefix;
        }
        $name = $row->{$field} ?? '';
        if ($table === 'artists' && $name === '') {
            $name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
        }
        return $prefix . ' ' . $name;
    }

    public static function output_meta(): void
    {
        $settings = get_option('wp_art_coa_settings', []);
        if (isset($settings['enable_seo']) && empty($settings['enable_seo'])) {
            return;
        }
        if (!self::is_coa_page()) {
            return;
        }

        $type = get_query_var('art_coa_type');
        $slug = sanitize_title((string) get_query_var('art_coa_slug'));
        $url  = self::canonical_url($type, $slug);
        $desc = self::description($type, $slug);
        $title = wp_get_document_title();
        $image = self::og_image($type, $slug);

        echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";
        echo '<meta name="description" content="' . esc_attr(wp_strip_all_tags($desc)) . '" />' . "\n";
        $filtered = false;
        foreach (array_keys($_GET) as $k) { // phpcs:ignore WordPress.Security.NonceVerification
            if (strpos((string) $k, 'ac_') === 0) {
                $filtered = true;
                break;
            }
        }
        echo '<meta name="robots" content="' . ($filtered ? 'noindex, follow' : 'index, follow, max-image-preview:large') . '" />' . "\n";

        // Open Graph
        echo '<meta property="og:type" content="website" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr(wp_strip_all_tags($desc)) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";
        echo '<meta property="og:locale" content="fa_IR" />' . "\n";
        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
        }

        // Twitter
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr(wp_strip_all_tags($desc)) . '" />' . "\n";
        if ($image) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '" />' . "\n";
        }
    }

    public static function json_ld(): void
    {
        $settings = get_option('wp_art_coa_settings', []);
        if (isset($settings['enable_seo']) && empty($settings['enable_seo'])) {
            return;
        }
        if (!self::is_coa_page()) {
            return;
        }

        $type = get_query_var('art_coa_type');
        $slug = sanitize_title((string) get_query_var('art_coa_slug'));
        $data = self::schema($type, $slug);
        if (!$data) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    private static function schema(string $type, string $slug): ?array
    {
        $url = self::canonical_url($type, $slug);
        global $wpdb;

        return match ($type) {
            'artist' => self::schema_person($slug, $url),
            'curator' => self::schema_curator($slug, $url),
            'gallery' => self::schema_organization($slug, $url),
            'exhibition' => self::schema_event($slug, $url),
            'artwork' => self::schema_artwork($slug, $url),
            'certificate' => self::schema_certificate($slug, $url),
            'artists_list', 'curators_list', 'galleries_list', 'exhibitions_list', 'artworks_list' => [
                '@context' => 'https://schema.org',
                '@type'    => 'CollectionPage',
                'name'     => wp_get_document_title(),
                'url'      => $url,
            ],
            default => null,
        };
    }

    /** Merge the extended profile properties (social sameAs, birth date, geo, hours…) into a JSON-LD node. */
    private static function with_profile(array $data, string $table, object $row): array
    {
        $extra = ProfileFields::schema_extra($table, $row);
        if (isset($extra['sameAs'], $data['sameAs'])) {
            $extra['sameAs'] = array_values(array_unique(array_merge((array) $data['sameAs'], (array) $extra['sameAs'])));
        }
        return array_merge($data, $extra);
    }

    private static function schema_person(string $slug, string $url): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('artists') . ' WHERE slug = %s',
            $slug
        ));
        if (!$row) {
            return null;
        }
        $name = $row->display_name ?: trim($row->first_name . ' ' . $row->last_name);
        $data = [
            '@context' => 'https://schema.org',
            '@type'    => 'Person',
            'name'     => $name,
            'url'      => $url,
            'jobTitle' => 'Artist',
        ];
        if ($row->bio) {
            $data['description'] = wp_strip_all_tags($row->bio);
        }
        if ($img = Media::url($row, 'avatar', 'large')) {
            $data['image'] = $img;
        }
        if ($row->website) {
            $data['sameAs'] = [$row->website];
        }
        $data = self::with_profile($data, 'artists', $row);
        return $data;
    }

    private static function schema_curator(string $slug, string $url): ?array
    {
        $row = \WPArtCOA\Entities\Curator::get_by_slug($slug);
        if (!$row || $row->status !== 'active') {
            return null;
        }
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => (string) $row->display_name,
            'jobTitle' => I18n::is_fa() ? 'کیوریتور هنری' : 'Art curator',
            'url' => $url,
        ];
        $description = $row->curatorial_statement ?: $row->bio;
        if ($description) {
            $data['description'] = wp_strip_all_tags($description);
        }
        if ($row->affiliation) {
            $data['worksFor'] = ['@type' => 'Organization', 'name' => $row->affiliation];
        }
        if ($row->email) {
            $data['email'] = 'mailto:' . $row->email;
        }
        if ($row->phone) {
            $data['telephone'] = $row->phone;
        }
        if ($img = Media::url($row, 'avatar', 'large')) {
            $data['image'] = $img;
        }
        $same_as = array_values(array_filter([$row->website, $row->instagram_url, $row->linkedin_url]));
        if ($same_as) {
            $data['sameAs'] = $same_as;
        }
        $focus = preg_split('/[,،;\n]+/u', (string) $row->specialties, -1, PREG_SPLIT_NO_EMPTY);
        if ($focus) {
            $data['knowsAbout'] = array_values(array_map('trim', $focus));
        }
        return $data;
    }

    private static function schema_organization(string $slug, string $url): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('galleries') . ' WHERE slug = %s',
            $slug
        ));
        if (!$row) {
            return null;
        }
        $data = [
            '@context' => 'https://schema.org',
            '@type'    => 'ArtGallery',
            'name'     => $row->name,
            'url'      => $url,
        ];
        if ($row->about) {
            $data['description'] = wp_strip_all_tags($row->about);
        }
        if ($img = Media::url($row, 'logo', 'large')) {
            $data['image'] = $img;
        }
        if ($row->address || $row->city) {
            $data['address'] = [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $row->address ?: '',
                'addressLocality' => $row->city ?: '',
                'addressCountry'  => $row->country ?: 'IR',
            ];
        }
        $data = self::with_profile($data, 'galleries', $row);
        return $data;
    }

    private static function schema_event(string $slug, string $url): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT e.*, g.name AS gallery_name FROM ' . Database::table('exhibitions') . ' e
             LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = e.gallery_id
             WHERE e.slug = %s',
            $slug
        ));
        if (!$row) {
            return null;
        }
        $data = [
            '@context'  => 'https://schema.org',
            '@type'     => 'ExhibitionEvent',
            'name'      => $row->title,
            'url'       => $url,
            'startDate' => $row->start_date,
        ];
        if ($row->end_date) {
            $data['endDate'] = $row->end_date;
        }
        if ($row->description) {
            $data['description'] = wp_strip_all_tags($row->description);
        }
        if ($img = Media::url($row, 'poster', 'large')) {
            $data['image'] = $img;
        }
        if ($row->gallery_name) {
            $data['location'] = [
                '@type' => 'Place',
                'name'  => $row->gallery_name,
            ];
        }
        $data = self::with_profile($data, 'exhibitions', $row);
        return $data;
    }

    private static function schema_artwork(string $slug, string $url): ?array
    {
        $row = \WPArtCOA\Entities\Artwork::get_by_slug($slug);
        if (!$row) {
            return null;
        }
        $data = [
            '@context' => 'https://schema.org',
            '@type'    => 'VisualArtwork',
            'name'     => $row->title,
            'url'      => $url,
        ];
        if ($name = Catalog::artist_name($row)) {
            $data['creator'] = ['@type' => 'Person', 'name' => $name];
        }
        if ($row->description) {
            $data['description'] = wp_strip_all_tags($row->description);
        }
        if ($img = Media::url($row, 'image', 'large')) {
            $data['image'] = $img;
        }
        if ($row->medium) {
            $data['artMedium'] = $row->medium;
        }
        if ($row->year_created) {
            $data['dateCreated'] = (string) $row->year_created;
        }
        $data = self::with_profile($data, 'artworks', $row);
        return $data;
    }

    private static function schema_certificate(string $serial, string $url): ?array
    {
        $result = \WPArtCOA\Entities\Certificate::verify($serial);
        if (empty($result['valid'])) {
            return [
                '@context' => 'https://schema.org',
                '@type'    => 'DigitalDocument',
                'name'     => 'Certificate ' . $serial,
                'url'      => $url,
            ];
        }
        $d = $result['data'];
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'DigitalDocument',
            'name'        => 'گواهی اصالت ' . $d['serial_number'],
            'identifier'  => $d['serial_number'],
            'url'         => $url,
            'about'       => [
                '@type'  => 'VisualArtwork',
                'name'   => $d['artwork_title'],
                'creator'=> ['@type' => 'Person', 'name' => $d['artist_name']],
            ],
            'dateCreated' => $d['issued_at_utc'],
        ];
    }

    private static function description(string $type, string $slug): string
    {
        $site = get_bloginfo('name');
        return match ($type) {
            'artist' => 'پروفایل هنرمند در ' . $site . ' — آثار، گواهی اصالت و نمایشگاه‌ها.',
            'curator' => 'پروفایل کیوریتور در ' . $site . ' — رویکرد حرفه‌ای و نمایشگاه‌های کیوریت‌شده.',
            'gallery' => 'گالری هنری در ' . $site . ' — نمایشگاه‌ها، هنرمندان و آثار.',
            'exhibition' => 'جزئیات نمایشگاه و آثار ارائه‌شده در ' . $site . '.',
            'artwork' => 'مشخصات اثر هنری، هنرمند و گواهی اصالت در ' . $site . '.',
            'certificate' => 'تأیید گواهی اصالت اثر هنری — سریال ' . $slug,
            'artists_list' => 'فهرست هنرمندان ' . $site,
            'curators_list' => 'فهرست کیوریتورهای هنری ' . $site,
            'galleries_list' => 'فهرست گالری‌های ' . $site,
            'exhibitions_list' => 'تقویم و فهرست نمایشگاه‌های ' . $site,
            'artworks_list' => 'مجموعه آثار هنری ' . $site,
            default => $site,
        };
    }

    private static function og_image(string $type, string $slug): string
    {
        global $wpdb;
        $map = [
            'artist'     => ['artists', 'avatar'],
            'curator'    => ['curators', 'avatar'],
            'gallery'    => ['galleries', 'logo'],
            'exhibition' => ['exhibitions', 'poster'],
            'artwork'    => ['artworks', 'image'],
        ];
        if (!isset($map[$type])) {
            return '';
        }
        [$table, $field] = $map[$type];
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table($table) . ' WHERE slug = %s LIMIT 1',
            $slug
        ));
        if (!$row) {
            return '';
        }
        // Galleries: prefer their cover over the (often tiny) logo for social previews.
        if ($type === 'gallery' && ($cover = Media::url($row, 'cover', 'large'))) {
            return $cover;
        }
        return Media::url($row, $field, 'large');
    }

    public static function canonical_url(string $type, string $slug = ''): string
    {
        $map = [
            'artists_list'     => '/artists/',
            'curators_list'    => '/art-coa/curators',
            'galleries_list'   => '/art-coa/galleries',
            'exhibitions_list' => '/art-coa/exhibitions',
            'artworks_list'    => '/art-coa/artworks',
            'artist'           => '/artists/' . rawurlencode($slug),
            'curator'          => '/art-coa/curator/' . $slug,
            'gallery'          => '/art-coa/gallery/' . $slug,
            'exhibition'       => '/art-coa/exhibition/' . $slug,
            'artwork'          => '/art-coa/artwork/' . $slug,
            'collector'        => '/art-coa/collector/' . $slug,
            'certificate'      => '/art-coa/certificate/' . rawurlencode($slug),
        ];
        if ($type === 'artists_list') {
            return ContentSync::artist_archive_url();
        }
        if ($type === 'artist') {
            $canonical = $slug !== '' ? ContentSync::artist_url($slug) : ContentSync::artist_archive_url();
            return $canonical !== '' ? $canonical : home_url('/artists/');
        }
        // Content created as a post (Elementor / classic editor) keeps its own permalink as the canonical one.
        $tables = ['curator' => 'curators', 'gallery' => 'galleries', 'exhibition' => 'exhibitions', 'artwork' => 'artworks'];
        if ($slug !== '' && isset($tables[$type])) {
            $own = ContentSync::permalink($tables[$type], $slug);
            if ($own !== '') {
                return $own;
            }
        }
        return home_url($map[$type] ?? '/');
    }
}
