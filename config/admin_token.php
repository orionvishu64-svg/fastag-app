<?php
// config/admin_token.php
// Server-only config: admin host and token used to authenticate to admin APIs.
// IMPORTANT: do NOT expose this to the browser. Keep with chmod 640.

define('ADMIN_API_HOST', 'https://YOUR_ADMIN_HOST'); // e.g. https://admin.example.com  (no trailing slash)
define('ADMIN_API_TOKEN', 'REPLACE_WITH_YOUR_REAL_ADMIN_TOKEN');
