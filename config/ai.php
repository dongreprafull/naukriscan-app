<?php
// Server-side AI configuration. Prefer an environment variable on Hostinger.
define('AI_PROVIDER', 'openai');
define('AI_MODEL', getenv('OPENAI_API_MODEL') ?: 'gpt-5.6-luna');
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
