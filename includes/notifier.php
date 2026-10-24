<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/notifications.php';
require_once __DIR__ . '/notifications_settings.php';
require_once __DIR__ . '/mailer_smtp.php';

function notification_http_json(string $url, array $headers, array $payload): array {
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>30]);
    $body=curl_exec($ch); $err=curl_error($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($body===false || $err) return ['ok'=>false,'code'=>$code,'error'=>$err?:'HTTP request failed','body'=>''];
    $json=json_decode($body,true);
    return ['ok'=>$code>=200 && $code<300,'code'=>$code,'error'=>$code>=200&&$code<300?'':(is_array($json)?($json['message']??$json['error']['message']??'Provider rejected request'):'Provider rejected request'),'body'=>$body,'json'=>$json];
}

/**
 * Sends an arbitrary HTML email through the admin-configured provider.
 * 'hostinger' = PHP mail() via the hosting account's own mail relay, zero
 * setup, zero third-party account. 'smtp' = any SMTP provider the admin
 * points this at later (Brevo, SendGrid, Titan, etc.) via Admin > Email
 * Settings, using the built-in SMTP client — no Composer/API-key service
 * required by default.
 */
function send_raw_email(string $toEmail, string $toName, string $subject, string $html): array {
    $cfg = get_notification_settings(db());
    $fromEmail = $cfg['from_email'] ?: 'noreply@naukriscan.in';
    $fromName  = $cfg['from_name'] ?: 'NaukriScan';

    if (($cfg['email_provider'] ?? 'hostinger') === 'smtp') {
        return smtp_send($cfg, $toEmail, $toName, $subject, $html);
    }

    // Default: native PHP mail() through the Hostinger account's local relay.
    $boundary = uniqid('nsc_');
    $headers = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>\r\n"
        . "Reply-To: $fromEmail\r\n"
        . "X-Mailer: PHP/" . phpversion();
    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $ok = @mail($toEmail, $encodedSubject, $html, $headers);
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'mail() failed — check hosting mail relay / sending limits'];
}

function send_email_notification(array $recipient, array $notification): array {
    if(empty($recipient['email'])) return ['ok'=>false,'error'=>'Candidate email is missing.'];
    $html = '<div style="font-family:Arial,sans-serif"><h2>'.e($notification['title']).'</h2><p>'.nl2br(e($notification['message'])).'</p>'
        .(!empty($notification['job_id'])?'<p><a href="'.e((defined('APP_URL')?APP_URL:'')).'/user/job-details.php?id='.(int)$notification['job_id'].'">View job in portal</a></p>':'')
        .'</div>';
    $r = send_raw_email($recipient['email'], $recipient['name'] ?? '', $notification['title'], $html);
    return $r['ok'] ? ['ok'=>true,'provider'=>'email'] : ['ok'=>false,'provider'=>'email','error'=>$r['error']];
}

function normalize_indian_whatsapp(string $mobile): string {
    $digits=preg_replace('/\D+/','',$mobile);
    if(strpos($digits,'00')===0) $digits=substr($digits,2);
    if(strlen($digits)===10) $digits='91'.$digits;
    return $digits;
}
function send_whatsapp_notification(array $recipient, array $notification): array {
    // Deferred to a future update — kept as a stub so notification_queue
    // entries fail gracefully instead of breaking the queue processor.
    if(WHATSAPP_PROVIDER!=='meta_cloud') return ['ok'=>false,'error'=>'Unsupported WhatsApp provider: '.WHATSAPP_PROVIDER];
    if(WHATSAPP_ACCESS_TOKEN==='' || WHATSAPP_PHONE_NUMBER_ID==='') return ['ok'=>false,'error'=>'WhatsApp is not enabled yet (coming in a future update).'];
    if(empty($recipient['mobile'])) return ['ok'=>false,'error'=>'Candidate mobile is missing.'];
    $to=normalize_indian_whatsapp($recipient['mobile']);
    if(strlen($to)<11) return ['ok'=>false,'error'=>'Candidate mobile number is invalid.'];
    $payload=['messaging_product'=>'whatsapp','to'=>$to,'type'=>'text','text'=>['preview_url'=>false,'body'=>$notification['title']."\n\n".$notification['message']]];
    $url='https://graph.facebook.com/'.rawurlencode(WHATSAPP_GRAPH_VERSION).'/'.rawurlencode(WHATSAPP_PHONE_NUMBER_ID).'/messages';
    $r=notification_http_json($url,['Authorization: Bearer '.WHATSAPP_ACCESS_TOKEN,'Content-Type: application/json'],$payload);
    if($r['ok']) return ['ok'=>true,'provider'=>'meta_cloud','message_id'=>$r['json']['messages'][0]['id']??''];
    return ['ok'=>false,'provider'=>'meta_cloud','error'=>$r['error']];
}
