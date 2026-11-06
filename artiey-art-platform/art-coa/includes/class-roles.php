<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

final class Roles
{
    public const ROLES = [
        'art_artist'    => 'هنرمند',
        'art_gallery'   => 'گالری',
        'art_curator'   => 'کیوریتور',
        'art_collector' => 'مجموعه‌دار',
        'art_buyer'     => 'خریدار',
    ];

    public static function register_roles(): void
    {
        $caps_common = [
            'read' => true,
        ];

        $artist_caps = array_merge($caps_common, [
            'art_manage_own_artworks'   => true,
            'art_request_certificate'  => true,
            'art_view_own_certificates'=> true,
        ]);

        $gallery_caps = array_merge($caps_common, [
            'art_manage_gallery'         => true,
            'art_manage_artworks'        => true,
            'art_issue_certificate'      => true,
            'art_manage_exhibitions'     => true,
            'art_view_certificates'      => true,
        ]);

        $curator_caps = array_merge($caps_common, [
            'art_manage_exhibitions' => true,
            'art_view_artworks'      => true,
        ]);

        $collector_caps = array_merge($caps_common, [
            'art_view_own_collection' => true,
            'art_view_certificates'   => true,
        ]);

        $buyer_caps = array_merge($caps_common, [
            'art_view_own_purchases' => true,
        ]);

        add_role('art_artist', 'هنرمند', $artist_caps);
        add_role('art_gallery', 'گالری', $gallery_caps);
        add_role('art_curator', 'کیوریتور', $curator_caps);
        add_role('art_collector', 'مجموعه‌دار', $collector_caps);
        add_role('art_buyer', 'خریدار', $buyer_caps);

        // Give administrators full access
        $admin = get_role('administrator');
        if ($admin) {
            $all = array_merge(
                array_keys($artist_caps),
                array_keys($gallery_caps),
                array_keys($curator_caps),
                array_keys($collector_caps),
                array_keys($buyer_caps),
                ['art_manage_all', 'art_revoke_certificate']
            );
            foreach (array_unique($all) as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    public static function remove_roles(): void
    {
        foreach (array_keys(self::ROLES) as $role) {
            remove_role($role);
        }
    }
}
