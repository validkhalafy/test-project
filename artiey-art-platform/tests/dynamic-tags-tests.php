<?php
declare(strict_types=1);

require_once __DIR__ . '/dynamic-tag-stubs.php';
require_once dirname(__DIR__) . '/includes/class-elementor-shortcode-dynamic-tags.php';

$definitions = Artiey_AET_Elementor_Shortcode_Dynamic_Tags::definitions();
$GLOBALS['shortcode_tags'] = array_fill_keys(array_keys($definitions), static function () {});
$GLOBALS['shortcode_tags']['third_party_demo'] = static function () {};

$manager = new Test_Dynamic_Tag_Manager();
Artiey_AET_Elementor_Shortcode_Dynamic_Tags::register($manager);

T::eq(count($definitions), 45, 'dynamic tags: every bundled platform shortcode is defined');
T::eq(count($manager->tags), 46, 'dynamic tags: 45 dedicated tags plus one universal shortcode tag');
T::ok(isset($manager->groups['artiey-platform'], $manager->groups['artiey-art-coa'], $manager->groups['artiey-coa-pro'], $manager->groups['artiey-any-shortcode']), 'dynamic tags: four shortcode groups are registered');
T::eq(count(array_unique(array_keys($manager->tags))), count($manager->tags), 'dynamic tags: tag identifiers are unique');

$artist_tag = $manager->tags['artiey-shortcode-artiey_artist_field'] ?? null;
T::ok($artist_tag instanceof \Elementor\Core\DynamicTags\Tag, 'dynamic tags: artist-field shortcode tag is registered');
if ($artist_tag) {
    T::eq($artist_tag->get_categories(), ['text'], 'dynamic tags: shortcode tags are registered for text-compatible controls');
    T::eq($artist_tag->get_group(), ['artiey-platform'], 'dynamic tags: artist fields appear in Artiey group');
    $controls = $artist_tag->get_test_controls();
    T::ok(isset($controls['shortcode_attributes'], $controls['use_current_post_context'], $controls['shortcode_output_mode']), 'dynamic tags: attribute, current-post and output controls are available');
    $GLOBALS['__dynamic_test_shortcodes']['artiey_artist_field'] = true;
    $GLOBALS['__dynamic_queried_object_id'] = 42;
    $GLOBALS['__artist_media_post_types'][42] = 'artiey_artist';
    $artist_tag->set_test_settings([
        'shortcode_attributes' => 'field="name"',
        'use_current_post_context' => 'yes',
        'shortcode_output_mode' => 'html',
    ]);
    ob_start();
    $artist_tag->render();
    $rendered = ob_get_clean();
    T::eq($GLOBALS['__last_dynamic_shortcode'] ?? '', '[artiey_artist_field field="name" id="42"]', 'dynamic tags: current artist ID is injected into field shortcode');
    T::eq($rendered, '<figure class="art-coa-chart">test chart output</figure>', 'dynamic tags: shortcode HTML is returned to Elementor');

    $artist_tag->set_test_settings([
        'shortcode_attributes' => 'field="name" id="99"',
        'use_current_post_context' => 'yes',
        'shortcode_output_mode' => 'html',
    ]);
    ob_start();
    $artist_tag->render();
    ob_end_clean();
    T::ok(strpos((string) ($GLOBALS['__last_dynamic_shortcode'] ?? ''), 'id="99"') !== false
        && strpos((string) ($GLOBALS['__last_dynamic_shortcode'] ?? ''), 'id="42"') === false,
        'dynamic tags: explicit shortcode ID takes precedence over current post context');
}

$generic_tag = $manager->tags['artiey-shortcode-any'] ?? null;
T::ok($generic_tag instanceof \Elementor\Core\DynamicTags\Tag, 'dynamic tags: universal shortcode tag is registered');
if ($generic_tag) {
    $controls = $generic_tag->get_test_controls();
    T::ok(isset($controls['shortcode_tag'], $controls['shortcode_attributes']), 'dynamic tags: universal tag exposes shortcode selector and attributes');
    T::ok(isset($controls['shortcode_tag']['options']['third_party_demo']), 'dynamic tags: universal selector includes shortcodes from third-party plugins');
}
