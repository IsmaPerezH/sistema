<?php
/**
 * OctaBank - Verificación de autenticación
 * Incluir en páginas que requieren login
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name(defined('SESSION_NAME') ? SESSION_NAME : 'octabank_session');
    session_start();
}

require_once dirname(__DIR__) . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    setFlashMessage('error', 'Debes iniciar sesión para acceder.');
    redirect('/auth/login.php');
}

// Verificar si la cuenta sigue activa
$currentUser = getCurrentUser();
if (!$currentUser || !$currentUser['activo']) {
    session_destroy();
    header("Location: " . BASE_URL . "/auth/login.php?error=disabled");
    exit;
}

// Actualizar último acceso
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
