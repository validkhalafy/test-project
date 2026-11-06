<?php
declare(strict_types=1);

namespace WPArtCOA\Admin\Pages;

use WPArtCOA\Database;
use WPArtCOA\Entities\Artist;
use WPArtCOA\Entities\Artwork;
use WPArtCOA\Entities\Certificate;
use WPArtCOA\Entities\Collector;
use WPArtCOA\Entities\Curator;
use WPArtCOA\Entities\Gallery;
use WPArtCOA\Entities\Exhibition;
use WPArtCOA\I18n;
use WPArtCOA\Dates;

defined('ABSPATH') || exit;

/** Admin screens split out of Menus (used as a trait so existing self:: calls keep working). */
trait ArtistsPage
{
    public static function page_artists(): void
    {
        self::handle_entity('artist', Artist::class);
        $edit    = self::editing(Artist::class);
        $artists = Artist::all();
        ?>
        <div class="wrap art-coa-wrap">
            <h1><?php echo esc_html(I18n::t('artists')); ?> <button type="button" class="button button-primary" onclick="document.getElementById('art-form-artist').style.display='block'">+ <?php echo esc_html(I18n::t('add_artist')); ?></button></h1>
            <?php
            self::form_open('artist', 'artist', $edit, $edit ? ($edit->display_name ?: $edit->first_name) : I18n::t('add_artist'));
            self::field('first_name', I18n::t('first_name'), $edit->first_name ?? '', ['required' => true]);
            self::field('last_name', I18n::t('last_name'), $edit->last_name ?? '', ['required' => true]);
            self::field('display_name', I18n::t('display_name'), $edit->display_name ?? '');
            self::field('national_id', I18n::t('national_id'), $edit->national_id ?? '', ['attrs' => 'maxlength="10"']);
            self::field('email', I18n::t('email'), $edit->email ?? '', ['type' => 'email']);
            self::field('phone', I18n::t('phone'), $edit->phone ?? '');
            self::field('website', I18n::t('website'), $edit->website ?? '', ['type' => 'url']);
            echo '<div class="art-coa-form-grid">';
            echo \WPArtCOA\Media::picker('avatar', $edit, ['label' => I18n::t('avatar'), 'shape' => 'square', 'help' => I18n::t('help_avatar')]); // phpcs:ignore
            echo \WPArtCOA\Media::picker('cover', $edit, ['label' => I18n::t('cover_image'), 'shape' => 'wide', 'help' => I18n::t('help_cover')]); // phpcs:ignore
            echo '</div>';
            self::field('bio', I18n::t('bio'), $edit->bio ?? '', ['type' => 'textarea']);
            self::form_close('artist', $edit);
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th class="aap-s-a57414">ID</th><th class="aap-s-191371"></th><th>نام</th><th>اسلاگ</th><th>ایمیل</th><th>وضعیت</th><th class="aap-s-c66442"><?php echo esc_html(I18n::t('actions')); ?></th></tr></thead>
                <tbody>
                <?php if (!$artists): ?>
                    <tr><td colspan="7" class="aap-center">هنرمندی ثبت نشده</td></tr>
                <?php else: foreach ($artists as $a): ?>
                    <tr>
                        <td><?php echo (int)$a->id; ?></td>
                        <td><?php echo self::thumb($a, 'avatar', 'is-round'); // phpcs:ignore ?></td>
                        <td><strong><?php echo esc_html($a->display_name ?: $a->first_name . ' ' . $a->last_name); ?></strong>
                            <br><a href="<?php echo esc_url(\WPArtCOA\ContentSync::artist_url((string) $a->slug)); ?>" target="_blank">مشاهده پروفایل</a>
                        </td>
                        <td><code><?php echo esc_html($a->slug); ?></code></td>
                        <td><?php echo esc_html($a->email); ?></td>
                        <td><span class="art-badge art-badge-<?php echo esc_attr($a->status); ?>"><?php echo esc_html($a->status); ?></span></td>
                        <td><a class="button button-small" href="<?php echo self::edit_url((int) $a->id); // phpcs:ignore ?>"><?php echo esc_html(I18n::t('edit')); ?></a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
