<?php
require __DIR__.'/common_start.php';
$_SESSION['probe'] = ($_SESSION['probe'] ?? 0) + 1;
echo "ok: ".$_SESSION['probe'];
