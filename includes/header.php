<?php
/**
 * OctaBank - Header con Sidebar
 * Requiere: $pageTitle, $currentPage
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name(defined('SESSION_NAME') ? SESSION_NAME : 'octabank_session');
    session_start();
}

$user = getCurrentUser();
$pendingCount = 0;

if (isAdmin()) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT COUNT(*) FROM transacciones WHERE estado = 'pendiente'");
    $pendingCount = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="OctaBank - Sistema Financiero del 8vo Semestre">
    <title><?= sanitize($pageTitle ?? 'Dashboard') ?> | OctaBank</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon"><i class="fa-solid fa-building-columns"></i></div>
                <div class="brand-text">
                    <h2>OctaBank</h2>
                    <span>8vo Semestre</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section-title">Principal</div>
                
                <a href="<?= BASE_URL ?>/dashboard/" class="nav-link <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fa-solid fa-chart-simple"></i></span>
                    Dashboard
                </a>
                
                <div class="nav-section-title">Operaciones</div>
                
                <a href="<?= BASE_URL ?>/transacciones/depositar.php" class="nav-link <?= ($currentPage ?? '') === 'depositar' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fa-solid fa-sack-dollar"></i></span>
                    Depositar
                </a>
                
                <a href="<?= BASE_URL ?>/transacciones/retirar.php" class="nav-link <?= ($currentPage ?? '') === 'retirar' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                    Retirar
                </a>
                
                <a href="<?= BASE_URL ?>/transacciones/transferir.php" class="nav-link <?= ($currentPage ?? '') === 'transferir' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fa-solid fa-rotate"></i></span>
                    Transferir
                </a>
                
                <a href="<?= BASE_URL ?>/transacciones/historial.php" class="nav-link <?= ($currentPage ?? '') === 'historial' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fa-solid fa-clipboard-list"></i></span>
                    Historial
                </a>
                
                <?php if (isAdmin()): ?>
                <div class="nav-section-title">Administración</div>
                
                <a href="<?= BASE_URL ?>/admin/" class="nav-link <?= ($currentPage ?? '') === 'admin_dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                    Panel Admin
                </a>
                
                <a href="<?= BASE_URL ?>/admin/aprobar_transacciones.php" class="nav-link <?= ($currentPage ?? '') === 'aprobar' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fa-solid fa-check"></i></span>
                    Aprobar
                    <?php if ($pendingCount > 0): ?>
                        <span class="nav-badge"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="<?= BASE_URL ?>/admin/miembros.php" class="nav-link <?= ($currentPage ?? '') === 'miembros' ? 'active' : '' ?>">
                    <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                    Miembros
                </a>
                <?php endif; ?>
            </nav>
            
            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="user-avatar"><?= $user['avatar'] ?? '<i class="fa-solid fa-user"></i>' ?></div>
                    <div class="user-info">
                        <div class="name"><?= sanitize($user['nombre'] ?? '') ?> <?= sanitize($user['apellido'] ?? '') ?></div>
                        <div class="account"><?= sanitize($user['numero_cuenta'] ?? '') ?></div>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-link mt-1" style="color: var(--color-error);">
                    <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                    Cerrar Sesión
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div style="display:flex;align-items:center;gap:16px;">
                    <button class="mobile-toggle" id="mobileToggle">☰</button>
                    <div class="page-title">
                        <h1><?= sanitize($pageTitle ?? 'Dashboard') ?></h1>
                        <p><?= sanitize($pageSubtitle ?? '') ?></p>
                    </div>
                </div>
                <div class="header-actions">
                    <span class="badge badge-info" style="font-size:11px;">
                        <i class="fa-solid fa-gem"></i> <?= isAdmin() ? 'Administrador' : 'Miembro' ?>
                    </span>
                </div>
            </header>
            
            <!-- Page Content -->
            <div class="page-container fade-in">
                <?php 
                $flash = getFlashMessage();
                if ($flash): 
                ?>
                <div class="alert alert-<?= $flash['type'] ?>" data-auto-dismiss="5000">
                    <?php 
                    $icons = ['success' => '<i class="fa-solid fa-check"></i>', 'error' => '<i class="fa-solid fa-xmark"></i>', 'warning' => '<i class="fa-solid fa-triangle-exclamation"></i>', 'info' => '<i class="fa-solid fa-circle-info"></i>'];
                    echo ($icons[$flash['type']] ?? '') . ' ' . sanitize($flash['message']); 
                    ?>
                </div>
                <?php endif; ?>
