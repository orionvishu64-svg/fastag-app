<?php
// config/admin_token.php 
// Server-only config: admin host and token used to authenticate to admin APIs.
// IMPORTANT: do NOT expose this to the browser. Keep with chmod 640.

define('ADMIN_API_HOST', 'http://43.205.43.30/admin'); // e.g. https://admin.example.com  (no trailing slash)
define('ADMIN_API_TOKEN', '82882be3c32322a1fc1b9a65e2b3f0c9552c9a69');
