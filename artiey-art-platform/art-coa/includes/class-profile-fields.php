<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

/**
 * Single definition of the "complete profile" fields added to artists, galleries, artworks and exhibitions.
 * One place drives: DB columns, sanitising, admin/member form fields, public facts, social links, JSON-LD and the
 * post-type sync - so a field is added once and shows up everywhere.
 *
 * kind: text | textarea | year | social | phone | url | geo | tags
 */
final class ProfileFields
{
    /**
     * table => field => [kind, column definition, post-type meta key]. Columns that already exist in the base schema
     * (nationality, artistic_statement, visitor_info, material, provenance, curator_name…) are not repeated here.
     * @var array<string,array<string,array{kind:string,col:string,meta:string}>>
     */
    public const DEF = [
        'artists' => [
            'name_en'    => ['kind' => 'text',     'col' => 'VARCHAR(200) NULL',  'meta' => '_aa_name_en'],
            'birth_year' => ['kind' => 'year',     'col' => 'SMALLINT UNSIGNED NULL', 'meta' => '_aa_birth'],
            'city'       => ['kind' => 'text',     'col' => 'VARCHAR(100) NULL',  'meta' => '_aa_city'],
            'short_bio'  => ['kind' => 'textarea', 'col' => 'VARCHAR(500) NULL',  'meta' => '_aa_short_bio'],
            'mediums'    => ['kind' => 'tags',     'col' => 'VARCHAR(300) NULL',  'meta' => '_aa_mediums'],
            'education'  => ['kind' => 'textarea', 'col' => 'TEXT NULL',          'meta' => '_aa_education'],
            'awards'     => ['kind' => 'textarea', 'col' => 'TEXT NULL',          'meta' => '_aa_awards'],
            'instagram'  => ['kind' => 'social',   'col' => 'VARCHAR(100) NULL',  'meta' => '_aa_instagram'],
            'telegram'   => ['kind' => 'social',   'col' => 'VARCHAR(100) NULL',  'meta' => '_aa_telegram'],
        ],
        'galleries' => [
            'name_en'      => ['kind' => 'text',     'col' => 'VARCHAR(200) NULL', 'meta' => '_ag_name_en'],
            'founded_year' => ['kind' => 'year',     'col' => 'SMALLINT UNSIGNED NULL', 'meta' => '_ag_founded'],
            'short_bio'    => ['kind' => 'textarea', 'col' => 'VARCHAR(500) NULL', 'meta' => '_ag_short_bio'],
            'hours'        => ['kind' => 'textarea', 'col' => 'VARCHAR(800) NULL', 'meta' => ''],
            'instagram'    => ['kind' => 'social',   'col' => 'VARCHAR(100) NULL', 'meta' => '_ag_instagram'],
            'telegram'     => ['kind' => 'social',   'col' => 'VARCHAR(100) NULL', 'meta' => '_ag_telegram'],
            'whatsapp'     => ['kind' => 'phone',    'col' => 'VARCHAR(30) NULL',  'meta' => '_ag_whatsapp'],
            'lat'          => ['kind' => 'geo',      'col' => 'DECIMAL(10,7) NULL', 'meta' => '_ag_lat'],
            'lng'          => ['kind' => 'geo',      'col' => 'DECIMAL(10,7) NULL', 'meta' => '_ag_lng'],
        ],
        'artworks' => [
            'title_en'         => ['kind' => 'text', 'col' => 'VARCHAR(255) NULL', 'meta' => '_aw_title_en'],
            'edition'          => ['kind' => 'text', 'col' => 'VARCHAR(100) NULL', 'meta' => '_aw_edition'],
            'accession_number' => ['kind' => 'text', 'col' => 'VARCHAR(100) NULL', 'meta' => '_aw_accession'],
        ],
        'exhibitions' => [
            'opening_note' => ['kind' => 'text', 'col' => 'VARCHAR(300) NULL', 'meta' => '_ae_opening'],
            'website'      => ['kind' => 'url',  'col' => 'VARCHAR(300) NULL', 'meta' => '_ae_link'],
        ],
    ];

    /** @return array<string,string> field => column definition, for one table */
    public static function columns(string $table): array
    {
        $out = [];
        foreach (self::DEF[$table] ?? [] as $f => $d) {
            $out[$f] = $d['col'];
        }
        return $out;
    }

    /** @return string[] */
    public static function fields(string $table): array
    {
        return array_keys(self::DEF[$table] ?? []);
    }

    public static function kind(string $table, string $field): string
    {
        return self::DEF[$table][$field]['kind'] ?? 'text';
    }

    /** Updater schema entries (all 'raw': values are normalised by clean()). @return array<string,string> */
    public static function schema(string $table): array
    {
        return array_fill_keys(self::fields($table), 'raw');
    }

    /**
     * DB-ready values for every profile field PRESENT in $data (absent keys are left alone, so partial updates are safe).
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function clean(string $table, array $data): array
    {
        $out = [];
        foreach (self::DEF[$table] ?? [] as $f => $d) {
            if (!array_key_exists($f, $data)) {
                continue;
            }
            $v = $data[$f];
            $out[$f] = match ($d['kind']) {
                'year'     => self::year($v),
                'social'   => self::handle($v),
                'phone'    => self::phone($v),
                'geo'      => self::geo($v, $f),
                'url'      => ($u = esc_url_raw((string) $v)) !== '' ? $u : null,
                'textarea' => ($t = sanitize_textarea_field((string) $v)) !== '' ? $t : null,
                'tags'     => self::tags($v),
                default    => ($t = sanitize_text_field((string) $v)) !== '' ? $t : null,
            };
        }
        return $out;
    }

    /** Merge cleaned profile fields into an Updater input array. */
    public static function prepare(string $table, array $data): array
    {
        return array_merge($data, self::clean($table, $data));
    }

    /* ------------------------------------------------------------------ normalisers */

    private static function digits(string $s): string
    {
        return strtr($s, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
    }

    /** Four-digit year (Gregorian or Jalali); anything else -> null. */
    private static function year($v): ?int
    {
        $y = (int) preg_replace('/\D+/', '', self::digits((string) $v));
        if ($y >= 1200 && $y <= 2100) {
            return $y;
        }
        // "1360/05/12" or "1981-03-02": take the first four digits
        if (preg_match('/(\d{4})/', self::digits((string) $v), $m) && (int) $m[1] >= 1200 && (int) $m[1] <= 2100) {
            return (int) $m[1];
        }
        return null;
    }

    /** Instagram / Telegram handle from a handle, @handle or full URL. */
    private static function handle($v): ?string
    {
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        $s = preg_replace('#^https?://(www\.)?(instagram\.com|t\.me|telegram\.me)/#i', '', $s) ?? $s;
        $s = ltrim(explode('?', explode('/', $s)[0])[0], '@');
        return preg_match('/^[A-Za-z0-9._]{2,60}$/', $s) ? $s : null;
    }

    private static function phone($v): ?string
    {
        $s = preg_replace('/[^\d+]/', '', self::digits((string) $v)) ?? '';
        return strlen(ltrim($s, '+')) >= 7 && strlen($s) <= 20 ? $s : null;
    }

    private static function geo($v, string $field): ?string
    {
        $s = self::digits(trim((string) $v));
        if ($s === '' || !is_numeric($s)) {
            return null;
        }
        $n = (float) $s;
        $max = $field === 'lat' ? 90.0 : 180.0;
        return abs($n) <= $max ? number_format($n, 7, '.', '') : null;
    }

    /** Comma/newline/Persian-comma separated list -> "a، b، c" (max 12). */
    private static function tags($v): ?string
    {
        $parts = preg_split('/[,\n،;]+/u', (string) $v) ?: [];
        $parts = array_slice(array_values(array_unique(array_filter(array_map(static fn($p) => sanitize_text_field($p), $parts)))), 0, 12);
        return $parts ? implode('، ', $parts) : null;
    }

    /**
     * Values for the mirror columns, read from the post-type meta of $post_id (the post is the editor; this is one-way).
     * @return array<string,mixed>
     */
    public static function from_meta(string $table, int $post_id): array
    {
        $raw = [];
        foreach (self::DEF[$table] ?? [] as $f => $d) {
            if ($d['meta'] === '') {
                continue;
            }
            $v = get_post_meta($post_id, $d['meta'], true);
            $raw[$f] = is_scalar($v) ? (string) $v : '';
        }
        if ($table === 'galleries') {
            $raw['hours'] = self::hours_text($post_id);
        }
        return self::clean($table, $raw);
    }

    /** Weekly hours (post meta array, keyed by PHP weekday 0=Sunday) -> one line per day, Persian week order. */
    private static function hours_text(int $post_id): string
    {
        $h = get_post_meta($post_id, '_ag_hours', true);
        $names = [6 => 'شنبه', 0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه'];
        $lines = [];
        if (is_array($h)) {
            foreach ($names as $w => $label) {
                $d = isset($h[$w]) && is_array($h[$w]) ? $h[$w] : [];
                if (!empty($d['closed'])) {
                    $lines[] = $label . ': ' . I18n::t('pf_closed');
                    continue;
                }
                $slots = [];
                foreach ([['o1', 'c1'], ['o2', 'c2']] as [$o, $c]) {
                    if (!empty($d[$o]) && !empty($d[$c])) {
                        $slots[] = $d[$o] . '–' . $d[$c];
                    }
                }
                if ($slots) {
                    $lines[] = $label . ': ' . implode('، ', $slots);
                }
            }
        }
        $note = get_post_meta($post_id, '_ag_hours_note', true);
        if (is_string($note) && trim($note) !== '') {
            $lines[] = trim($note);
        }
        return implode("\n", $lines);
    }

    /* ------------------------------------------------------------------ public output */

    public static function social_url(string $field, string $value): string
    {
        return match ($field) {
            'instagram' => 'https://instagram.com/' . rawurlencode($value),
            'telegram'  => 'https://t.me/' . rawurlencode($value),
            'whatsapp'  => 'https://wa.me/' . ltrim($value, '+'),
            default     => '',
        };
    }

    /** @return string[] absolute URLs of the social profiles (for sameAs and links) */
    public static function social_urls(string $table, object $row): array
    {
        $urls = [];
        foreach (self::DEF[$table] ?? [] as $f => $d) {
            if (in_array($d['kind'], ['social', 'phone'], true) && !empty($row->{$f})) {
                $u = self::social_url($f, (string) $row->{$f});
                if ($u !== '') {
                    $urls[$f] = $u;
                }
            }
        }
        return $urls;
    }

    /** "Facts" list + social links for a profile page. Echoes escaped HTML; prints nothing when there is no data. */
    public static function render(string $table, object $row): void
    {
        $facts = [];
        $add = static function (string $label, string $value) use (&$facts): void {
            if ($value !== '') {
                $facts[] = [$label, $value];
            }
        };
        $n = static fn($v): string => $v ? Catalog::n($v) : '';
        $e = static fn($v): string => esc_html((string) $v);

        switch ($table) {
            case 'artists':
                $add(I18n::t('pf_name_en'), $e($row->name_en ?? ''));
                $add(I18n::t('pf_birth_year'), $n($row->birth_year ?? 0));
                $add(I18n::t('city'), $e($row->city ?? ''));
                $add(I18n::t('pf_nationality'), $e($row->nationality ?? ''));
                $add(I18n::t('pf_mediums'), $e($row->mediums ?? ''));
                break;
            case 'galleries':
                $add(I18n::t('pf_name_en'), $e($row->name_en ?? ''));
                $add(I18n::t('pf_founded_year'), $n($row->founded_year ?? 0));
                $add(I18n::t('pf_hours'), nl2br($e($row->hours ?? '')));
                $add(I18n::t('pf_visitor_info'), nl2br($e($row->visitor_info ?? '')));
                if (!empty($row->lat) && !empty($row->lng)) {
                    $url = 'https://www.google.com/maps?q=' . rawurlencode($row->lat . ',' . $row->lng);
                    $add(I18n::t('pf_map'), '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . $e(I18n::t('pf_open_map')) . '</a>');
                }
                break;
            case 'artworks':
                $add(I18n::t('pf_title_en'), $e($row->title_en ?? ''));
                $add(I18n::t('pf_edition'), $e($row->edition ?? ''));
                $add(I18n::t('pf_material'), $e($row->material ?? ''));
                $add(I18n::t('pf_condition_note'), $e($row->condition_note ?? ''));
                $add(I18n::t('pf_accession_number'), $e($row->accession_number ?? ''));
                break;
            case 'exhibitions':
                $add(I18n::t('pf_opening_note'), $e($row->opening_note ?? ''));
                $add(I18n::t('pf_admission_info'), $e($row->admission_info ?? ''));
                if (!empty($row->website)) {
                    $add(I18n::t('website'), '<a href="' . esc_url($row->website) . '" target="_blank" rel="noopener">' . $e(wp_parse_url($row->website, PHP_URL_HOST) ?: $row->website) . '</a>');
                }
                break;
        }

        if ($facts) {
            echo '<dl class="art-coa-facts art-coa-facts--extra">';
            foreach ($facts as [$l, $v]) {
                echo '<dt>' . esc_html($l) . '</dt><dd>' . $v . '</dd>'; // phpcs:ignore -- escaped above
            }
            echo '</dl>';
        }
        self::render_social($table, $row);
    }

    /** Social link pills only (the artist page prints facts inside the biography section). */
    public static function render_social(string $table, object $row): void
    {
        $social = self::social_urls($table, $row);
        if ($social) {
            echo '<ul class="art-coa-social" aria-label="' . esc_attr(I18n::t('pf_social')) . '">';
            foreach ($social as $f => $u) {
                echo '<li><a href="' . esc_url($u) . '" target="_blank" rel="noopener me">' . esc_html(I18n::t('pf_' . $f)) . '</a></li>';
            }
            echo '</ul>';
        }
    }

    /** Long-form sections (education, awards, provenance, short bio). */
    public static function render_sections(string $table, object $row): void
    {
        $map = ['artists' => ['artistic_statement', 'education', 'awards'], 'artworks' => ['provenance']][$table] ?? [];
        foreach ($map as $f) {
            if (!empty($row->{$f})) {
                echo '<section class="art-coa-section"><h2>' . esc_html(I18n::t('pf_' . $f)) . '</h2><div class="art-coa-bio">'
                    . wp_kses_post(wpautop(esc_html((string) $row->{$f}))) . '</div></section>';
            }
        }
    }

    /** Stored years are Jalali (as entered in the editor); schema.org wants Gregorian (approximate, year only). */
    private static function gregorian_year(int $y): string
    {
        return (string) ($y < 1700 ? $y + 621 : $y);
    }

    /** Extra schema.org properties for a profile. @return array<string,mixed> */
    public static function schema_extra(string $table, object $row): array
    {
        $d = [];
        $same = array_values(self::social_urls($table, $row));
        if ($table === 'artists') {
            if (!empty($row->name_en)) { $d['alternateName'] = $row->name_en; }
            if (!empty($row->birth_year)) { $d['birthDate'] = self::gregorian_year((int) $row->birth_year); }
            if (!empty($row->city)) { $d['homeLocation'] = ['@type' => 'Place', 'name' => $row->city]; }
            if (!empty($row->nationality)) { $d['nationality'] = $row->nationality; }
            if (!empty($row->mediums)) { $d['knowsAbout'] = array_map('trim', explode('،', (string) $row->mediums)); }
            if (!empty($row->awards)) { $d['award'] = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $row->awards) ?: []))); }
            if (!empty($row->education)) { $d['alumniOf'] = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $row->education) ?: []))); }
        } elseif ($table === 'galleries') {
            if (!empty($row->name_en)) { $d['alternateName'] = $row->name_en; }
            if (!empty($row->founded_year)) { $d['foundingDate'] = self::gregorian_year((int) $row->founded_year); }
            if (!empty($row->hours)) { $d['openingHours'] = trim((string) $row->hours); }
            if (!empty($row->lat) && !empty($row->lng)) {
                $d['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $row->lat, 'longitude' => (float) $row->lng];
            }
        } elseif ($table === 'artworks') {
            if (!empty($row->title_en)) { $d['alternateName'] = $row->title_en; }
            if (!empty($row->edition)) { $d['artEdition'] = $row->edition; }
            if (!empty($row->material)) { $d['material'] = $row->material; }
            if (!empty($row->accession_number)) { $d['identifier'] = $row->accession_number; }
        } elseif ($table === 'exhibitions') {
            if (!empty($row->website)) { $d['sameAs'] = [$row->website]; }
        }
        if ($same && $table !== 'exhibitions') {
            $d['sameAs'] = $same;
        }
        return $d;
    }
}
