<?php
/**
 * Shared "why NaukriScan" / Free vs Paid messaging block, used on the
 * homepage, dashboard, subscription and notifications pages so the value
 * proposition is visible everywhere, not just buried on one pricing page.
 */
function render_plan_highlight(array $opts = []): void {
    $compact = !empty($opts['compact']);
    $linkPrefix = $opts['link_prefix'] ?? '';
    $subLink = $opts['link'] ?? ($linkPrefix . 'subscription.php');
    $ctaLabel = $opts['cta_label'] ?? null;

    if ($compact) {
        ?>
        <div class="card" style="background:linear-gradient(135deg,#eaf3ff,#fff);border-color:#cfe3fb">
            <div class="card-head">
                <div>
                    <h2 style="margin-bottom:2px">🆓 Free forever: job-match alerts. ⚡ ₹149/month: unlimited auto-fill.</h2>
                    <p class="muted">Every genuine match reaches you free, for life. Upgrade only when you want us to auto-fill unlimited application forms for you.</p>
                </div>
            </div>
            <a class="btn btn-primary btn-small" href="<?= e($subLink) ?>"><?= e($ctaLabel ?: 'See Plans →') ?></a>
        </div>
        <?php
        return;
    }
    ?>
    <div class="section-title"></div>
    <div class="page-head"><div><span class="eyebrow">WHY NAUKRISCAN</span><h1>Free for life. Pay only for speed.</h1><p class="muted">We track government job notifications and tell you exactly which ones you're eligible for — that part is always free. If you want us to auto-fill your application forms too, that's where the paid plan comes in.</p></div></div>
    <div class="grid-2">
        <div class="card">
            <span class="tag">FREE — FOREVER</span>
            <h2 style="margin:10px 0 4px">₹0</h2>
            <p class="muted" style="margin-top:0">No card, no expiry. Just create your profile.</p>
            <ul style="margin:0;padding-left:18px;line-height:1.9">
                <li>Lifetime job-match alerts — in-app &amp; email</li>
                <li>Full profile builder &amp; AI eligibility matching</li>
                <li>Browse and save unlimited government jobs</li>
                <li>1 free auto-fill application, on us, to try it</li>
            </ul>
        </div>
        <div class="card" style="border-color:#1469d7;box-shadow:0 8px 26px rgba(20,90,200,.12)">
            <span class="tag" style="background:#1469d7;color:#fff">PAID — ₹149 / MONTH</span>
            <h2 style="margin:10px 0 4px">₹149<span class="muted" style="font-size:14px">/month</span></h2>
            <p class="muted" style="margin-top:0">Cancel anytime. Everything in Free, plus:</p>
            <ul style="margin:0;padding-left:18px;line-height:1.9">
                <li><strong>Unlimited</strong> auto-fill applications every month</li>
                <li>Never miss a deadline — apply in seconds, not hours</li>
                <li>Priority document handling for every application</li>
            </ul>
            <div class="form-actions" style="margin-top:16px"><a class="btn btn-primary" href="<?= e($subLink) ?>"><?= e($ctaLabel ?: 'Upgrade Now →') ?></a></div>
        </div>
    </div>
    <?php
}
