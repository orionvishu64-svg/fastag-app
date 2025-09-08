<?php
require_once 'common_start.php';
echo isset($_SESSION['user_id']) ? 'logged_in' : 'not_logged_in';
?>
