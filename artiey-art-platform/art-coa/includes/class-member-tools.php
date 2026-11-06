<?php
declare(strict_types=1);

namespace WPArtCOA;

use WPArtCOA\Entities\Artist;
use WPArtCOA\Entities\Artwork;
use WPArtCOA\Entities\Collector;
use WPArtCOA\Entities\Exhibition;
use WPArtCOA\Entities\Gallery;

defined('ABSPATH') || exit;

/**
 * Front-end tools for logged-in members (member dashboard):
 *  - artists: submit artworks (main image + extra images), update avatar / cover
 *  - galleries: submit exhibitions (poster + photos), update logo / cover
 *  - collectors: update avatar
 * Everything submitted lands as "pending" and needs admin approval. Entity IDs always come
 * from the user's own meta — never from the request.
 */
final class MemberTools
{
    public static function init(): void
    {
        add_action('init', [self::class, 'handle'], 20);
    }

    /** @return array{role:string,id:int} */
    private static function me(): array
    {
        $uid = get_current_user_id();
        return [
            'role' => (string) get_user_meta($uid, 'art_coa_role', true),
            'id'   => (int) get_user_meta($uid, 'art_coa_entity_id', true),
        ];
    }

    private static function flash(string $msg, bool $ok = true): void
    {
        set_transient('art_coa_mt_' . get_current_user_id(), ['ok' => $ok, 'msg' => $msg], 120);
    }

    private static function back(): void
    {
        wp_safe_redirect(wp_get_referer() ?: home_url('/art-coa/member-dashboard/'));
        exit;
    }

    public static function handle(): void
    {
        if (empty($_POST['art_coa_member_action']) || !is_user_logged_in()) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash((string) ($_POST['_wpnonce'] ?? ''))), 'art_coa_member')) {
            self::flash(I18n::t('error'), false);
            self::back();
        }
        $me   = self::me();
        $data = wp_unslash($_POST);
        $act  = sanitize_key((string) $data['art_coa_member_action']);

        if ($me['id'] <= 0) {
            return;
        }

        if ($act === 'submit_artwork' && $me['role'] === 'artist') {
            if (trim((string) ($data['title'] ?? '')) === '' || empty($data['image_id'])) {
                self::flash(I18n::t('required_missing'), false);
                self::back();
            }
            $res = Artwork::create([
                'title'        => $data['title'],
                'artist_id'    => $me['id'],
                'medium'       => $data['medium'] ?? '',
                'year_created' => $data['year_created'] ?? '',
                'dimensions'   => $data['dimensions'] ?? '',
                'description'  => $data['description'] ?? '',
                'image_id'     => $data['image_id'] ?? 0,
                'gallery_ids'  => $data['gallery_ids'] ?? '',
                'for_sale'     => !empty($data['for_sale']) ? 1 : 0,
                'price'        => $data['price'] ?? '',
                'currency'     => $data['currency'] ?? 'IRR',
                'status'       => 'pending',
            ]);
            self::flash(!empty($res['success']) ? I18n::t('saved_pending') : ($res['message'] ?? I18n::t('error')), !empty($res['success']));
            self::back();
        }

        if ($act === 'submit_exhibition' && $me['role'] === 'gallery') {
            if (trim((string) ($data['title'] ?? '')) === '' || empty($data['start_date'])) {
                self::flash(I18n::t('required_missing'), false);
                self::back();
            }
            $res = Exhibition::create([
                'title'        => $data['title'],
                'subtitle'     => $data['subtitle'] ?? '',
                'artist_label' => $data['artist_label'] ?? '',
                'event_type'   => $data['event_type'] ?? 'exhibition',
                'gallery_id'   => $me['id'],
                'description'  => $data['description'] ?? '',
                'start_date'   => $data['start_date'] ?? '',
                'end_date'     => $data['end_date'] ?? '',
                'poster_id'    => $data['poster_id'] ?? 0,
                'gallery_ids'  => $data['gallery_ids'] ?? '',
                'status'       => 'pending',
            ]);
            self::flash(!empty($res['success']) ? I18n::t('saved_pending') : ($res['message'] ?? I18n::t('error')), !empty($res['success']));
            self::back();
        }

        if ($act === 'profile_images') {
            $fields = match ($me['role']) {
                'artist'    => ['avatar', 'cover'],
                'gallery'   => ['logo', 'cover'],
                'collector' => ['avatar'],
                default     => [],
            };
            $upd = [];
            foreach ($fields as $f) {
                if (array_key_exists($f . '_id', $data)) {
                    $upd[$f . '_id'] = $data[$f . '_id'];
                }
            }
            if ($upd) {
                $res = match ($me['role']) {
                    'artist'    => Artist::update($me['id'], $upd),
                    'gallery'   => Gallery::update($me['id'], $upd),
                    'collector' => Collector::update($me['id'], $upd),
                    default     => ['success' => false],
                };
                self::flash(!empty($res['success']) ? I18n::t('success') : I18n::t('error'), !empty($res['success']));
            }
            self::back();
        }
    }

    /** Dashboard panel for the current member. */
    public static function panel(string $role, int $entity_id): string
    {
        if ($entity_id <= 0 || !in_array($role, ['artist', 'gallery', 'collector'], true)) {
            return '';
        }
        $flash = get_transient('art_coa_mt_' . get_current_user_id());
        if ($flash) {
            delete_transient('art_coa_mt_' . get_current_user_id());
        }
        global $wpdb;

        ob_start();
        echo '<div class="art-coa-member-tools">';
        if ($flash) {
            echo '<div class="art-coa-result ' . (!empty($flash['ok']) ? 'valid' : 'invalid') . '"><p>' . esc_html((string) $flash['msg']) . '</p></div>';
        }

        // ---- profile images
        $row = match ($role) {
            'artist'    => Artist::get($entity_id),
            'gallery'   => Gallery::get($entity_id),
            default     => Collector::get($entity_id),
        };
        echo '<h3>' . esc_html(I18n::t('profile_images')) . '</h3>';
        echo '<form method="post" class="art-coa-member-form">';
        wp_nonce_field('art_coa_member');
        echo '<input type="hidden" name="art_coa_member_action" value="profile_images">';
        $mk = static function (string $field, string $label) use ($row): string {
            $id  = (int) ($row->{$field . '_id'} ?? 0);
            $url = Media::url($row, $field, 'thumbnail');
            return Media::dropzone($field, ['label' => $label, 'current' => $id && $url ? [['id' => $id, 'url' => $url]] : []]);
        };
        if ($role === 'gallery') {
            echo $mk('logo', I18n::t('logo')); // phpcs:ignore
        } else {
            echo $mk('avatar', I18n::t('avatar')); // phpcs:ignore
        }
        if ($role !== 'collector') {
            echo $mk('cover', I18n::t('cover_image')); // phpcs:ignore
        }
        echo '<p><button class="art-coa-btn" type="submit">' . esc_html(I18n::t('save_images')) . '</button></p></form>';

        // ---- artist: new artwork
        if ($role === 'artist') {
            echo '<h3>' . esc_html(I18n::t('submit_artwork')) . '</h3>';
            echo '<form method="post" class="art-coa-member-form">';
            wp_nonce_field('art_coa_member');
            echo '<input type="hidden" name="art_coa_member_action" value="submit_artwork">';
            echo '<label>' . esc_html(I18n::t('title')) . ' *<input type="text" name="title" required maxlength="255"></label>';
            echo Media::dropzone('image', ['label' => I18n::t('artwork_image'), 'required' => true]); // phpcs:ignore
            echo Media::dropzone('gallery_ids', ['label' => I18n::t('artwork_more_images'), 'multiple' => true, 'max' => 8]); // phpcs:ignore
            echo '<label>' . esc_html(I18n::t('medium')) . '<input type="text" name="medium" maxlength="150"></label>';
            echo '<label>' . esc_html(I18n::t('year_created')) . '<input type="number" name="year_created" min="1000" max="2100"></label>';
            echo '<label>' . esc_html(I18n::t('dimensions')) . '<input type="text" name="dimensions" maxlength="120"></label>';
            echo '<label>' . esc_html(I18n::t('description')) . '<textarea name="description" rows="3"></textarea></label>';
            echo '<label><input type="checkbox" name="for_sale" value="1"> ' . esc_html(I18n::t('for_sale')) . '</label>';
            echo '<label>' . esc_html(I18n::t('price')) . '<input type="number" name="price" min="0" step="any"></label>';
            echo '<label>' . esc_html(I18n::t('currency')) . '<select name="currency"><option value="IRR">IRR</option><option value="IRT">IRT</option><option value="USD">USD</option><option value="EUR">EUR</option></select></label>';
            echo '<p><button class="art-coa-btn" type="submit">' . esc_html(I18n::t('submit_artwork')) . '</button></p></form>';

            $mine = $wpdb->get_results($wpdb->prepare(
                'SELECT * FROM ' . Database::table('artworks') . ' WHERE artist_id = %d ORDER BY id DESC LIMIT 24',
                $entity_id
            )) ?: [];
            if ($mine) {
                echo '<h4>' . esc_html(I18n::t('my_artworks')) . '</h4><div class="art-coa-cards art-coa-cards--artworks">';
                foreach ($mine as $a) {
                    echo Catalog::card_artwork($a); // phpcs:ignore
                }
                echo '</div>';
            }
        }

        // ---- gallery: new exhibition
        if ($role === 'gallery') {
            echo '<h3>' . esc_html(I18n::t('submit_exhibition')) . '</h3>';
            echo '<form method="post" class="art-coa-member-form">';
            wp_nonce_field('art_coa_member');
            echo '<input type="hidden" name="art_coa_member_action" value="submit_exhibition">';
            echo '<label>' . esc_html(I18n::t('title')) . ' *<input type="text" name="title" required maxlength="255"></label>';
            echo '<label>' . esc_html(I18n::t('subtitle')) . '<input type="text" name="subtitle" maxlength="500"></label>';
            echo '<label>' . esc_html(I18n::t('artist_label')) . '<input type="text" name="artist_label" maxlength="255"></label>';
            echo '<label>' . esc_html(I18n::t('event_type')) . '<select name="event_type"><option value="exhibition">' . esc_html(I18n::t('exhibition')) . '</option><option value="artfair">' . esc_html(I18n::t('artfair')) . '</option><option value="auction">' . esc_html(I18n::t('auction')) . '</option></select></label>';
            echo '<label>' . esc_html(I18n::t('start_date_g')) . ' *<input type="date" name="start_date" required></label>';
            echo '<label>' . esc_html(I18n::t('end_date_g')) . '<input type="date" name="end_date"></label>';
            echo Media::dropzone('poster', ['label' => I18n::t('exhibition_poster')]); // phpcs:ignore
            echo Media::dropzone('gallery_ids', ['label' => I18n::t('exhibition_photos'), 'multiple' => true, 'max' => 12]); // phpcs:ignore
            echo '<label>' . esc_html(I18n::t('description')) . '<textarea name="description" rows="4"></textarea></label>';
            echo '<p><button class="art-coa-btn" type="submit">' . esc_html(I18n::t('submit_exhibition')) . '</button></p></form>';

            $mine = $wpdb->get_results($wpdb->prepare(
                'SELECT e.* FROM ' . Database::table('exhibitions') . ' e WHERE e.gallery_id = %d ORDER BY e.id DESC LIMIT 24',
                $entity_id
            )) ?: [];
            if ($mine) {
                echo '<h4>' . esc_html(I18n::t('my_exhibitions')) . '</h4><div class="art-coa-cards art-coa-cards--exhibitions">';
                foreach ($mine as $e) {
                    echo Catalog::card_exhibition($e); // phpcs:ignore
                }
                echo '</div>';
            }
        }
        echo '</div>';
        return (string) ob_get_clean();
    }
}
