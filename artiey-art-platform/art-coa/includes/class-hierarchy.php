<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Builds hierarchical trees between galleries, artists, artworks, collectors, buyers.
 */
final class Hierarchy
{
    /**
     * Full gallery tree:
     * Gallery → Artists → Artworks → Certificates / Current owner
     *         → Exhibitions → Artworks
     */
    public static function gallery_tree(int $gallery_id): array
    {
        global $wpdb;
        $g = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('galleries') . ' WHERE id = %d',
            $gallery_id
        ));
        if (!$g) {
            return [];
        }

        // One query per level (artists, artworks, certificates, exhibitions, linked works)
        // instead of one query per artist / artwork / exhibition.
        $artists = $wpdb->get_results($wpdb->prepare(
            'SELECT DISTINCT ar.* FROM ' . Database::table('artists') . ' ar
             INNER JOIN ' . Database::table('artworks') . ' a ON a.artist_id = ar.id
             WHERE a.gallery_id = %d ORDER BY ar.display_name ASC',
            $gallery_id
        )) ?: [];

        $artworks = $wpdb->get_results($wpdb->prepare(
            'SELECT a.* FROM ' . Database::table('artworks') . ' a
             WHERE a.gallery_id = %d ORDER BY a.id DESC',
            $gallery_id
        )) ?: [];

        self::prefetch_owners($artworks);
        $certs_by_artwork = [];
        $artwork_ids = array_values(array_unique(array_map(static fn($aw): int => (int) $aw->id, $artworks)));
        if ($artwork_ids) {
            $placeholders = implode(',', array_fill(0, count($artwork_ids), '%d'));
            $cert_rows = $wpdb->get_results($wpdb->prepare(
                'SELECT artwork_id, serial_number, status, issued_to_name, issued_at_utc
                 FROM ' . Database::table('certificates') . ' WHERE artwork_id IN (' . $placeholders . ') ORDER BY id DESC',
                ...$artwork_ids
            )) ?: [];
            foreach ($cert_rows as $row) {
                $certs_by_artwork[(int) $row->artwork_id][] = $row;
            }
        }

        $works_by_artist = [];
        foreach ($artworks as $aw) {
            $works_by_artist[(int) $aw->artist_id][] = [
                'id'     => (int) $aw->id,
                'title'  => $aw->title,
                'year'   => $aw->year_created,
                'medium' => $aw->medium,
                'status' => $aw->status,
                'owner'  => self::owner_label($aw),
                'certs'  => $certs_by_artwork[(int) $aw->id] ?? [],
            ];
        }

        $artist_nodes = [];
        foreach ($artists as $ar) {
            $artist_nodes[] = [
                'id'       => (int) $ar->id,
                'name'     => $ar->display_name ?: trim($ar->first_name . ' ' . $ar->last_name),
                'slug'     => $ar->slug,
                'email'    => $ar->email,
                'artworks' => $works_by_artist[(int) $ar->id] ?? [],
            ];
        }

        $exhibitions = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Database::table('exhibitions') . ' WHERE gallery_id = %d ORDER BY start_date DESC',
            $gallery_id
        )) ?: [];

        $linked_by_exhibition = [];
        $exhibition_ids = array_values(array_unique(array_map(static fn($ex): int => (int) $ex->id, $exhibitions)));
        if ($exhibition_ids) {
            $placeholders = implode(',', array_fill(0, count($exhibition_ids), '%d'));
            $linked_rows = $wpdb->get_results($wpdb->prepare(
                'SELECT ea.exhibition_id, a.id, a.title, a.status, ar.display_name, ar.slug AS artist_slug, ar.first_name, ar.last_name
                 FROM ' . Database::table('exhibition_artworks') . ' ea
                 INNER JOIN ' . Database::table('artworks') . ' a ON a.id = ea.artwork_id
                 LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
                 WHERE ea.exhibition_id IN (' . $placeholders . ')',
                ...$exhibition_ids
            )) ?: [];
            foreach ($linked_rows as $row) {
                $linked_by_exhibition[(int) $row->exhibition_id][] = $row;
            }
        }

        $ex_nodes = [];
        foreach ($exhibitions as $ex) {
            $ex_nodes[] = [
                'id'       => (int) $ex->id,
                'title'    => $ex->title,
                'slug'     => $ex->slug,
                'start'    => $ex->start_date,
                'end'      => $ex->end_date,
                'artworks' => $linked_by_exhibition[(int) $ex->id] ?? [],
            ];
        }

        return [
            'gallery'     => $g,
            'artists'     => $artist_nodes,
            'exhibitions' => $ex_nodes,
        ];
    }

    /**
     * Artist tree: Artist → Galleries → Artworks → Certificates → Owners
     */
    public static function artist_tree(int $artist_id): array
    {
        global $wpdb;
        $ar = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('artists') . ' WHERE id = %d',
            $artist_id
        ));
        if (!$ar) {
            return [];
        }

        $galleries = $wpdb->get_results($wpdb->prepare(
            'SELECT DISTINCT g.* FROM ' . Database::table('galleries') . ' g
             INNER JOIN ' . Database::table('artworks') . ' a ON a.gallery_id = g.id
             WHERE a.artist_id = %d ORDER BY g.name ASC',
            $artist_id
        )) ?: [];

        $artworks = $wpdb->get_results($wpdb->prepare(
            'SELECT a.*, g.name AS gallery_name, g.slug AS gallery_slug
             FROM ' . Database::table('artworks') . ' a
             LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = a.gallery_id
             WHERE a.artist_id = %d ORDER BY a.id DESC',
            $artist_id
        )) ?: [];

        self::prefetch_owners($artworks);
        $certs_by_artwork = [];
        $history_by_artwork = [];
        $artwork_ids = array_values(array_unique(array_map(static fn($aw): int => (int) $aw->id, $artworks)));
        if ($artwork_ids) {
            $placeholders = implode(',', array_fill(0, count($artwork_ids), '%d'));
            foreach ($wpdb->get_results($wpdb->prepare(
                'SELECT * FROM ' . Database::table('certificates') . ' WHERE artwork_id IN (' . $placeholders . ') ORDER BY id DESC',
                ...$artwork_ids
            )) ?: [] as $row) {
                $certs_by_artwork[(int) $row->artwork_id][] = $row;
            }
            foreach ($wpdb->get_results($wpdb->prepare(
                'SELECT * FROM ' . Database::table('ownership_history') . ' WHERE artwork_id IN (' . $placeholders . ') ORDER BY transferred_at_utc DESC',
                ...$artwork_ids
            )) ?: [] as $row) {
                $aid = (int) $row->artwork_id;
                if (count($history_by_artwork[$aid] ?? []) < 10) {
                    $history_by_artwork[$aid][] = $row;
                }
            }
        }

        $aw_nodes = [];
        foreach ($artworks as $aw) {
            $aw_nodes[] = [
                'artwork'  => $aw,
                'certs'    => $certs_by_artwork[(int) $aw->id] ?? [],
                'history'  => $history_by_artwork[(int) $aw->id] ?? [],
                'owner'    => self::owner_label($aw),
            ];
        }

        // Extend the COA joins with the canonical WordPress relationships. Direct gallery/exhibition
        // links should be visible even when an artist has not yet had an artwork mirrored to COA.
        $artist_post_id = absint($ar->cpt_post_id ?? 0);
        $direct_galleries = [];
        $direct_exhibitions = [];
        $unmirrored_artworks = [];
        if ($artist_post_id > 0 && function_exists('get_post_type') && get_post_type($artist_post_id) === 'artiey_artist' && function_exists('get_posts')) {
            $direct_galleries = get_posts([
                'post_type' => 'artiey_gallery', 'post_status' => 'publish', 'numberposts' => 200,
                'orderby' => 'title', 'order' => 'ASC',
                'meta_query' => [['key' => '_ag_artists', 'value' => ';i:' . $artist_post_id . ';', 'compare' => 'LIKE']],
            ]) ?: [];
            $direct_exhibitions = get_posts([
                'post_type' => 'artiey_exhibition', 'post_status' => 'publish', 'numberposts' => 200,
                'meta_query' => [['key' => '_ae_artists', 'value' => ';i:' . $artist_post_id . ';', 'compare' => 'LIKE']],
                'orderby' => 'meta_value', 'meta_key' => '_ae_start', 'order' => 'DESC',
            ]) ?: [];
            $canonical_artworks = get_posts([
                'post_type' => 'artiey_artwork', 'post_status' => 'publish', 'numberposts' => 500,
                'meta_query' => [['key' => '_aw_artist', 'value' => $artist_post_id, 'compare' => '=', 'type' => 'NUMERIC']],
                'orderby' => 'date', 'order' => 'DESC',
            ]) ?: [];
            $linked_gallery_posts = [];
            $linked_gallery_slugs = [];
            foreach ($galleries as $gallery_row) {
                $linked_gallery_posts[] = absint($gallery_row->cpt_post_id ?? 0);
                $linked_gallery_slugs[] = sanitize_title((string) ($gallery_row->slug ?? ''));
            }
            $direct_galleries = array_values(array_filter($direct_galleries, static function ($post) use ($linked_gallery_posts, $linked_gallery_slugs): bool {
                if (!is_object($post)) { return false; }
                $id = absint($post->ID ?? 0);
                $slug = sanitize_title((string) ($post->post_name ?? ''));
                return !in_array($id, $linked_gallery_posts, true) && ($slug === '' || !in_array($slug, $linked_gallery_slugs, true));
            }));
            $linked_artwork_posts = [];
            $linked_artwork_slugs = [];
            foreach ($aw_nodes as $node) {
                $linked_artwork_posts[] = absint($node['artwork']->cpt_post_id ?? 0);
                $linked_artwork_slugs[] = sanitize_title((string) ($node['artwork']->slug ?? ''));
            }
            $unmirrored_artworks = array_values(array_filter($canonical_artworks, static function ($post) use ($linked_artwork_posts, $linked_artwork_slugs): bool {
                if (!is_object($post)) { return false; }
                $id = absint($post->ID ?? 0);
                $slug = sanitize_title((string) ($post->post_name ?? ''));
                return !in_array($id, $linked_artwork_posts, true) && ($slug === '' || !in_array($slug, $linked_artwork_slugs, true));
            }));
        }

        // COA exhibitions are also related to an artist through the artworks included in an exhibition.
        // This fills the gap for exhibitions that have no `_ae_artists` WordPress meta but do contain
        // one or more artworks whose canonical COA artist_id points at this artist.
        $gallery_table = Database::table('galleries');
        $gallery_columns = $wpdb->get_col("DESCRIBE `{$gallery_table}`", 0) ?: [];
        $gallery_post_select = in_array('cpt_post_id', $gallery_columns, true) ? 'g.cpt_post_id AS gallery_post_id' : '0 AS gallery_post_id';
        $coa_exhibitions = $wpdb->get_results($wpdb->prepare(
            'SELECT DISTINCT e.*, g.name AS gallery_name, g.slug AS gallery_slug, ' . $gallery_post_select . '
             FROM ' . Database::table('exhibitions') . ' e
             INNER JOIN ' . Database::table('exhibition_artworks') . ' ea ON ea.exhibition_id = e.id
             INNER JOIN ' . Database::table('artworks') . ' a ON a.id = ea.artwork_id
             LEFT JOIN ' . $gallery_table . ' g ON g.id = e.gallery_id
             WHERE a.artist_id = %d AND e.status IN ("published", "active")
             ORDER BY e.start_date DESC, e.id DESC LIMIT 100',
            $artist_id
        )) ?: [];
        $direct_exhibition_ids = [];
        $direct_exhibition_slugs = [];
        foreach ($direct_exhibitions as $direct_exhibition) {
            $direct_exhibition_ids[] = absint($direct_exhibition->ID ?? 0);
            $direct_exhibition_slugs[] = sanitize_title((string) ($direct_exhibition->post_name ?? ''));
        }
        $coa_exhibitions = array_values(array_filter($coa_exhibitions, static function ($exhibition) use ($direct_exhibition_ids, $direct_exhibition_slugs): bool {
            if (!is_object($exhibition)) { return false; }
            $post_id = absint($exhibition->cpt_post_id ?? 0);
            $slug = sanitize_title((string) ($exhibition->slug ?? ''));
            return !in_array($post_id, $direct_exhibition_ids, true) && ($slug === '' || !in_array($slug, $direct_exhibition_slugs, true));
        }));

        return [
            'artist'                => $ar,
            'artist_post_id'        => $artist_post_id,
            'galleries'             => $galleries,
            'direct_galleries'      => $direct_galleries,
            'artworks'              => $aw_nodes,
            'unmirrored_artworks'   => $unmirrored_artworks,
            'direct_exhibitions'    => $direct_exhibitions,
            'coa_exhibitions'       => $coa_exhibitions,
        ];
    }

    /**
     * A tabbed presentation of a complete artist tree, combining COA relational records with canonical WP links.
     * Access is still restricted by the Elementor hierarchy widget to administrators.
     */
    public static function render_artist_tabs(array $tree, array $settings = []): string
    {
        if (empty($tree['artist'])) { return ''; }
        $panels = [];
        $name = (string) (($tree['artist']->display_name ?? '') ?: trim(($tree['artist']->first_name ?? '') . ' ' . ($tree['artist']->last_name ?? '')));

        $gallery_html = '<div class="art-coa-hierarchy-tab art-coa-hierarchy-galleries"><ul class="art-coa-tree">';
        $gallery_count = 0;
        foreach (($tree['galleries'] ?? []) as $g) {
            $post_id = absint($g->cpt_post_id ?? 0);
            $url = $post_id && function_exists('get_permalink') && get_post_type($post_id) === 'artiey_gallery' ? get_permalink($post_id) : home_url('/art-coa/gallery/' . (string) ($g->slug ?? ''));
            $gallery_html .= '<li><a href="' . esc_url($url) . '">' . esc_html((string) ($g->name ?? '')) . '</a></li>';
            $gallery_count++;
        }
        foreach (($tree['direct_galleries'] ?? []) as $g) {
            $gallery_html .= '<li><a href="' . esc_url(get_permalink($g->ID)) . '">' . esc_html(get_the_title($g->ID)) . '</a> <span class="art-coa-h-meta">(ارتباط مستقیم)</span></li>';
            $gallery_count++;
        }
        $gallery_html .= '</ul></div>';
        if ($gallery_count) { $panels[] = ['key' => 'galleries', 'label' => 'گالری‌های مرتبط', 'html' => $gallery_html]; }

        $art_html = '<div class="art-coa-hierarchy-tab art-coa-hierarchy-artworks"><ul class="art-coa-tree">';
        $art_count = 0;
        foreach (($tree['artworks'] ?? []) as $node) {
            $aw = $node['artwork'];
            $post_id = absint($aw->cpt_post_id ?? 0);
            $title = (string) ($aw->title ?? '');
            $art_html .= '<li>🖼 ';
            if ($post_id && function_exists('get_permalink') && get_post_type($post_id) === 'artiey_artwork') {
                $art_html .= '<a href="' . esc_url(get_permalink($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a>';
            } else { $art_html .= esc_html($title); }
            if (!empty($aw->gallery_name)) { $art_html .= ' — ' . esc_html((string) $aw->gallery_name); }
            $art_html .= '</li>';
            $art_count++;
        }
        foreach (($tree['unmirrored_artworks'] ?? []) as $wp_art) {
            $art_html .= '<li>🖼 <a href="' . esc_url(get_permalink($wp_art->ID)) . '">' . esc_html(get_the_title($wp_art->ID)) . '</a> <span class="art-coa-h-warning">(هنوز در جداول COA همگام نشده)</span></li>';
            $art_count++;
        }
        $art_html .= '</ul></div>';
        if ($art_count) { $panels[] = ['key' => 'artworks', 'label' => 'نمونه آثار', 'html' => $art_html]; }

        $ex_html = '<div class="art-coa-hierarchy-tab art-coa-hierarchy-exhibitions"><ul class="art-coa-tree">';
        $ex_count = 0;
        foreach (($tree['direct_exhibitions'] ?? []) as $ex) {
            $ex_html .= '<li><a href="' . esc_url(get_permalink($ex->ID)) . '">' . esc_html(get_the_title($ex->ID)) . '</a>';
            $start = (string) get_post_meta($ex->ID, '_ae_start', true);
            $end = (string) get_post_meta($ex->ID, '_ae_end', true);
            if ($start !== '') { $ex_html .= ' <span class="art-coa-h-meta">(' . esc_html(Dates::format($start)); if ($end && $end !== $start) { $ex_html .= ' — ' . esc_html(Dates::format($end)); } $ex_html .= ')</span>'; }
            $gid = absint(get_post_meta($ex->ID, '_ae_gallery', true));
            if ($gid && get_post_type($gid) === 'artiey_gallery' && get_post_status($gid) === 'publish') { $ex_html .= '<div class="art-coa-h-meta">گالری: <a href="' . esc_url(get_permalink($gid)) . '">' . esc_html(get_the_title($gid)) . '</a></div>'; }
            $ex_html .= '</li>';
            $ex_count++;
        }
        foreach (($tree['coa_exhibitions'] ?? []) as $ex) {
            $post_id = absint($ex->cpt_post_id ?? 0);
            $url = $post_id && function_exists('get_post_type') && get_post_type($post_id) === 'artiey_exhibition'
                ? get_permalink($post_id)
                : home_url('/art-coa/exhibition/' . (string) ($ex->slug ?? ''));
            $ex_html .= '<li><a href="' . esc_url($url) . '">' . esc_html((string) ($ex->title ?? '')) . '</a>';
            $start = (string) ($ex->start_date ?? '');
            $end = (string) ($ex->end_date ?? '');
            if ($start !== '') {
                $ex_html .= ' <span class="art-coa-h-meta">(' . esc_html(Dates::format($start));
                if ($end !== '' && $end !== $start) { $ex_html .= ' — ' . esc_html(Dates::format($end)); }
                $ex_html .= ')</span>';
            }
            if (!empty($ex->gallery_name)) {
                $gallery_post_id = absint($ex->gallery_post_id ?? 0);
                $gallery_url = $gallery_post_id && function_exists('get_post_type') && get_post_type($gallery_post_id) === 'artiey_gallery'
                    ? get_permalink($gallery_post_id)
                    : home_url('/art-coa/gallery/' . (string) ($ex->gallery_slug ?? ''));
                $ex_html .= '<div class="art-coa-h-meta">گالری: <a href="' . esc_url($gallery_url) . '">' . esc_html((string) $ex->gallery_name) . '</a></div>';
            }
            $ex_html .= ' <span class="art-coa-h-meta">(ارتباط از طریق آثار)</span></li>';
            $ex_count++;
        }
        $ex_html .= '</ul></div>';
        if ($ex_count) { $panels[] = ['key' => 'exhibitions', 'label' => 'نمایشگاه‌ها', 'html' => $ex_html]; }

        $history_html = '<div class="art-coa-hierarchy-tab art-coa-hierarchy-provenance"><ul class="art-coa-tree">';
        $history_count = 0;
        foreach (($tree['artworks'] ?? []) as $node) {
            $aw = $node['artwork'];
            $art_title = (string) ($aw->title ?? 'اثر هنری');
            foreach (($node['certs'] ?? []) as $cert) {
                $history_html .= '<li><strong>' . esc_html($art_title) . '</strong> — گواهی <a href="' . esc_url(home_url('/art-coa/certificate/' . rawurlencode((string) $cert->serial_number))) . '"><code>' . esc_html((string) $cert->serial_number) . '</code></a> (' . esc_html((string) ($cert->status ?? '')) . ')</li>';
                $history_count++;
            }
            foreach (($node['history'] ?? []) as $row) {
                $history_html .= '<li><strong>' . esc_html($art_title) . '</strong> — ' . esc_html(Dates::format((string) ($row->transferred_at_utc ?? ''))) . ' → ' . esc_html((string) ($row->to_name ?? '')) . ' (' . esc_html((string) ($row->to_type ?? '')) . ')</li>';
                $history_count++;
            }
        }
        $history_html .= '</ul></div>';
        if ($history_count) { $panels[] = ['key' => 'provenance', 'label' => 'گواهی و مالکیت', 'html' => $history_html]; }

        if (!$panels) { return '<p class="art-coa-empty">برای «' . esc_html($name) . '» هنوز ارتباطی در گالری‌ها، آثار، نمایشگاه‌ها یا سوابق COA ثبت نشده است.</p>'; }
        return '\Artiey_AET_Tabs'::render($panels, $settings, 'سلسله‌مراتب هنرمند');
    }

    /**
     * Collector tree: Collector → Artworks → Artist → Gallery → Certificate
     */
    public static function collector_tree(int $collector_id): array
    {
        global $wpdb;
        $col = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('collectors') . ' WHERE id = %d',
            $collector_id
        ));
        if (!$col) {
            return [];
        }

        $artworks = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, ar.display_name AS artist_name, ar.slug AS artist_slug, ar.first_name, ar.last_name,
                    g.name AS gallery_name, g.slug AS gallery_slug
             FROM " . Database::table('artworks') . " a
             LEFT JOIN " . Database::table('artists') . " ar ON ar.id = a.artist_id
             LEFT JOIN " . Database::table('galleries') . " g ON g.id = a.gallery_id
             WHERE a.current_owner_type = 'collector' AND a.current_owner_id = %d
             ORDER BY a.id DESC",
            $collector_id
        )) ?: [];

        $nodes = [];
        foreach ($artworks as $aw) {
            $cert = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . Database::table('certificates') . "
                 WHERE artwork_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1",
                $aw->id
            ));
            $nodes[] = [
                'artwork' => $aw,
                'cert'    => $cert,
            ];
        }

        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT oh.*, a.title AS artwork_title FROM " . Database::table('ownership_history') . " oh
             LEFT JOIN " . Database::table('artworks') . " a ON a.id = oh.artwork_id
             WHERE oh.to_type = 'collector' AND oh.to_id = %d
             ORDER BY oh.transferred_at_utc DESC LIMIT 20",
            $collector_id
        )) ?: [];

        return [
            'collector' => $col,
            'artworks'  => $nodes,
            'history'   => $history,
        ];
    }

    /**
     * Buyer tree: purchases history → artworks → artists
     */
    public static function buyer_tree(int $buyer_id, string $buyer_name = ''): array
    {
        global $wpdb;
        $buyer = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Database::table('buyers') . ' WHERE id = %d',
            $buyer_id
        ));

        $sql = 'SELECT oh.*, a.title AS artwork_title, a.artist_id, ar.display_name AS artist_name, ar.slug AS artist_slug,
                       ar.first_name, ar.last_name, g.name AS gallery_name, g.slug AS gallery_slug
                FROM ' . Database::table('ownership_history') . ' oh
                LEFT JOIN ' . Database::table('artworks') . ' a ON a.id = oh.artwork_id
                LEFT JOIN ' . Database::table('artists') . ' ar ON ar.id = a.artist_id
                LEFT JOIN ' . Database::table('galleries') . ' g ON g.id = a.gallery_id
                WHERE oh.to_type = %s';
        $params = ['buyer'];
        if ($buyer_id) {
            $sql .= ' AND (oh.to_id = %d';
            $params[] = $buyer_id;
            if ($buyer_name !== '') {
                $sql .= ' OR oh.to_name = %s';
                $params[] = $buyer_name;
            }
            $sql .= ')';
        } elseif ($buyer_name !== '') {
            $sql .= ' AND oh.to_name = %s';
            $params[] = $buyer_name;
        }
        $sql .= ' ORDER BY oh.transferred_at_utc DESC LIMIT 50';

        $purchases = $wpdb->get_results($wpdb->prepare($sql, ...$params)) ?: [];

        return [
            'buyer'     => $buyer,
            'purchases' => $purchases,
        ];
    }

    /** @var array<string,string> request-level cache: "type:id" => display name */
    private static array $owner_names = [];

    private const OWNER_TABLES = ['collector' => ['collectors', 'display_name'], 'buyer' => ['buyers', 'display_name'], 'gallery' => ['galleries', 'name']];

    /** Load every owner name needed by $artworks with one query per owner type. */
    private static function prefetch_owners(array $artworks): void
    {
        global $wpdb;
        $need = [];
        foreach ($artworks as $aw) {
            $type = (string) ($aw->current_owner_type ?? '');
            $id = (int) ($aw->current_owner_id ?? 0);
            if ($id && isset(self::OWNER_TABLES[$type]) && !isset(self::$owner_names[$type . ':' . $id])) {
                $need[$type][$id] = true;
            }
        }
        foreach ($need as $type => $ids) {
            [$table, $col] = self::OWNER_TABLES[$type];
            $ids = array_keys($ids);
            $rows = $wpdb->get_results($wpdb->prepare(
                'SELECT id, ' . $col . ' AS label FROM ' . Database::table($table) . ' WHERE id IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')',
                ...$ids
            )) ?: [];
            foreach ($ids as $id) {
                self::$owner_names[$type . ':' . $id] = '';
            }
            foreach ($rows as $row) {
                self::$owner_names[$type . ':' . (int) $row->id] = (string) $row->label;
            }
        }
    }

    private static function owner_label(object $aw): string
    {
        if (empty($aw->current_owner_type) || empty($aw->current_owner_id)) {
            return I18n::t('none');
        }
        $type = (string) $aw->current_owner_type;
        $id = (int) $aw->current_owner_id;
        if (!isset(self::OWNER_TABLES[$type])) {
            return $type . ' #' . $id;
        }
        self::prefetch_owners([$aw]);
        $name = self::$owner_names[$type . ':' . $id] ?? '';
        return ($name !== '' ? $name : '#' . $id) . ' (' . I18n::t($type) . ')';
    }

    /**
     * HTML tree renderer for admin / front.
     */
    /**
     * Visitor-safe copy of a gallery/artist tree: only publicly visible artworks, active certificates, and no
     * owner names, ownership history or e-mail addresses (those stay admin-only).
     */
    public static function public_tree(array $tree, string $type): array
    {
        $visible = ['approved', 'published', 'active', 'sold'];
        $active_certs = static fn($certs): array => array_values(array_filter((array) $certs, static fn($c): bool => (($c->status ?? '') === 'active')));
        if ($type === 'gallery' && !empty($tree['artists'])) {
            $artists = [];
            foreach ($tree['artists'] as $ar) {
                $works = [];
                foreach ((array) ($ar['artworks'] ?? []) as $aw) {
                    if (!in_array((string) ($aw['status'] ?? ''), $visible, true)) { continue; }
                    $aw['owner'] = '';
                    $aw['certs'] = $active_certs($aw['certs'] ?? []);
                    $works[] = $aw;
                }
                if (!$works) { continue; }
                $ar['artworks'] = $works;
                $ar['email'] = '';
                $artists[] = $ar;
            }
            $tree['artists'] = $artists;
        }
        if ($type === 'gallery' && !empty($tree['exhibitions'])) {
            // Exhibition rows may link to artworks that are still drafts/pending; keep only publicly visible ones.
            foreach ($tree['exhibitions'] as $i => $ex) {
                $tree['exhibitions'][$i]['artworks'] = array_values(array_filter(
                    (array) ($ex['artworks'] ?? []),
                    static fn($aw): bool => in_array((string) ($aw->status ?? ''), $visible, true)
                ));
            }
        }
        if ($type === 'collector') {
            // Public collector pages show only public works with active certificates and no transfer history.
            $nodes = [];
            foreach ((array) ($tree['artworks'] ?? []) as $node) {
                if (!in_array((string) ($node['artwork']->status ?? ''), $visible, true)) { continue; }
                if (!empty($node['cert']) && (($node['cert']->status ?? '') !== 'active')) { $node['cert'] = null; }
                $nodes[] = $node;
            }
            $tree['artworks'] = $nodes;
            $tree['history'] = [];
        }
        if ($type === 'artist' && !empty($tree['artworks'])) {
            $nodes = [];
            foreach ($tree['artworks'] as $node) {
                if (!in_array((string) ($node['artwork']->status ?? ''), $visible, true)) { continue; }
                $node['owner'] = '';
                $node['history'] = [];
                $node['certs'] = $active_certs($node['certs'] ?? []);
                $nodes[] = $node;
            }
            $tree['artworks'] = $nodes;
        }
        return $tree;
    }

    public static function render_html(array $tree, string $type): string
    {
        ob_start();
        echo '<div class="art-coa-hierarchy" dir="' . (I18n::is_fa() ? 'rtl' : 'ltr') . '">';
        match ($type) {
            'gallery'   => self::html_gallery($tree),
            'artist'    => self::html_artist($tree),
            'collector' => self::html_collector($tree),
            'buyer'     => self::html_buyer($tree),
            default     => print '',
        };
        echo '</div>';
        return (string) ob_get_clean();
    }

    private static function html_gallery(array $tree): void
    {
        if (!$tree) {
            echo '<p>' . esc_html(I18n::t('no_items')) . '</p>';
            return;
        }
        $g = $tree['gallery'];
        echo '<h3 class="art-coa-h-root">🏢 ' . esc_html($g->name) . '</h3>';
        echo '<ul class="art-coa-tree">';
        echo '<li><strong>' . esc_html(I18n::t('artists')) . '</strong><ul>';
        foreach ($tree['artists'] as $ar) {
            echo '<li><a href="' . esc_url(ContentSync::artist_url((string) $ar['slug'])) . '">' . esc_html($ar['name']) . '</a>';
            if ($ar['artworks']) {
                echo '<ul>';
                foreach ($ar['artworks'] as $aw) {
                    echo '<li>🖼 ' . esc_html($aw['title']);
                    if ($aw['year']) echo ' (' . esc_html((string) $aw['year']) . ')';
                    if (($aw['owner'] ?? '') !== '') { echo ' — ' . esc_html(I18n::t('owner')) . ': ' . esc_html($aw['owner']); }
                    if ($aw['certs']) {
                        echo '<ul>';
                        foreach ($aw['certs'] as $c) {
                            echo '<li>🎖️ <a href="' . esc_url(home_url('/art-coa/certificate/' . rawurlencode($c->serial_number))) . '"><code>' . esc_html($c->serial_number) . '</code></a> (' . esc_html($c->status) . ')</li>';
                        }
                        echo '</ul>';
                    }
                    echo '</li>';
                }
                echo '</ul>';
            }
            echo '</li>';
        }
        echo '</ul></li>';
        echo '<li><strong>' . esc_html(I18n::t('exhibitions')) . '</strong><ul>';
        foreach ($tree['exhibitions'] as $ex) {
            echo '<li><a href="' . esc_url(home_url('/art-coa/exhibition/' . $ex['slug'])) . '">' . esc_html($ex['title']) . '</a>';
            if ($ex['start']) {
                echo ' <span class="meta">(' . esc_html(Dates::format($ex['start']));
                if ($ex['end']) echo ' — ' . esc_html(Dates::format($ex['end']));
                echo ')</span>';
            }
            if ($ex['artworks']) {
                echo '<ul>';
                foreach ($ex['artworks'] as $aw) {
                    $an = $aw->artist_name ?: trim(($aw->first_name ?? '') . ' ' . ($aw->last_name ?? ''));
                    echo '<li>' . esc_html($aw->title);
                    if ($an && !empty($aw->artist_slug)) {
                        echo ' — <a href="' . esc_url(ContentSync::artist_url((string) $aw->artist_slug)) . '">' . esc_html($an) . '</a>';
                    }
                    echo '</li>';
                }
                echo '</ul>';
            }
            echo '</li>';
        }
        echo '</ul></li></ul>';
    }

    private static function html_artist(array $tree): void
    {
        if (!$tree) {
            echo '<p>' . esc_html(I18n::t('no_items')) . '</p>';
            return;
        }
        $ar = $tree['artist'];
        $name = $ar->display_name ?: trim(($ar->first_name ?? '') . ' ' . ($ar->last_name ?? ''));
        echo '<h3 class="art-coa-h-root">🎨 ' . esc_html($name) . '</h3>';
        echo '<ul class="art-coa-tree">';

        $galleries = (array) ($tree['galleries'] ?? []);
        $direct_galleries = (array) ($tree['direct_galleries'] ?? []);
        if ($galleries || $direct_galleries) {
            echo '<li><strong>' . esc_html(I18n::t('galleries')) . '</strong><ul>';
            foreach ($galleries as $g) {
                $post_id = absint($g->cpt_post_id ?? 0);
                $url = $post_id && function_exists('get_post_type') && get_post_type($post_id) === 'artiey_gallery'
                    ? get_permalink($post_id)
                    : home_url('/art-coa/gallery/' . (string) ($g->slug ?? ''));
                echo '<li><a href="' . esc_url($url) . '">' . esc_html((string) ($g->name ?? '')) . '</a></li>';
            }
            foreach ($direct_galleries as $g) {
                $gid = absint($g->ID ?? 0);
                if (!$gid) { continue; }
                echo '<li><a href="' . esc_url(get_permalink($gid)) . '">' . esc_html(get_the_title($gid)) . '</a> <span class="art-coa-h-meta">(ارتباط مستقیم وردپرس)</span></li>';
            }
            echo '</ul></li>';
        }

        $artworks = (array) ($tree['artworks'] ?? []);
        $unmirrored = (array) ($tree['unmirrored_artworks'] ?? []);
        if ($artworks || $unmirrored) {
            echo '<li><strong>' . esc_html(I18n::t('artworks')) . '</strong><ul>';
            foreach ($artworks as $node) {
                $aw = $node['artwork'];
                $post_id = absint($aw->cpt_post_id ?? 0);
                echo '<li>🖼 ';
                if ($post_id && function_exists('get_post_type') && get_post_type($post_id) === 'artiey_artwork') {
                    echo '<a href="' . esc_url(get_permalink($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a>';
                } else {
                    echo esc_html((string) ($aw->title ?? ''));
                }
                if (!empty($aw->gallery_name) && !empty($aw->gallery_slug)) {
                    echo ' @ <a href="' . esc_url(home_url('/art-coa/gallery/' . (string) $aw->gallery_slug)) . '">' . esc_html((string) $aw->gallery_name) . '</a>';
                }
                if (($node['owner'] ?? null) !== '') { echo ' — ' . esc_html(I18n::t('owner')) . ': ' . esc_html((string) ($node['owner'] ?? I18n::t('none'))); }
                if (!empty($node['certs'])) {
                    echo '<ul>';
                    foreach ($node['certs'] as $c) {
                        echo '<li>🎖️ <a href="' . esc_url(home_url('/art-coa/certificate/' . rawurlencode((string) ($c->serial_number ?? '')))) . '"><code>' . esc_html((string) ($c->serial_number ?? '')) . '</code></a></li>';
                    }
                    echo '</ul>';
                }
                if (!empty($node['history'])) {
                    echo '<ul class="art-coa-history">';
                    foreach ($node['history'] as $h) {
                        echo '<li>' . esc_html(Dates::format((string) ($h->transferred_at_utc ?? ''))) . ' → ' . esc_html((string) ($h->to_name ?? '')) . ' (' . esc_html((string) ($h->to_type ?? '')) . ')</li>';
                    }
                    echo '</ul>';
                }
                echo '</li>';
            }
            foreach ($unmirrored as $wp_art) {
                $post_id = absint($wp_art->ID ?? 0);
                if (!$post_id) { continue; }
                echo '<li>🖼 <a href="' . esc_url(get_permalink($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a> <span class="art-coa-h-warning">(ارتباط وردپرس؛ هنوز در COA همگام نشده)</span></li>';
            }
            echo '</ul></li>';
        }

        $exhibitions = (array) ($tree['direct_exhibitions'] ?? []);
        $coa_exhibitions = (array) ($tree['coa_exhibitions'] ?? []);
        if ($exhibitions || $coa_exhibitions) {
            echo '<li><strong>' . esc_html(I18n::t('exhibitions')) . '</strong><ul>';
            foreach ($exhibitions as $ex) {
                $eid = absint($ex->ID ?? 0);
                if (!$eid) { continue; }
                echo '<li><a href="' . esc_url(get_permalink($eid)) . '">' . esc_html(get_the_title($eid)) . '</a>';
                $start = (string) get_post_meta($eid, '_ae_start', true);
                $end = (string) get_post_meta($eid, '_ae_end', true);
                if ($start !== '') {
                    echo ' <span class="art-coa-h-meta">(' . esc_html(Dates::format($start));
                    if ($end !== '' && $end !== $start) { echo ' — ' . esc_html(Dates::format($end)); }
                    echo ')</span>';
                }
                $gallery_id = absint(get_post_meta($eid, '_ae_gallery', true));
                if ($gallery_id && get_post_type($gallery_id) === 'artiey_gallery' && get_post_status($gallery_id) === 'publish') {
                    echo '<div class="art-coa-h-meta">گالری: <a href="' . esc_url(get_permalink($gallery_id)) . '">' . esc_html(get_the_title($gallery_id)) . '</a></div>';
                }
                echo '</li>';
            }
            foreach ($coa_exhibitions as $ex) {
                $post_id = absint($ex->cpt_post_id ?? 0);
                $url = $post_id && function_exists('get_post_type') && get_post_type($post_id) === 'artiey_exhibition'
                    ? get_permalink($post_id)
                    : home_url('/art-coa/exhibition/' . (string) ($ex->slug ?? ''));
                echo '<li><a href="' . esc_url($url) . '">' . esc_html((string) ($ex->title ?? '')) . '</a>';
                $start = (string) ($ex->start_date ?? ''); $end = (string) ($ex->end_date ?? '');
                if ($start !== '') {
                    echo ' <span class="art-coa-h-meta">(' . esc_html(Dates::format($start));
                    if ($end !== '' && $end !== $start) { echo ' — ' . esc_html(Dates::format($end)); }
                    echo ')</span>';
                }
                if (!empty($ex->gallery_name)) {
                    $gallery_post_id = absint($ex->gallery_post_id ?? 0);
                    $gallery_url = $gallery_post_id && function_exists('get_post_type') && get_post_type($gallery_post_id) === 'artiey_gallery'
                        ? get_permalink($gallery_post_id)
                        : home_url('/art-coa/gallery/' . (string) ($ex->gallery_slug ?? ''));
                    echo '<div class="art-coa-h-meta">گالری: <a href="' . esc_url($gallery_url) . '">' . esc_html((string) $ex->gallery_name) . '</a></div>';
                }
                echo ' <span class="art-coa-h-meta">(ارتباط از طریق آثار)</span></li>';
            }
            echo '</ul></li>';
        }
        echo '</ul>';
    }

    private static function html_collector(array $tree): void
    {
        if (!$tree) {
            echo '<p>' . esc_html(I18n::t('no_items')) . '</p>';
            return;
        }
        $col = $tree['collector'];
        echo '<h3 class="art-coa-h-root">🏛 ' . esc_html($col->display_name);
        if ($col->collection_name) {
            echo ' — ' . esc_html($col->collection_name);
        }
        echo '</h3>';
        echo '<ul class="art-coa-tree"><li><strong>' . esc_html(I18n::t('artworks')) . '</strong><ul>';
        foreach ($tree['artworks'] as $node) {
            $aw = $node['artwork'];
            $an = $aw->artist_name ?: trim(($aw->first_name ?? '') . ' ' . ($aw->last_name ?? ''));
            echo '<li>🖼 ' . esc_html($aw->title);
            if ($an && $aw->artist_slug) {
                echo ' — <a href="' . esc_url(ContentSync::artist_url((string) $aw->artist_slug)) . '">' . esc_html($an) . '</a>';
            }
            if ($aw->gallery_name && $aw->gallery_slug) {
                echo ' @ <a href="' . esc_url(home_url('/art-coa/gallery/' . $aw->gallery_slug)) . '">' . esc_html($aw->gallery_name) . '</a>';
            }
            if ($node['cert']) {
                echo ' — 🎖️ <a href="' . esc_url(home_url('/art-coa/certificate/' . rawurlencode($node['cert']->serial_number))) . '"><code>' . esc_html($node['cert']->serial_number) . '</code></a>';
            }
            echo '</li>';
        }
        echo '</ul></li>';
        if ($tree['history']) {
            echo '<li><strong>' . esc_html(I18n::t('recent_transfers')) . '</strong><ul>';
            foreach ($tree['history'] as $h) {
                echo '<li>' . esc_html(Dates::format($h->transferred_at_utc, true)) . ' — ' . esc_html($h->artwork_title ?: '#' . $h->artwork_id) . '</li>';
            }
            echo '</ul></li>';
        }
        echo '</ul>';
    }

    private static function html_buyer(array $tree): void
    {
        if (!$tree) {
            echo '<p>' . esc_html(I18n::t('no_items')) . '</p>';
            return;
        }
        $name = $tree['buyer']->display_name ?? I18n::t('buyer');
        echo '<h3 class="art-coa-h-root">🛒 ' . esc_html($name) . '</h3>';
        echo '<ul class="art-coa-tree"><li><strong>' . esc_html(I18n::t('sales_total')) . '</strong><ul>';
        foreach ($tree['purchases'] as $p) {
            $an = $p->artist_name ?: trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? ''));
            echo '<li>' . esc_html(Dates::format($p->transferred_at_utc)) . ' — ' . esc_html($p->artwork_title ?: '#' . $p->artwork_id);
            if ($an && !empty($p->artist_slug)) {
                echo ' — <a href="' . esc_url(ContentSync::artist_url((string) $p->artist_slug)) . '">' . esc_html($an) . '</a>';
            }
            if (!empty($p->gallery_name) && !empty($p->gallery_slug)) {
                echo ' @ <a href="' . esc_url(home_url('/art-coa/gallery/' . $p->gallery_slug)) . '">' . esc_html($p->gallery_name) . '</a>';
            }
            echo '</li>';
        }
        if (!$tree['purchases']) {
            echo '<li>' . esc_html(I18n::t('no_items')) . '</li>';
        }
        echo '</ul></li></ul>';
    }
}
