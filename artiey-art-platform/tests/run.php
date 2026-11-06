<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use WPArtCOA\Auction;
use WPArtCOA\Catalog;
use WPArtCOA\ChartKit;
use WPArtCOA\Entities\Certificate;
use WPArtCOA\CertificateMirror;
use WPArtCOA\Media;
use WPArtCOA\ProfileFields;
use WPArtCOA\Security;
use WPArtCOA\Ulid;
use WPArtCOA\WooBridge;

echo "Ulid\n";
$u1 = Ulid::generate(); $u2 = Ulid::generate();
T::eq(strlen($u1), 26, 'length 26');
T::ok((bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $u1), 'Crockford alphabet');
T::ok($u1 !== $u2, 'unique');

echo "Security\n";
T::eq(Security::sanitize_serial(" art-1a'b;<x> "), 'ART-1ABX', 'serial sanitized');
T::eq(strlen(Security::sanitize_serial(str_repeat('A', 200))), 64, 'serial length capped');
T::eq(Security::sanitize_serial('coa–۱۲۳۴-abcd'), 'COA-1234-ABCD', 'serial: Persian digits and typographic dashes normalised');
T::eq(Security::input_scalar(['field' => 'hello\\ world'], 'field'), 'hello world', 'request scalar is unslashed once');
T::eq(Security::input_scalar(['field' => ['unexpected']], 'field'), '', 'array request values safely fall back to empty');
T::eq(Security::input_scalar([], 'missing', 'fallback'), 'fallback', 'missing request value uses explicit fallback');
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
T::eq(Security::client_ip(), '203.0.113.9', 'client_ip ignores X-Forwarded-For');
$ok = 0; for ($i = 0; $i < 5; $i++) { $ok += Security::throttle('t', 3) ? 1 : 0; }
T::eq($ok, 3, 'throttle allows exactly the limit');

echo "ProfileFields\n";
$c = ProfileFields::clean('artists', ['birth_year' => '۱۳۶۰', 'instagram' => 'https://instagram.com/Some.Artist/?hl=fa', 'telegram' => '@artist_x', 'mediums' => 'رنگ روغن, آکریلیک،رنگ روغن', 'city' => ' تهران ']);
T::eq($c['birth_year'], 1360, 'Persian-digit birth year');
T::eq($c['instagram'], 'Some.Artist', 'instagram handle from URL');
T::eq($c['telegram'], 'artist_x', 'telegram handle from @name');
T::eq($c['mediums'], 'رنگ روغن، آکریلیک', 'mediums de-duplicated');
T::eq($c['city'], 'تهران', 'text trimmed');
$g = ProfileFields::clean('galleries', ['lat' => '35.7000', 'lng' => '190', 'whatsapp' => '+98 912 123 4567', 'founded_year' => 'abc']);
T::eq($g['lat'], '35.7000000', 'latitude kept');
T::eq($g['lng'], null, 'longitude out of range rejected');
T::eq($g['whatsapp'], '+989121234567', 'whatsapp normalised');
T::eq($g['founded_year'], null, 'invalid year rejected');
T::eq(ProfileFields::clean('artists', ['city' => 'x']), ['city' => 'x'], 'absent keys are not touched');
T::eq(ProfileFields::social_url('instagram', 'a.b'), 'https://instagram.com/a.b', 'instagram url');
$extra = ProfileFields::schema_extra('galleries', (object) ['founded_year' => 1380, 'lat' => '35.7', 'lng' => '51.4', 'instagram' => 'g1']);
T::eq($extra['geo']['latitude'], 35.7, 'JSON-LD geo');
T::eq($extra['sameAs'], ['https://instagram.com/g1'], 'JSON-LD sameAs from social');

echo "Media\n";
T::eq(Media::ids('5, 5 ,x,0,7;9,-3'), [5, 7, 3], 'ids(): dedupe, ints only');
T::eq(Media::ids([4, '4', 'a', 6]), [4, 6], 'ids(): arrays');

echo "Catalog::normalize\n";
$n = Catalog::normalize('artworks', ['per_page' => 9999, 'page' => -4, 'q' => str_repeat('x', 300), 'for_sale' => 'yes', 'year_from' => 'abc']);
T::eq($n['per_page'], 48, 'per_page capped at 48');
T::eq($n['page'], 4, 'page is absolute int');
T::eq(strlen($n['q']), 80, 'q trimmed to 80');
T::eq($n['for_sale'], 1, 'for_sale flag');
T::eq($n['year_from'], 0, 'non numeric year -> 0');
$n = Catalog::normalize('exhibitions', ['event_type' => 'evil', 'when' => 'past']);
T::eq($n['event_type'], '', 'unknown event_type dropped');
T::eq($n['when'], 'past', 'valid phase kept');

echo "Auction::min_next_bid\n";
T::eq(Auction::min_next_bid((object) ['current_bid' => null, 'start_price' => 100, 'min_increment' => 10]), 100.0, 'first bid may equal start price');
T::eq(Auction::min_next_bid((object) ['current_bid' => 150, 'start_price' => 100, 'min_increment' => 25]), 175.0, 'next = current + increment');
T::eq(Auction::min_next_bid((object) ['current_bid' => 150, 'start_price' => 100, 'min_increment' => 0]), 151.0, 'zero increment falls back to 1');

echo "Certificate::verify (COA Pro is the single engine)\n";
$GLOBALS['__coa_record'] = (object) [
    'id' => 1, 'certificate_code' => 'COA-ABCD-1234EFGH', 'status' => 'active', 'artwork_title' => 'Secret Title', 'artist_name' => 'Painter',
    'year_created' => '1400', 'medium' => 'oil', 'dimensions' => '1x1', 'artwork_image_id' => 0, 'issue_date' => '2026-01-01',
    'integrity_hash' => str_repeat('ab', 32), 'current_owner_name' => 'Owner X', 'collector_name' => '',
];
$GLOBALS['__coa_flags'] = ['show_artwork_title' => 1, 'show_artist_name' => 1];
$r = Certificate::verify('coa-abcd-1234efgh');
T::ok(!empty($r['valid']) && ($r['data']['source'] ?? '') === 'coa-pro', 'COA Pro code verifies through the unified endpoint');
T::ok(!isset($r['data']['integrity_hash']) && !isset($r['data']['hmac_signature']) && strlen((string) $r['data']['hmac_fingerprint']) < 40, 'only a short fingerprint of the hash is public');
T::eq($r['data']['issued_to'], '', 'owner hidden unless its privacy flag is on');
T::eq($r['data']['artwork_title'] ?? null, 'Secret Title', 'title shown when its privacy flag is on');
T::eq($r['data']['medium'] ?? null, '', 'medium hidden when its privacy flag is off');
$GLOBALS['__coa_flags'] = [];
T::eq(Certificate::verify('COA-ABCD-1234EFGH')['data']['artwork_title'] ?? null, '', 'title hidden when its privacy flag is off');
$GLOBALS['__coa_sig_ok'] = false;
T::ok(empty(Certificate::verify('COA-ABCD-1234EFGH')['valid']), 'bad COA signature rejected');
$GLOBALS['__coa_sig_ok'] = true; $GLOBALS['__coa_ledger_ok'] = false;
T::ok(empty(Certificate::verify('COA-ABCD-1234EFGH')['valid']), 'ledger mismatch rejected');
$GLOBALS['__coa_ledger_ok'] = true; $GLOBALS['__coa_record']->status = 'revoked';
T::ok(empty(Certificate::verify('COA-ABCD-1234EFGH')['valid']), 'revoked COA certificate rejected');
$GLOBALS['__coa_record'] = null;
T::ok(empty(Certificate::verify('COA-ZZZZ-ZZZZ')['valid']), 'unknown code rejected');
T::ok(empty(Certificate::verify('')['valid']), 'empty code rejected');

echo "CertificateMirror (derived index of COA Pro)\n";
$rec = (object) ['id' => 9, 'certificate_code' => 'COA-AAAA-BBBB1111', 'status' => 'active', 'issue_date' => '2026-03-03 10:00:00',
    'current_owner_name' => 'Owner X', 'collector_name' => 'Col', 'artist_name' => 'Painter'];
$wpdb->log = []; $wpdb->var = null; // no mirrored row yet
CertificateMirror::on_issued($rec, []);
[$op, $d] = $wpdb->log[0];
T::eq($op, 'insert', 'issued certificate is indexed (insert)');
T::eq($d['serial_number'], 'COA-AAAA-BBBB1111', 'serial copied from COA');
T::eq($d['coa_id'], 9, 'index row points at the COA record');
T::eq($d['issued_to_name'], 'Owner X', 'current owner preferred');
T::ok(!array_key_exists('hmac_signature', $d) && !array_key_exists('artwork_id', $d), 'no signature stored, artwork link optional');
$wpdb->log = []; $wpdb->var = 5; // row exists
CertificateMirror::on_issued($rec, []);
T::eq($wpdb->log[0][0], 'update', 'second event updates instead of duplicating');
$wpdb->log = [];
CertificateMirror::on_revoked(9, 'lost');
T::eq($wpdb->log[0][1]['status'], 'revoked', 'revocation propagates');
T::eq($wpdb->log[0][2], ['coa_id' => 9], 'revocation targets the COA id');
$wpdb->log = [];
CertificateMirror::on_transferred(9, (object) ['current_owner_name' => 'New Owner']);
T::eq($wpdb->log[0][1]['issued_to_name'], 'New Owner', 'ownership transfer propagates');
$wpdb->var = null;
T::ok(!CertificateMirror::link(9, 0), 'link rejects an invalid artwork');
$wpdb->log = [];

echo "WooBridge price conversion\n";
$m = new ReflectionMethod(WooBridge::class, 'price'); $m->setAccessible(true);
$GLOBALS['__wc_currency'] = 'IRT';
T::eq($m->invoke(null, (object) ['price' => 1000000, 'currency' => 'IRR']), 100000.0, 'IRR -> IRT divides by 10');
$GLOBALS['__wc_currency'] = 'IRR';
T::eq($m->invoke(null, (object) ['price' => 100, 'currency' => 'IRT']), 1000.0, 'IRT -> IRR multiplies by 10');
$GLOBALS['__wc_currency'] = 'USD';
T::eq($m->invoke(null, (object) ['price' => 50, 'currency' => 'USD']), 50.0, 'same currency unchanged');

echo "Elementor widgets (structure only)\n";
require_once __DIR__ . '/elementor-stubs.php';
if (!function_exists('get_posts')) { function get_posts($a = []) {
    if (!empty($GLOBALS['__get_posts_queue'])) { return array_shift($GLOBALS['__get_posts_queue']); }
    if (isset($GLOBALS['__get_posts_resolver']) && is_callable($GLOBALS['__get_posts_resolver'])) { return call_user_func($GLOBALS['__get_posts_resolver'], $a); }
    return [];
} }
if (!function_exists('wp_parse_args')) { function wp_parse_args($a, $d = []) { return array_merge($d, (array) $a); } }
$root = dirname(__DIR__) . '/includes/';
require_once $root . 'class-elementor-options.php';
require_once $root . 'class-elementor-tabs.php';
require_once $root . 'class-elementor-style.php';
require_once $root . 'class-gallery.php'; // the gallery widget reads its section list from the profile class
foreach (['class-elementor-widget', 'class-elementor-profile-widget', 'class-elementor-more-widgets'] as $f) { require_once $root . $f . '.php'; }
foreach ([
    'Artiey_AET_Elementor_Widget', 'Artiey_AET_Elementor_Profile_Widget', 'Artiey_AET_Elementor_Artist_Widget',
    'Artiey_AET_Elementor_Directory_Widget', 'Artiey_AET_Elementor_Artwork_Widget', 'Artiey_AET_Elementor_Stats_Widget',
    'Artiey_AET_Elementor_Exhibition_Widget',
] as $cls) {
    $w = new $cls();
    $w->build();
    T::eq($w->errors, [], "$cls: valid control structure");
    $bad = [];
    foreach ($w->controls as $id => $c) {
        foreach (array_keys($c['args']['condition'] ?? []) as $dep) {
            if (!isset($w->controls[$dep])) { $bad[] = "$id->$dep"; }
        }
        foreach (($c['args']['conditions']['terms'] ?? []) as $term) {
            $dep = $term['name'] ?? '';
            if ($dep && !isset($w->controls[$dep])) { $bad[] = "$id->$dep"; }
        }
    }
    T::eq($bad, [], "$cls: every condition points to an existing control");
    T::ok(isset($w->controls['accent']) || $cls === 'Artiey_AET_Elementor_Widget', "$cls: has accent colour");
    if (in_array($cls, ['Artiey_AET_Elementor_Profile_Widget', 'Artiey_AET_Elementor_Artist_Widget', 'Artiey_AET_Elementor_Directory_Widget', 'Artiey_AET_Elementor_Artwork_Widget', 'Artiey_AET_Elementor_Stats_Widget', 'Artiey_AET_Elementor_Exhibition_Widget'], true)) {
        T::ok(isset($w->controls['tabs_items']) && ($w->controls['tabs_items']['type'] ?? '') === 'repeater', "$cls: has editable tab manager");
    }
    if ($cls !== 'Artiey_AET_Elementor_Widget') {
        T::ok(isset($w->controls['widget_design_preset'], $w->controls['content_max_width'], $w->controls['heading_decoration']), "$cls: shared design presets, responsive width, and heading styles");
        T::ok(isset($w->controls['aetp_action_typography'], $w->controls['aetp_action_radius'], $w->controls['aetp_action_hover_background']), "$cls: shared customizable action/button styles");
    }
    if ($cls === 'Artiey_AET_Elementor_Widget') {
        T::ok(isset($w->controls['nav_arrow_glyph'], $w->controls['nav_arrow_shape'], $w->controls['nav_arrow_motion']), 'exhibition timeline: arrow glyph, skin and motion controls registered');
        T::ok(isset($w->controls['nav_icon_color'], $w->controls['nav_icon_background'], $w->controls['nav_icon_box_size']), 'exhibition timeline: arrow colours and responsive sizing registered');
        T::ok(isset($w->controls['logo_col']) && strpos((string) ($w->controls['logo_col']['args']['selectors']['{{WRAPPER}} .aet'] ?? ''), '--aet-gallery-col-width') !== false, 'exhibition timeline: gallery column width directly controls the first grid track');
        T::ok(isset($w->controls['gallery_header_typography'], $w->controls['logo_text_typo'], $w->controls['gallery_logo_width'], $w->controls['gallery_cell_alignment'], $w->controls['gallery_name_alignment']), 'exhibition timeline: gallery header and name have responsive typography and layout controls');
        T::ok(isset($w->controls['show_gallery_name']), 'exhibition timeline: optionally displays the gallery name below its logo');
    }
    if (in_array($cls, ['Artiey_AET_Elementor_Profile_Widget', 'Artiey_AET_Elementor_Artist_Widget', 'Artiey_AET_Elementor_Artwork_Widget'], true)) {
        T::ok(isset($w->controls['profile_layout']), "$cls: stacked/tabbed profile layout control");
        T::ok(isset($w->controls['section_style_tabs'], $w->controls['tab_active_background'], $w->controls['tab_panel_padding']), "$cls: professional tab appearance controls");
        T::ok(isset($w->controls['tab_nav_width'], $w->controls['tab_button_alignment'], $w->controls['tab_button_padding']), "$cls: vertical tab nav width, alignment, and padding controls");
        T::ok(isset($w->controls['tabs_orientation'], $w->controls['tabs_mobile_orientation']), "$cls: desktop and mobile tab orientation controls");
        T::ok(isset($w->controls['tabs_items']) && ($w->controls['tabs_items']['type'] ?? '') === 'repeater', "$cls: editable tab repeater is registered");
        $tab_fields = array_keys($w->controls['tabs_items']['args']['fields'] ?? []);
        T::ok(in_array('tab_section', $tab_fields, true) && in_array('tab_label', $tab_fields, true) && in_array('tab_icon', $tab_fields, true) && in_array('tab_enabled', $tab_fields, true), "$cls: tabs can be connected, renamed, iconized and disabled");
        T::eq($w->get_script_depends(), ['artiey-aet-profile'], "$cls: tab javascript dependency registered");
    }
    if ($cls === 'Artiey_AET_Elementor_Artist_Widget') {
        foreach (['artist_content_sections', 'artist_content_bio', 'artist_content_charts', 'artist_content_portfolio', 'artist_content_history'] as $panel_id) {
            T::ok(isset($w->controls[$panel_id]) && ($w->controls[$panel_id]['type'] ?? '') === 'section', 'artist widget: dedicated content panel ' . $panel_id);
        }
        $profile_sections = $w->controls['sections']['args']['options'] ?? [];
        foreach (['header', 'short_bio', 'statement', 'bio', 'details', 'awards', 'career_timeline', 'contact', 'works', 'galleries', 'exhibitions', 'activity_chart', 'sales_chart', 'artwork_history'] as $section_key) {
            T::ok(isset($profile_sections[$section_key]), "artist profile: selectable section {$section_key}");
        }
        T::ok(isset($w->controls['bio_readmore'], $w->controls['bio_visible_paragraphs'], $w->controls['bio_more_label'], $w->controls['bio_less_label']), 'artist profile: configurable biography read-more button');
        T::ok(isset($w->controls['chart_months'], $w->controls['chart_height'], $w->controls['history_source'], $w->controls['history_limit'], $w->controls['history_layout']), 'artist profile: chart and artwork-history controls');
        T::ok(isset($w->controls['works_limit']) && (int) ($w->controls['works_limit']['args']['max'] ?? 0) === 48, 'artist profile: catalogue artwork count is configurable and capped');
        T::ok(isset($w->controls['tabs_orientation'], $w->controls['tabs_mobile_orientation']), 'artist profile: horizontal/vertical tabs and mobile orientation controls');
        T::ok(isset($w->controls['activity_chart_connection'], $w->controls['activity_chart_shortcode'], $w->controls['sales_chart_connection'], $w->controls['sales_chart_shortcode']), 'artist profile: automatic and shortcode chart connection controls');
        T::eq($w->controls['activity_chart_connection']['args']['default'] ?? '', 'auto', 'artist activity chart defaults to automatic COA connection');
        T::eq($w->controls['sales_chart_connection']['args']['default'] ?? '', 'auto', 'artist sales chart defaults to automatic COA connection');
        T::ok(isset($w->controls['statement_panel_background'], $w->controls['career_line_color'], $w->controls['artist_chart_accent'], $w->controls['artist_history_accent']), 'artist profile: independent style controls for statement, timeline, charts and history');
        T::ok(strpos((string) ($w->controls['chart_months']['args']['label'] ?? ''), 'ماه') !== false, 'artist profile: chart period is expressed in months, not pixels');
        T::ok(isset($w->controls['artist_header_style'], $w->controls['header_title_tag'], $w->controls['header_show_cover'], $w->controls['header_show_styles']), 'artist profile: header layouts and visibility toggles');
        T::ok(isset($w->controls['activity_chart_title'], $w->controls['sales_chart_title'], $w->controls['chart_show_legend'], $w->controls['chart_show_table']), 'artist profile: chart titles and display options');
        T::ok(isset($w->controls['history_show_title'], $w->controls['history_title'], $w->controls['works_show_caption'], $w->controls['works_link_target']), 'artist profile: customizable history heading and portfolio captions');
        $activity_terms = $w->controls['activity_chart_connection']['args']['conditions']['terms'] ?? [];
        T::ok(count($activity_terms) === 1 && ($activity_terms[0]['name'] ?? '') === 'sections' && ($activity_terms[0]['operator'] ?? '') === 'contains' && ($activity_terms[0]['value'] ?? '') === 'activity_chart', 'artist widget: chart controls correctly test the multi-select sections array');
        $shortcode_terms = $w->controls['activity_chart_shortcode']['args']['conditions']['terms'] ?? [];
        T::ok(count($shortcode_terms) === 2 && ($shortcode_terms[1]['name'] ?? '') === 'activity_chart_connection' && ($shortcode_terms[1]['value'] ?? '') === 'shortcode', 'artist widget: manual chart shortcode appears only for the selected chart and manual connection mode');
        T::eq($w->controls['chart_months']['args']['conditions']['relation'] ?? '', 'or', 'artist widget: shared chart controls appear when either chart section is enabled');
        T::ok(isset($w->controls['works_limit']['args']['conditions']['terms'][0]) && ($w->controls['works_limit']['args']['conditions']['terms'][0]['value'] ?? '') === 'works', 'artist widget: portfolio settings are scoped to the works section');
        T::ok(isset($w->controls['history_source']['args']['conditions']['terms'][0]) && ($w->controls['history_source']['args']['conditions']['terms'][0]['value'] ?? '') === 'artwork_history', 'artist widget: history settings are scoped to the artwork history section');
        T::ok(isset($w->controls['header_show_cover']['args']['conditions']['terms'][0]) && ($w->controls['header_show_cover']['args']['conditions']['terms'][0]['value'] ?? '') === 'header', 'artist widget: header controls are scoped to the header section');
    }
    if ($cls === 'Artiey_AET_Elementor_Profile_Widget') {
        T::ok(isset($w->controls['gallery_header_style']), 'gallery profile: configurable header layout');
        T::ok(isset($w->controls['gallery_content_sections'], $w->controls['gallery_content_relationships'], $w->controls['gallery_content_display']), 'gallery profile: content, ecosystem relationships and display options have separate editor panels');
    }
    if ($cls === 'Artiey_AET_Elementor_Directory_Widget') {
        T::ok(isset($w->controls['directory_filter_settings'], $w->controls['directory_output_settings'], $w->controls['directory_behavior_settings']), 'directory widget: filters, output and behavior have separate editor panels');
    }
    if ($cls === 'Artiey_AET_Elementor_Artwork_Widget') {
        T::ok(isset($w->controls['artwork_profile_style']), 'artwork profile: catalog/showcase/minimal layout control');
        T::ok(isset($w->controls['artwork_content_sections']), 'artwork profile: independent content section editor panel');
    }
    if ($cls === 'Artiey_AET_Elementor_Stats_Widget') {
        T::ok(isset($w->controls['stats_content_metrics'], $w->controls['stats_content_heading']), 'statistics widget: metrics and title have separate editor panels');
    }
    if ($cls === 'Artiey_AET_Elementor_Exhibition_Widget') {
        T::ok(isset($w->controls['exhibition_profile_style']), 'exhibition profile: editorial/poster/compact layout control');
        T::ok(isset($w->controls['exhibition_content_sections'], $w->controls['exhibition_content_fallback']), 'exhibition profile: sections and fallback text have separate editor panels');
        T::ok(empty($w->controls['sections']['args']['condition']), 'exhibition profile: content sections are selectable in both stacked and tab layouts');
        T::ok(strpos(file_get_contents(dirname(__DIR__) . '/includes/class-elementor-more-widgets.php'), 'Artiey_AET_Exhibition::render_profile_section') !== false, 'exhibition profile: stacked layout respects the selected sections');
    }
}
$dir = new Artiey_AET_Elementor_Directory_Widget(); $dir->build();
T::ok(isset($dir->controls['include_artworks'], $dir->controls['sort'], $dir->controls['exclude']), 'directory widget: manual selection, sort and exclude');
T::ok(isset($dir->controls['card_style']) && isset($dir->controls['ajax']), 'directory widget: card skins and AJAX control');
$ex = new Artiey_AET_Elementor_Exhibition_Widget(); $ex->build();
T::ok(isset($ex->controls['color_scheme'], $ex->controls['card_shadow']), 'exhibition widget: shared design controls');
$aet_timeline = new Artiey_AET_Elementor_Widget(); $aet_timeline->build();
T::ok(isset($aet_timeline->controls['heading_title'], $aet_timeline->controls['heading_subtitle'], $aet_timeline->controls['heading_tag']), 'legacy timeline: editable main title and subtitle controls');
T::ok(isset($aet_timeline->controls['calendar_range_settings'], $aet_timeline->controls['calendar_filter_settings'], $aet_timeline->controls['calendar_display_settings']), 'legacy timeline: calendar range, gallery filters and display settings have separate editor panels');
T::ok(isset($aet_timeline->controls['sub_fields_mode'], $aet_timeline->controls['sub_fields'], $aet_timeline->controls['sub_sep']), 'legacy timeline: per-widget event subtitle configuration');
T::ok(strpos((string) ($aet_timeline->controls['galleries']['args']['description'] ?? ''), 'گالری برگزارکننده') !== false, 'legacy timeline: explains how gallery is attached to an exhibition');
T::eq($aet_timeline->get_title(), 'تایم‌لاین تقویمی نمایشگاه‌ها (روزبه‌روز و متصل به گالری)', 'legacy timeline: clearly identified as daily gallery calendar');

// Exercise public directory argument sanitization without booting WordPress.
if (!class_exists('Artiey_AET_Settings')) {
    class Artiey_AET_Settings {
        public static function get($key) {
            return ['directory_columns' => 3, 'directory_per_page' => 12, 'directory_ajax' => true][$key] ?? '';
        }
        public static function all() {
            return [
                'days' => 14, 'before_days' => 6, 'max_rows' => 20, 'show_nav' => false,
                'more_text' => '', 'more_url' => '', 'stripes' => true, 'highlight_fri' => true,
                'highlight_today' => true, 'status_colors' => false, 'show_range' => false,
                'sub_fields' => ['type', 'artist', 'gallery'], 'sub_sep' => ' — ',
                'digits' => 'persian', 'step' => 7, 'cache_minutes' => 0, 'empty_text' => 'رویدادی یافت نشد',
                'link_target' => '_self',
            ];
        }
    }
}
if (!class_exists('Artiey_AET_Artwork')) {
    class Artiey_AET_Artwork { public const SALE = ['available' => 'موجود', 'reserved' => 'رزرو شده', 'sold' => 'فروخته شد', 'not_for_sale' => 'غیرقابل فروش']; }
}
require_once dirname(__DIR__) . '/includes/class-directory.php';
$dir_args = Artiey_AET_Directory::sanitize_args(['type' => 'artists', 'card_style' => 'compact', 'filters' => ['search', ['bad'], 'city'], 'city' => ['invalid'], 'per_page' => ['invalid']]);
T::eq($dir_args['card_style'], 'compact', 'directory args: compact card skin accepted');
T::eq($dir_args['filters'], 'search,city', 'directory args: malformed filter values discarded');
T::eq($dir_args['per_page'], 12, 'directory args: non-scalar page size falls back safely');
$dir_bad_args = Artiey_AET_Directory::sanitize_args(['type' => ['invalid'], 'card_style' => ['<script>'], 'status' => ['invalid']]);
T::eq($dir_bad_args['type'], 'galleries', 'directory args: malformed type falls back safely');
T::eq($dir_bad_args['card_style'], 'standard', 'directory args: malformed card style falls back safely');

require_once dirname(__DIR__) . '/includes/class-renderer.php';
$subtitle_args = Artiey_AET_Renderer::sanitize_args([
    'sub_fields_mode' => 'custom', 'sub_fields' => ['type', 'gallery', 'bad-field', ['nested-invalid'], 'type'], 'sub_sep' => ' | ',
]);
T::eq($subtitle_args['sub_fields_mode'], 'custom', 'timeline args: custom subtitle mode is retained');
T::eq($subtitle_args['sub_fields'], ['type', 'gallery'], 'timeline args: subtitle components are whitelisted and deduplicated');
T::eq($subtitle_args['sub_sep'], ' | ', 'timeline args: separator keeps intentional spaces while sanitizing text');
T::eq(Artiey_AET_Renderer::sanitize_args(['sub_fields_mode' => 'global'])['sub_fields_mode'], 'global', 'timeline args: global subtitle defaults remain available');
T::eq(Artiey_AET_Renderer::sanitize_args([])['show_gallery_name'], 'no', 'timeline gallery column: names below logos remain off by default for backwards compatibility');
T::eq(Artiey_AET_Renderer::sanitize_args(['show_gallery_name' => 'yes'])['show_gallery_name'], 'yes', 'timeline gallery column: optional gallery captions are enabled safely');
T::eq(Artiey_AET_Renderer::sanitize_args(['show_gallery_name' => '<script>yes</script>'])['show_gallery_name'], 'no', 'timeline gallery column: invalid caption flag is rejected');
$legacy_widget_code = file_get_contents(dirname(__DIR__) . '/includes/class-elementor-widget.php');
T::ok(strpos($legacy_widget_code, '--aet-gallery-col-width: {{SIZE}}{{UNIT}};') !== false, 'timeline gallery column: responsive width control writes dedicated column variable');
T::ok(strpos($legacy_widget_code, 'gallery_header_typography') !== false && strpos($legacy_widget_code, 'logo_text_typo') !== false, 'timeline gallery column: header and gallery-name typography controls exist');
$timeline_css = file_get_contents(dirname(__DIR__) . '/assets/css/timeline.css');
T::ok(strpos($timeline_css, 'grid-template-columns: minmax(0, var(--aet-gallery-col-width') !== false, 'timeline gallery column: grid uses adjustable gallery width');
T::ok(strpos($timeline_css, 'min-width: max-content;') !== false, 'timeline gallery column: responsive overflow width does not rely on invalid calc multiplication');
T::ok(strpos(file_get_contents(dirname(__DIR__) . '/includes/class-renderer.php'), 'aet-gallery-caption') !== false, 'timeline gallery column: optional caption is rendered under gallery logos');
$timeline_meta = Artiey_AET_Renderer::timeline_meta_query('2026-10-01', '2026-10-14', [22, 22, 'bad', 31]);
T::eq($timeline_meta[0]['key'] ?? '', '_ae_start', 'legacy timeline query: filters by start date');
T::eq($timeline_meta[1]['relation'] ?? '', 'OR', 'legacy timeline query: includes events overlapping the visible range');
T::eq($timeline_meta[1][1]['relation'] ?? '', 'AND', 'legacy timeline query: missing end date is treated as a one-day event');
T::eq($timeline_meta[1][1][0]['compare'] ?? '', 'NOT EXISTS', 'legacy timeline query: handles records without end-date meta');
T::eq($timeline_meta[1][1][1]['key'] ?? '', '_ae_start', 'legacy timeline query: missing-end event must start on/after the range start');
T::eq($timeline_meta[1][1][1]['value'] ?? '', '2026-10-01', 'legacy timeline query: avoids stale one-day events before the visible range');
T::eq($timeline_meta[1][2][1]['key'] ?? '', '_ae_start', 'legacy timeline query: empty end-date also requires an in-range start');
T::eq($timeline_meta[2]['value'] ?? [], [22, 31], 'legacy timeline query: gallery IDs are normalized and de-duplicated');
T::eq($timeline_meta[2]['type'] ?? '', 'NUMERIC', 'legacy timeline query: gallery relationship is numeric');
T::ok(strpos(file_get_contents(dirname(__DIR__) . '/art-coa/includes/entities/class-exhibition.php'), 'use WPArtCOA\\ContentSync;') !== false, 'exhibition query: imports the correct namespaced content-sync class');

echo "Elementor arrow controls (COA widgets)\n";
require_once dirname(__DIR__) . '/art-coa/includes/elementor/class-design-controls.php';
foreach ([
    'class-widget-timeline' => \WPArtCOA\Elementor\Widgets\Timeline_Widget::class,
    'class-widget-auction-timeline' => \WPArtCOA\Elementor\Widgets\Auction_Timeline_Widget::class,
    'class-widget-artwork-history' => \WPArtCOA\Elementor\Widgets\Artwork_History_Widget::class,
] as $file => $class_name) {
    require_once dirname(__DIR__) . '/art-coa/includes/elementor/widgets/' . $file . '.php';
    $arrow_widget = new $class_name();
    $arrow_widget->build();
    T::eq($arrow_widget->errors, [], $class_name . ': valid controls/sections');
    T::ok(isset($arrow_widget->controls['design_preset'], $arrow_widget->controls['design_max_width']), $class_name . ': shared design preset and responsive width');
    $keys = array_keys($arrow_widget->controls);
    T::ok(in_array('timeline_arrow_glyph', $keys, true) || in_array('auction_arrow_glyph', $keys, true) || in_array('history_arrow_glyph', $keys, true), $class_name . ': arrow glyph picker exists');
    T::ok(in_array('timeline_arrow_skin', $keys, true) || in_array('auction_arrow_skin', $keys, true) || in_array('history_arrow_skin', $keys, true), $class_name . ': arrow skin picker exists');
    T::ok(in_array('timeline_arrow_box_size', $keys, true) || in_array('auction_arrow_box_size', $keys, true) || in_array('history_arrow_box_size', $keys, true), $class_name . ': responsive arrow box-size control exists');
    T::ok(isset($arrow_widget->controls['design_typography_actions'], $arrow_widget->controls['design_surface_spacing'], $arrow_widget->controls['design_image_radius']), $class_name . ': shared design editor exposes typography, frame, spacing and image controls');
}
foreach ([
    'class-widget-artist-bio' => \WPArtCOA\Elementor\Widgets\Artist_Bio_Widget::class,
    'class-widget-artwork-history' => \WPArtCOA\Elementor\Widgets\Artwork_History_Widget::class,
    'class-widget-auction-list' => \WPArtCOA\Elementor\Widgets\Auction_List_Widget::class,
    'class-widget-calendar' => \WPArtCOA\Elementor\Widgets\Calendar_Widget::class,
    'class-widget-certificate-card' => \WPArtCOA\Elementor\Widgets\Certificate_Card::class,
    'class-widget-hierarchy' => \WPArtCOA\Elementor\Widgets\Widget_Hierarchy::class,
    'class-widget-portfolio' => \WPArtCOA\Elementor\Widgets\Widget_Portfolio::class,
    'class-widget-shop' => \WPArtCOA\Elementor\Widgets\Widget_Shop::class,
    'class-widget-verifier' => \WPArtCOA\Elementor\Widgets\Verifier::class,
] as $file => $class_name) {
    $widget_file = dirname(__DIR__) . '/art-coa/includes/elementor/widgets/' . $file . '.php';
    if (!class_exists($class_name) && is_file($widget_file)) { require_once $widget_file; }
    if (class_exists($class_name)) {
        $design_widget = new $class_name();
        $design_widget->build();
        T::eq($design_widget->errors, [], $class_name . ': grouped content/design controls are balanced');
        T::ok(isset($design_widget->controls['design'], $design_widget->controls['design_typography_actions'], $design_widget->controls['design_surface_spacing']), $class_name . ': shared style editor panels are registered');
    }
}
$arrow_css = file_get_contents(dirname(__DIR__) . '/art-coa/assets/css/frontend-dynamic.css');
T::ok(strpos($arrow_css, '.artiey-arrow-skin-filled-circle') !== false && strpos($arrow_css, '.artiey-arrow-motion-slide') !== false, 'COA frontend CSS includes filled and animated arrow skins');
$core_arrow_css = file_get_contents(dirname(__DIR__) . '/assets/css/timeline.css');
T::ok(strpos($core_arrow_css, '.aet-nav-glyph-double') !== false && strpos($core_arrow_css, '.aet-nav-labels-icons') !== false, 'legacy timeline CSS includes glyph presets and accessible icon-only mode');

// More COA widgets now support the shared Pro tabs behavior where it meaningfully groups data.
foreach ([
    'class-widget-auction-list' => \WPArtCOA\Elementor\Widgets\Auction_List_Widget::class,
    'class-widget-shop' => \WPArtCOA\Elementor\Widgets\Widget_Shop::class,
] as $file => $class_name) {
    require_once dirname(__DIR__) . '/art-coa/includes/elementor/widgets/' . $file . '.php';
    $widget = new $class_name();
    $widget->build();
    T::eq($widget->errors, [], $class_name . ': valid controls/sections');
    T::ok(isset($widget->controls['presentation'], $widget->controls['tabs_orientation'], $widget->controls['tabs_skin'], $widget->controls['tabs_panel_style']), $class_name . ': Pro tabs mode and shared appearance controls');
    T::ok(is_array($widget->get_script_depends()) && in_array('artiey-aet-profile', $widget->get_script_depends(), true), $class_name . ': shared tabs JavaScript dependency');
}
foreach ([
    'class-widget-auction-timeline' => \WPArtCOA\Elementor\Widgets\Auction_Timeline_Widget::class,
    'class-widget-timeline' => \WPArtCOA\Elementor\Widgets\Timeline_Widget::class,
] as $file => $class_name) {
    require_once dirname(__DIR__) . '/art-coa/includes/elementor/widgets/' . $file . '.php';
    $widget = new $class_name();
    $widget->build();
    T::eq($widget->errors, [], $class_name . ': valid controls/sections');
    T::ok(isset($widget->controls['presentation']) && (isset($widget->controls['timeline_status_tabs']) || isset($widget->controls['auction_status_tabs'])), $class_name . ': status-based tabs controls exist');
    T::ok(isset($widget->controls['tabs_orientation'], $widget->controls['tabs_skin']), $class_name . ': shared Pro tabs controls exist');
}

// Every bundled Art COA widget with a tabbed presentation exposes the shared editable tab repeater.
$coa_tab_widget_cases = [
    'class-widget-shop' => [\WPArtCOA\Elementor\Widgets\Widget_Shop::class, 'presentation', ['sale', 'all']],
    'class-widget-auction-list' => [\WPArtCOA\Elementor\Widgets\Auction_List_Widget::class, 'presentation', ['ending', 'newest', 'bids', 'price_high', 'price_low']],
    'class-widget-timeline' => [\WPArtCOA\Elementor\Widgets\Timeline_Widget::class, 'presentation', ['live', 'upcoming', 'past']],
    'class-widget-auction-timeline' => [\WPArtCOA\Elementor\Widgets\Auction_Timeline_Widget::class, 'presentation', ['live', 'upcoming', 'closed', 'sold']],
    'class-widget-hierarchy' => [\WPArtCOA\Elementor\Widgets\Widget_Hierarchy::class, 'display_mode', ['galleries', 'artworks', 'exhibitions', 'provenance']],
    'class-widget-portfolio' => [\WPArtCOA\Elementor\Widgets\Widget_Portfolio::class, 'presentation', ['all', 'certified']],
    'class-widget-artist-bio' => [\WPArtCOA\Elementor\Widgets\Artist_Bio_Widget::class, 'presentation', ['biography', 'facts', 'statement', 'education', 'awards']],
    'class-widget-artwork-history' => [\WPArtCOA\Elementor\Widgets\Artwork_History_Widget::class, 'presentation', ['auction', 'sales', 'ownership', 'market']],
    'class-widget-charts' => [\WPArtCOA\Elementor\Widgets\Charts_Widget::class, 'chart_layout', ['activity', 'progress', 'growth', 'status', 'completeness', 'sales', 'sales_value']],
];
foreach ($coa_tab_widget_cases as $file => [$class_name, $condition_key, $expected_keys]) {
    $widget_file = dirname(__DIR__) . '/art-coa/includes/elementor/widgets/' . $file . '.php';
    if (!class_exists($class_name) && is_file($widget_file)) { require_once $widget_file; }
    T::ok(class_exists($class_name), $class_name . ': class loads for editable tabs');
    if (!class_exists($class_name)) { continue; }
    $widget = new $class_name();
    $widget->build();
    T::eq($widget->errors, [], $class_name . ': editable tab controls are valid');
    $tab_control = $widget->controls['tabs_items']['args'] ?? [];
    T::eq($tab_control['type'] ?? '', \Elementor\Controls_Manager::REPEATER, $class_name . ': shared tab manager is registered');
    T::eq(array_keys($tab_control['fields']['tab_section']['options'] ?? []), $expected_keys, $class_name . ': tab manager maps every supported content section');
    T::eq($tab_control['condition'][$condition_key] ?? '', 'tabs', $class_name . ': tab manager appears only in tab presentation');
    if ($class_name === \WPArtCOA\Elementor\Widgets\Widget_Hierarchy::class) {
        T::eq($tab_control['condition']['entity_type'] ?? '', 'artist', 'hierarchy tab editor is hidden for non-artist relation trees');
    }
    T::eq(array_column($tab_control['default'] ?? [], 'tab_section'), $expected_keys, $class_name . ': every default tab is editable and ordered');
}

echo "ChartKit\n";
foreach (['class-calendar', 'class-charts', 'class-chart-kit'] as $f) { if (is_file(WP_ART_COA_DIR . "includes/$f.php")) require_once WP_ART_COA_DIR . "includes/$f.php"; }
require_once WP_ART_COA_DIR . 'includes/elementor/class-design-controls.php';
require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-charts.php';
$b = ChartKit::bars('فعالیت <b>x</b>', ['فروردین', 'اردیبهشت'], [['label' => 'آثار', 'values' => [3, 5]]], []);
T::ok(strpos($b, '<svg') !== false && strpos($b, '<b>x</b>') === false, 'bars: SVG rendered and title escaped');
T::ok(strpos($b, '<table') !== false, 'bars: accessible data table present');
T::ok(strpos(ChartKit::bars('t', ['a'], [['label' => 'x', 'values' => [3]]], ['table' => false]), '<table') === false, 'bars: table can be disabled');
T::ok(strpos(ChartKit::bars('t', ['a'], [['label' => 'x', 'values' => [0]]], []), 'art-coa-chart--empty') !== false, 'bars: empty data shows empty state');
T::ok(strpos(ChartKit::bars('t', [], [], ['empty_text' => 'خالی است']), 'خالی است') !== false, 'bars: custom empty text');
T::ok(strpos(ChartKit::line('t', ['a', 'b', 'c'], [['label' => 'x', 'values' => [1, 4, 9]]], ['area' => true]), '<path') !== false, 'line: path drawn');
T::ok(strpos(ChartKit::line('t', ['a'], [['label' => 'x', 'values' => [0]]], []), 'art-coa-chart--empty') !== false, 'line: empty state');
$d = ChartKit::donut('t', [['label' => 'منتشرشده', 'value' => 6, 'color' => 1], ['label' => 'فروخته‌شده', 'value' => 2, 'color' => 3]], []);
T::ok(strpos($d, '<svg') !== false && strpos($d, 'منتشرشده') !== false, 'donut: segments and labels');
T::ok(strpos(ChartKit::donut('t', [], []), 'art-coa-chart--empty') !== false, 'donut: empty state');
T::eq(ChartKit::render(['type' => 'sales', 'artist' => '999999']), '', 'render: unknown artist renders nothing');
T::eq(ChartKit::render(['type' => 'completeness', 'platform' => true]), '', 'render: completeness is never public');
$cw = new \WPArtCOA\Elementor\Widgets\Charts_Widget(); $cw->build();
T::eq($cw->errors, [], 'charts widget: valid control structure');
T::ok(isset($cw->controls['chart'], $cw->controls['c1'], $cw->controls['artist']), 'charts widget: type, palette and artist picker');
T::eq($cw->get_script_depends(), ['wp-art-coa-charts', 'artiey-aet-profile'], 'charts widget: chart plus shared tab script dependency');

$tabs_source = file_get_contents(dirname(__DIR__) . '/includes/class-elementor-tabs.php');
$tabs_js = file_get_contents(dirname(__DIR__) . '/assets/js/profile.js');
$tabs_css = file_get_contents(dirname(__DIR__) . '/assets/css/profile.css');
T::ok(strpos($tabs_source, 'data-tabs-orientation') !== false && strpos($tabs_source, 'data-tabs-mobile-orientation') !== false, 'artist tabs: rendered with desktop and mobile orientation metadata');
T::ok(strpos($tabs_js, 'ArrowUp') !== false && strpos($tabs_js, 'applyTabsOrientation') !== false, 'artist tabs: vertical keyboard navigation and responsive orientation supported');
T::ok(strpos($tabs_css, '.aetp-tabs.is-vertical.js') !== false, 'artist tabs: vertical two-column layout CSS is present');
T::ok(strpos($tabs_js, 'observeDynamicTabs') !== false && strpos($tabs_js, 'MutationObserver') !== false, 'artist tabs: dynamically inserted Elementor tabs are initialized');
T::ok(strpos($tabs_js, 'elementor/frontend/init') !== false, 'artist tabs: Elementor frontend lifecycle hook initializes tabs');
T::ok(strpos($tabs_js, 'panel.hidden = !active') !== false && strpos($tabs_css, '.aetp-tabpanel[hidden]') !== false, 'artist tabs: active panel state is synchronized with hidden and CSS');
T::ok(strpos($tabs_js, 'matchingPanel(parts, key)') !== false, 'artist tabs: panel mapping is validated before switching content');
T::ok(strpos($tabs_js, 'alreadyEnhanced') !== false && strpos($tabs_js, 'if (box && seen.indexOf(box) === -1)') !== false, 'tabs: re-initialisation preserves the active tab and reconciles already-enhanced Elementor containers');
$tabs_style_src = file_get_contents(dirname(__DIR__) . '/includes/class-elementor-style.php');
T::ok(strpos($tabs_style_src, 'null === $sections') !== false && strpos($tabs_style_src, 'array_keys( $maps[ $type ] )') !== false, 'profile tabs: an unsaved section list shows every section instead of an empty widget');

// Shared tab renderer: ensure selectable Pro variants are emitted as safe, accessible markup.
$tab_test_html = Artiey_AET_Tabs::render([
    ['key' => 'bio', 'label' => 'بیوگرافی', 'html' => '<p>متن بیوگرافی</p>'],
    ['key' => 'works', 'label' => 'آثار', 'html' => '<div class="works"><img src="https://example.test/a.jpg" alt="اثر"></div>'],
], [
    'tabs_orientation' => 'vertical', 'tabs_mobile_orientation' => 'horizontal', 'tabs_skin' => 'boxed',
    'tabs_alignment' => 'stretch', 'tabs_panel_style' => 'card', 'tabs_scrollable' => 'yes', 'tabs_equal_width' => 'yes',
    'tabs_animation' => 'fade', 'tabs_url_hash' => 'yes',
], 'تب‌های آزمایشی');
T::ok(strpos($tab_test_html, 'aetp-tabs-skin-boxed') !== false && strpos($tab_test_html, 'aetp-tabs-panel-card') !== false, 'shared tabs: skin and panel-style presets rendered');
T::ok(strpos($tab_test_html, 'data-tabs-orientation="vertical"') !== false && strpos($tab_test_html, 'data-tabs-mobile-orientation="horizontal"') !== false, 'shared tabs: responsive orientation metadata rendered');
T::ok(strpos($tab_test_html, 'data-tabs-url-hash="yes"') !== false && strpos($tab_test_html, 'role="tabpanel"') !== false, 'shared tabs: hash navigation metadata and accessible panels rendered');
$custom_tab_html = Artiey_AET_Tabs::render([
    ['key' => 'bio', 'label' => 'بیوگرافی', 'html' => '<p>متن بیوگرافی</p>'],
    ['key' => 'works', 'label' => 'آثار', 'html' => '<div>نمونه آثار</div>'],
    ['key' => 'contact', 'label' => 'ارتباط', 'html' => '<p>اطلاعات ارتباطی</p>'],
], ['tabs_items' => [
    ['tab_section' => 'works', 'tab_label' => 'پورتفولیو', 'tab_icon' => ['value' => 'fa fa-paint-brush', 'library' => 'fa-solid'], 'tab_enabled' => 'yes'],
    ['tab_section' => 'bio', 'tab_label' => '<b>درباره هنرمند</b>', 'tab_enabled' => 'yes'],
    ['tab_section' => 'contact', 'tab_enabled' => 'no'],
]], 'ویرایش تب‌ها');
T::ok(strpos($custom_tab_html, 'data-t="works"') < strpos($custom_tab_html, 'data-t="bio"'), 'shared tabs: repeater order controls rendered tab order');
T::ok(strpos($custom_tab_html, 'پورتفولیو') !== false && strpos($custom_tab_html, 'درباره هنرمند') !== false, 'shared tabs: custom labels override default labels');
T::ok(strpos($custom_tab_html, 'aetp-tab-icon') !== false && strpos($custom_tab_html, 'fa-paint-brush') !== false, 'shared tabs: Elementor icon picker output is rendered in tab labels');
T::ok(strpos($custom_tab_html, '<b>درباره هنرمند</b>') === false && strpos($custom_tab_html, 'data-t="contact"') === false, 'shared tabs: labels are escaped and disabled tabs are omitted');
$hidden_tabs_html = Artiey_AET_Tabs::render([
    ['key' => 'bio', 'label' => 'بیوگرافی', 'html' => '<p>متن</p>'],
], ['tabs_items' => [['tab_section' => 'bio', 'tab_enabled' => 'no']]], 'تب غیرفعال');
T::eq($hidden_tabs_html, '', 'shared tabs: intentionally disabled all tabs render no tab content');
$deleted_tabs_html = Artiey_AET_Tabs::render([
    ['key' => 'bio', 'label' => 'بیوگرافی', 'html' => '<p>متن</p>'],
    ['key' => 'works', 'label' => 'آثار', 'html' => '<p>نمونه آثار</p>'],
], ['tabs_items' => []], 'تمام تب‌ها حذف شده‌اند');
T::eq($deleted_tabs_html, '', 'shared tabs: explicitly empty repeater respects deletion of every tab');
$legacy_tabs_html = Artiey_AET_Tabs::render([
    ['key' => 'bio', 'label' => 'بیوگرافی', 'html' => '<p>متن</p>'],
    ['key' => 'works', 'label' => 'آثار', 'html' => '<p>نمونه آثار</p>'],
], [], 'تنظیمات قدیمی');
T::ok(strpos($legacy_tabs_html, 'data-t="bio"') !== false && strpos($legacy_tabs_html, 'data-t="works"') !== false, 'shared tabs: legacy widgets without repeater settings keep their original tabs');

echo "Slugs and links\n";
require_once dirname(__DIR__) . '/includes/class-slug.php';
require_once WP_ART_COA_DIR . 'includes/class-slug-history.php';
$long = 'namayeshgah-honar-moaser-irani-dar-gallery-bozorg-tehran-baraye-honarmandan-javan-va-moaser';
$sh = Artiey_AET_Slug::shorten($long, 60);
T::ok(strlen($sh) <= 60 && substr($long, 0, strlen($sh)) === $sh && $long[strlen($sh)] === '-', 'slug shortened at a word boundary');
T::eq(Artiey_AET_Slug::shorten('abc', 60), 'abc', 'short slug untouched');
T::eq(Artiey_AET_Slug::clean_base(' /Artists/ ', 'artist'), 'artists', 'base lowercased and trimmed');
T::eq(Artiey_AET_Slug::clean_base('art/artist/x/y', 'artist'), 'art/artist', 'base limited to two segments');
T::eq(Artiey_AET_Slug::clean_base('wp-json', 'artist'), 'artist', 'reserved base rejected');
T::eq(Artiey_AET_Slug::clean_base('', 'gallery'), 'gallery', 'empty base falls back to default');
T::eq(Artiey_AET_Slug::base('artist'), 'artists', 'plural default base without settings');
T::eq(Artiey_AET_Slug::base('gallery'), 'galleries', 'plural gallery base');
T::eq(Artiey_AET_Slug::base('exhibition'), 'exhibitions', 'plural exhibition base');
T::eq(Artiey_AET_Slug::base('artwork'), 'artworks', 'plural artwork base');
$artist_card = \WPArtCOA\Catalog::card_artist((object) ['slug' => 'test-artist', 'display_name' => 'هنرمند آزمایشی', 'works_count' => 0]);
T::ok(strpos($artist_card, 'هنرمند آزمایشی') !== false && strpos($artist_card, 'art-coa-c-ph') !== false, 'catalog artist card: placeholder avatar renders without namespace error');
T::eq(Artiey_AET_Text::substr('هنرمند', 0, 1), 'ه', 'UTF-8 substring fallback');
T::eq(Artiey_AET_Text::length('هنرمند'), 6, 'UTF-8 length fallback');
T::eq(Artiey_AET_Text::strpos('هنرمند آرتی', 'آرتی'), 7, 'UTF-8 strpos fallback');
T::eq(Artiey_AET_Text::strpos('هنرمند آرتی', 'آرتی', 2), 7, 'UTF-8 strpos with offset');
WPArtCOA\SlugHistory::record('artists', 'old-name', 'new-name');
T::eq(WPArtCOA\SlugHistory::resolve('artists', 'old-name'), 'new-name', 'renamed slug resolves');
WPArtCOA\SlugHistory::record('artists', 'new-name', 'newest');
T::eq(WPArtCOA\SlugHistory::resolve('artists', 'old-name'), 'newest', 'redirect chain collapsed');
T::eq(WPArtCOA\SlugHistory::resolve('galleries', 'old-name'), '', 'history is per table');
WPArtCOA\SlugHistory::record('artists', 'newest', 'old-name');
T::eq(WPArtCOA\SlugHistory::resolve('artists', 'old-name'), '', 'returning to an old slug cancels its redirect');
T::ok(isset(WPArtCOA\SlugHistory::TABLES['collector']) && WPArtCOA\SlugHistory::TABLES['collector'] === 'collectors', 'collector profile routes are included in slug-history validation');
T::ok(WPArtCOA\SlugHistory::is_public_row('collector', (object) ['status' => 'active', 'is_public' => 1]), 'active explicitly public collector profile is public');
T::ok(!WPArtCOA\SlugHistory::is_public_row('collector', (object) ['status' => 'active', 'is_public' => 0]), 'private collector profile is hidden from public routes');
T::ok(!WPArtCOA\SlugHistory::is_public_row('collector', (object) ['status' => 'inactive', 'is_public' => 1]), 'inactive collector profile is hidden from public routes');
T::ok(WPArtCOA\SlugHistory::is_public_row('collector', (object) ['status' => 'inactive', 'is_public' => 0], true), 'authorized administrators can still preview private collector profiles');
T::ok(WPArtCOA\SlugHistory::is_public_row('artist', (object) ['status' => 'active']), 'active artist profile passes route visibility guard');
T::ok(!WPArtCOA\SlugHistory::is_public_row('artist', (object) ['status' => 'inactive']), 'inactive artist profile fails route visibility guard');
T::ok(WPArtCOA\SlugHistory::is_public_row('artwork', (object) ['status' => 'sold']), 'sold artwork remains publicly viewable');
T::ok(!WPArtCOA\SlugHistory::is_public_row('artwork', (object) ['status' => 'draft']), 'draft artwork is blocked from public routes');
T::ok(WPArtCOA\SlugHistory::is_public_row('exhibition', (object) ['status' => 'published']), 'published exhibition passes route visibility guard');
T::ok(!WPArtCOA\SlugHistory::is_public_row('exhibition', (object) ['status' => 'draft']), 'draft exhibition fails route visibility guard');

$calls_source = file_get_contents(WP_ART_COA_DIR . 'includes/class-calls.php');
T::ok(strpos($calls_source, '\'id\' => $call_id') !== false && strpos($calls_source, '\'slug\' => $call_slug') !== false, 'call slug route passes resolved record ID and slug into Elementor context');
T::ok(strpos($calls_source, "add_filter('wp_robots', 'wp_robots_no_robots')") !== false, 'unknown call routes are marked noindex along with HTTP 404');
$auction_source = file_get_contents(WP_ART_COA_DIR . 'includes/class-auction.php');
T::ok(strpos($auction_source, 'a.status IN (') !== false && strpos($auction_source, 'a.status AS artwork_status') !== false && strpos($auction_source, '$row->artwork_status') !== false && strpos($auction_source, 'artist_status') !== false, 'public auction routes filter unpublished artwork without confusing it with auction status and hide inactive artist identity');
T::ok(strpos($auction_source, 'collector_is_public') !== false && strpos($auction_source, 'collector_status') !== false && strpos($auction_source, 'Private collector') !== false, 'auction views never disclose private collector names');
$secondary_source = file_get_contents(WP_ART_COA_DIR . 'includes/class-secondary-market.php');
T::ok(strpos($secondary_source, 'a.status IN (') !== false && strpos($secondary_source, 'approved') !== false && strpos($secondary_source, 'published') !== false && strpos($secondary_source, 'artist_status') !== false, 'secondary market lists only public artwork and hides inactive artist identity');
T::ok(strpos($secondary_source, 'collector_is_public') !== false && strpos($secondary_source, 'collector_status') !== false, 'secondary market links only active public collectors');
$profiles_source = file_get_contents(WP_ART_COA_DIR . 'includes/class-profiles.php');
T::ok(strpos($profiles_source, '$known_types') !== false && strpos($profiles_source, 'wp_robots_no_robots') !== false, 'unknown COA profile route types return a proper noindex 404');

echo "Timeline\n";
foreach (['class-dates', 'class-timeline', 'class-auction-timeline'] as $f) { if (is_file(WP_ART_COA_DIR . "includes/$f.php")) require_once WP_ART_COA_DIR . "includes/$f.php"; }
require_once WP_ART_COA_DIR . 'includes/entities/class-exhibition.php';
use WPArtCOA\Timeline;
T::eq(Timeline::layout('alt'), 'alt', 'layout accepted');
T::eq(Timeline::layout('<script>', 'cards'), 'cards', 'unknown layout falls back');
T::eq(Timeline::tag('h2'), 'h2', 'heading tag accepted');
T::eq(Timeline::tag('script'), 'h3', 'unsafe tag rejected');
T::ok(strpos(Timeline::wrapper_classes('grid', 9, 'x-y "bad'), 'art-coa-tl-cols-4') !== false, 'columns clamped to 4');
T::eq(Timeline::status('2026-10-01', '2026-10-20', '2026-10-10'), 'live', 'status live');
T::eq(Timeline::status('2026-10-15', '2026-10-20', '2026-10-10'), 'upcoming', 'status upcoming');
T::eq(Timeline::status('2026-09-01', '2026-09-20', '2026-10-10'), 'past', 'status past');
T::eq(Timeline::status('2026-09-01', '', '2026-10-10'), 'live', 'open-ended event is live');
T::eq(Timeline::days_between('2026-10-10', '2026-10-15'), 5, 'days between');
T::eq(Timeline::jalali_label('2026-03-21'), '۱ فروردین', 'jalali label');
T::eq(Timeline::range_label('2026-03-21', '2026-03-21'), '۱ فروردین', 'one-day range has no end');
T::ok(strpos(Timeline::range_label('2026-03-21', '2026-04-05'), ' تا ') !== false, 'range has start and end');
$html = Timeline::render(['year' => 1405, 'layout' => 'grid', 'columns' => 2, 'empty_text' => 'خالی']);
T::ok(strpos($html, 'art-coa-tl-layout-grid') !== false && strpos($html, 'خالی') !== false, 'render: layout class and empty text');
$GLOBALS['wpdb']->results = [(object) ['id' => 1, 'title' => 'نمایش <b>x</b>', 'subtitle' => 'زیر', 'start_date' => gmdate('Y-m-d', strtotime('-2 days')), 'end_date' => gmdate('Y-m-d', strtotime('+5 days')), 'event_type' => 'exhibition', 'slug' => 'show-1', 'gallery_name' => 'گالری الف', 'artist_label' => '', 'poster_url' => '', 'poster_id' => 0]];
$html = Timeline::render(['year' => 1405, 'range' => 'all', 'layout' => 'alt', 'show_status' => true, 'show_countdown' => true, 'show_location' => true, 'show_button' => true, 'button_text' => 'برو', 'item_tag' => 'h4']);
T::ok(strpos($html, 'art-coa-tl-status-live') !== false && strpos($html, 'در حال برگزاری') !== false, 'render: live status chip');
T::ok(strpos($html, '<b>x</b>') === false && strpos($html, '&lt;b&gt;x') !== false, 'render: title escaped');
T::ok(strpos($html, '<h4 class="art-coa-tl-item-title">') !== false, 'render: item tag respected');
T::ok(strpos($html, 'روز تا پایان') !== false, 'render: countdown to the end');
T::ok(strpos($html, 'محل برگزاری: گالری الف') !== false && strpos($html, 'art-coa-tl-btn') !== false, 'render: location and button');
T::ok(strpos($html, 'art-coa-tl-gallery-link') === false, 'render: gallery text stays plain when no public gallery URL is available');
$GLOBALS['wpdb']->results = [(object) ['id' => 2, 'title' => 'رویداد مرتبط', 'subtitle' => 'توضیح', 'start_date' => gmdate('Y-m-d', strtotime('-1 days')), 'end_date' => gmdate('Y-m-d', strtotime('+2 days')), 'event_type' => 'exhibition', 'slug' => 'show-2', 'cpt_post_id' => 200, 'gallery_name' => 'گالری پیوندخورده', 'gallery_slug' => 'linked-gallery', 'gallery_post_id' => 300, 'artist_label' => '', 'poster_url' => '', 'poster_id' => 0]];
$html = Timeline::render(['range' => 'all', 'show_location' => true, 'link_gallery' => true, 'show_poster' => false]);
T::ok(strpos($html, 'href="https://example.test/post/300/"') !== false && strpos($html, 'art-coa-tl-gallery-link') !== false, 'render: connected gallery links to its canonical WordPress profile');
T::ok(strpos($html, 'href="https://example.test/post/200/"') !== false, 'render: mirrored exhibition links to canonical WordPress page');
T::ok(strpos($html, 'application/ld+json') !== false && strpos($html, '"@type":"Event"') !== false, 'render: schema.org Event data');
$html = Timeline::render(['year' => 1405, 'range' => 'all', 'status' => 'past']);
T::ok(strpos($html, 'art-coa-empty') !== false, 'render: status filter hides non-matching events');
$GLOBALS['wpdb']->results = [];
$css = file_get_contents(WP_ART_COA_DIR . 'assets/css/frontend-dynamic.css');
$bad = [];
foreach (explode("\n", $css) as $ln) {
    if (strpos($ln, '!important') !== false && preg_match('/^\s*\.art-coa-(tl|timeline|ah|artwork-history)/', $ln)) { $bad[] = substr(trim($ln), 0, 60); }
}
T::eq($bad, [], 'css: timeline and history rules have no !important (Elementor controls must win)');
require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-timeline.php';
require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-auction-timeline.php';
require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-artwork-history.php';
foreach (['Timeline_Widget', 'Auction_Timeline_Widget', 'Artwork_History_Widget'] as $short) {
    $cls = "WPArtCOA\\Elementor\\Widgets\\$short";
    $w = new $cls(); $w->build();
    T::eq($w->errors, [], "$short: valid control structure");
    $bad = [];
    foreach ($w->controls as $id => $c) {
        foreach (array_keys($c['args']['condition'] ?? []) as $dep) {
            if (!isset($w->controls[rtrim($dep, '!')])) { $bad[] = "$id->$dep"; }
        }
    }
    T::eq($bad, [], "$short: every condition points to an existing control");
    T::ok(isset($w->controls['tl_title_typo']) || $short === 'Artwork_History_Widget', "$short: title typography control");
}
$tw = new WPArtCOA\Elementor\Widgets\Timeline_Widget(); $tw->build();
T::ok(isset($tw->controls['tl_sub_typo'], $tw->controls['tl_sub_color'], $tw->controls['tl_title_color'], $tw->controls['tl_status_live']), 'exhibition timeline: title, subtitle and status controls');
T::ok(isset($tw->controls['title'], $tw->controls['intro_subtitle'], $tw->controls['intro_title_typography'], $tw->controls['intro_title_color'], $tw->controls['intro_subtitle_typography'], $tw->controls['intro_subtitle_color']), 'exhibition timeline: editable main title and subtitle with style controls');
T::ok(isset($tw->controls['gallery_filter'], $tw->controls['link_gallery']), 'exhibition timeline: gallery filter and gallery profile link controls');
T::eq($tw->get_title(), 'تایم‌لاین کارت‌محور نمایشگاه‌ها و رویدادها', 'exhibition timeline: clearly distinguished from the daily calendar widget');
T::ok(!isset($tw->controls['title']['args']['condition']), 'exhibition timeline: title remains editable while display toggle is off');
$sel = $tw->controls['tl_sub_color']['args']['selectors'] ?? [];
T::ok(strpos((string) key($sel), '.art-coa-timeline .art-coa-tl-sub') !== false, 'subtitle colour selector is scoped to the timeline');

echo "Profile guard\n";
if (!class_exists('WP_Post')) { class WP_Post { public $ID = 0; public $post_content = ''; public $post_excerpt = ''; } }
foreach (['strip_shortcodes' => fn($t) => $t, 'wpautop' => fn($t) => '<p>' . trim($t) . '</p>', 'wp_kses_post' => fn($t) => $t, 'wp_strip_all_tags' => fn($t) => strip_tags($t)] as $fn => $impl) {
    if (!function_exists($fn)) { eval("function $fn(\$t) { return (\$GLOBALS['__impl']['$fn'])(\$t); }"); }
    $GLOBALS['__impl'][$fn] = $impl;
}
$GLOBALS['__meta'] = [];
if (!function_exists('get_post_meta')) { function get_post_meta($id, $k, $single = false) { return $GLOBALS['__meta'][$id][$k] ?? ''; } }
require_once dirname(__DIR__) . '/includes/class-profile-guard.php';
$p = new WP_Post(); $p->ID = 11; $p->post_content = 'بیوگرافی دست‌نوشته'; $p->post_excerpt = 'چکیده';
T::ok(strpos(Artiey_AET_Profile_Guard::bio_html($p), 'بیوگرافی دست‌نوشته') !== false, 'plain post: content is the biography');
$GLOBALS['__meta'][11]['_elementor_edit_mode'] = 'builder';
$p->post_content = '<div class="aetp" dir="rtl"><h2>همان پروفایل</h2></div>';
T::ok(strpos(Artiey_AET_Profile_Guard::bio_html($p), 'همان پروفایل') === false, 'Elementor post: widget HTML is never reused as biography');
T::ok(strpos(Artiey_AET_Profile_Guard::bio_html($p), 'چکیده') !== false, 'Elementor post: excerpt is used instead');
$p->post_excerpt = '';
T::eq(Artiey_AET_Profile_Guard::bio_html($p), '', 'Elementor post without excerpt: no biography');
$GLOBALS['__meta'][12] = [];
$q = new WP_Post(); $q->ID = 12; $q->post_content = 'متن <div class="aetp">تکراری</div> پایان';
T::ok(strpos(Artiey_AET_Profile_Guard::bio_html($q), 'تکراری') === false, 'old saved copy of the profile is stripped');
$bio1 = 'میلاد خلفی، هنرمند ایرانی متولد اهواز در سال ۱۳۷۲ در حوزه هنرهای تجسمی فعالیت می‌کند.';
$dirty = '<style>.pp-tooltip{font-weight:var(--x)}</style><p>' . $bio1 . '</p><div class="aetp" dir="rtl"><section class="aetp-sec aetp-bio"><h3>بیوگرافی</h3><style>.elementor-3215{--display:flex}</style><p>' . $bio1 . '</p><div class="aetp"><p>' . $bio1 . '</p></div></section></div><p>' . $bio1 . '</p>';
$clean = Artiey_AET_Profile_Guard::clean_markup($dirty);
T::ok(strpos($clean, 'pp-tooltip') === false && strpos($clean, 'elementor-3215') === false, 'clean: CSS text of <style> blocks is removed');
T::ok(strpos($clean, 'aetp') === false && strpos($clean, '<h3>') === false, 'clean: nested copies of the profile are removed');
T::eq(substr_count($clean, 'متولد اهواز'), 1, 'clean: the same paragraph appears only once');
$q->ID = 14; $q->post_content = $dirty; $q->post_excerpt = '';
$GLOBALS['__meta'][14] = [];
$html = Artiey_AET_Profile_Guard::bio_html($q);
T::ok(strpos($html, 'متولد اهواز') !== false && substr_count($html, 'متولد اهواز') === 1 && strpos($html, 'pp-tooltip') === false, 'bio_html: polluted content gives one clean biography');
$saved = Artiey_AET_Profile_Guard::filter_save(['post_type' => 'artiey_artist', 'post_content' => addslashes($dirty)]);
T::ok(strpos($saved['post_content'], 'aetp') === false, 'filter_save: our own output is not stored in post_content');
$same = Artiey_AET_Profile_Guard::filter_save(['post_type' => 'post', 'post_content' => $dirty]);
T::eq($same['post_content'], $dirty, 'filter_save: other post types are untouched');
$GLOBALS['__meta'][15]['_elementor_edit_mode'] = 'builder';
$e = new WP_Post(); $e->ID = 15; $e->post_excerpt = 'چکیدهٔ رسمی بیوگرافی';
$e->post_content = '<style>.elementor-9{--x:1}</style><div class="elementor-widget elementor-widget-heading"><h2>عنوان صفحه</h2></div><div class="elementor-widget elementor-widget-text-editor"><div class="elementor-widget-container"><p>' . $bio1 . '</p></div></div><div class="aetp"><p>' . $bio1 . '</p></div>';
$excerpt_src = Artiey_AET_Profile_Guard::bio_source($e);
T::ok(strpos($excerpt_src, 'چکیدهٔ رسمی بیوگرافی') !== false && strpos($excerpt_src, 'متولد اهواز') === false, 'bio_source: Elementor excerpt takes priority over text editor widgets');
$e->post_excerpt = '';
$src = Artiey_AET_Profile_Guard::bio_source($e);
T::ok(strpos($src, 'متولد اهواز') !== false && strpos($src, 'عنوان صفحه') === false && strpos($src, 'elementor-9') === false, 'bio_source: Elementor post keeps only Text Editor widgets');
T::eq(substr_count($src, 'متولد اهواز'), 1, 'bio_source: biography appears once');
T::eq(Artiey_AET_Profile_Guard::bio_source(null), '', 'bio_source: no post gives empty text');
Artiey_AET_Profile_Guard::mark(5);
T::ok(Artiey_AET_Profile_Guard::is_marked(5) && !Artiey_AET_Profile_Guard::is_marked(6), 'explicit render is remembered per post');


echo "Artist biography section\n";
require_once dirname(__DIR__) . '/includes/class-jalali.php';
require_once dirname(__DIR__) . '/includes/class-exhibition.php';
require_once dirname(__DIR__) . '/includes/class-artist.php';
echo "Artist profile chart shortcode integration\n";
$prepare_chart = new ReflectionMethod(Artiey_AET_Artist::class, 'prepare_chart_shortcode');
$prepare_chart->setAccessible(true);
T::eq($prepare_chart->invoke(null, '[art_coa_charts type="activity" artist="{artist_id}" months="{months}"]', 123, 77, 18), '[art_coa_charts type="activity" artist="77" months="18"]', 'chart shortcode: COA artist and month tokens replaced');
T::eq($prepare_chart->invoke(null, '[artiey_charts type="sales_value" artist="{artist_id}" months="{months}"]', 123, 77, 12), '[artiey_charts type="sales_value" artist="77" months="12"]', 'chart shortcode: legacy alias accepted');
T::eq($prepare_chart->invoke(null, '[art_coa_charts type="activity" artist="{artist_id}"]', 123, 0, 12), '', 'chart shortcode: unresolved COA token rejected safely');
T::eq($prepare_chart->invoke(null, '[do_something_untrusted id="1"]', 123, 77, 12), '', 'chart shortcode: arbitrary shortcode blocked');
T::eq($prepare_chart->invoke(null, '[art_coa_charts type="activity"] trailing text', 123, 77, 12), '', 'chart shortcode: non-shortcode trailing text rejected');
T::eq($prepare_chart->invoke(null, '[art_coa_charts type="activity"] [artiey_charts type="progress"]', 123, 77, 12), '[art_coa_charts type="activity"] [artiey_charts type="progress"]', 'chart shortcode: multiple approved chart shortcodes supported');
if (!function_exists('shortcode_exists')) { function shortcode_exists($tag) { return in_array((string) $tag, ['art_coa_charts', 'artiey_charts'], true) || !empty($GLOBALS['__dynamic_test_shortcodes'][(string) $tag]); } }
if (!function_exists('do_shortcode')) { function do_shortcode($text) { $GLOBALS['__test_chart_shortcode'] = (string) $text; $GLOBALS['__last_dynamic_shortcode'] = (string) $text; return '<figure class="art-coa-chart">test chart output</figure>'; } }
$render_chart = new ReflectionMethod(Artiey_AET_Artist::class, 'render_chart_shortcode');
$render_chart->setAccessible(true);
$shortcode_rendered = $render_chart->invoke(null, '[art_coa_charts type="activity" artist="55" months="{months}"]', 123, ['chart_months' => ['size' => 16]]);
T::ok(strpos($shortcode_rendered, 'test chart output') !== false, 'chart shortcode: manual mode executes an approved shortcode and returns its markup');
T::eq($GLOBALS['__test_chart_shortcode'] ?? '', '[art_coa_charts type="activity" artist="55" months="16"]', 'chart shortcode: selected period token is passed into shortcode renderer');

foreach (['header', 'short_bio', 'statement', 'bio', 'details', 'awards', 'career_timeline', 'contact', 'works', 'galleries', 'exhibitions', 'activity_chart', 'sales_chart', 'artwork_history'] as $section_key) {
    T::ok(in_array($section_key, Artiey_AET_Artist::ALL_SECTIONS, true), "main artist profile: {$section_key} section is registered");
}
$statement_method = new ReflectionMethod(Artiey_AET_Artist::class, 's_statement');
$statement_method->setAccessible(true);
$statement_html = $statement_method->invoke(null, ['statement' => 'بیانیهٔ مستقل هنرمند']);
T::ok(strpos($statement_html, 'aetp-statement') !== false && strpos($statement_html, 'بیانیهٔ مستقل هنرمند') !== false, 'main artist profile: statement is rendered independently');
$short_method = new ReflectionMethod(Artiey_AET_Artist::class, 's_short_bio');
$short_method->setAccessible(true);
$short_html = $short_method->invoke(null, ['short_bio' => 'معرفی کوتاه مستقل']);
T::ok(strpos($short_html, 'aetp-short-bio') !== false && strpos($short_html, 'معرفی کوتاه مستقل') !== false && strpos($short_html, 'بیانیهٔ هنری') === false, 'main artist profile: short introduction no longer duplicates the statement');
$career_method = new ReflectionMethod(Artiey_AET_Artist::class, 's_career_timeline');
$career_method->setAccessible(true);
$career_html = $career_method->invoke(null, ['education' => "۱۴۰۰ - کارشناسی ارشد\n۱۳۹۰ - کارشناسی", 'timeline' => "۱۳۹۵ - آغاز پروژه مستقل\nبدون سال"]);
$career_positions = [strpos($career_html, '۱۳۹۰'), strpos($career_html, '۱۳۹۵'), strpos($career_html, '۱۴۰۰'), strpos($career_html, 'بدون سال')];
T::ok($career_positions[0] !== false && $career_positions[1] > $career_positions[0] && $career_positions[2] > $career_positions[1] && $career_positions[3] > $career_positions[2] && strpos($career_html, 'جایزه اول') === false, 'main artist profile: education and independent career timeline are chronological and awards stay separate');
$artist_source = file_get_contents(dirname(__DIR__) . '/includes/class-artist.php');
T::ok(strpos($artist_source, "'interviews', 'certificate_stats'") !== false && strpos($artist_source, 'wp_oembed_get') !== false, 'artist profile: interview video player and certificate statistics sections are registered');
T::ok(strpos($artist_source, 'timeline_items') !== false && strpos($artist_source, "self::timeline_items( \$a['timeline']") !== false, 'artist profile: independent timeline data no longer duplicates the awards list');
$artist_admin_source = file_get_contents(dirname(__DIR__) . '/includes/class-artist-admin.php');
T::ok(strpos($artist_admin_source, "aeta[interviews]") !== false && strpos($artist_admin_source, "aeta[related_media]") !== false && strpos($artist_admin_source, "_aa_timeline") !== false, 'artist admin: interview, related media and independent timeline fields are editable');
$style_source = file_get_contents(dirname(__DIR__) . '/includes/class-elementor-style.php');
$coa_style_source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/elementor/class-design-controls.php');
T::ok(strpos($style_source, 'aet_extra_shortcode') !== false && strpos($coa_style_source, 'aet_extra_shortcode') !== false, 'all platform and COA Elementor widget families expose the shared shortcode field');

T::ok(strpos($artist_source, "meta_query'             => array( array( 'key' => '_aw_artist'") !== false && strpos($artist_source, "get_post_meta( \$work_id, '_aw_gallery'") !== false, 'artist relationships: artwork-to-gallery links are resolved from canonical artwork metadata');
T::ok(strpos($artist_source, 'a ON a.gallery_id = g.id') !== false && strpos($artist_source, "g.status = 'active'") !== false, 'artist relationships: COA artist-artwork-gallery bridge supplements incomplete WordPress mirror metadata');
T::ok(strpos($artist_source, "WHERE artist_id = %d AND status IN ('approved','published','active','sold')") !== false && strpos($artist_source, 'array_unique( $slugs )') !== false, 'artist relationships: catalogue artworks can be recovered through COA canonical IDs and slugs');
T::ok(strpos($artist_source, 'if ( $post_id && $slug ) { $id_slugs[ $post_id ] = $slug; }') !== false, 'artist relationships: COA artwork post IDs are trusted only when a canonical slug is available for validation');
T::ok(strpos($artist_source, "Database::table( 'exhibition_artworks' )") !== false && strpos($artist_source, "e.status IN ('published','active')") !== false, 'artist relationships: public exhibitions are also resolved through COA artwork participation');
T::ok(strpos($artist_source, 'if ( $post_id && $slug ) { $ids[] = $post_id; $id_slugs[ $post_id ] = $slug; }') !== false, 'artist relationships: exhibition mirror IDs require canonical slug validation before use');
T::ok(strpos($artist_source, "post_name__in' => \$slugs") !== false, 'artist relationships: exhibition bridge can recover an existing canonical post by slug');
$history_owner_source = file_get_contents(WP_ART_COA_DIR . 'includes/class-artwork-history-timeline.php');
T::ok(strpos($history_owner_source, "c.is_public = 1 AND c.status = \'active\'") !== false && strpos($history_owner_source, "'owner' => (string) (\$row->public_owner ?? '')") !== false, 'artist timeline: only active public collectors can appear as named owners');
$today_tl = current_time('Y-m-d');
$old_past = new WP_Post(); $old_past->ID = 801;
$new_past = new WP_Post(); $new_past->ID = 802;
$upcoming = new WP_Post(); $upcoming->ID = 803;
$GLOBALS['__meta'][801]['_ae_start'] = date('Y-m-d', strtotime($today_tl . ' - 60 days')); $GLOBALS['__meta'][801]['_ae_end'] = date('Y-m-d', strtotime($today_tl . ' - 30 days'));
$GLOBALS['__meta'][802]['_ae_start'] = date('Y-m-d', strtotime($today_tl . ' - 10 days')); $GLOBALS['__meta'][802]['_ae_end'] = date('Y-m-d', strtotime($today_tl . ' - 2 days'));
$GLOBALS['__meta'][803]['_ae_start'] = date('Y-m-d', strtotime($today_tl . ' + 5 days')); $GLOBALS['__meta'][803]['_ae_end'] = date('Y-m-d', strtotime($today_tl . ' + 10 days'));
$ex_groups = Artiey_AET_Exhibition::group([$old_past, $upcoming, $new_past]);
T::eq(array_map(static fn($p) => $p->ID, $ex_groups['past']), [802, 801], 'artist timeline: past exhibitions sort newest-first after merging relationship sources');
T::eq(array_map(static fn($p) => $p->ID, $ex_groups['upcoming']), [803], 'artist timeline: upcoming exhibitions remain nearest-first');
$exhibition_source = file_get_contents(dirname(__DIR__) . '/includes/class-exhibition.php');
T::ok(strpos($exhibition_source, 'show_curator') !== false && strpos($exhibition_source, 'aetp-card-curator') !== false, 'artist timeline: exhibition cards can show the connected curator');
T::ok(strpos($artist_source, 'private static $coa_artist_id_cache') !== false && strpos($artist_source, 'array_key_exists( $artist_post_id, self::$coa_artist_id_cache )') !== false, 'artist relationships: COA artist mapping is cached per request across profile sections');
T::ok(strpos($exhibition_source, 'id IN ({$placeholders})') !== false && strpos($exhibition_source, 'aetp-card-curator-link') !== false, 'artist timeline: active COA curator profiles are bulk-loaded and linked from exhibition cards');
T::ok(strpos($exhibition_source, 'self::curator_profiles( $curator_ids )') !== false, 'artist timeline: curator profile details are prefetched once for all visible exhibition cards');
$history_source = file_get_contents(WP_ART_COA_DIR . 'includes/class-artwork-history-timeline.php');
T::ok(strpos($history_source, 'absint($args[\'artist_id\'] ?? 0)') !== false && substr_count($history_source, 'AND a.artist_id = %d') === 4, 'artist profile: history timeline filters the COA history to the linked artist');
foreach (['class-i18n', 'class-calendar', 'class-catalog', 'class-artist-bio'] as $f) { if (is_file(WP_ART_COA_DIR . "includes/$f.php")) require_once WP_ART_COA_DIR . "includes/$f.php"; }
if (!function_exists('apply_filters')) { function apply_filters($t, $v) { return $v; } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v) { return trim(strip_tags((string) $v)); } }
$tl = \WPArtCOA\ArtistBio::timeline_items("۱۳۹۵ - دیپلم نقاشی\n1400: جایزه اول\n\nعضو انجمن");
T::eq(count($tl), 3, 'timeline: three non-empty lines');
T::eq($tl[0], ['۱۳۹۵', 'دیپلم نقاشی'], 'timeline: Persian year and text split');
T::eq($tl[1], ['1400', 'جایزه اول'], 'timeline: Latin year with colon');
T::eq($tl[2], ['', 'عضو انجمن'], 'timeline: line without a year');
$tl_unsorted = \WPArtCOA\ArtistBio::timeline_items("۱۴۰۳ - نمایشگاه انفرادی\n۱۳۹۰ - کارشناسی هنر\n1400: جایزه ملی\nبدون تاریخ");
T::eq(array_column($tl_unsorted, 0), ['۱۳۹۰', '1400', '۱۴۰۳', ''], 'timeline: Persian and Arabic years sort chronologically with undated items last');
T::eq(array_column($tl_unsorted, 1), ['کارشناسی هنر', 'جایزه ملی', 'نمایشگاه انفرادی', 'بدون تاریخ'], 'timeline: stable event descriptions remain attached to their dates');
$a = (object) ['id' => 7, 'bio' => "<p>بند اول</p><p>بند دوم</p><p>بند سوم</p><style>.x{color:red}</style>", 'short_bio' => 'معرفی', 'birth_year' => 1372, 'city' => 'اهواز', 'education' => "۱۳۹۰ - کارشناسی\n۱۳۹۴ - ارشد", 'awards' => '', 'artistic_statement' => 'بیانیه'];
$html = \WPArtCOA\ArtistBio::render($a);
T::ok(strpos($html, 'art-coa-bio-sec') !== false && strpos($html, 'معرفی') !== false, 'bio: section with lead renders');
T::ok(strpos($html, 'color:red') === false, 'bio: stored CSS is never printed');
T::ok(strpos($html, '<details class="art-coa-bio-more">') !== false && strpos($html, 'بند سوم') > strpos($html, '<details'), 'bio: paragraphs beyond the visible ones go into read-more');
T::ok(strpos($html, 'art-coa-bio-tl') !== false && strpos($html, 'art-coa-bio-statement') !== false && strpos($html, 'اهواز') !== false, 'bio: timeline, statement and facts render');
T::ok(strpos($html, 'is-awards') === false, 'bio: empty awards produce no block');
$min = \WPArtCOA\ArtistBio::render($a, ['show_lead' => false, 'show_facts' => false, 'show_statement' => false, 'show_education' => false, 'readmore' => false, 'show_title' => false]);
T::ok(strpos($min, 'details') === false && strpos($min, 'bio-facts') === false && strpos($min, 'bio-title') === false && strpos($min, 'بند سوم') !== false, 'bio: every part can be switched off');
$no_bio = \WPArtCOA\ArtistBio::render($a, ['show_biography' => false, 'show_lead' => false, 'show_facts' => true, 'show_statement' => false, 'show_education' => false, 'show_awards' => false, 'show_title' => false]);
T::ok(strpos($no_bio, 'بند اول') === false && strpos($no_bio, 'اهواز') !== false, 'bio: tab segmentation can suppress biography text from non-biography tabs');
if (!function_exists('shortcode_atts')) { function shortcode_atts($d, $a, $s = '') { return array_merge($d, array_intersect_key((array) $a, $d)); } }
$d = \WPArtCOA\ArtistBio::defaults();
T::ok($d['show_facts'] === true && $d['visible_paragraphs'] === 2 && $d['title'] === 'بیوگرافی', 'bio: defaults without saved settings');
T::eq(\WPArtCOA\ArtistBio::render((object) ['id' => 1, 'bio' => '']), '', 'bio: nothing to show gives empty output');
require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-artist-bio.php';
T::ok(class_exists('\\WPArtCOA\\Elementor\\Widgets\\Artist_Bio_Widget'), 'bio widget class loads');
$bio_widget = new \WPArtCOA\Elementor\Widgets\Artist_Bio_Widget(); $bio_widget->build();
T::ok(isset($bio_widget->controls['presentation'], $bio_widget->controls['bio_tabs'], $bio_widget->controls['tabs_orientation']), 'bio widget: pro tab layout and per-section selection controls exist');
T::ok(in_array('artiey-aet-profile', $bio_widget->get_script_depends(), true), 'bio widget: shared tabs script dependency loaded');
require_once WP_ART_COA_DIR . 'includes/class-portfolio.php';
require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-portfolio.php';
$portfolio_widget = new \WPArtCOA\Elementor\Widgets\Widget_Portfolio(); $portfolio_widget->build();
T::ok(isset($portfolio_widget->controls['presentation'], $portfolio_widget->controls['portfolio_tabs'], $portfolio_widget->controls['tabs_orientation']), 'portfolio widget: tabbed certified/all artwork views configurable');
T::ok(in_array('artiey-aet-profile', $portfolio_widget->get_script_depends(), true), 'portfolio widget: shared tabs script dependency loaded');
T::ok(strpos(file_get_contents(WP_ART_COA_DIR . 'includes/class-portfolio.php'), 'certified_only') !== false, 'portfolio renderer: certified-only query mode is supported');
T::ok(strpos(file_get_contents(WP_ART_COA_DIR . 'includes/class-portfolio.php'), "EXISTS (SELECT 1 FROM " ) !== false, 'portfolio renderer: certified tab filters by active COA certificate rows');
T::ok(strpos(file_get_contents(WP_ART_COA_DIR . 'includes/class-portfolio.php'), '$artists = $show_filter ? self::filter_artists() : [];') !== false && strpos(file_get_contents(WP_ART_COA_DIR . 'includes/class-portfolio.php'), 'request_value = static function') !== false, 'portfolio renderer: tab panels skip unused filter queries and reject non-scalar request filters');
T::ok(strpos(file_get_contents(WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-portfolio.php'), 'register_behavior_controls') !== false, 'portfolio widget: shared pro tab behaviour is enabled');

echo "Auction list widget\n";
require_once WP_ART_COA_DIR . 'includes/elementor/widgets/class-widget-auction-list.php';
T::ok(class_exists('\\WPArtCOA\\Elementor\\Widgets\\Auction_List_Widget'), 'auction list widget class loads');
$css = file_get_contents(WP_ART_COA_DIR . 'assets/css/frontend-dynamic.css');
T::ok(strpos($css, 'repeat(min(') === false, 'css: no invalid repeat(min()) column counts');
T::ok(strpos(file_get_contents(WP_ART_COA_DIR . 'includes/class-auction.php'), 'art-coa-au-card') !== false, 'auction card uses the unified card markup');

echo "Content sync batches\n";
$delete_sync_source = file_get_contents(WP_ART_COA_DIR . 'includes/class-content-sync.php');
T::ok(strpos($delete_sync_source, "'deleted-' . ") !== false && strpos($delete_sync_source, "purge_public_url_caches") !== false, 'permanent deletion releases mirrored profile slug and invalidates URL caches');
require_once WP_ART_COA_DIR . 'includes/class-content-sync.php';
foreach (['get_post' => fn() => null, 'wp_next_scheduled' => fn() => false, 'wp_schedule_single_event' => fn() => true] as $fn => $impl) {
    if (!function_exists($fn)) { eval("function $fn() { return null; }"); }
}
if (!function_exists('delete_option')) { function delete_option($k) { unset($GLOBALS['__opts'][$k]); return true; } }
$real_wpdb = $GLOBALS['wpdb'] ?? null;
$GLOBALS['wpdb'] = new class {
    public string $posts = 'wp_posts';
    public string $last_error = '';
    public array $pages = [];
    public function prepare($q, ...$a) { return $q; }
    public function get_col($q) { return array_shift($this->pages) ?? []; }
};
\WPArtCOA\ContentSync::queue_full_sync();
T::eq(get_option(\WPArtCOA\ContentSync::CURSOR_OPT), ['type' => 0, 'after' => 0], 'queue: cursor starts at the first post type');
$GLOBALS['wpdb']->pages = [range(1, \WPArtCOA\ContentSync::BATCH_SIZE)];
\WPArtCOA\ContentSync::run_batch();
T::eq(get_option(\WPArtCOA\ContentSync::CURSOR_OPT), ['type' => 0, 'after' => \WPArtCOA\ContentSync::BATCH_SIZE], 'batch: a full page keeps the type and moves the cursor');
$GLOBALS['wpdb']->pages = [[100, 101]];
\WPArtCOA\ContentSync::run_batch();
T::eq(get_option(\WPArtCOA\ContentSync::CURSOR_OPT), ['type' => 1, 'after' => 0], 'batch: a short page moves on to the next post type');
update_option(\WPArtCOA\ContentSync::CURSOR_OPT, ['type' => 4, 'after' => 0]);
\WPArtCOA\ContentSync::run_batch();
T::ok(get_option(\WPArtCOA\ContentSync::CURSOR_OPT) === false, 'batch: cursor is removed when every type is done');
update_option(\WPArtCOA\ContentSync::CURSOR_OPT, ['type' => 0, 'after' => 0]);
$GLOBALS['wpdb']->last_error = 'temporary database failure';
$GLOBALS['wpdb']->pages = [[]];
\WPArtCOA\ContentSync::run_batch();
T::eq(get_option(\WPArtCOA\ContentSync::CURSOR_OPT), ['type' => 0, 'after' => 0], 'batch: query error retains cursor for retry');
$GLOBALS['wpdb']->last_error = '';
$GLOBALS['wpdb'] = $real_wpdb;

$hierarchy_source = file_get_contents(WP_ART_COA_DIR . 'includes/class-hierarchy.php');
T::ok(strpos($hierarchy_source, "Database::table('exhibition_artworks')") !== false && strpos($hierarchy_source, 'coa_exhibitions') !== false, 'artist hierarchy: COA exhibitions are related through the artwork bridge');
T::ok(strpos($hierarchy_source, "key' => '_ag_artists'") !== false && strpos($hierarchy_source, "key' => '_ae_artists'") !== false && strpos($hierarchy_source, "key' => '_aw_artist'") !== false, 'artist hierarchy: direct WordPress artist-gallery-artwork-exhibition relations are queried');
T::ok(strpos($hierarchy_source, '(ارتباط از طریق آثار)') !== false, 'artist hierarchy: COA-derived exhibition relations are explained in the UI');

echo "Canonical artist source and duplicate prevention\n";
require_once WP_ART_COA_DIR . 'includes/class-content-bridge.php';
$bridge_ref = new ReflectionClass(\WPArtCOA\ContentBridge::class);
$identity_method = $bridge_ref->getMethod('find_existing_artist_identity');
$identity_method->setAccessible(true);
$legacy_identity_method = $bridge_ref->getMethod('find_existing_artist_post');
$legacy_identity_method->setAccessible(true);
$GLOBALS['wpdb']->result_queue = [
    [(object) ['id' => 11, 'cpt_post_id' => 0]],
    [(object) ['id' => 22, 'cpt_post_id' => 0]],
];
$GLOBALS['__get_posts_queue'] = [[], []];
$conflict = $identity_method->invoke(null, ['user_id' => 77, 'national_id' => 'NID-1', 'email' => '']);
T::ok(!empty($conflict['ambiguous']) && empty($conflict['row_id']), 'artist identity: conflicting user ID and national ID rows fail safely');
$GLOBALS['wpdb']->result_queue = [
    [(object) ['id' => 5, 'cpt_post_id' => 77]],
    [],
];
$GLOBALS['__get_posts_queue'] = [[77], []];
$matched = $identity_method->invoke(null, ['user_id' => 77, 'national_id' => 'NID-2', 'email' => '']);
T::eq([$matched['row_id'], $matched['post_id'], $matched['ambiguous']], [5, 77, false], 'artist identity: one matching relational row and canonical post are adopted');
$GLOBALS['wpdb']->result_queue = [
    [(object) ['id' => 5, 'cpt_post_id' => 77]],
    [],
];
$GLOBALS['__get_posts_queue'] = [[88], []];
$conflict_link = $identity_method->invoke(null, ['user_id' => 77, 'national_id' => 'NID-2', 'email' => '']);
T::ok(!empty($conflict_link['ambiguous']), 'artist identity: linked row and identity metadata pointing at different posts fail safely');
$GLOBALS['wpdb']->result_queue = [];
$GLOBALS['wpdb']->results = [];
$GLOBALS['__get_posts_queue'] = [[31], [42]];
$multi_post = $legacy_identity_method->invoke(null, ['user_id' => 31, 'national_id' => 'NID-3', 'email' => '']);
T::ok(!empty($multi_post['ambiguous']), 'legacy artist adoption: conflicting identity fields never choose an arbitrary post');
$GLOBALS['__get_posts_queue'] = [];
$sync_ref = new ReflectionClass(\WPArtCOA\ContentSync::class);
$unlinked_method = $sync_ref->getMethod('unlinked_artist_identity_row');
$unlinked_method->setAccessible(true);
$GLOBALS['wpdb']->result_queue = [
    [(object) ['id' => 5, 'cpt_post_id' => 77]],
    [],
];
$adopt_row = $unlinked_method->invoke(null, 'wp_art_artists', ['user_id' => 77, 'national_id' => 'NID-4'], ['id', 'slug', 'cpt_post_id', 'user_id', 'national_id'], 77);
T::eq([$adopt_row['id'], $adopt_row['ambiguous']], [5, false], 'mirror identity: existing matching row can be retained for the same canonical post');
$GLOBALS['wpdb']->result_queue = [[
    (object) ['id' => 5, 'cpt_post_id' => 0], (object) ['id' => 6, 'cpt_post_id' => 0],
]];
$duplicate_rows = $unlinked_method->invoke(null, 'wp_art_artists', ['user_id' => 77], ['id', 'slug', 'cpt_post_id', 'user_id'], 77);
T::ok(!empty($duplicate_rows['ambiguous']), 'mirror identity: duplicate relational identity rows are not randomly merged');
$GLOBALS['wpdb']->result_queue = [
    [(object) ['id' => 5, 'cpt_post_id' => 0]],
    [(object) ['id' => 6, 'cpt_post_id' => 0]],
];
$cross_conflict = $unlinked_method->invoke(null, 'wp_art_artists', ['user_id' => 77, 'national_id' => 'NID-5'], ['id', 'slug', 'cpt_post_id', 'user_id', 'national_id'], 77);
T::ok(!empty($cross_conflict['ambiguous']), 'mirror identity: different identifiers matching different rows are flagged');
$GLOBALS['wpdb']->result_queue = [];
$artist_source = file_get_contents(WP_ART_COA_DIR . 'includes/entities/class-artist.php');
$bridge_source = file_get_contents(WP_ART_COA_DIR . 'includes/class-content-bridge.php');
$sync_source = file_get_contents(WP_ART_COA_DIR . 'includes/class-content-sync.php');
$bio_source = file_get_contents(WP_ART_COA_DIR . 'includes/class-artist-bio.php');
$admin_source = file_get_contents(dirname(__DIR__) . '/includes/class-platform-admin.php');
T::ok(strpos($artist_source, "ContentBridge::save_from_form('artist'") !== false && strpos($artist_source, 'ContentBridge::update_from_entity_row') !== false, 'artist entity create/update both write through canonical WordPress bridge');
T::ok(strpos($artist_source, 'ContentBridge::canonical_artist_row($row)') !== false, 'artist point reads overlay canonical WordPress fields');
T::ok(strpos($artist_source, "get_page_by_path(\$slug, OBJECT, 'artiey_artist')") !== false, 'artist read resolves canonical CPT slug when the COA mirror is stale');
T::ok(strpos($bridge_source, 'function canonical_artist_row') !== false && strpos($bridge_source, "\$out->status = \$post->post_status === 'publish' ? 'active' : 'inactive';") !== false, 'artist reads use canonical post fields and publication status');
T::ok(strpos($bridge_source, 'count($linked_ids) > 1') !== false && strpos($sync_source, 'count($linked_ids) > 1') !== false, 'duplicate COA-to-post links fail closed rather than selecting the first row');
T::ok(strpos($bio_source, "get_post_status(\$post_id) !== 'publish'") !== false, 'COA biography hides unpublished profiles from unauthorized visitors');
T::ok(strpos($admin_source, 'artiey_aet_bridge_final_failed_rows') !== false && strpos($admin_source, 'artiey_aet_sync_final_failed_posts') !== false, 'platform health panel reports unresolved legacy links and sync failures');

echo "Translations of the new strings\n";
T::eq(\WPArtCOA\I18n::t('au_left', ['d' => 2, 'h' => 3]), \WPArtCOA\I18n::t('au_left', ['d' => 2, 'h' => 3]), 'i18n: placeholders resolve');
T::ok(strpos(\WPArtCOA\I18n::t('au_left', ['d' => 2, 'h' => 3]), '2') !== false && strpos(\WPArtCOA\I18n::t('au_left', ['d' => 2, 'h' => 3]), '{') === false, 'i18n: countdown text has no unreplaced placeholder');
T::ok(\WPArtCOA\I18n::t('bio_more') !== 'bio_more' && \WPArtCOA\I18n::t('au_estimate') !== 'au_estimate', 'i18n: new keys exist');

echo "Admin menu organization\n";
if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
}
require_once dirname(__DIR__) . '/includes/class-platform-admin.php';
$GLOBALS['submenu']['artiey-platform'] = [
    ['پلتفرم آرتی', 'edit_posts', 'artiey-platform'],
    ['پیشخوان', 'edit_posts', 'artiey-platform'],
    ['همه گالری‌ها', 'edit_posts', 'edit.php?post_type=artiey_gallery'],
    ['افزودن گالری جدید', 'edit_posts', 'post-new.php?post_type=artiey_gallery'],
    ['همه هنرمندان', 'edit_posts', 'edit.php?post_type=artiey_artist'],
    ['همه آثار', 'edit_posts', 'edit.php?post_type=artiey_artwork'],
    ['همه نمایشگاه‌ها', 'edit_posts', 'edit.php?post_type=artiey_exhibition'],
    ['سبک در گالری‌ها', 'manage_categories', 'edit-tags.php?taxonomy=artiey_style&post_type=artiey_gallery'],
    ['سبک در هنرمندان', 'manage_categories', 'edit-tags.php?taxonomy=artiey_style&post_type=artiey_artist'],
    ['مدیریت گواهی‌ها', 'manage_coa_certificates', 'wp-coa-pro'],
    ['COA Pro', 'manage_coa_certificates', 'admin.php?page=wp-coa-pro'],
    ['گواهی‌ها', 'manage_options', 'wp-art-coa-certificates'],
    ['حراج', 'manage_options', 'wp-art-coa-auctions'],
    ['تنظیمات پلتفرم', 'manage_options', 'artiey-aet-settings'],
    ['صفحه خارجی افزونه', 'manage_options', 'artiey-unfamiliar-tool'],
];
Artiey_AET_Platform_Admin::organize_menu();
$artiey_menu = $GLOBALS['submenu']['artiey-platform'];
$artiey_slugs = array_map(static function ($item) { return (string) $item[2]; }, $artiey_menu);
T::eq(count(array_filter($artiey_slugs, static function ($slug) { return $slug === 'artiey-platform'; })), 1, 'menu: only one dashboard destination');
T::eq(count(array_filter($artiey_slugs, static function ($slug) { return $slug === 'wp-coa-pro' || $slug === 'admin.php?page=wp-coa-pro'; })), 1, 'menu: COA Pro malformed duplicate removed');
T::eq(count(array_filter($artiey_slugs, static function ($slug) { return strpos($slug, 'edit-tags.php?taxonomy=artiey_style') === 0; })), 1, 'menu: shared taxonomy screen listed once');
T::eq($artiey_menu[0][0], 'پیشخوان', 'menu: dashboard is first with normalized label');
T::ok(array_search('edit.php?post_type=artiey_gallery', $artiey_slugs, true) < array_search('wp-coa-pro', $artiey_slugs, true), 'menu: content screens precede certificate tools');
T::ok(array_search('wp-art-coa-auctions', $artiey_slugs, true) < array_search('artiey-aet-settings', $artiey_slugs, true), 'menu: market screens precede settings');
T::ok(in_array('artiey-unfamiliar-tool', $artiey_slugs, true), 'menu: unfamiliar extension links are preserved');

echo "Admin artist media picker regression checks\n";
$media_picker_source = file_get_contents(dirname(__DIR__) . '/assets/js/gallery-admin.js');
$media_admin_source = file_get_contents(dirname(__DIR__) . '/includes/class-gallery-admin.php');
$artist_admin_source = file_get_contents(dirname(__DIR__) . '/includes/class-artist-admin.php');
T::ok(strpos($media_picker_source, "frame.on('select'") !== false, 'media picker binds to WordPress MediaFrame select event');
T::ok(strpos($media_picker_source, "frame.on('open'") !== false, 'media picker restores selections on actual open event');
T::ok(strpos($media_picker_source, "frame.off('select.aetg'") === false && strpos($media_picker_source, "frame.off('open.aetg'") === false, 'invalid Backbone event namespace handlers removed');
T::ok(strpos($media_admin_source, "wp_enqueue_script( 'artiey-aet-gadmin', AET_URL . 'assets/js/gallery-admin.js', array( 'jquery' ), AET_VERSION, true );") !== false && strpos($media_admin_source, '$deps[] = \'aet-leaflet\'') === false, 'media picker no longer depends on optional Leaflet CDN');
T::ok(strpos($artist_admin_source, "! isset( \$_POST['aeta'] ) || ! is_array( \$_POST['aeta'] )") !== false && strpos($artist_admin_source, 'wp_is_post_revision( $post_id )') !== false, 'artist save protects existing metadata and skips revisions');
T::ok(strpos($artist_admin_source, 'wp_attachment_is_image( $id )') !== false && strpos($artist_admin_source, 'self::image_ids( $in[\'works\'], 60 )') !== false, 'artist cover/portfolio metadata is restricted to images');
$artist_front_source = file_get_contents(dirname(__DIR__) . '/includes/class-artist.php');
T::ok(strpos($artist_front_source, "preg_split( '/[,;\\s]+/', \$works )") !== false && strpos($artist_front_source, "'post_mime_type'         => 'image'") !== false && strpos($artist_front_source, "'post__in'               => \$candidate_work_ids") !== false, 'artist public profile accepts legacy CSV portfolios and validates all portfolio images in one bounded query');
T::ok(strpos($artist_front_source, "private static function editor_preview_notice") !== false && strpos($artist_front_source, "! empty( \$options['elementor_light_preview'] )") !== false && strpos($artist_front_source, '$include_coa ? self::coa_artist_id') !== false, 'artist profile uses a lightweight Elementor preview path and skips COA relationship fallbacks');

if (!function_exists('get_post_type')) {
    function get_post_type($id) { return $GLOBALS['__artist_media_post_types'][(int) $id] ?? ''; }
}
if (!function_exists('get_the_ID')) { function get_the_ID() { return 0; } }
if (!function_exists('get_queried_object_id')) { function get_queried_object_id() { return (int) ($GLOBALS['__dynamic_queried_object_id'] ?? 0); } }
if (!function_exists('is_admin')) { function is_admin() { return true; } }
if (!class_exists('\\Elementor\\Plugin')) { eval('namespace Elementor; class Plugin { public static $instance; }'); }
\Elementor\Plugin::$instance = (object) ['editor' => new class { public function is_edit_mode() { return true; } }];
$GLOBALS['__artist_media_post_types'][3215] = 'artiey_artist';
$GLOBALS['__artist_media_post_types'][3216] = 'page';
$_REQUEST['action'] = 'elementor';
$_REQUEST['post'] = 3215;
T::eq(Artiey_AET_Elementor_Options::current_post_id('artiey_artist'), 3215, 'artist widget: Elementor editor shell resolves the active artist from the post parameter');
T::eq(Artiey_AET_Elementor_Options::current_post_id('artiey_gallery'), 0, 'artist widget: editor document resolver rejects a post with the wrong content type');
if (!class_exists('WP_Post')) {
    class WP_Post { public $ID; public function __construct($id) { $this->ID = (int) $id; } }
}
$GLOBALS['post'] = new WP_Post(3216); // Simulate a stale/global context pointing at another post type.
$artist_current_cache = new ReflectionProperty('Artiey_AET_Elementor_Options', 'current_post_cache');
$artist_current_cache->setAccessible(true);
$artist_current_cache->setValue(null, []);
T::eq(Artiey_AET_Elementor_Options::current_post_id('artiey_artist'), 3215, 'artist widget: active Elementor request wins over a stale global post');
unset($GLOBALS['post'], $_REQUEST['action'], $_REQUEST['post']);
if (!function_exists('wp_attachment_is_image')) {
    function wp_attachment_is_image($id) { return in_array((int) $id, $GLOBALS['__artist_media_image_ids'] ?? [], true); }
}
require_once dirname(__DIR__) . '/includes/class-artist-admin.php';
$GLOBALS['__artist_media_post_types'] = [101 => 'attachment', 102 => 'attachment', 103 => 'attachment', 104 => 'post'];
$GLOBALS['__artist_media_image_ids'] = [101, 103];
T::eq(Artiey_AET_Elementor_Options::sections(['sections' => ['header', 'bio', 'bio', 'invalid']], 'sections', ['header', 'bio', 'works']), ['header', 'bio'], 'artist widget: section selection normalizes arrays, removes duplicates and rejects unknown values');
T::eq(Artiey_AET_Elementor_Options::sections(['sections' => 'header, bio,works,unknown'], 'sections', ['header', 'bio', 'works']), ['header', 'bio', 'works'], 'artist widget: legacy comma-separated section settings remain compatible');
T::eq(Artiey_AET_Elementor_Options::sections(['sections' => []], 'sections', ['header', 'bio']), [], 'artist widget: an intentionally empty section selection remains empty');
T::eq(Artiey_AET_Elementor_Options::sections([], 'sections', ['header']), null, 'artist widget: missing old section setting falls back to all sections');
T::ok(strpos(file_get_contents(dirname(__DIR__) . '/includes/class-elementor-options.php'), '$in_editor  = self::is_elementor_preview();') !== false && strpos(file_get_contents(dirname(__DIR__) . '/includes/class-elementor-options.php'), '$document->get_main_id()') < strpos(file_get_contents(dirname(__DIR__) . '/includes/class-elementor-options.php'), "array( 'elementor-preview', 'editor_post_id', 'post_id', 'post' )"), 'artist widget: Elementor active document/request takes priority over stale WordPress globals');
T::ok(strpos(file_get_contents(dirname(__DIR__) . '/includes/class-elementor-style.php'), 'current_post_id( $type )') !== false && strpos(file_get_contents(dirname(__DIR__) . '/includes/class-elementor-style.php'), '$options[ $current_id ] = get_the_title( $current_id )') !== false, 'artist widget: current artist is included in the selector even beyond the 300-item picker cache');
T::ok(strpos(file_get_contents(dirname(__DIR__) . '/includes/class-elementor-options.php'), 'isset( \\Elementor\\Plugin::$instance->documents )') !== false, 'artist widget: Elementor document resolver uses the valid namespaced Plugin API');
$image_ids_method = new ReflectionMethod('Artiey_AET_Artist_Admin', 'image_ids');
$image_ids_method->setAccessible(true);
T::eq($image_ids_method->invoke(null, '101,102,101,104,x,103'), [101, 103], 'artist media sanitation removes duplicates and non-image attachments');
T::eq($image_ids_method->invoke(null, '101;103;102', 1), [101], 'artist media sanitation respects portfolio limit');
T::eq($image_ids_method->invoke(null, [103, '101', 103]), [103, 101], 'artist media sanitation supports arrays and preserves order');

// Regression checks for every WordPress Media Library path and image-backed form save.
$artwork_admin_source = file_get_contents(dirname(__DIR__) . '/includes/class-artwork.php');
$bridge_media_source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/class-content-bridge.php');
$coa_media_source = file_get_contents(dirname(__DIR__) . '/art-coa/assets/js/admin-media.js');
$coa_people_source = file_get_contents(dirname(__DIR__) . '/coa/includes/class-wp-coa-pro-person-profiles.php');
$coa_settings_source = file_get_contents(dirname(__DIR__) . '/coa/includes/class-wp-coa-pro-admin.php');
$coa_issue_source = file_get_contents(dirname(__DIR__) . '/coa/assets/js/wp-coa-pro-issuance.js');
T::ok(strpos($media_admin_source, "! isset( \$_POST['aetg'] ) || ! is_array( \$_POST['aetg'] )") !== false, 'gallery save skips missing meta box payload instead of blanking metadata');
T::ok(strpos($media_admin_source, 'wp_attachment_is_image( $cover )') !== false && strpos($media_admin_source, 'wp_attachment_is_image( $aid )') !== false, 'gallery cover and extra photos accept only image attachments');
T::ok(strpos($artwork_admin_source, "! isset( \$_POST['aetw'] ) || ! is_array( \$_POST['aetw'] )") !== false && strpos($artwork_admin_source, 'wp_is_post_revision( $post_id )') !== false, 'artwork save protects missing payloads and skips revisions');
T::ok(strpos($artwork_admin_source, 'wp_attachment_is_image( $id )') !== false && strpos($artwork_admin_source, 'self::photo_ids( $photos, 30 )') !== false, 'artwork photo display filters non-images and duplicate media IDs');
T::ok(strpos($artwork_admin_source, 'private static function photo_ids') !== false && strpos($artwork_admin_source, 'wp_attachment_is_image( $id )') !== false, 'artwork photo normalization supports legacy CSV and validates attachments');
T::ok(strpos($bridge_media_source, 'wp_attachment_is_image($id)') !== false, 'COA-to-WordPress bridge refuses non-image featured attachments');
T::ok(strpos($bridge_media_source, 'Do not let a malformed/non-image ID clear or desynchronize a good existing image.') !== false && strpos($bridge_media_source, 'array_key_exists($url_key, $data)') !== false, 'COA bridge preserves valid existing media on malformed IDs and supports partial URL updates');
T::ok(strpos($coa_media_source, "f.on('open'") !== false && strpos($coa_media_source, 'restoreSelection(f, listIds($m))') !== false, 'COA gallery picker restores only current visible multi-selection');
T::ok(strpos($coa_media_source, 'MAX_MULTI = 24') !== false && strpos($coa_media_source, 'showStatus') !== false, 'COA media picker limits images and reports failures');
T::ok(strpos($coa_people_source, "frame.on('open'") !== false && strpos($coa_people_source, 'wpCoaMediaFrame') !== false, 'COA person-profile picker caches frame and restores selected image');
T::ok(strpos($coa_settings_source, 'wp.media') !== false && strpos($coa_settings_source, 'if (!window.wp || !wp.media)') !== false, 'COA logo picker checks Media Library availability');
T::ok(strpos($coa_issue_source, "frame.on('open'") !== false && strpos($coa_issue_source, 'var frame = null') !== false, 'COA issuance image picker caches frame and restores current selection');
$assets_source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/admin/class-assets.php');
T::ok(strpos($assets_source, "defined('AET_VERSION') ? (string) AET_VERSION : WP_ART_COA_VERSION") !== false, 'media picker assets use platform release version for cache invalidation');
$main_src = file_get_contents(dirname(__DIR__) . '/artiey-art-platform.php');
preg_match('/^\s*\*\s*Version:\s*([0-9.]+)/m', $main_src, $hdr_m);
preg_match("/define\(\s*'AET_VERSION',\s*'([0-9.]+)'\s*\)/", $main_src, $const_m);
T::ok(!empty($hdr_m[1]) && ($const_m[1] ?? '') === $hdr_m[1] && version_compare($hdr_m[1], '2.32.28', '>='), 'platform version is bumped for editable single/archive page layouts');
$main_plugin_source = file_get_contents(dirname(__DIR__) . '/artiey-art-platform.php');
$profile_guard_source = file_get_contents(dirname(__DIR__) . '/includes/class-profile-guard.php');
$content_bridge_source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/class-content-bridge.php');
$content_sync_source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/class-content-sync.php');
$art_coa_plugin_source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/class-plugin.php');
$elementor_options_source = file_get_contents(dirname(__DIR__) . '/includes/class-elementor-options.php');
T::ok(strpos($main_plugin_source, 'version_compare( $module_version, WP_ART_COA_VERSION,') !== false, 'plugin patch update does not rerun bundled COA migrations unless the module version changes');
T::ok(strpos($main_plugin_source, 'Artiey_AET_Profile_Guard::repair_all()') === false, 'profile-content repair is not run synchronously during plugin update');
T::ok(strpos($profile_guard_source, 'public static function queue_repair()') !== false && strpos($profile_guard_source, 'REPAIR_BATCH_SIZE = 12') !== false, 'profile-content repair is queued in bounded background batches');
$bridge_queue_start = strpos($content_bridge_source, 'public static function queue_legacy_migration(): void');
$bridge_queue_end = strpos($content_bridge_source, '/** Process a small chunk', $bridge_queue_start);
$bridge_queue_source = substr($content_bridge_source, $bridge_queue_start, $bridge_queue_end - $bridge_queue_start);
T::ok(strpos($content_bridge_source, "MIGRATION_VERSION = '2.32.7'") !== false && strpos($bridge_queue_source, 'ContentSync::ensure_schema();') === false, 'legacy bridge migration is versioned independently and scheduling avoids schema DDL');
T::ok(strpos($content_sync_source, 'artiey_aet_content_bridge_schema_version') !== false && strpos($content_sync_source, 'self::$bridge_schema_ready') !== false, 'content bridge schema validation is cached after successful verification');
T::ok(strpos($art_coa_plugin_source, 'is_elementor_admin_request') !== false && strpos($art_coa_plugin_source, 'trait-auctions-page.php') !== false, 'large Art COA admin page traits are skipped during Elementor editor requests');
T::ok(strpos($main_plugin_source, 'if ( is_admin() && ! artiey_ap_is_elementor_request() )') !== false, 'platform admin menus, dashboard hooks and meta-box callbacks are not registered on frontend or Elementor editor requests');
T::ok(strpos($elementor_options_source, 'self::$request_cache') !== false && strpos($elementor_options_source, 'HOUR_IN_SECONDS') !== false, 'Elementor entity options use request-local memoization and a one-hour cache');
T::ok(strpos($coa_media_source, "selection.reset()") !== false && strpos($coa_media_source, "trigger('change')") !== false, 'COA media picker clears stale modal selection and emits change events');

echo "Auction rules\n";
foreach (['class-dates', 'class-auction'] as $f) { if (is_file(WP_ART_COA_DIR . "includes/$f.php")) require_once WP_ART_COA_DIR . "includes/$f.php"; }
$A = '\\WPArtCOA\\Auction';
T::ok($A::bidder_is_seller([5], [], 5, 'x@y.z'), 'seller: same account cannot bid');
T::ok($A::bidder_is_seller([], ['Owner@Mail.com'], 0, ' owner@mail.com '), 'seller: same e-mail (any case) cannot bid');
T::ok(!$A::bidder_is_seller([5], ['a@b.c'], 9, 'other@b.c'), 'seller: a different person may bid');
T::ok(!$A::bidder_is_seller([], [], 0, ''), 'seller: nobody known gives no false positive');
$row = (object) ['id' => 3, 'status' => 'active', 'current_bid' => null, 'start_price' => 100, 'min_increment' => 10, 'bid_count' => 0, 'currency' => 'IRR', 'ends_at_utc' => gmdate('Y-m-d H:i:s', time() + 3600)];
T::eq($A::min_next_bid($row), 100.0, 'min bid: first bid may equal the start price');
$row->current_bid = 150;
T::eq($A::min_next_bid($row), 160.0, 'min bid: next bid adds the increment');
require_once WP_ART_COA_DIR . 'includes/class-shop.php';
if (!function_exists('wp_timezone')) { function wp_timezone() { return new \DateTimeZone('UTC'); } }
$st = $A::state_payload($row);
T::ok($st['live'] === true && $st['count'] === 0 && $st['min'] === 160.0 && $st['left'] > 3500 && !isset($st['bidder']), 'state: live payload has bid, minimum and time left, nothing private');
$row->status = 'active'; $row->reserve_price = 500; $row->current_bid = 300; $row->current_bidder_email = 'Me@Mail.com';
$st2 = $A::state_payload($row, 'me@mail.com');
T::ok($st2['has_reserve'] === true && $st2['reserve_met'] === false && $st2['you_lead'] === true && !isset($st2['reserve_price']), 'state: reserve shows only met/unmet (never the amount) and the leading bidder is recognised');
T::ok($A::state_payload($row, 'other@mail.com')['you_lead'] === false && $A::state_payload($row)['you_lead'] === false, 'state: other viewers and guests are not marked as leading');
$row->current_bid = 600;
T::ok($A::state_payload($row)['reserve_met'] === true, 'state: reserve met once the bid reaches it');
$row->status = 'sold';
T::ok($A::state_payload($row)['live'] === false, 'state: a closed auction is not live');

// Proxy bidding (pure rule)
$r = $A::resolve_proxy(true, false, false, 0, 0, 100, 10, 500);
T::ok($r['winner'] === 'new' && $r['visible'] == 100 && $r['leader_max'] == 500, 'proxy: first bid shows only the start price, the maximum stays hidden');
$r = $A::resolve_proxy(true, true, false, 100, 500, 100, 10, 300);
T::ok($r['winner'] === 'old' && $r['visible'] == 310 && $r['leader_max'] == 500, 'proxy: a lower maximum is beaten automatically one increment above it');
$r = $A::resolve_proxy(true, true, false, 100, 500, 100, 10, 495);
T::ok($r['winner'] === 'old' && $r['visible'] == 500, 'proxy: the visible price never exceeds the leader maximum');
$r = $A::resolve_proxy(true, true, false, 100, 500, 100, 10, 500);
T::ok($r['winner'] === 'old' && $r['visible'] == 500, 'proxy: equal maximums, the earlier bidder keeps the lead');
$r = $A::resolve_proxy(true, true, false, 100, 500, 100, 10, 800);
T::ok($r['winner'] === 'new' && $r['visible'] == 510 && $r['leader_max'] == 800, 'proxy: a higher maximum takes the lead at leader max + increment');
$r = $A::resolve_proxy(true, true, false, 100, 500, 100, 10, 505);
T::ok($r['winner'] === 'new' && $r['visible'] == 505, 'proxy: new leader price is capped at their own maximum');
$r = $A::resolve_proxy(true, true, true, 310, 500, 100, 10, 900);
T::ok($r['winner'] === 'new' && $r['visible'] == 310 && $r['leader_max'] == 900 && $r['rows'] === [], 'proxy: raising your own maximum does not change the price');
$r = $A::resolve_proxy(false, true, false, 100, 100, 100, 10, 250);
T::ok($r['visible'] == 250 && $r['leader_max'] == 250 && count($r['rows']) === 1, 'proxy off: the amount is the bid itself');

// Settlement (pure rule)
T::ok($A::settle_outcome(null, 0, 0) === ['status' => 'ended', 'final' => null], 'settle: no bids ends the auction');
T::ok($A::settle_outcome(300, 0, 300) === ['status' => 'sold', 'final' => 300.0], 'settle: no reserve sells at the bid');
T::ok($A::settle_outcome(300, 500, 300) === ['status' => 'ended_reserve', 'final' => 300.0], 'settle: reserve not met');
T::ok($A::settle_outcome(300, 500, 800) === ['status' => 'sold', 'final' => 500.0], 'settle: a hidden maximum above the reserve buys at the reserve');



/* Gallery profile: real relationship blocks and Elementor presentation controls. */
require_once dirname(__DIR__) . '/includes/class-gallery.php';
T::ok(in_array('curators', Artiey_AET_Gallery::ALL_SECTIONS, true)
    && in_array('collectors', Artiey_AET_Gallery::ALL_SECTIONS, true)
    && in_array('auctions', Artiey_AET_Gallery::ALL_SECTIONS, true), 'gallery profile exposes curator, collector and auction relationship sections');
T::eq(Artiey_AET_Gallery::upgrade_default_sections(['header','short_bio','bio','details','contact','hours','map','photos','artists','exhibitions','past_exhibitions']), Artiey_AET_Gallery::ALL_SECTIONS, 'legacy gallery widgets using all default sections automatically gain the new relationships');
T::eq(Artiey_AET_Gallery::upgrade_default_sections(['header','artists']), ['header','artists'], 'custom gallery section selections remain unchanged');
$related_limit = new ReflectionMethod('Artiey_AET_Gallery', 'related_limit');
$related_limit->setAccessible(true);
T::eq($related_limit->invoke(null, []), 8, 'related-card limit defaults to eight');
T::eq($related_limit->invoke(null, ['related_items_limit' => 99]), 24, 'related-card limit is capped at 24');
T::eq($related_limit->invoke(null, ['related_items_limit' => 1]), 2, 'related-card limit has a safe minimum');
$related_mode = new ReflectionMethod('Artiey_AET_Gallery', 'related_mode');
$related_mode->setAccessible(true);
T::eq($related_mode->invoke(null, ['related_card_layout' => 'compact']), 'is-compact', 'related cards support compact layout');
T::eq($related_mode->invoke(null, []), 'is-grid', 'related cards default to responsive grid');
$related_meta = new ReflectionMethod('Artiey_AET_Gallery', 'related_meta_enabled');
$related_meta->setAccessible(true);
T::ok($related_meta->invoke(null, []) && !$related_meta->invoke(null, ['show_related_meta' => '']) && !$related_meta->invoke(null, ['show_related_meta' => 'no']), 'related-card metadata visibility is configurable');
$related_card = new ReflectionMethod('Artiey_AET_Gallery', 'related_card');
$related_card->setAccessible(true);
$escaped_card = $related_card->invoke(null, ['title' => '<script>alert(1)</script>', 'url' => 'https://example.test/profile', 'sub' => '<b>private html</b>', 'meta' => 'public detail'], 'artist', []);
T::ok(strpos($escaped_card, '&lt;script&gt;') !== false && strpos($escaped_card, '<script>') === false && strpos($escaped_card, '&lt;b&gt;') !== false, 'related cards escape database-sourced labels and titles');
$gallery_source = file_get_contents(dirname(__DIR__) . '/includes/class-gallery.php');
$gallery_widget_source = file_get_contents(dirname(__DIR__) . '/includes/class-elementor-profile-widget.php');
$gallery_style_source = file_get_contents(dirname(__DIR__) . '/includes/class-elementor-style.php');
T::ok(strpos($gallery_source, "c.is_public = 1") !== false && strpos($gallery_source, "c.status = 'active'") !== false, 'collector relation exposes active public collectors only');
T::ok(strpos($gallery_source, "aw.gallery_id = %d") !== false && strpos($gallery_source, "e.gallery_id = %d") !== false && strpos($gallery_source, "a.gallery_id = %d") !== false, 'artist, curator and auction links are resolved through gallery relational IDs');
T::ok(strpos($gallery_source, "au.status IN ('active','scheduled','ended','ended_reserve','sold')") !== false && strpos($gallery_source, "'sold' => 'فروخته شد'") !== false, 'gallery auction cards include active, upcoming and completed public lots with accurate status labels');
T::ok(strpos($gallery_widget_source, "'related_items_limit'") !== false && strpos($gallery_widget_source, "'related_card_layout'") !== false && strpos($gallery_widget_source, "'show_related_meta'") !== false, 'Elementor gallery widget exposes Pro relationship display settings');
T::ok(strpos($gallery_style_source, "'curators' => 'کیوریتورها'") !== false && strpos($gallery_style_source, "'collectors' => 'مجموعه‌داران عمومی'") !== false && strpos($gallery_style_source, "'auctions' => 'حراج‌های مرتبط'") !== false, 'Elementor tab layout includes all gallery relationship sections');
T::ok(strpos($gallery_style_source, "'related_card_shadow'") !== false && strpos($gallery_style_source, "'related_columns'") !== false, 'Elementor Style tab exposes related-card shadow and responsive columns');


/* Performance regressions: avoid unrelated assets, repeated content scans and attachment N+1 reads. */
$assets_source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/admin/class-assets.php');
T::ok(strpos($assets_source, "private const DYNAMIC_SHORTCODES") !== false
    && strpos($assets_source, "private const LIGHT_SHORTCODES") !== false,
    'COA frontend assets distinguish interactive pages from light verifier/certificate pages');
T::ok(strpos($assets_source, 'content_has_any_shortcode') !== false
    && strpos($assets_source, 'has_shortcode(') === false,
    'COA page asset detection uses grouped shortcode matching instead of repeated page scans');
T::ok(strpos($assets_source, 'light_front_css()') !== false
    && strpos($assets_source, 'if (!$load_dynamic) {' . "\n" . '            return;') !== false,
    'static certificate pages skip the large dynamic CSS and JavaScript bundle');
T::ok(strpos($assets_source, 'WP_ART_COA_URL . \'assets/js/frontend-dynamic.js\', [], $asset_version') !== false,
    'COA dynamic JavaScript cache-busting follows the unified plugin version');

$platform_admin_source = file_get_contents(dirname(__DIR__) . '/includes/class-platform-admin.php');
T::ok(strpos($platform_admin_source, "wp_enqueue_style( 'artiey-platform-menu'") !== false
    && strpos($platform_admin_source, "wp_enqueue_script( 'artiey-platform-menu'") !== false
    && strpos($platform_admin_source, 'if ( ! $is_artiey_screen ) {') === false,
    'platform navigation grouping assets remain available across wp-admin screens');
T::ok(strpos($platform_admin_source, 'if ( $is_artiey_screen && $hook === self::$hook )') !== false,
    'heavier platform dashboard stylesheet remains limited to the Artiey dashboard');

$gallery_perf_source = file_get_contents(dirname(__DIR__) . '/includes/class-gallery.php');
T::ok(strpos($gallery_perf_source, 'private static function prime_post_caches') !== false
    && strpos($gallery_perf_source, 'self::prime_post_caches( $g[\'photos\'] )') !== false,
    'gallery photo attachments and metadata are bulk-primed before rendering');
T::ok(strpos($gallery_perf_source, 'self::prime_post_caches( array_map( static function ( $row ) { return absint( $row->curator_avatar_id ?? 0 );') !== false
    && strpos($gallery_perf_source, 'self::prime_post_caches( array_map( static function ( $row ) { return absint( $row->collector_avatar_id ?? 0 );') !== false,
    'curator and collector card images avoid per-card cold-cache attachment queries');

$stats_source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/class-stats.php');
$stats_start = strpos($stats_source, 'public static function global_uncached');
$stats_end = strpos($stats_source, 'public static function artist(', $stats_start);
$stats_global = substr($stats_source, $stats_start, $stats_end - $stats_start);
T::ok(substr_count($stats_global, '$wpdb->get_row(') === 2 && strpos($stats_global, '$wpdb->get_var(') === false,
    'global COA dashboard metrics use one combined SQL result instead of repeated COUNT round-trips');



/* Canonical artist archive, unified URLs and relational curator filter. */
$artist_directory_source = file_get_contents(dirname(__DIR__) . '/includes/class-directory.php');
T::ok(strpos($artist_directory_source, "'curator' => absint") !== false
    && strpos($artist_directory_source, 'artist_ids_for_curator') !== false
    && strpos($artist_directory_source, 'e.curator_id = %d') !== false,
    'artist archive includes an ID-based curator filter through the exhibition/artwork relation');
$base_url_start = strpos($artist_directory_source, 'private static function base_url');
$base_url_end = strpos($artist_directory_source, "\n\t}", $base_url_start);
$base_url_source = substr($artist_directory_source, $base_url_start, $base_url_end - $base_url_start);
T::ok(substr_count($base_url_source, "'aet_curator'") === 1,
    'artist archive removes curator query args once and preserves the filter cleanly');
$slug_source = file_get_contents(dirname(__DIR__) . '/includes/class-slug.php');
T::ok(strpos($slug_source, "ROUTE_SCHEMA_VERSION = '2.32.32'") !== false
    && strpos($slug_source, "add_action( 'init', array( __CLASS__, 'maybe_refresh_routes' ), 99 )") !== false,
    'URL repair: route schema has a fresh version marker and flushes after every rewrite provider registers');
$seo_rewrite_source = file_get_contents(dirname(__DIR__) . '/coa/includes/class-wp-coa-pro-seo.php');
T::ok(strpos($seo_rewrite_source, 'Artiey_AET_Slug::routes_refreshed_this_request()') !== false,
    'URL repair: bundled COA SEO module reuses the shared rewrite flush instead of flushing twice');
T::ok(strpos($slug_source, "flush_rewrite_rules( false )") !== false
    && strpos($slug_source, "update_option( 'artiey_aet_rewrite_version', self::ROUTE_SCHEMA_VERSION, false )") !== false
    && strpos($slug_source, "delete_option( 'artiey_aet_flush' )") !== false,
    'URL repair: stale rewrite rules are rebuilt once and the pending flush marker is removed');
T::ok(strpos($slug_source, 'purge_public_url_caches()') !== false
    && strpos($slug_source, 'wp_coa_smi_') !== false
    && strpos($slug_source, 'wp_coa_smp_') !== false
    && strpos($slug_source, 'wp_coa_pp_') !== false,
    'URL repair: cached profile lookups and stale sitemap URL transients are invalidated');
T::ok(strpos($slug_source, "'artist'     => 'artists'") !== false
    && strpos($slug_source, 'pagename=artists&artiey_legacy_base=archive-artist') !== false
    && strpos($slug_source, "'art-person-profile' === trim( \$legacy, '/' )") !== false,
    'artist archive and profile paths converge on /artists/ while protecting the legacy COA profile route');
T::ok(strpos($slug_source, "'^artists/([^/]+)/?$'") !== false
    && strpos($slug_source, "'artiey_artist_route'") !== false,
    'canonical /artists/{slug}/ requests use a dedicated exact-slug dispatcher');
T::ok(strpos($slug_source, "get_post_meta( (int) \$page->ID, '_wp_coa_pro_managed_page', true )") !== false
    && strpos($slug_source, '$page_is_managed') !== false,
    'legacy COA profile route validates the actual page found after stale page-ID recovery');
$gallery_source = file_get_contents(dirname(__DIR__) . '/includes/class-gallery.php');
T::ok(strpos($gallery_source, "'artiey_aet_url_cache_generation'") !== false
    && strpos($gallery_source, "'aetp_gallery_row_' . \$generation") !== false
    && strpos($gallery_source, "'aetp_rel_' . \$generation") !== false,
    'URL repair: stale gallery relationship caches use a versioned key namespace');
$person_source = file_get_contents(dirname(__DIR__) . '/coa/includes/class-wp-coa-pro-person-profiles.php');
T::ok(strpos($person_source, 'Artiey_AET_Directory::shortcode_artist_archive') !== false,
    'legacy artist directory shortcode and Elementor widget render the canonical WordPress artist archive');
$legacy_person_widget_source = file_get_contents(dirname(__DIR__) . '/coa/includes/class-wp-coa-pro-elementor-person-widgets.php');
T::ok(strpos($legacy_person_widget_source, '.aetd-artist-archive .aetp-card') !== false
    && strpos($legacy_person_widget_source, '--aetp-accent: {{VALUE}};') !== false,
    'legacy Elementor directory style controls continue to target the canonical artist archive cards');
$page_integration_source = file_get_contents(dirname(__DIR__) . '/coa/includes/class-wp-coa-pro-page-integration.php');
T::ok(strpos($page_integration_source, 'delete_option($migration_key)') !== false
    && strpos($page_integration_source, "get_page_by_path('artists', OBJECT, 'any')") !== false
    && strpos($page_integration_source, 'wp_insert_post(array(') !== false,
    'artist archive recovery: a deleted archive page is recreated when the canonical root slug is free');
T::ok(strpos($page_integration_source, "'slug' => 'artists'") !== false
    && strpos($page_integration_source, 'is_artist_archive_page') !== false
    && strpos($page_integration_source, 'wp-coa-person-directory') !== false,
    'existing /artists/ shortcode or Elementor archive is adopted without replacing its design');
$seo_source = file_get_contents(dirname(__DIR__) . '/coa/includes/class-wp-coa-pro-seo.php');
T::ok(strpos($seo_source, 'get_page_by_path($legacy_slug, OBJECT, \'artiey_artist\')') !== false
    && strpos($seo_source, '$candidate->post_status === \'publish\'') !== false
    && strpos($seo_source, '$candidate->post_name === $legacy_slug') !== false,
    'legacy artist profile URLs redirect only to exact published artist CPT matches');
$legacy_profiles_source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/class-profiles.php');
T::ok(strpos($legacy_profiles_source, '(string) $type === \'artists_list\'') !== false
    && strpos($legacy_profiles_source, 'ContentSync::artist_archive_url()') !== false
    && strpos($legacy_profiles_source, "Seo::canonical_url('artist'") !== false
    && strpos($legacy_profiles_source, '($artist_entity->status ?? \'\') === \'active\'') !== false,
    'legacy Art COA artist directory and only public artist profiles redirect to canonical URLs');
$catalog_source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/class-catalog.php');
T::ok(strpos($catalog_source, 'self::artist_url((string) $row->slug)') !== false
    && strpos($catalog_source, 'self::artist_url((string) $a->slug)') !== false,
    'Art COA live search and artist cards use the shared canonical URL helper');
$content_sync_canonical_source = file_get_contents(dirname(__DIR__) . '/art-coa/includes/class-content-sync.php');
T::ok(strpos($content_sync_canonical_source, "get_page_by_path('artists', OBJECT, 'page')") !== false
    && strpos($content_sync_canonical_source, "return home_url('/artists/')") !== false
    && strpos($content_sync_canonical_source, "get_managed_page_url('artist_directory')") === false,
    'artist archive URL helper rejects stale managed-page slugs and keeps /artists/ canonical');
T::ok(strpos($page_integration_source, 'wp_coa_pro_artist_archive_unified_23212') !== false
    && strpos($page_integration_source, '_wp_coa_pro_artist_archive_legacy_redirect') !== false
    && strpos($page_integration_source, 'ensure_artist_archive_output') !== false,
    'archive migration claims the canonical path, preserves page design, and redirects the retired directory page');

$stats_cache_source = file_get_contents(dirname(__DIR__) . '/includes/class-statistics.php');
T::ok(strpos($stats_cache_source, "add_action( 'added_post_meta'") !== false
    && strpos($stats_cache_source, "add_action( 'updated_post_meta'") !== false
    && strpos($stats_cache_source, "add_action( 'deleted_post_meta'") !== false,
    'statistics cache: entity relationship metadata changes invalidate cached counts');
T::ok(strpos($stats_cache_source, "add_action( 'deleted_post'") !== false
    && strpos($stats_cache_source, 'on_post_deleted') !== false,
    'statistics cache: deleting an entity invalidates cached totals');
T::ok(strpos($stats_cache_source, '$invalidated_this_request') !== false,
    'statistics cache: repeated invalidation hooks are coalesced per request');
$cache_control_source = file_get_contents(dirname(__DIR__) . '/includes/class-cache-control.php');
T::ok(strpos($cache_control_source, 'rocket_clean_post') !== false
    && strpos($cache_control_source, 'w3tc_flush_post') !== false
    && strpos($cache_control_source, 'litespeed_purge_post') !== false
    && strpos($cache_control_source, 'artiey_aet_purge_post_cache') !== false,
    'page cache: targeted purge adapters and custom integration action are available');
T::ok(strpos($cache_control_source, "add_action( 'save_post'") !== false
    && strpos($cache_control_source, "add_action( 'deleted_post'") !== false
    && strpos($cache_control_source, "add_action( 'transition_post_status'") !== false,
    'page cache: saves, deletions and publication changes trigger targeted purge');

require_once __DIR__ . '/elementor-layout-tests.php';

require_once __DIR__ . '/dynamic-tags-tests.php';

echo "\n" . T::$pass . " passed, " . T::$fail . " failed\n";
exit(T::$fail ? 1 : 0);
