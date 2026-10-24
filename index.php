<?php
require_once __DIR__ . '/config/app.php';
start_secure_session();
if (isset($_SESSION['user_id'])) header('Location: user/dashboard.php');
elseif (isset($_SESSION['admin_id'])) header('Location: admin/dashboard.php');
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(APP_NAME) ?></title><meta name="theme-color" content="#166534"><link rel="manifest" href="manifest.webmanifest"><link rel="apple-touch-icon" href="assets/icons/icon-192.png"><link rel="stylesheet" href="assets/css/style.css">
</head><body>
<header class="site-header"><div class="container nav"><a class="brand" href="index.php">🇮🇳 <?= e(APP_NAME) ?></a><nav><a href="login.php">Candidate Login</a><a class="btn btn-primary" href="register.php">Create Profile</a></nav></div></header>
<main><section class="hero"><div class="container"><span class="eyebrow">ONE PROFILE → GOVERNMENT JOB OPPORTUNITIES</span><h1>Find government jobs that match your profile.</h1><p>Create your profile once. Get relevant government job matches, deadline alerts and application assistance from one place.</p><div class="actions"><a class="btn btn-primary" href="register.php">Create Free Profile</a><a class="btn btn-light" href="login.php">Login</a></div></div></section>
<section class="container cards"><div class="card"><h3>🎯 AI Matching</h3><p>Match your education, age, category and other eligibility information with published jobs.</p></div><div class="card"><h3>🔔 Job Alerts</h3><p>See new matches and important application deadlines in your dashboard.</p></div><div class="card"><h3>📝 Application Assistance</h3><p>Prepare your stored profile information for supported application workflows.</p></div></section></main>
<footer><div class="container">© <?= date('Y') ?> <?= e(APP_NAME) ?> · Government job information should always be verified against the official notification.</div></footer>
<script>if("serviceWorker" in navigator){window.addEventListener("load",()=>navigator.serviceWorker.register("service-worker.js").catch(()=>{}));}</script></body></html>