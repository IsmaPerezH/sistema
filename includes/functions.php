<?php
/**
 * OctaBank - Funciones de utilidad
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/classes/Database.php';

/**
 * Formatear monto en pesos mexicanos
 */
function formatMoney($amount) {
    return APP_CURRENCY_SYMBOL . number_format((float)$amount, 2, '.', ',');
}

/**
 * Generar número de referencia único
 */
function generateReference() {
    return 'TXN-' . strtoupper(bin2hex(random_bytes(6))) . '-' . date('ymd');
}

/**
 * Generar número de cuenta único
 */
function generateAccountNumber() {
    $db = Database::getInstance()->getConnection();
    do {
        $number = ACCOUNT_PREFIX . str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE numero_cuenta = ?");
        $stmt->execute([$number]);
    } while ($stmt->fetchColumn() > 0);
    
    return $number;
}

/**
 * Generar token CSRF
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verifyCSRFToken($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        unset($_SESSION['csrf_token']);
        return true;
    }
    return false;
}

/**
 * Sanitizar entrada
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Redireccionar
 */
function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit;
}

/**
 * Mostrar mensaje flash
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Formatear fecha
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Registrar auditoría
 */
function logAudit($userId, $action, $table = null, $detail = null) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, detalle, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $table,
            $detail,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    } catch (Exception $e) {
        // Silenciar errores de auditoría para no afectar operación
    }
}

/**
 * Obtener información del usuario actual
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) return null;
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT u.*, c.saldo, c.saldo_retenido, c.estado as estado_cuenta 
                          FROM usuarios u 
                          JOIN cuentas c ON u.id = c.usuario_id 
                          WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Verificar si es administrador
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Obtener estadísticas del sistema (admin)
 */
function getSystemStats() {
    $db = Database::getInstance()->getConnection();
    
    $stats = [];
    
    // Total miembros activos
    $stmt = $db->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND rol = 'miembro'");
    $stats['total_miembros'] = $stmt->fetchColumn();
    
    // Balance total del sistema
    $stmt = $db->query("SELECT COALESCE(SUM(saldo), 0) FROM cuentas WHERE estado = 'activa'");
    $stats['balance_total'] = $stmt->fetchColumn();
    
    // Transacciones pendientes
    $stmt = $db->query("SELECT COUNT(*) FROM transacciones WHERE estado = 'pendiente'");
    $stats['pendientes'] = $stmt->fetchColumn();
    
    // Total depositado hoy
    $stmt = $db->query("SELECT COALESCE(SUM(monto), 0) FROM transacciones 
                        WHERE tipo = 'deposito' AND estado = 'aprobada' 
                        AND DATE(fecha_creacion) = CURDATE()");
    $stats['depositos_hoy'] = $stmt->fetchColumn();
    
    return $stats;
}
