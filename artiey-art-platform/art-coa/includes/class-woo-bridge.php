<?php
declare(strict_types=1);

namespace WPArtCOA;

use WPArtCOA\Entities\Artwork;

defined('ABSPATH') || exit;

/**
 * WooCommerce (+ Dokan) bridge.
 *
 * - An artwork that is for sale + approved becomes a one-of-a-kind (sold individually, stock 1) simple product.
 * - A paid order marks the artwork "sold"; a cancelled/refunded order puts it back on sale.
 * - With Dokan: the product's author is the artist's user when that user is a Dokan vendor (role "seller").
 * Nothing runs unless WooCommerce is active.
 */
final class WooBridge
{
    private const META = '_art_coa_artwork_id';

    public static function init(): void
    {
        add_action('plugins_loaded', [self::class, 'boot'], 30);
    }

    public static function active(): bool
    {
        return class_exists('WooCommerce') && function_exists('wc_get_product');
    }

    public static function dokan_active(): bool
    {
        return function_exists('dokan') || class_exists('WeDevs_Dokan');
    }

    public static function boot(): void
    {
        if (!self::active()) {
            return;
        }
        add_action('art_coa_artwork_changed', [self::class, 'on_artwork_changed']);
        foreach (['processing', 'completed'] as $st) {
            add_action('woocommerce_order_status_' . $st, [self::class, 'on_order_paid']);
        }
        foreach (['cancelled', 'refunded', 'failed'] as $st) {
            add_action('woocommerce_order_status_' . $st, [self::class, 'on_order_released']);
        }
        add_action('woocommerce_single_product_summary', [self::class, 'product_artwork_link'], 45);
    }

    /* ------------------------------------------------------------------
     * Artwork → product
     * ---------------------------------------------------------------- */

    public static function on_artwork_changed(int $artwork_id): void
    {
        static $busy = false;
        if ($busy || !apply_filters('art_coa_wc_auto_sync', true)) {
            return;
        }
        $art = Artwork::get($artwork_id);
        if (!$art) {
            return;
        }
        // Only artworks that are (or were) on sale get a product.
        if (empty($art->wc_product_id) && (empty($art->for_sale) || (float) ($art->price ?? 0) <= 0)) {
            return;
        }
        $busy = true;
        self::sync($artwork_id);
        $busy = false;
    }

    /** @return array{success:bool,message:string,product_id?:int} */
    public static function sync(int $artwork_id): array
    {
        if (!self::active()) {
            return ['success' => false, 'message' => I18n::t('wc_missing')];
        }
        $art = Artwork::get($artwork_id);
        if (!$art) {
            return ['success' => false, 'message' => I18n::t('not_found')];
        }
        $price = self::price($art);
        $sold  = $art->status === 'sold';
        $live  = !$sold && !empty($art->for_sale) && $price > 0
            && in_array($art->status, ['approved', 'published', 'active'], true);

        $product = null;
        if (!empty($art->wc_product_id)) {
            $product = wc_get_product((int) $art->wc_product_id);
        }
        if (!$product) {
            if (!$sold && !$live && empty($art->for_sale)) {
                return ['success' => false, 'message' => I18n::t('wc_not_for_sale')];
            }
            $product = new \WC_Product_Simple();
        }

        $artist = trim((string) ($art->artist_name ?? '')) ?: trim(($art->first_name ?? '') . ' ' . ($art->last_name ?? ''));
        $short  = array_filter([
            $artist,
            $art->medium ?? '',
            $art->dimensions ?? '',
            !empty($art->year_created) ? (string) $art->year_created : '',
        ]);

        $product->set_name((string) $art->title);
        $product->set_description(wp_kses_post((string) ($art->description ?? '')));
        $product->set_short_description(esc_html(implode(' — ', $short)));
        $product->set_regular_price($price > 0 ? (string) $price : '');
        $product->set_manage_stock(true);
        $product->set_stock_quantity($live ? 1 : 0);
        $product->set_stock_status($live ? 'instock' : 'outofstock');
        $product->set_sold_individually(true);
        $product->set_catalog_visibility('visible');
        $product->set_virtual(false);
        $product->set_status($live || $sold ? 'publish' : 'draft');
        if ($art->image_id ?? 0) {
            $product->set_image_id((int) $art->image_id);
        }
        $gallery = Media::ids($art->gallery_ids ?? '');
        if ($gallery) {
            $product->set_gallery_image_ids($gallery);
        }
        $sku = 'ART-' . (int) $art->id;
        $other = wc_get_product_id_by_sku($sku);
        if (!$other || $other === (int) $product->get_id()) {
            $product->set_sku($sku);
        }
        $cat = self::category_id();
        if ($cat) {
            $product->set_category_ids([$cat]);
        }
        $product->update_meta_data(self::META, (int) $art->id);

        $pid = (int) $product->save();
        if (!$pid) {
            return ['success' => false, 'message' => I18n::t('error')];
        }

        if (empty($art->wc_product_id) || (int) $art->wc_product_id !== $pid) {
            global $wpdb;
            $wpdb->update(Database::table('artworks'), ['wc_product_id' => $pid], ['id' => (int) $art->id]);
        }
        self::assign_vendor($pid, (int) ($art->artist_id ?? 0));

        return ['success' => true, 'message' => I18n::t('wc_synced'), 'product_id' => $pid];
    }

    public static function sync_all(): int
    {
        global $wpdb;
        $ids = $wpdb->get_col(
            'SELECT id FROM ' . Database::table('artworks') . ' WHERE (for_sale = 1 AND price > 0) OR wc_product_id IS NOT NULL ORDER BY id DESC LIMIT 500'
        ) ?: [];
        $n = 0;
        foreach ($ids as $id) {
            $r = self::sync((int) $id);
            $n += !empty($r['success']) ? 1 : 0;
        }
        return $n;
    }

    /** Artwork price expressed in the store currency (IRR <-> IRT handled; filterable). */
    private static function price(object $art): float
    {
        $price = (float) ($art->price ?? 0);
        $from  = strtoupper((string) ($art->currency ?? 'IRR'));
        $to    = strtoupper((string) get_woocommerce_currency());
        if ($from === 'IRR' && $to === 'IRT') {
            $price /= 10;
        } elseif ($from === 'IRT' && $to === 'IRR') {
            $price *= 10;
        }
        return (float) apply_filters('art_coa_wc_price', round($price, 2), $art, $from, $to);
    }

    private static function category_id(): int
    {
        if (!taxonomy_exists('product_cat')) {
            return 0;
        }
        $name = I18n::t('artworks');
        $t = term_exists($name, 'product_cat');
        if (!$t) {
            $t = wp_insert_term($name, 'product_cat', ['slug' => 'art-coa-artworks']);
        }
        if (is_wp_error($t)) {
            return 0;
        }
        return (int) (is_array($t) ? $t['term_id'] : $t);
    }

    /** Dokan: the product belongs to the artist when the artist is a vendor (role "seller"). Never promotes users. */
    private static function assign_vendor(int $product_id, int $artist_id): void
    {
        if (!self::dokan_active() || !$artist_id || !apply_filters('art_coa_dokan_assign_vendor', true)) {
            return;
        }
        $artist = \WPArtCOA\Entities\Artist::get($artist_id);
        $uid = $artist && !empty($artist->user_id) ? (int) $artist->user_id : 0;
        if (!$uid) {
            return;
        }
        $u = get_userdata($uid);
        if (!$u || !in_array('seller', (array) $u->roles, true)) {
            return;
        }
        if ((int) get_post_field('post_author', $product_id) !== $uid) {
            wp_update_post(['ID' => $product_id, 'post_author' => $uid]);
        }
    }

    /* ------------------------------------------------------------------
     * Orders
     * ---------------------------------------------------------------- */

    public static function on_order_paid($order_id): void
    {
        $order = wc_get_order((int) $order_id);
        if (!$order) {
            return;
        }
        foreach ($order->get_items() as $item) {
            $aid = (int) get_post_meta((int) $item->get_product_id(), self::META, true);
            if (!$aid || $order->get_meta('_art_coa_sold_' . $aid)) {
                continue;
            }
            global $wpdb;
            $wpdb->update(Database::table('artworks'), ['for_sale' => 0], ['id' => $aid]);
            Artwork::set_status($aid, 'sold');
            Audit::log('artwork', $aid, 'woo_sold', ['order_id' => (int) $order_id]);
            $order->update_meta_data('_art_coa_sold_' . $aid, 1);
            do_action('art_coa_artwork_sold', $aid, (int) $order_id);
        }
        $order->save();
    }

    public static function on_order_released($order_id): void
    {
        $order = wc_get_order((int) $order_id);
        if (!$order) {
            return;
        }
        foreach ($order->get_items() as $item) {
            $aid = (int) get_post_meta((int) $item->get_product_id(), self::META, true);
            if (!$aid || !$order->get_meta('_art_coa_sold_' . $aid)) {
                continue;
            }
            global $wpdb;
            $wpdb->update(Database::table('artworks'), ['for_sale' => 1], ['id' => $aid]);
            Artwork::set_status($aid, 'approved');
            Audit::log('artwork', $aid, 'woo_released', ['order_id' => (int) $order_id]);
            $order->delete_meta_data('_art_coa_sold_' . $aid);
        }
        $order->save();
    }

    /* ------------------------------------------------------------------
     * Front end
     * ---------------------------------------------------------------- */

    /** URL for the "buy" button on the artwork page, or '' when the work cannot be bought. */
    public static function buy_url(object $art): string
    {
        if (!self::active() || empty($art->wc_product_id) || empty($art->for_sale) || $art->status === 'sold') {
            return '';
        }
        $p = wc_get_product((int) $art->wc_product_id);
        if (!$p || $p->get_status() !== 'publish' || !$p->is_in_stock()) {
            return '';
        }
        return (string) $p->get_permalink();
    }

    public static function product_artwork_link(): void
    {
        global $product;
        if (!$product instanceof \WC_Product) {
            return;
        }
        $aid = (int) $product->get_meta(self::META);
        if (!$aid) {
            return;
        }
        $art = Artwork::get($aid);
        if ($art) {
            echo '<p class="art-coa-wc-link"><a href="' . esc_url(home_url('/art-coa/artwork/' . $art->slug)) . '">'
                . esc_html(I18n::t('view_page')) . '</a></p>';
        }
    }

    /** Admin: edit link of the linked product. */
    public static function admin_cell(object $art): string
    {
        if (!self::active()) {
            return '—';
        }
        $out = '';
        if (!empty($art->wc_product_id) && get_post((int) $art->wc_product_id)) {
            $out .= '<a href="' . esc_url(get_edit_post_link((int) $art->wc_product_id)) . '">#' . (int) $art->wc_product_id . '</a> ';
        }
        $out .= '<form method="post" class="aap-inline">' . wp_nonce_field('art_coa_moderate', '_wpnonce', true, false)
            . '<input type="hidden" name="art_coa_action" value="wc_sync_artwork">'
            . '<input type="hidden" name="item_id" value="' . (int) $art->id . '">'
            . '<button class="button button-small">' . esc_html(I18n::t('wc_sync')) . '</button></form>';
        return $out;
    }
}
