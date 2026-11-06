<?php
// Minimal stand-ins for classes the hierarchy tests touch.
namespace WPArtCOA;

if (!class_exists(__NAMESPACE__ . '\\Database')) { final class Database { public static function table(string $n): string { return 'wp_' . $n; } } }
if (!class_exists(__NAMESPACE__ . '\\I18n')) { final class I18n { public static function t(string $k): string { return $k; } } }
