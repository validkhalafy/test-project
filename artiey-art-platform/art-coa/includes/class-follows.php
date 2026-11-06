<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Follow / followers for artists, galleries, curators and public collectors.
 *
 * A "subject" is the thing being followed: [type, id]. Relational profiles use the Art COA row id
 * (artist, gallery, curator, collector). A WordPress artist/gallery post that has no mirrored row yet
 * is followed as ['post', post_id] so the button still works before the content sync has run.
 *
 * - Followers of a subject  = users who follow it.
 * - Following of a subject  = what the WordPress user linked to that profile (user_id column) follows.
 *
 * Everything is dynamic: the button works over admin-ajax (compatible with full-page caches because
 * the state and a fresh nonce are fetched after page load) and falls back to a classic POST form.
 * Privacy: follower lists only ever show users that own a PUBLIC profile; everyone else is a count.
 */
final class Follows
{
    public const TYPES = ['artist', 'gallery', 'curator', 'collector', 'post'];
    private const POST_TYPES = ['artiey_artist' => 'artist', 'artiey_gallery' => 'gallery'];
    private const TABLES = ['artist' => 'artists', 'gallery' => 'galleries', 'curator' => 'curators', 'collector' => 'collectors'];
    private const DB_VERSION = '3.1.0';
    private const CACHE_GROUP = 'artiey_follows';
    private const RATE_LIMIT = 40;          // toggles per window per user
    private const RATE_WINDOW = 600;        // seconds
    private const MAX_STATE_SUBJECTS = 60;

    /** @var array<string,array<string,mixed>|null> request-local entity cache */
    private static array $entities = [];
    /** @var array<string,int> request-local follower counts */
    private static array $counts = [];
    private static bool $assets_printed = false;

    public static function init(): void
    {
        add_action('admin_init', [self::class, 'maybe_migrate']);
        add_action('init', [self::class, 'handle']);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets'], 5);
        add_action('wp_ajax_artiey_follow_toggle', [self::class, 'ajax_toggle']);
        add_action('wp_ajax_nopriv_artiey_follow_toggle', [self::class, 'ajax_toggle']);
        add_action('wp_ajax_artiey_follow_state', [self::class, 'ajax_state']);
        add_action('wp_ajax_nopriv_artiey_follow_state', [self::class, 'ajax_state']);
        add_action('deleted_user', [self::class, 'purge_user']);
        add_shortcode('art_coa_follow_button', [self::class, 'button_shortcode']);
        add_shortcode('art_coa_my_follows', [self::class, 'dashboard_shortcode']);
        add_shortcode('artiey_follow', [self::class, 'button_shortcode']);
        add_shortcode('artiey_followers', [self::class, 'followers_shortcode']);
    }

    public static function table(): string
    {
        return Database::table('follows');
    }

    /* ------------------------------------------------------------------ schema */

    public static function maybe_migrate(): void
    {
        if (get_option('art_coa_dbv_follows') === self::DB_VERSION) {
            return;
        }
        self::run_migration();
        update_option('art_coa_dbv_follows', self::DB_VERSION, false);
    }

    private static function run_migration(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta('CREATE TABLE ' . self::table() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            entity_type VARCHAR(20) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_entity (user_id,entity_type,entity_id),
            KEY entity (entity_type,entity_id),
            KEY user_created (user_id,created_at_utc)
        ) {$charset};");
    }

    /** Remove the follows of a deleted WordPress account (counts must not include ghosts). */
    public static function purge_user($user_id): void
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return;
        }
        global $wpdb;
        $wpdb->delete(self::table(), ['user_id' => $user_id], ['%d']);
        self::$counts = [];
    }

    /* ------------------------------------------------------------------ subjects */

    public static function normalize_type(string $type): ?string
    {
        $type = sanitize_key($type);
        $aliases = ['artiey_artist' => 'post', 'artiey_gallery' => 'post', 'artist_post' => 'post', 'gallery_post' => 'post'];
        $type = $aliases[$type] ?? $type;
        return in_array($type, self::TYPES, true) ? $type : null;
    }

    public static function key(string $type, int $id): string
    {
        return $type . ':' . $id;
    }

    /** @return array{0:string,1:int}|null */
    public static function parse_key(string $key): ?array
    {
        if (!preg_match('/^([a-z_]{3,20}):(\d{1,18})$/D', $key, $m)) {
            return null;
        }
        $type = self::normalize_type($m[1]);
        $id = (int) $m[2];
        return $type && $id > 0 ? [$type, $id] : null;
    }

    /**
     * Subject for a WordPress artist/gallery post. Read-only: it never triggers a content sync.
     * @return array{0:string,1:int}|null
     */
    public static function subject_for_post(int $post_id): ?array
    {
        $post_type = $post_id > 0 ? (string) get_post_type($post_id) : '';
        if (!isset(self::POST_TYPES[$post_type])) {
            return null;
        }
        $type = self::POST_TYPES[$post_type];
        $table = Database::table(self::TABLES[$type]);
        global $wpdb;
        $cache_key = 'post_subject_' . $post_id;
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (is_array($cached)) {
            return $cached;
        }
        $ids = [];
        if (get_option('artiey_aet_content_bridge_schema_version')) {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$table} WHERE cpt_post_id = %d LIMIT 2", $post_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $ids = is_array($ids) ? array_values(array_filter(array_map('absint', $ids))) : [];
        }
        $subject = count($ids) === 1 ? [$type, (int) $ids[0]] : ['post', $post_id];
        wp_cache_set($cache_key, $subject, self::CACHE_GROUP, 300);
        return $subject;
    }

    /** Resolve "type + id" where artist/gallery may be given as a WordPress post id. */
    public static function subject(string $type, int $id, bool $id_is_post = false): ?array
    {
        if ($id <= 0) {
            return null;
        }
        if ($id_is_post || in_array(sanitize_key($type), ['artiey_artist', 'artiey_gallery', 'artist_post', 'gallery_post', 'post'], true)) {
            return self::subject_for_post($id);
        }
        $type = self::normalize_type($type);
        return $type ? [$type, $id] : null;
    }

    /** Subject of the page being viewed (COA profile route, artist/gallery post, Elementor preview). */
    public static function context_subject(string $prefer = ''): ?array
    {
        $route_type = sanitize_key((string) get_query_var('art_coa_type'));
        $slug = sanitize_title((string) get_query_var('art_coa_slug'));
        if ($slug !== '' && isset(self::TABLES[$route_type]) && ($prefer === '' || $prefer === $route_type)) {
            $id = self::id_by_slug($route_type, $slug);
            if ($id) {
                return [$route_type, $id];
            }
        }
        $post_id = 0;
        if (function_exists('is_singular') && is_singular(array_keys(self::POST_TYPES))) {
            $post_id = (int) get_queried_object_id();
        }
        if (!$post_id && class_exists('\\Artiey_AET_Elementor_Options')) {
            foreach (array_keys(self::POST_TYPES) as $post_type) {
                if ($prefer !== '' && self::POST_TYPES[$post_type] !== $prefer) {
                    continue;
                }
                $candidate = (int) \Artiey_AET_Elementor_Options::current_post_id($post_type);
                if ($candidate) {
                    $post_id = $candidate;
                    break;
                }
            }
        }
        return $post_id ? self::subject_for_post($post_id) : null;
    }

    public static function id_by_slug(string $type, string $slug): int
    {
        if (!isset(self::TABLES[$type]) || $slug === '') {
            return 0;
        }
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . Database::table(self::TABLES[$type]) . ' WHERE slug = %s LIMIT 1',
            $slug
        ));
    }

    /**
     * Public card data for a subject, or null when it does not exist.
     * @return array{type:string,id:int,name:string,url:string,image:string,public:bool,user_id:int,label:string}|null
     */
    public static function entity(string $type, int $id): ?array
    {
        $type = self::normalize_type($type) ?? '';
        if ($type === '' || $id <= 0) {
            return null;
        }
        $key = self::key($type, $id);
        if (!array_key_exists($key, self::$entities)) {
            self::hydrate([[$type, $id]]);
        }
        return self::$entities[$key] ?? null;
    }

    /**
     * Load many subjects with one query per type.
     * @param array<int,array{0:string,1:int}> $subjects
     */
    public static function hydrate(array $subjects): void
    {
        $by_type = [];
        foreach ($subjects as $s) {
            $type = self::normalize_type((string) ($s[0] ?? '')) ?? '';
            $id = (int) ($s[1] ?? 0);
            if ($type !== '' && $id > 0 && !array_key_exists(self::key($type, $id), self::$entities)) {
                $by_type[$type][$id] = $id;
                self::$entities[self::key($type, $id)] = null;
            }
        }
        if (!$by_type) {
            return;
        }
        global $wpdb;
        foreach ($by_type as $type => $ids) {
            $in = implode(',', array_map('intval', array_slice(array_values($ids), 0, 500)));
            if ($type === 'post') {
                foreach ($ids as $pid) {
                    $post = get_post($pid);
                    if (!$post || !isset(self::POST_TYPES[$post->post_type])) {
                        continue;
                    }
                    $thumb = get_the_post_thumbnail_url($post, 'thumbnail');
                    self::$entities[self::key('post', (int) $pid)] = [
                        'type' => 'post', 'id' => (int) $pid, 'name' => (string) get_the_title($post),
                        'url' => (string) get_permalink($post), 'image' => $thumb ? (string) $thumb : '',
                        'public' => $post->post_status === 'publish' && $post->post_password === '',
                        'user_id' => 0, 'label' => self::type_label(self::POST_TYPES[$post->post_type]),
                    ];
                }
                continue;
            }
            $table = Database::table(self::TABLES[$type]);
            $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE id IN ({$in})") ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integer list
            foreach ($rows as $row) {
                $entity = self::from_row($type, $row);
                if ($entity) {
                    self::$entities[self::key($type, (int) $row->id)] = $entity;
                }
            }
        }
    }

    /** @return array<string,mixed>|null */
    private static function from_row(string $type, object $row): ?array
    {
        $slug = (string) ($row->slug ?? '');
        $status = (string) ($row->status ?? 'active');
        switch ($type) {
            case 'artist':
                $name = (string) (($row->display_name ?? '') ?: trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')));
                $url = ContentSync::artist_url($slug);
                $image = Media::url($row, 'avatar', 'thumbnail');
                $public = $status === 'active';
                break;
            case 'gallery':
                $name = (string) ($row->name ?? '');
                $url = ContentSync::permalink('galleries', $slug) ?: home_url('/art-coa/gallery/' . rawurlencode($slug) . '/');
                $image = Media::url($row, 'logo', 'thumbnail');
                $public = $status === 'active';
                break;
            case 'curator':
                $name = (string) ($row->display_name ?? '');
                $url = home_url('/art-coa/curator/' . rawurlencode($slug) . '/');
                $image = Media::url($row, 'avatar', 'thumbnail');
                $public = $status === 'active';
                break;
            case 'collector':
                $name = (string) (($row->display_name ?? '') ?: ($row->collection_name ?? ''));
                $url = home_url('/art-coa/collector/' . rawurlencode($slug) . '/');
                $image = Media::url($row, 'avatar', 'thumbnail');
                $public = $status === 'active' && !empty($row->is_public);
                break;
            default:
                return null;
        }
        if ($name === '' || $slug === '') {
            return null;
        }
        return [
            'type' => $type, 'id' => (int) $row->id, 'name' => $name, 'url' => (string) $url,
            'image' => (string) $image, 'public' => $public, 'user_id' => (int) ($row->user_id ?? 0),
            'label' => self::type_label($type),
        ];
    }

    public static function type_label(string $type, bool $plural = false): string
    {
        $fa = I18n::is_fa();
        $labels = [
            'artist'    => $fa ? ['هنرمند', 'هنرمندان'] : ['Artist', 'Artists'],
            'gallery'   => $fa ? ['گالری', 'گالری‌ها'] : ['Gallery', 'Galleries'],
            'curator'   => $fa ? ['کیوریتور', 'کیوریتورها'] : ['Curator', 'Curators'],
            'collector' => $fa ? ['مجموعه‌دار', 'مجموعه‌داران'] : ['Collector', 'Collectors'],
            'post'      => $fa ? ['پروفایل', 'پروفایل‌ها'] : ['Profile', 'Profiles'],
        ];
        return $labels[$type][$plural ? 1 : 0] ?? $type;
    }

    /** The WordPress user who owns this profile must not follow themself. */
    public static function is_own(int $user_id, string $type, int $id): bool
    {
        $entity = self::entity($type, $id);
        return $user_id > 0 && $entity && (int) $entity['user_id'] === $user_id;
    }

    /* ------------------------------------------------------------------ data */

    public static function is_following(int $user_id, string $type, int $entity_id): bool
    {
        $type = self::normalize_type($type);
        if ($user_id <= 0 || $entity_id <= 0 || !$type) {
            return false;
        }
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::table() . ' WHERE user_id = %d AND entity_type = %s AND entity_id = %d',
            $user_id,
            $type,
            $entity_id
        )) > 0;
    }

    /**
     * Which of these subjects the user follows.
     * @param array<int,array{0:string,1:int}> $subjects
     * @return array<string,bool> key => following
     */
    public static function following_map(int $user_id, array $subjects): array
    {
        $out = [];
        $where = [];
        $params = [$user_id];
        foreach ($subjects as $s) {
            $out[self::key($s[0], (int) $s[1])] = false;
            $where[] = '(entity_type = %s AND entity_id = %d)';
            $params[] = $s[0];
            $params[] = (int) $s[1];
        }
        if ($user_id <= 0 || !$where) {
            return $out;
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT entity_type, entity_id FROM ' . self::table() . ' WHERE user_id = %d AND (' . implode(' OR ', $where) . ')',
            ...$params
        )) ?: [];
        foreach ($rows as $r) {
            $out[self::key((string) $r->entity_type, (int) $r->entity_id)] = true;
        }
        return $out;
    }

    public static function count(string $type, int $entity_id): int
    {
        $type = self::normalize_type($type);
        if (!$type || $entity_id <= 0) {
            return 0;
        }
        $map = self::counts([[$type, $entity_id]]);
        return $map[self::key($type, $entity_id)] ?? 0;
    }

    /**
     * Follower counts for many subjects in one grouped query (cached per request and in the object cache).
     * @param array<int,array{0:string,1:int}> $subjects
     * @return array<string,int>
     */
    public static function counts(array $subjects): array
    {
        $out = [];
        $missing = [];
        foreach ($subjects as $s) {
            $key = self::key($s[0], (int) $s[1]);
            if (isset(self::$counts[$key])) {
                $out[$key] = self::$counts[$key];
                continue;
            }
            $cached = wp_cache_get('count_' . $key, self::CACHE_GROUP);
            if ($cached !== false) {
                $out[$key] = self::$counts[$key] = (int) $cached;
                continue;
            }
            $missing[$s[0]][(int) $s[1]] = (int) $s[1];
            $out[$key] = 0;
        }
        if ($missing) {
            global $wpdb;
            $where = [];
            $params = [];
            foreach ($missing as $type => $ids) {
                $where[] = '(entity_type = %s AND entity_id IN (' . implode(',', array_map('intval', $ids)) . '))';
                $params[] = $type;
            }
            $rows = $wpdb->get_results($wpdb->prepare(
                'SELECT entity_type, entity_id, COUNT(*) AS c FROM ' . self::table() . ' WHERE ' . implode(' OR ', $where) . ' GROUP BY entity_type, entity_id',
                ...$params
            )) ?: [];
            foreach ($rows as $r) {
                $out[self::key((string) $r->entity_type, (int) $r->entity_id)] = (int) $r->c;
            }
            foreach ($missing as $type => $ids) {
                foreach ($ids as $id) {
                    $key = self::key($type, $id);
                    self::$counts[$key] = $out[$key];
                    wp_cache_set('count_' . $key, $out[$key], self::CACHE_GROUP, 600);
                }
            }
        }
        return $out;
    }

    public static function following_count(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id = %d', $user_id));
    }

    private static function flush_count(string $type, int $id): void
    {
        $key = self::key($type, $id);
        unset(self::$counts[$key]);
        wp_cache_delete('count_' . $key, self::CACHE_GROUP);
    }

    public static function follow(int $user_id, string $type, int $entity_id): bool
    {
        $type = self::normalize_type($type);
        if ($user_id <= 0 || !$type || $entity_id <= 0) {
            return false;
        }
        if (self::is_following($user_id, $type, $entity_id)) {
            return true;
        }
        global $wpdb;
        self::maybe_migrate();
        $ok = false !== $wpdb->insert(self::table(), [
            'user_id'        => $user_id,
            'entity_type'    => $type,
            'entity_id'      => $entity_id,
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
        ], ['%d', '%s', '%d', '%s']);
        if ($ok) {
            self::flush_count($type, $entity_id);
            Audit::log($type, $entity_id, 'followed', ['user_id' => $user_id]);
            do_action('artiey_followed', $user_id, $type, $entity_id);
        }
        return (bool) $ok;
    }

    public static function unfollow(int $user_id, string $type, int $entity_id): bool
    {
        $type = self::normalize_type($type);
        if ($user_id <= 0 || !$type || $entity_id <= 0) {
            return false;
        }
        global $wpdb;
        $deleted = $wpdb->delete(self::table(), [
            'user_id'     => $user_id,
            'entity_type' => $type,
            'entity_id'   => $entity_id,
        ], ['%d', '%s', '%d']);
        if ($deleted) {
            self::flush_count($type, $entity_id);
            Audit::log($type, $entity_id, 'unfollowed', ['user_id' => $user_id]);
            do_action('artiey_unfollowed', $user_id, $type, $entity_id);
        }
        return $deleted !== false;
    }

    /**
     * Validate and perform a follow change for the current request.
     * @return array{ok:bool,code:string,following:bool,count:int}
     */
    public static function apply(int $user_id, string $type, int $id, string $action): array
    {
        $type = self::normalize_type($type) ?? '';
        $result = ['ok' => false, 'code' => 'invalid', 'following' => false, 'count' => 0];
        if ($user_id <= 0) {
            $result['code'] = 'login';
            return $result;
        }
        $entity = $type !== '' ? self::entity($type, $id) : null;
        if (!$entity || !$entity['public']) {
            return $result;
        }
        if ((int) $entity['user_id'] === $user_id) {
            $result['code'] = 'self';
            $result['count'] = self::count($type, $id);
            return $result;
        }
        $rate_key = 'aet_follow_rl_' . $user_id;
        $hits = (int) get_transient($rate_key);
        if ($hits >= self::RATE_LIMIT) {
            $result['code'] = 'rate';
            $result['following'] = self::is_following($user_id, $type, $id);
            $result['count'] = self::count($type, $id);
            return $result;
        }
        set_transient($rate_key, $hits + 1, self::RATE_WINDOW);

        $now = self::is_following($user_id, $type, $id);
        if ($action === 'toggle') {
            $action = $now ? 'unfollow' : 'follow';
        }
        $ok = $action === 'unfollow' ? self::unfollow($user_id, $type, $id) : self::follow($user_id, $type, $id);
        return [
            'ok' => $ok,
            'code' => $ok ? 'ok' : 'error',
            'following' => self::is_following($user_id, $type, $id),
            'count' => self::count($type, $id),
        ];
    }

    /**
     * Profiles followed by a user, newest first, grouped by type.
     * @return array<string,array<int,array<string,mixed>>> artists|galleries|curators|collectors|posts => entities
     */
    public static function for_user(int $user_id, bool $public_only = false, int $limit = 200): array
    {
        $groups = ['artists' => [], 'galleries' => [], 'curators' => [], 'collectors' => [], 'posts' => []];
        if ($user_id <= 0) {
            return $groups;
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT entity_type, entity_id FROM ' . self::table() . ' WHERE user_id = %d ORDER BY created_at_utc DESC, id DESC LIMIT %d',
            $user_id,
            max(1, min(500, $limit))
        )) ?: [];
        $subjects = [];
        foreach ($rows as $r) {
            $type = self::normalize_type((string) $r->entity_type);
            if ($type) {
                $subjects[] = [$type, (int) $r->entity_id];
            }
        }
        self::hydrate($subjects);
        $group_of = ['artist' => 'artists', 'gallery' => 'galleries', 'curator' => 'curators', 'collector' => 'collectors', 'post' => 'posts'];
        foreach ($subjects as [$type, $id]) {
            $entity = self::$entities[self::key($type, $id)] ?? null;
            if (!$entity || ($public_only && !$entity['public'])) {
                continue;
            }
            $groups[$group_of[$type]][] = $entity;
        }
        return $groups;
    }

    /** User linked to a profile (artist/gallery/curator/collector user_id), or 0. */
    public static function owner_user(string $type, int $id): int
    {
        $entity = self::entity($type, $id);
        return $entity ? (int) $entity['user_id'] : 0;
    }

    /**
     * Public followers: users that own a public profile are listed; all others only counted.
     * @return array{total:int,items:array<int,array<string,mixed>>}
     */
    public static function public_followers(string $type, int $id, int $limit = 12): array
    {
        $type = self::normalize_type($type) ?? '';
        $total = $type !== '' ? self::count($type, $id) : 0;
        if ($total === 0) {
            return ['total' => 0, 'items' => []];
        }
        global $wpdb;
        $user_ids = $wpdb->get_col($wpdb->prepare(
            'SELECT user_id FROM ' . self::table() . ' WHERE entity_type = %s AND entity_id = %d ORDER BY created_at_utc DESC LIMIT 300',
            $type,
            $id
        )) ?: [];
        $user_ids = array_values(array_filter(array_map('intval', $user_ids)));
        if (!$user_ids) {
            return ['total' => $total, 'items' => []];
        }
        $in = implode(',', $user_ids);
        $items = [];
        $seen = [];
        foreach (['artist', 'curator', 'gallery', 'collector'] as $profile_type) {
            $table = Database::table(self::TABLES[$profile_type]);
            $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE user_id IN ({$in}) LIMIT 100") ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integer list
            foreach ($rows as $row) {
                $entity = self::from_row($profile_type, $row);
                if (!$entity || !$entity['public'] || isset($seen[$entity['user_id']])) {
                    continue;
                }
                $seen[$entity['user_id']] = true;
                self::$entities[self::key($profile_type, (int) $row->id)] = $entity;
                $items[] = $entity;
                if (count($items) >= $limit) {
                    break 2;
                }
            }
        }
        return ['total' => $total, 'items' => $items];
    }

    /* ------------------------------------------------------------------ assets */

    public static function register_assets(): void
    {
        $version = defined('AET_VERSION') ? (string) AET_VERSION : WP_ART_COA_VERSION;
        $base = defined('AET_URL') ? AET_URL : plugins_url('/', WP_ART_COA_FILE);
        wp_register_style('artiey-follow', $base . 'assets/css/follow.css', [], $version);
        wp_register_script('artiey-follow', $base . 'assets/js/follow.js', [], $version, ['in_footer' => true, 'strategy' => 'defer']);
        wp_localize_script('artiey-follow', 'ArtieyFollow', [
            'ajax'  => admin_url('admin-ajax.php'),
            'login' => wp_login_url(),
            'fa'    => I18n::is_fa(),
            'i18n'  => [
                'follow'    => I18n::t('follow'),
                'following' => I18n::t('following'),
                'unfollow'  => I18n::is_fa() ? 'لغو دنبال کردن' : 'Unfollow',
                'error'     => I18n::is_fa() ? 'خطا در ارتباط؛ دوباره تلاش کنید.' : 'Network error, please try again.',
                'rate'      => I18n::is_fa() ? 'تعداد درخواست‌ها زیاد است؛ کمی بعد تلاش کنید.' : 'Too many requests, try again shortly.',
                'self'      => I18n::is_fa() ? 'این پروفایل متعلق به شماست.' : 'This is your own profile.',
                'followed'  => I18n::is_fa() ? 'دنبال شد' : 'Followed',
                'unfollowed'=> I18n::is_fa() ? 'از فهرست دنبال‌شده‌ها حذف شد' : 'Unfollowed',
                'thousand'  => I18n::is_fa() ? 'هزار' : 'K',
                'million'   => I18n::is_fa() ? 'میلیون' : 'M',
            ],
        ]);
    }

    public static function enqueue(): void
    {
        if (!wp_style_is('artiey-follow', 'registered')) {
            self::register_assets();
        }
        wp_enqueue_style('artiey-follow');
        wp_enqueue_script('artiey-follow');
    }

    /* ------------------------------------------------------------------ formatting */

    public static function digits(string $text): string
    {
        return I18n::is_fa() ? strtr($text, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫']) : $text;
    }

    /** 950 → ۹۵۰ ، 1250 → ۱٫۳ هزار */
    public static function compact_number(int $n): string
    {
        $fa = I18n::is_fa();
        if ($n >= 1000000) {
            $text = rtrim(rtrim(number_format($n / 1000000, 1, '.', ''), '0'), '.') . ($fa ? ' میلیون' : 'M');
        } elseif ($n >= 1000) {
            $text = rtrim(rtrim(number_format($n / 1000, 1, '.', ''), '0'), '.') . ($fa ? ' هزار' : 'K');
        } else {
            $text = (string) $n;
        }
        return self::digits($text);
    }

    /* ------------------------------------------------------------------ markup */

    /**
     * @param array{show_count?:bool,label_follow?:string,label_following?:string,size?:string,style?:string,count_label?:string,icon?:bool} $o
     */
    public static function button_html(string $type, int $entity_id, array $o = []): string
    {
        $type = self::normalize_type($type);
        if (!$type || $entity_id <= 0) {
            return '';
        }
        $entity = self::entity($type, $entity_id);
        if (!$entity || (!$entity['public'] && !current_user_can('manage_options'))) {
            return '';
        }
        self::enqueue();
        $key = self::key($type, $entity_id);
        $count = self::count($type, $entity_id);
        $user_id = get_current_user_id();
        $own = $user_id && (int) $entity['user_id'] === $user_id;
        $following = $user_id && !$own ? self::is_following($user_id, $type, $entity_id) : false;
        $show_count = !array_key_exists('show_count', $o) || !empty($o['show_count']);
        $label_follow = trim((string) ($o['label_follow'] ?? '')) ?: I18n::t('follow');
        $label_following = trim((string) ($o['label_following'] ?? '')) ?: I18n::t('following');
        $label_unfollow = I18n::is_fa() ? 'لغو دنبال کردن' : 'Unfollow';
        $count_label = trim((string) ($o['count_label'] ?? '')) ?: I18n::t('followers');
        $size = in_array($o['size'] ?? 'md', ['sm', 'md', 'lg'], true) ? (string) ($o['size'] ?? 'md') : 'md';
        $style = in_array($o['style'] ?? 'solid', ['solid', 'outline', 'pill', 'ghost'], true) ? (string) ($o['style'] ?? 'solid') : 'solid';
        $icon = !array_key_exists('icon', $o) || !empty($o['icon']);
        $label = $following ? $label_following : $label_follow;
        $aria = sprintf(I18n::is_fa() ? '%1$s «%2$s»' : '%1$s %2$s', $label_follow, $entity['name']);

        $attrs = ' class="aet-follow-btn' . ($following ? ' is-following' : '') . '" data-aet-follow-btn'
            . ' data-label-follow="' . esc_attr($label_follow) . '" data-label-following="' . esc_attr($label_following) . '"'
            . ' data-label-unfollow="' . esc_attr($label_unfollow) . '" aria-pressed="' . ($following ? 'true' : 'false') . '"'
            . ' aria-label="' . esc_attr($aria) . '"';
        $inner = ($icon ? '<span class="aet-follow-icon" aria-hidden="true"></span>' : '')
            . '<span class="aet-follow-label">' . esc_html($label) . '</span>';

        $h = '<div class="aet-follow aet-follow--' . esc_attr($size) . ' aet-follow--' . esc_attr($style) . '" data-aet-follow="' . esc_attr($key) . '"'
            . ' data-following="' . ($following ? '1' : '0') . '" data-own="' . ($own ? '1' : '0') . '">';
        if ($own) {
            $h .= '<span class="aet-follow-btn is-own" aria-disabled="true">' . ($icon ? '<span class="aet-follow-icon" aria-hidden="true"></span>' : '')
                . '<span class="aet-follow-label">' . esc_html(I18n::is_fa() ? 'پروفایل شما' : 'Your profile') . '</span></span>';
        } elseif (!$user_id) {
            $return = self::current_url();
            $h .= '<a href="' . esc_url(wp_login_url($return)) . '" rel="nofollow"' . $attrs . '>' . $inner . '</a>';
        } else {
            $h .= '<form method="post" class="aet-follow-form">'
                . wp_nonce_field('art_coa_follow', '_wpnonce', false, false)
                . '<input type="hidden" name="art_coa_follow" value="1">'
                . '<input type="hidden" name="entity_type" value="' . esc_attr($type) . '">'
                . '<input type="hidden" name="entity_id" value="' . (int) $entity_id . '">'
                . '<input type="hidden" name="follow_action" value="' . ($following ? 'unfollow' : 'follow') . '">'
                . '<button type="submit"' . $attrs . '>' . $inner . '</button></form>';
        }
        if ($show_count) {
            $h .= '<span class="aet-follow-count"><strong data-aet-follow-count="' . (int) $count . '">' . esc_html(self::compact_number($count)) . '</strong> '
                . '<span class="aet-follow-count-label">' . esc_html($count_label) . '</span></span>';
        }
        $h .= '<span class="aet-follow-status screen-reader-text" role="status" aria-live="polite"></span>';
        return $h . '</div>';
    }

    /**
     * Followers / following panel for a profile.
     * @param array{avatars?:int,show_following?:bool,show_button?:bool,heading?:string,layout?:string,button?:array} $o
     */
    public static function followers_html(string $type, int $id, array $o = []): string
    {
        $type = self::normalize_type($type) ?? '';
        $entity = $type !== '' ? self::entity($type, $id) : null;
        if (!$entity || (!$entity['public'] && !current_user_can('manage_options'))) {
            return '';
        }
        self::enqueue();
        $avatars = max(0, min(48, (int) ($o['avatars'] ?? 8)));
        $show_following = !array_key_exists('show_following', $o) || !empty($o['show_following']);
        $show_button = !array_key_exists('show_button', $o) || !empty($o['show_button']);
        $layout = in_array($o['layout'] ?? 'card', ['card', 'inline', 'minimal'], true) ? (string) ($o['layout'] ?? 'card') : 'card';
        $fa = I18n::is_fa();
        $followers = self::public_followers($type, $id, max(1, $avatars));
        $owner = (int) $entity['user_id'];
        $following = $show_following && $owner ? self::for_user($owner, true, 60) : [];
        $following_items = $following ? array_merge($following['artists'], $following['galleries'], $following['curators'], $following['collectors'], $following['posts']) : [];
        $following_total = count($following_items);

        $h = '<section class="aet-followers aet-followers--' . esc_attr($layout) . '" data-aet-followers="' . esc_attr(self::key($type, $id)) . '">';
        $heading = trim((string) ($o['heading'] ?? ''));
        if ($heading !== '') {
            $h .= '<h3 class="aet-followers-title">' . esc_html($heading) . '</h3>';
        }
        $h .= '<div class="aet-followers-stats">';
        $h .= '<div class="aet-followers-stat"><strong data-aet-follow-count="' . (int) $followers['total'] . '">' . esc_html(self::compact_number($followers['total'])) . '</strong><span>' . esc_html($fa ? 'دنبال‌کننده' : 'Followers') . '</span></div>';
        if ($show_following && $owner) {
            $h .= '<div class="aet-followers-stat"><strong>' . esc_html(self::compact_number($following_total)) . '</strong><span>' . esc_html($fa ? 'دنبال‌شونده' : 'Following') . '</span></div>';
        }
        $h .= '</div>';
        if ($show_button) {
            $h .= self::button_html($type, $id, ($o['button'] ?? []) + ['show_count' => false]);
        }
        if ($avatars > 0 && $followers['items']) {
            $h .= self::people_list($followers['items'], $fa ? 'دنبال‌کنندگان' : 'Followers', $followers['total'] - count($followers['items']));
        }
        if ($avatars > 0 && $following_items) {
            $h .= self::people_list(array_slice($following_items, 0, $avatars), $fa ? 'دنبال می‌کند' : 'Follows', $following_total - min($avatars, $following_total));
        }
        return $h . '</section>';
    }

    /** @param array<int,array<string,mixed>> $items */
    private static function people_list(array $items, string $label, int $more): string
    {
        $h = '<div class="aet-followers-group"><span class="aet-followers-group-label">' . esc_html($label) . '</span><ul class="aet-followers-list">';
        foreach ($items as $e) {
            $initial = function_exists('mb_substr') ? mb_substr((string) $e['name'], 0, 1) : substr((string) $e['name'], 0, 1);
            $img = $e['image'] !== ''
                ? '<img src="' . esc_url($e['image']) . '" alt="" width="40" height="40" loading="lazy" decoding="async">'
                : '<span aria-hidden="true">' . esc_html($initial) . '</span>';
            $h .= '<li><a href="' . esc_url($e['url']) . '" title="' . esc_attr($e['name'] . ' — ' . $e['label']) . '">' . $img
                . '<span class="screen-reader-text">' . esc_html($e['name'] . ' (' . $e['label'] . ')') . '</span></a></li>';
        }
        if ($more > 0) {
            $h .= '<li class="aet-followers-more"><span>+' . esc_html(self::compact_number($more)) . '</span></li>';
        }
        return $h . '</ul></div>';
    }

    private static function current_url(): string
    {
        if (function_exists('is_singular') && is_singular() && get_permalink()) {
            return (string) get_permalink();
        }
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = (string) wp_parse_url($uri, PHP_URL_PATH);
        return home_url($path !== '' ? $path : '/');
    }

    /* ------------------------------------------------------------------ shortcodes */

    /**
     * [artiey_follow type="artist|gallery|curator|collector|auto" id="12" post="34" count="1" style="solid|outline|pill|ghost" size="sm|md|lg"]
     * [art_coa_follow_button type="artist" id="12"] (legacy)
     */
    public static function button_shortcode($atts = []): string
    {
        $atts = shortcode_atts([
            'type' => 'auto', 'id' => 0, 'post' => 0, 'count' => '1', 'style' => 'solid', 'size' => 'md',
            'label' => '', 'label_following' => '',
        ], (array) $atts, 'artiey_follow');
        $subject = self::shortcode_subject($atts);
        if (!$subject) {
            return '';
        }
        return self::button_html($subject[0], $subject[1], [
            'show_count' => !in_array(strtolower((string) $atts['count']), ['0', 'no', 'false', 'off'], true),
            'style' => (string) $atts['style'], 'size' => (string) $atts['size'],
            'label_follow' => sanitize_text_field((string) $atts['label']),
            'label_following' => sanitize_text_field((string) $atts['label_following']),
        ]);
    }

    /** [artiey_followers type="auto" id="" avatars="8" following="1" button="1" heading=""] */
    public static function followers_shortcode($atts = []): string
    {
        $atts = shortcode_atts([
            'type' => 'auto', 'id' => 0, 'post' => 0, 'avatars' => 8, 'following' => '1', 'button' => '1', 'heading' => '', 'layout' => 'card',
        ], (array) $atts, 'artiey_followers');
        $subject = self::shortcode_subject($atts);
        if (!$subject) {
            return '';
        }
        $on = static fn($v): bool => !in_array(strtolower((string) $v), ['0', 'no', 'false', 'off', ''], true);
        return self::followers_html($subject[0], $subject[1], [
            'avatars' => (int) $atts['avatars'], 'show_following' => $on($atts['following']), 'show_button' => $on($atts['button']),
            'heading' => sanitize_text_field((string) $atts['heading']), 'layout' => sanitize_key((string) $atts['layout']),
        ]);
    }

    /** @param array<string,mixed> $atts */
    private static function shortcode_subject(array $atts): ?array
    {
        $post = absint($atts['post'] ?? 0);
        if ($post) {
            return self::subject_for_post($post);
        }
        $type = sanitize_key((string) ($atts['type'] ?? 'auto'));
        $id = absint($atts['id'] ?? 0);
        if ($type === 'auto' || $type === '') {
            return self::context_subject();
        }
        if (!$id) {
            return self::context_subject($type);
        }
        return self::subject($type, $id);
    }

    public static function dashboard_html(int $user_id = 0): string
    {
        $user_id = $user_id ?: get_current_user_id();
        if (!$user_id) {
            return '';
        }
        self::enqueue();
        $data = self::for_user($user_id);
        $fa = I18n::is_fa();
        $total = array_sum(array_map('count', $data));
        ob_start();
        echo '<div class="art-coa-my-follows aet-my-follows">';
        echo '<h3>' . esc_html(I18n::t('my_favorites')) . ' <span class="aet-my-follows-total">' . esc_html(self::digits((string) $total)) . '</span></h3>';
        if ($total === 0) {
            echo '<p class="art-coa-empty">' . esc_html($fa ? 'هنوز هنرمند، گالری یا کیوریتوری را دنبال نکرده‌اید.' : I18n::t('no_follows')) . '</p>';
            echo '</div>';
            return (string) ob_get_clean();
        }
        $types = ['artists' => 'artist', 'galleries' => 'gallery', 'curators' => 'curator', 'collectors' => 'collector', 'posts' => 'post'];
        foreach ($types as $group => $type) {
            if (!$data[$group]) {
                continue;
            }
            echo '<h4>' . esc_html(self::type_label($type, true)) . '</h4><ul class="aet-follow-cards">';
            foreach ($data[$group] as $e) {
                $initial = function_exists('mb_substr') ? mb_substr((string) $e['name'], 0, 1) : substr((string) $e['name'], 0, 1);
                echo '<li class="aet-follow-card">';
                echo '<a class="aet-follow-card-media" href="' . esc_url($e['url']) . '" tabindex="-1" aria-hidden="true">'
                    . ($e['image'] !== '' ? '<img src="' . esc_url($e['image']) . '" alt="" loading="lazy" width="56" height="56">' : '<span>' . esc_html($initial) . '</span>') . '</a>';
                echo '<div class="aet-follow-card-body"><a href="' . esc_url($e['url']) . '">' . esc_html($e['name']) . '</a><small>' . esc_html($e['label']) . '</small></div>';
                echo self::button_html($e['type'], (int) $e['id'], ['size' => 'sm', 'style' => 'outline', 'show_count' => false]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in button_html
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    public static function dashboard_shortcode($atts = []): string
    {
        return self::dashboard_html();
    }

    /* ------------------------------------------------------------------ request handlers */

    /** No-JavaScript fallback: classic form POST, then redirect back. */
    public static function handle(): void
    {
        if (empty($_POST['art_coa_follow']) || !is_user_logged_in()) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash((string) ($_POST['_wpnonce'] ?? ''))), 'art_coa_follow')) {
            return;
        }
        $type = self::normalize_type(sanitize_key(Security::input_scalar($_POST, 'entity_type')));
        $id = absint($_POST['entity_id'] ?? 0);
        $action = sanitize_key(Security::input_scalar($_POST, 'follow_action', 'follow'));
        if (!$type || !$id) {
            return;
        }
        self::apply(get_current_user_id(), $type, $id, $action === 'unfollow' ? 'unfollow' : 'follow');
        wp_safe_redirect(wp_get_referer() ?: home_url('/art-coa/member-dashboard/'));
        exit;
    }

    public static function ajax_toggle(): void
    {
        nocache_headers();
        if (!is_user_logged_in()) {
            $ref = wp_get_referer();
            wp_send_json_error(['code' => 'login', 'login' => wp_login_url($ref ?: home_url('/'))], 401);
        }
        if (!check_ajax_referer('artiey_follow', 'nonce', false)) {
            wp_send_json_error(['code' => 'nonce', 'nonce' => wp_create_nonce('artiey_follow')], 403);
        }
        $parsed = self::parse_key(sanitize_text_field(wp_unslash((string) ($_POST['subject'] ?? ''))));
        $action = sanitize_key(wp_unslash((string) ($_POST['follow_action'] ?? 'toggle')));
        if (!$parsed || !in_array($action, ['follow', 'unfollow', 'toggle'], true)) {
            wp_send_json_error(['code' => 'invalid'], 400);
        }
        $r = self::apply(get_current_user_id(), $parsed[0], $parsed[1], $action);
        $payload = [
            'code' => $r['code'], 'subject' => self::key($parsed[0], $parsed[1]), 'following' => $r['following'],
            'count' => $r['count'], 'countText' => self::compact_number($r['count']),
        ];
        if ($r['ok']) {
            wp_send_json_success($payload);
        }
        wp_send_json_error($payload, $r['code'] === 'rate' ? 429 : ($r['code'] === 'invalid' ? 404 : 400));
    }

    /** Fresh state for every button on a (possibly cached) page + a fresh nonce for logged-in users. */
    public static function ajax_state(): void
    {
        nocache_headers();
        $raw = isset($_POST['subjects']) ? wp_unslash($_POST['subjects']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed by parse_key
        $raw = is_array($raw) ? $raw : explode(',', (string) $raw);
        $subjects = [];
        foreach (array_slice($raw, 0, self::MAX_STATE_SUBJECTS) as $item) {
            $parsed = is_scalar($item) ? self::parse_key((string) $item) : null;
            if ($parsed) {
                $subjects[self::key($parsed[0], $parsed[1])] = $parsed;
            }
        }
        $subjects = array_values($subjects);
        $user_id = get_current_user_id();
        $counts = self::counts($subjects);
        $map = self::following_map($user_id, $subjects);
        self::hydrate($subjects);
        $states = [];
        foreach ($subjects as [$type, $id]) {
            $key = self::key($type, $id);
            $entity = self::$entities[$key] ?? null;
            $states[$key] = [
                'following' => (bool) ($map[$key] ?? false),
                'count' => (int) ($counts[$key] ?? 0),
                'countText' => self::compact_number((int) ($counts[$key] ?? 0)),
                'own' => $user_id > 0 && $entity && (int) $entity['user_id'] === $user_id,
            ];
        }
        wp_send_json_success([
            'loggedIn' => $user_id > 0,
            'nonce' => $user_id > 0 ? wp_create_nonce('artiey_follow') : '',
            'states' => (object) $states,
        ]);
    }
}
