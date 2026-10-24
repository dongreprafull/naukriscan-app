<?php if (!defined('APP_NAME')) require_once __DIR__.'/../config/app.php'; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0b5cab"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="NaukriScan"><link rel="manifest" href="<?=e(($asset_prefix??''))?>manifest.webmanifest"><link rel="apple-touch-icon" href="<?=e(($asset_prefix??''))?>assets/icons/icon-192.png"><link rel="stylesheet" href="<?=e($asset_prefix??'')?>assets/css/style.css"></head><body>
<?php $isAdmin=!empty($_SESSION['admin_id']); $isUser=!empty($_SESSION['user_id']); $base=$asset_prefix??''; ?>
<?php if($isAdmin || $isUser): ?>
<div class="app-layout">
<aside class="sidebar" id="appSidebar"><div class="side-brand"><a href="<?=$isAdmin?$base.'admin/dashboard.php':$base.'user/dashboard.php'?>"><span class="brand-mark">N</span><span><b>NaukriScan</b><small><?= $isAdmin?'Admin Console':'Candidate Portal' ?></small></span></a></div>
<nav class="side-nav">
<?php if($isAdmin): ?>
<div class="nav-label">Overview</div><a href="<?=$base?>admin/dashboard.php">⌂ <span>Dashboard</span></a>
<div class="nav-label">Jobs & AI</div><a href="<?=$base?>admin/sources.php">◎ <span>Government Sources</span></a><a href="<?=$base?>admin/discovery.php">◈ <span>Discovery Queue</span></a><a href="<?=$base?>admin/jobs.php">▣ <span>Jobs</span></a><a href="<?=$base?>admin/ai-review.php">✦ <span>AI Review</span></a><a href="<?=$base?>admin/job-intake.php">＋ <span>Manual Intake</span></a>
<div class="nav-label">Operations</div><a href="<?=$base?>admin/users.php">♙ <span>Candidates</span></a><a href="<?=$base?>admin/notification-queue.php">◉ <span>Notifications</span></a><a href="<?=$base?>admin/application-templates.php">▤ <span>Application Templates</span></a><a href="<?=$base?>admin/subscription-plans.php">₹ <span>Subscriptions</span></a>
<div class="nav-label">Administration</div><?php if(($_SESSION['admin_role']??'')==='super_admin'): ?><a href="<?=$base?>admin/staff.php">♟ <span>Staff & Roles</span></a><?php endif; ?><a href="<?=$base?>admin/system-check.php">⚙ <span>System Health</span></a><a href="<?=$base?>admin/email-settings.php">✉ <span>Email Settings</span></a>
<?php else: ?>
<div class="nav-label">My NaukriScan</div><a href="<?=$base?>user/dashboard.php">⌂ <span>Dashboard</span></a><a href="<?=$base?>user/jobs.php">▣ <span>Government Jobs</span></a><a href="<?=$base?>user/notifications.php">◉ <span>Notifications</span></a><a href="<?=$base?>user/applications.php">▤ <span>My Applications</span></a>
<div class="nav-label">My Profile</div><a href="<?=$base?>user/profile.php">♙ <span>Master Profile</span></a><a href="<?=$base?>user/education.php">◆ <span>Education</span></a><a href="<?=$base?>user/experience.php">⌁ <span>Experience</span></a><a href="<?=$base?>user/documents.php">▧ <span>Documents</span></a><a href="<?=$base?>user/subscription.php">₹ <span>Subscription</span></a><a href="<?=$base?>user/settings.php">⚙ <span>Settings</span></a>
<?php endif; ?></nav>
<div class="side-bottom"><a class="logout-link" href="<?=$base?>logout.php">↪ <span>Logout</span></a></div></aside>
<div class="app-content"><header class="topbar"><button class="menu-btn" type="button" onclick="document.getElementById('appSidebar').classList.toggle('open')">☰</button><div class="topbar-title"><b><?=e($page_title??APP_NAME)?></b></div><a class="top-user" href="<?=$isAdmin?$base.'admin/dashboard.php':$base.'user/profile.php'?>"><span class="avatar"><?= $isAdmin?'A':'U' ?></span><span><?= $isAdmin?'Admin':'My Profile' ?></span></a></header><main class="main-area">
<?php else: ?>
<header class="site-header"><div class="container nav"><a class="brand" href="<?=e($home_link??'index.php')?>">🇮🇳 <?=e(APP_NAME)?></a><nav><a href="<?=$base?>login.php">Login</a><a class="btn btn-primary btn-small" href="<?=$base?>register.php">Create Profile</a></nav></div></header><main class="container">
<?php endif; ?>
<?php if($m=flash('success')):?><div class="alert success"><?=e($m)?></div><?php endif;?><?php if($m=flash('error')):?><div class="alert error"><?=e($m)?></div><?php endif;?>
