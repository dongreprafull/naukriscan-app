<?php
/**
 * Simple key-value helpers over the system_settings table, used for
 * admin-configurable values that don't need their own table (QR code
 * path, UPI ID, payment instructions, etc).
 */
function get_setting(PDO $pdo, string $key, string $default = ''): string {
    try {
        $st = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key=? LIMIT 1");
        $st->execute([$key]);
        $val = $st->fetchColumn();
        return $val !== false && $val !== null ? (string)$val : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function get_settings(PDO $pdo, array $keys): array {
    $out = [];
    foreach ($keys as $k) { $out[$k] = get_setting($pdo, $k); }
    return $out;
}

function set_setting(PDO $pdo, string $key, string $value): void {
    $st = $pdo->prepare("INSERT INTO system_settings(setting_key, setting_value) VALUES(?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $st->execute([$key, $value]);
}
