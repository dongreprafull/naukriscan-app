<?php
function start_secure_session() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.use_only_cookies','1');
    ini_set('session.use_strict_mode','1');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
function client_ip() { return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45); }
function login_rate_allowed($scope,$identifier,$limit=8,$windowMinutes=15) {
    try {
        $pdo=db();
        $key=hash('sha256', strtolower($scope.'|'.trim($identifier).'|'.client_ip()));
        $minutes=max(1,min(1440,(int)$windowMinutes));
        $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL ".$minutes." MINUTE)");
        $st=$pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE rate_key=? AND success=0 AND attempted_at >= (NOW() - INTERVAL ".$minutes." MINUTE)");
        $st->execute([$key]);
        return ((int)$st->fetchColumn()) < $limit;
    } catch(Throwable $e) { return true; }
}
function record_login_attempt($scope,$identifier,$success) {
    try {
        $pdo=db(); $key=hash('sha256', strtolower($scope.'|'.trim($identifier).'|'.client_ip()));
        $pdo->prepare("INSERT INTO login_attempts(rate_key,scope,identifier_hash,ip_address,success,attempted_at) VALUES(?,?,?,?,?,NOW())")
            ->execute([$key,$scope,hash('sha256',trim($identifier)) ,client_ip(),$success?1:0]);
    } catch(Throwable $e) {}
}
function rotate_session_on_login() { if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true); }
function security_headers() {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self';");
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
