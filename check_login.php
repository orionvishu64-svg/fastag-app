<?php
session_start();
echo isset($_SESSION['user_id']) ? 'logged_in' : 'not_logged_in';
?>
