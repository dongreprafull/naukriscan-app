<?php
/**
 * DB-backed, admin-editable email settings. Replaces hardcoded Resend-only
 * config. Default provider 'hostinger' uses PHP mail() — zero config, no
 * third-party account needed. Admin can switch to 'smtp' and fill in any
 * provider's host/port/user/pass later from the Admin > Email Settings page.
 */
function get_notification_settings(PDO $pdo): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $row = $pdo->query("SELECT * FROM app_notification_settings WHERE id=1 LIMIT 1")->fetch();
    if (!$row) {
        $row = [
            'email_provider' => 'hostinger', 'smtp_host' => null, 'smtp_port' => 587,
            'smtp_username' => null, 'smtp_password' => null, 'smtp_encryption' => 'tls',
            'from_email' => 'noreply@naukriscan.in', 'from_name' => 'NaukriScan',
        ];
    }
    return $cache = $row;
}

function update_notification_settings(PDO $pdo, array $fields): void {
    $allowed = ['email_provider','smtp_host','smtp_port','smtp_username','smtp_password','smtp_encryption','from_email','from_name'];
    $set = []; $vals = [];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $fields)) { $set[] = "$k=?"; $vals[] = $fields[$k] === '' ? null : $fields[$k]; }
    }
    if (!$set) return;
    $pdo->prepare("UPDATE app_notification_settings SET " . implode(',', $set) . " WHERE id=1")->execute($vals);
}
