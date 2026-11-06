<?php
declare(strict_types=1);

namespace WPArtCOA;

defined('ABSPATH') || exit;

spl_autoload_register(static function (string $class): void {
    $prefix = 'WPArtCOA\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relative = str_replace('\\', '/', $relative);
    $file = WP_ART_COA_DIR . 'includes/' . strtolower(str_replace('_', '-', $relative)) . '.php';

    // Map class names to file paths
    $map = [
        'Plugin'              => 'class-plugin.php',
        'Activator'           => 'class-activator.php',
        'Database'            => 'class-database.php',
        'Roles'               => 'class-roles.php',
        'Ulid'                => 'class-ulid.php',
        'CertificateMirror'   => 'class-certificate-mirror.php',
        'Audit'               => 'class-audit.php',
        'Admin\\Menus'        => 'admin/class-menus.php',
        'Admin\\Assets'       => 'admin/class-assets.php',
        'Rest\\Api'           => 'rest/class-api.php',
        'Elementor\\Loader'   => 'elementor/class-loader.php',
        'Entities\\Artist'    => 'entities/class-artist.php',
        'Entities\\Gallery'   => 'entities/class-gallery.php',
        'Entities\\Curator'   => 'entities/class-curator.php',
        'Entities\\Collector' => 'entities/class-collector.php',
        'Entities\\Artwork'   => 'entities/class-artwork.php',
        'Entities\\Certificate' => 'entities/class-certificate.php',
        'Frontend\\Profiles'  => 'class-profiles.php',
        'Frontend\\Shortcodes'=> 'class-shortcodes.php',
    ];

    if (isset($map[$relative])) {
        $path = WP_ART_COA_DIR . 'includes/' . $map[$relative];
        if (file_exists($path)) {
            require_once $path;
        }
        return;
    }

    // Fallback: convert CamelCase to class-kebab.php under includes/
    $parts = explode('/', $relative);
    $file_name = 'class-' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', end($parts))) . '.php';
    array_pop($parts);
    $sub = $parts ? strtolower(implode('/', $parts)) . '/' : '';
    $path = WP_ART_COA_DIR . 'includes/' . $sub . $file_name;

    if (file_exists($path)) {
        require_once $path;
    }
});
