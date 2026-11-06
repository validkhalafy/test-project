<?php
declare(strict_types=1);

namespace WPArtCOA;

use WPArtCOA\Entities\Artist;

defined('ABSPATH') || exit;

/**
 * Artist biography section: lead, biography (with "read more"), quick facts, artist statement and
 * education / awards timelines. Used by the public artist page, the shortcode and the Elementor widget.
 * Pure server-rendered HTML (no JavaScript); every part can be switched off through $opts.
 */
final class ArtistBio
{
    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        $g = class_exists('\\WPArtCOA\\Admin\\Settings') ? \WPArtCOA\Admin\Settings::get() : [];
        $on = static fn(string $k): bool => !array_key_exists('bio_' . $k, $g) || !empty($g['bio_' . $k]);
        return apply_filters('art_coa_artist_bio_defaults', [
            'title' => (($g['bio_title'] ?? 'بیوگرافی') === 'بیوگرافی' || ($g['bio_title'] ?? '') === '') ? I18n::t('bio_title') : (string) $g['bio_title'], 'layout' => (string) ($g['bio_layout'] ?? 'split'), 'title_tag' => 'h2',
            'show_title' => $on('show_title'), 'show_biography' => true, 'show_lead' => $on('show_lead'), 'show_facts' => $on('show_facts'), 'show_statement' => $on('show_statement'),
            'show_education' => $on('show_education'), 'show_awards' => $on('show_awards'),
            'readmore' => $on('readmore'), 'visible_paragraphs' => (int) ($g['bio_paragraphs'] ?? 2),
            'more_label' => I18n::t('bio_more'), 'less_label' => I18n::t('bio_less'),
        ]);
    }

    /** Lines of a free-text field as timeline items: "۱۴۰۰ - متن" becomes [year, text]. @return array<int,array{0:string,1:string}> */
    public static function timeline_items(string $text): array
    {
        $items = [];
        foreach (preg_split('/\R+/u', trim($text)) ?: [] as $index => $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(?:\x{200F}|\x{200E})?\s*([0-9\x{06F0}-\x{06F9}\x{0660}-\x{0669}]{4})\s*[-–—:،.]*\s*(.+)$/u', $line, $m)) {
                $items[] = ['year' => $m[1], 'text' => trim($m[2]), 'order' => (int) $index];
            } else {
                $items[] = ['year' => '', 'text' => $line, 'order' => (int) $index];
            }
        }
        usort($items, static function (array $a, array $b): int {
            $year_a = $a['year'] !== '' ? (int) strtr($a['year'], ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']) : 0;
            $year_b = $b['year'] !== '' ? (int) strtr($b['year'], ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']) : 0;
            if ($year_a && $year_b && $year_a !== $year_b) { return $year_a <=> $year_b; }
            if (!$year_a && $year_b) { return 1; }
            if ($year_a && !$year_b) { return -1; }
            return $a['order'] <=> $b['order'];
        });
        return array_map(static fn(array $item): array => [$item['year'], $item['text']], $items);
    }

    private static function timeline(string $heading, string $text, string $cls): string
    {
        $items = self::timeline_items($text);
        if (!$items) {
            return '';
        }
        $h = '<div class="art-coa-bio-block ' . esc_attr($cls) . '"><h3 class="art-coa-bio-sub">' . esc_html($heading) . '</h3><ol class="art-coa-bio-tl">';
        foreach ($items as [$year, $txt]) {
            $h .= '<li class="art-coa-bio-tl-item">' . ($year !== '' ? '<span class="art-coa-bio-tl-year">' . esc_html(Catalog::n($year)) . '</span>' : '')
                . '<span class="art-coa-bio-tl-text">' . esc_html($txt) . '</span></li>';
        }
        return $h . '</ol></div>';
    }

    /** Paragraphs of a cleaned biography. @return string[] */
    private static function paragraphs(string $html): array
    {
        $html = class_exists('Artiey_AET_Profile_Guard') ? \Artiey_AET_Profile_Guard::clean_markup($html) : $html;
        if (trim(wp_strip_all_tags($html)) === '') {
            return [];
        }
        $html = wp_kses_post(wpautop($html));
        preg_match_all('#<(?:p|ul|ol|blockquote|h[2-6])\b.*?</(?:p|ul|ol|blockquote|h[2-6])>#su', $html, $m);
        return $m[0] ?: (trim(wp_strip_all_tags($html)) !== '' ? [$html] : []);
    }

    /** @param array<string,mixed> $opts */
    public static function render(object $artist, array $opts = []): string
    {
        $o = array_merge(self::defaults(), $opts);
        $paras = !empty($o['show_biography']) ? self::paragraphs((string) ($artist->bio ?? '')) : [];
        $short = trim((string) ($artist->short_bio ?? ''));
        $lead  = (!empty($o['show_lead']) && $short !== '') ? $short : '';

        $facts = [];
        if (!empty($o['show_facts'])) {
            $add = static function (string $l, string $v) use (&$facts): void {
                if ($v !== '') {
                    $facts[] = [$l, $v];
                }
            };
            $add(I18n::t('pf_name_en'), (string) ($artist->name_en ?? ''));
            $add(I18n::t('pf_birth_year'), !empty($artist->birth_year) ? Catalog::n($artist->birth_year) : '');
            $add(I18n::t('city'), (string) ($artist->city ?? ''));
            $add(I18n::t('pf_nationality'), (string) ($artist->nationality ?? ''));
            $add(I18n::t('pf_mediums'), (string) ($artist->mediums ?? ''));
        }
        $statement = !empty($o['show_statement']) ? trim((string) ($artist->artistic_statement ?? '')) : '';
        $edu = !empty($o['show_education']) ? self::timeline(I18n::t('pf_education'), (string) ($artist->education ?? ''), 'is-education') : '';
        $awd = !empty($o['show_awards']) ? self::timeline(I18n::t('pf_awards'), (string) ($artist->awards ?? ''), 'is-awards') : '';

        if (!$paras && $lead === '' && !$facts && $statement === '' && $edu === '' && $awd === '') {
            return '';
        }

        $layout = in_array($o['layout'], ['split', 'stacked'], true) ? (string) $o['layout'] : 'split';
        $tag    = in_array($o['title_tag'], ['h2', 'h3', 'h4', 'div'], true) ? (string) $o['title_tag'] : 'h2';
        $uid    = 'art-coa-bio-' . (int) ($artist->id ?? 0);
        $has_side = (bool) $facts;

        $h  = '<section class="art-coa-bio-sec is-' . esc_attr($layout) . ($has_side ? ' has-side' : '') . '" id="' . esc_attr($uid) . '"'
            . (!empty($o['show_title']) ? ' aria-labelledby="' . esc_attr($uid) . '-t"' : '') . '>';
        if (!empty($o['show_title']) || $lead !== '') {
            $h .= '<header class="art-coa-bio-head">';
            if (!empty($o['show_title'])) {
                $h .= '<' . $tag . ' class="art-coa-bio-title" id="' . esc_attr($uid) . '-t">' . esc_html((string) $o['title']) . '</' . $tag . '>';
            }
            if ($lead !== '') {
                $h .= '<p class="art-coa-bio-lead">' . esc_html($lead) . '</p>';
            }
            $h .= '</header>';
        }

        $h .= '<div class="art-coa-bio-grid"><div class="art-coa-bio-main">';
        if ($paras) {
            $vis  = max(1, (int) $o['visible_paragraphs']);
            $head = array_slice($paras, 0, $vis);
            $rest = array_slice($paras, $vis);
            $h   .= '<div class="art-coa-bio-text">' . implode('', $head) . '</div>';
            if ($rest) {
                if (!empty($o['readmore'])) {
                    $h .= '<details class="art-coa-bio-more"><summary><span class="when-closed">' . esc_html((string) $o['more_label']) . '</span><span class="when-open">' . esc_html((string) $o['less_label']) . '</span></summary><div class="art-coa-bio-text">' . implode('', $rest) . '</div></details>';
                } else {
                    $h .= '<div class="art-coa-bio-text">' . implode('', $rest) . '</div>';
                }
            }
        }
        if ($statement !== '') {
            $h .= '<blockquote class="art-coa-bio-statement"><h3 class="art-coa-bio-sub">' . esc_html(I18n::t('pf_artistic_statement')) . '</h3>' . wp_kses_post(wpautop(esc_html($statement))) . '</blockquote>';
        }
        $h .= '</div>';
        if ($facts) {
            $h .= '<aside class="art-coa-bio-side"><dl class="art-coa-bio-facts">';
            foreach ($facts as [$l, $v]) {
                $h .= '<div class="art-coa-bio-fact"><dt>' . esc_html($l) . '</dt><dd>' . esc_html($v) . '</dd></div>';
            }
            $h .= '</dl></aside>';
        }
        $h .= '</div>';
        if ($edu !== '' || $awd !== '') {
            $h .= '<div class="art-coa-bio-cols' . (($edu !== '' && $awd !== '') ? ' is-two' : '') . '">' . $edu . $awd . '</div>';
        }
        return $h . '</section>';
    }

    /** [art_coa_artist_bio artist="12" slug="" title="" layout="split" lead="yes" facts="yes" statement="yes" education="yes" awards="yes" readmore="yes" paragraphs="2"] */
    public static function shortcode($atts = []): string
    {
        // Empty attribute = inherit the global setting (Platform settings > Artist biography section).
        $a = shortcode_atts([
            'artist' => 0, 'slug' => '', 'title' => '', 'layout' => '', 'title_tag' => '', 'show_title' => '',
            'lead' => '', 'facts' => '', 'statement' => '', 'education' => '', 'awards' => '',
            'readmore' => '', 'paragraphs' => '',
        ], (array) $atts, 'art_coa_artist_bio');
        $row = self::resolve((int) $a['artist'], (string) $a['slug']);
        if (!$row) {
            return '';
        }
        $opts = [];
        foreach (['show_title' => 'show_title', 'lead' => 'show_lead', 'facts' => 'show_facts', 'statement' => 'show_statement',
            'education' => 'show_education', 'awards' => 'show_awards', 'readmore' => 'readmore'] as $attr => $key) {
            if ($a[$attr] !== '') {
                $opts[$key] = in_array(strtolower((string) $a[$attr]), ['yes', '1', 'true'], true);
            }
        }
        foreach (['layout' => 'layout', 'title_tag' => 'title_tag'] as $attr => $key) {
            if ($a[$attr] !== '') {
                $opts[$key] = (string) $a[$attr];
            }
        }
        if ($a['paragraphs'] !== '') {
            $opts['visible_paragraphs'] = (int) $a['paragraphs'];
        }
        if ($a['title'] !== '') {
            $opts['title'] = sanitize_text_field((string) $a['title']);
        }
        return self::render($row, $opts);
    }

    /**
     * Resolve an artist from the stable COA ID, canonical WordPress slug, or current single-artist post.
     * Editorial fields are returned from the WordPress post; its linked table ID stays available for COA joins.
     */
    public static function resolve(int $id, string $slug = ''): ?object
    {
        $row = $id > 0 ? Artist::get($id) : null;
        if (!$row) {
            $raw_slug = $slug !== '' ? $slug : (string) get_query_var('art_coa_slug');
            $slug = sanitize_title($raw_slug);
            if ($slug !== '') {
                $row = Artist::get_by_slug($slug);
                if (!$row) {
                    $post = get_page_by_path($slug, OBJECT, 'artiey_artist');
                    if ($post instanceof \WP_Post && class_exists('\WPArtCOA\ContentBridge')) {
                        $row_id = \WPArtCOA\ContentBridge::row_id_for_post('artists', (int) $post->ID);
                        $row = $row_id > 0 ? Artist::get($row_id) : null;
                    }
                }
            }
        }
        if (!$row && is_singular('artiey_artist') && class_exists('\WPArtCOA\ContentBridge')) {
            $post_id = (int) get_queried_object_id();
            $row_id = \WPArtCOA\ContentBridge::row_id_for_post('artists', $post_id);
            $row = $row_id > 0 ? Artist::get($row_id) : null;
        }
        if (!$row) {
            return null;
        }
        if (class_exists('\WPArtCOA\ContentBridge')) {
            $row = \WPArtCOA\ContentBridge::canonical_artist_row($row);
            $post_id = absint($row->cpt_post_id ?? 0);
            // Published WordPress status is authoritative for public profile visibility.
            // Editors may still preview drafts from an authenticated administration context.
            if ($post_id > 0 && get_post_status($post_id) !== 'publish'
                && !(function_exists('current_user_can') && current_user_can('edit_post', $post_id))) {
                return null;
            }
        }
        return $row;
    }
}
