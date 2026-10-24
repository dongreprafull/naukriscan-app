<?php
require_once '../config/app.php'; require_once '../includes/security.php'; require_once '../config/ai.php'; start_secure_session(); require_permission('settings.manage'); require_once '../includes/settings.php';
$pdo = db(); $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) { $error = 'Invalid request.'; }
    else {
        $model = trim($_POST['ai_model'] ?? '') ?: 'gpt-5.6-luna';
        set_setting($pdo, 'ai_model', $model);
        if (trim($_POST['ai_openai_api_key'] ?? '') !== '') {
            set_setting($pdo, 'ai_openai_api_key', trim($_POST['ai_openai_api_key']));
        }
        flash('success', 'AI settings saved.');
        redirect('ai-settings.php');
    }
}

$cfg = get_settings($pdo, ['ai_openai_api_key', 'ai_model']);
$hasKey = $cfg['ai_openai_api_key'] !== '' || OPENAI_API_KEY !== '';
$page_title = 'AI Settings'; $asset_prefix = '../'; $home_link = '../index.php'; require '../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">ADMIN</span><h1>AI Settings</h1><p class="muted">Powers automatic job discovery (source scanner) and notification-PDF extraction.</p></div></div>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
<p>Status: <?php if ($hasKey): ?><span class="tag" style="background:#eaf9f0;color:#167443">Configured</span><?php else: ?><span class="tag" style="background:#fff0f0;color:#a32929">Not configured — scanner and AI review will not run</span><?php endif; ?></p>
<form method="post">
<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<label>OpenAI API Key<input type="password" name="ai_openai_api_key" placeholder="<?= $cfg['ai_openai_api_key'] !== '' ? 'Saved — leave blank to keep current key' : 'sk-...' ?>" autocomplete="off"></label>
<label>Model<input name="ai_model" value="<?= e($cfg['ai_model'] ?: 'gpt-5.6-luna') ?>"></label>
<div class="form-actions"><button class="btn btn-primary">Save</button></div>
</form>
<p class="muted" style="margin-top:14px">Get a key from <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com/api-keys</a>. The key is stored in the database, never committed to the codebase.</p>
</div>
<div class="card"><h2>How automatic discovery works</h2><p class="muted">Add official government recruitment URLs in <a href="sources.php">Government Sources</a>. A scheduled job visits each active source every few hours, follows recruitment-looking links, reads the notification page or PDF, and asks AI to extract structured facts (title, organization, eligibility, dates). High-confidence results become a job in <strong>Pending Verification</strong>; uncertain ones go to the <a href="discovery.php">Discovery Queue</a> for a staff member to check before it's published. Nothing is ever auto-published without this review step.</p></div>
<?php require '../includes/footer.php'; ?>
