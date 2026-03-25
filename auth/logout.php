<?php
/**
 * OctaBank - Logout
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

if (isset($_SESSION['user_id'])) {
    logAudit($_SESSION['user_id'], 'LOGOUT', 'usuarios', 'Cierre de sesión');
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: " . BASE_URL . "/auth/login.php");
exit;
