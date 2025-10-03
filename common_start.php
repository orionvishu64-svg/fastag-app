<?php
declare(strict_types=1);

// common_start.php - safe include (guarded)
if (defined('COMMON_START_INCLUDED')) return;
define('COMMON_START_INCLUDED', true);

if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('SESSION_USER_KEY')) define('SESSION_USER_KEY', 'user');
if (!defined('ALLOW_REQUEST_USER_FALLBACK')) define('ALLOW_REQUEST_USER_FALLBACK', true);
if (!defined('DEBUG_JSON_API')) define('DEBUG_JSON_API', false);

/** Return user array or null. Checks $_SESSION['user'], $_SESSION['user_id'], optional request fallback. */
if (!function_exists('get_current_user')) {
    function get_current_user(): ?array {
        if (!empty($_SESSION[SESSION_USER_KEY]) && is_array($_SESSION[SESSION_USER_KEY])) {
            return $_SESSION[SESSION_USER_KEY];
        }
        if (!empty($_SESSION['user_id'])) {
            return ['id' => (int)$_SESSION['user_id']];
        }
        if (ALLOW_REQUEST_USER_FALLBACK && !empty($_REQUEST['user_id'])) {
            return ['id' => (int)$_REQUEST['user_id']];
        }
        return null;
    }
}

/** Return current user id or null. */
if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): ?int {
        $u = get_current_user();
        if ($u === null) return null;
        if (isset($u['id'])) return (int)$u['id'];
        if (isset($u['user_id'])) return (int)$u['user_id'];
        return null;
    }
}

/** Require login or send 401 JSON and exit. */
if (!function_exists('require_login')) {
    function require_login(): void {
        if (get_current_user_id() === null) {
            json_response(['success' => false, 'message' => 'Not authenticated'], 401);
        }
    }
}

/** Send JSON and exit. */
if (!function_exists('json_response')) {
    function json_response($payload, int $status = 200): void {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        if (DEBUG_JSON_API && is_array($payload)) {
            $payload['_debug_time'] = microtime(true);
        }
        echo json_encode($payload);
        exit;
    }
}

/** Read JSON body (cached). */
if (!function_exists('get_json_input')) {
    function get_json_input(): array {
        static $cached = null;
        if ($cached !== null) return $cached;
        $raw = file_get_contents('php://input');
        if (!$raw) { $cached = []; return $cached; }
        $dec = json_decode($raw, true);
        $cached = (json_last_error() === JSON_ERROR_NONE && is_array($dec)) ? $dec : [];
        return $cached;
    }
}

/** Get request param from JSON body, POST, GET, then REQUEST. */
if (!function_exists('request_param')) {
    function request_param(string $key, $default = null) {
        $json = get_json_input();
        if (isset($json[$key])) return $json[$key];
        if (isset($_POST[$key])) return $_POST[$key];
        if (isset($_GET[$key])) return $_GET[$key];
        if (isset($_REQUEST[$key])) return $_REQUEST[$key];
        return $default;
    }
}

/** Simple logger to PHP error log. */
if (!function_exists('app_log')) {
    function app_log(string $msg, $context = null): void {
        if ($context !== null) {
            $msg .= ' | ' . (is_string($context) ? $context : json_encode($context));
        }
        error_log('[app] ' . $msg);
    }
}

/** Heuristic for AJAX/fetch requests. */
if (!function_exists('is_ajax_request')) {
    function is_ajax_request(): bool {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
        if (!empty($_SERVER['HTTP_ACCEPT']) &&
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) return true;
        return false;
    }
}
