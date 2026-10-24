<?php
require_once __DIR__.'/../includes/security.php';
security_headers();
define('APP_NAME', 'NaukriScan');
define('APP_URL', ''); // Optional: https://yourdomain.com
date_default_timezone_set('Asia/Kolkata');

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirect($url) { header('Location: '.$url); exit; }
function flash($key, $message=null) {
    if ($message !== null) { $_SESSION['_flash'][$key] = $message; return; }
    $msg = $_SESSION['_flash'][$key] ?? null; unset($_SESSION['_flash'][$key]); return $msg;
}
function csrf_token() {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_check($token) {
    return is_string($token) && hash_equals($_SESSION['_csrf'] ?? '', $token);
}
function old($key, $default='') { return e($_POST[$key] ?? $default); }
function db() {
    static $pdo;
    if ($pdo) return $pdo;
    require __DIR__ . '/database.php';
    return $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
}
function require_user() {
    if (empty($_SESSION['user_id'])) redirect('../login.php');
}
function require_admin() {
    if (empty($_SESSION['admin_id'])) redirect('index.php');
}
function admin_has_permission($permission) {
    if (empty($_SESSION['admin_id'])) return false;
    if (($_SESSION['admin_role'] ?? '') === 'super_admin') return true;
    try {
        $pdo = db();
        $st = $pdo->prepare("SELECT 1 FROM admin_role_permissions arp JOIN admin_roles r ON r.id=arp.role_id JOIN permissions p ON p.id=arp.permission_id JOIN admin_users a ON a.role=r.role_name WHERE a.id=? AND a.status='active' AND p.permission_key=? LIMIT 1");
        $st->execute([(int)$_SESSION['admin_id'], $permission]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
}
function require_permission($permission) {
    require_admin();
    if (!admin_has_permission($permission)) { http_response_code(403); exit('Access denied.'); }
}
function audit_admin($action,$module='',$recordId=null,$oldValue=null,$newValue=null) {
    try {
        $pdo=db(); $st=$pdo->prepare("INSERT INTO audit_logs(admin_id,action,module,record_id,old_value,new_value,ip_address,user_agent,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())");
        $st->execute([$_SESSION['admin_id']??null,$action,$module,$recordId,$oldValue,$newValue,$_SERVER['REMOTE_ADDR']??'',substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]);
    } catch (Throwable $e) {}
}
