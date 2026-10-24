<?php
require_once '../config/app.php'; require_once '../includes/security.php'; start_secure_session(); require_user();
$pdo=db(); $uid=(int)$_SESSION['user_id']; $id=(int)($_GET['id']??0);
$st=$pdo->prepare('SELECT * FROM user_documents WHERE id=? AND user_id=? LIMIT 1'); $st->execute([$id,$uid]); $doc=$st->fetch();
if(!$doc){http_response_code(404);exit('Document not found.');}
$base=realpath(__DIR__.'/../storage/documents/'.$uid); $path=realpath(__DIR__.'/../'.$doc['file_path']);
if(!$base || !$path || strpos($path,$base.DIRECTORY_SEPARATOR)!==0 || !is_file($path)){http_response_code(404);exit('Document unavailable.');}
header('Content-Type: '.($doc['mime_type'] ?: 'application/octet-stream')); header('Content-Length: '.filesize($path)); header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._ -]/','_',basename($doc['file_name'])).'"'); header('X-Content-Type-Options: nosniff'); readfile($path); exit;
