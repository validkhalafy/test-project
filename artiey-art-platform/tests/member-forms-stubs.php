<?php
// Namespaced stand-ins for the COA classes MemberForms talks to.
namespace WPArtCOA;

final class I18n { public static function is_fa(): bool { return true; } public static function t(string $k): string { return $k; } }
final class Database { public static function table(string $n): string { return 'wp_' . $n; } }
final class Audit { public static function log(...$a): void { $GLOBALS['audit'][] = $a; } }
final class Roles { public static function register_roles(): void {} }
final class Security { public static function client_ip(): string { return '203.0.113.9'; } }
abstract class FakeEntity {
    public static function create(array $d): array {
        $GLOBALS['entity_calls'][] = [static::class, $d];
        if (!empty($GLOBALS['entity_fail'])) { return ['success' => false, 'message' => 'x']; }
        return ['success' => true, 'id' => 77, 'slug' => 'slug-77'];
    }
    public static function update(int $id, array $d): array { $GLOBALS['update_calls'][] = [static::class, $id, $d]; return ['success' => true]; }
    public static function get(int $id): ?object { return $GLOBALS['entity_rows'][static::class] ?? null; }
}
final class Artist extends FakeEntity {}
final class Gallery extends FakeEntity {}
final class Curator extends FakeEntity {}
final class Collector extends FakeEntity {}
final class Buyer extends FakeEntity {}
final class Cache { public static function flush_stats(): void { $GLOBALS['stats_flushed'] = ($GLOBALS['stats_flushed'] ?? 0) + 1; } }
