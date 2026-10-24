<?php
// Configure these as server environment variables. Never commit real secrets.
define('EMAIL_PROVIDER', getenv('EMAIL_PROVIDER') ?: 'resend');
define('RESEND_API_KEY', getenv('RESEND_API_KEY') ?: '');
define('EMAIL_FROM', getenv('EMAIL_FROM') ?: '');
define('WHATSAPP_PROVIDER', getenv('WHATSAPP_PROVIDER') ?: 'meta_cloud');
define('WHATSAPP_ACCESS_TOKEN', getenv('WHATSAPP_ACCESS_TOKEN') ?: '');
define('WHATSAPP_PHONE_NUMBER_ID', getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '');
define('WHATSAPP_GRAPH_VERSION', getenv('WHATSAPP_GRAPH_VERSION') ?: 'v23.0');
define('NOTIFICATION_WORKER_SECRET', getenv('NOTIFICATION_WORKER_SECRET') ?: '');
define('NOTIFICATION_MAX_ATTEMPTS', 3);
