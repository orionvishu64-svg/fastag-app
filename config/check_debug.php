<?php
// DEBUG MARKER A (wrapper)
echo "DEBUG_A\n";
flush();
ob_flush();

// now include the real script (same directory)
require_once __DIR__ . '/check_login.php';
