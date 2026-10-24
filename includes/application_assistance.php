<?php
function load_candidate_context(PDO $pdo, int $uid): array {
    $ctx = [];
    $st=$pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1'); $st->execute([$uid]); $ctx['user']=$st->fetch() ?: [];
    $st=$pdo->prepare('SELECT * FROM user_profiles WHERE user_id=? LIMIT 1'); $st->execute([$uid]); $ctx['profile']=$st->fetch() ?: [];
    $ctx['addresses']=[]; $st=$pdo->prepare('SELECT * FROM user_addresses WHERE user_id=?'); $st->execute([$uid]); foreach($st as $a) $ctx['addresses'][$a['address_type']]=$a;
    $st=$pdo->prepare('SELECT * FROM user_education WHERE user_id=? ORDER BY passing_year DESC, id DESC'); $st->execute([$uid]); $ctx['education']=$st->fetchAll();
    $st=$pdo->prepare('SELECT * FROM user_experience WHERE user_id=? ORDER BY start_date DESC, id DESC'); $st->execute([$uid]); $ctx['experience']=$st->fetchAll();
    $st=$pdo->prepare('SELECT id,document_type,file_name,verification_status FROM user_documents WHERE user_id=? ORDER BY uploaded_at DESC'); $st->execute([$uid]); $ctx['documents']=$st->fetchAll();
    return $ctx;
}
function resolve_profile_source(array $ctx, string $source): string {
    if ($source==='') return '';
    if (isset($ctx['profile'][$source])) return (string)$ctx['profile'][$source];
    if (isset($ctx['user'][$source])) return (string)$ctx['user'][$source];
    if (preg_match('/^(current|permanent)\.(.+)$/',$source,$m)) return (string)($ctx['addresses'][$m[1]][$m[2]] ?? '');
    if (preg_match('/^education\.(.+)$/',$source,$m)) {
        foreach($ctx['education'] as $row) if (!empty($row[$m[1]])) return (string)$row[$m[1]];
    }
    if (preg_match('/^experience\.(.+)$/',$source,$m)) {
        foreach($ctx['experience'] as $row) if (!empty($row[$m[1]])) return (string)$row[$m[1]];
    }
    return '';
}
function document_source_value(array $ctx, string $source): string {
    if (strpos($source,'document:')!==0) return '';
    $type=substr($source,9); foreach($ctx['documents'] as $d) if ($d['document_type']===$type && $d['verification_status']!=='rejected') return $d['file_name'];
    return '';
}
