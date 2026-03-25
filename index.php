<?php
/**
 * OctaBank - Landing Page
 */
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Si ya tiene sesión, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/dashboard/");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="OctaBank - Sistema Financiero del 8vo Semestre. Gestiona las finanzas de tu grupo de forma segura.">
    <title>OctaBank | Sistema Financiero del 8vo Semestre</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
</head>
<body>
    <div class="landing-page">
        <!-- Navbar -->
        <nav class="landing-nav">
            <div class="logo">
                <div class="logo-box"><i class="fa-solid fa-building-columns"></i></div>
                <h1>OctaBank</h1>
            </div>
            <div class="hero-buttons">
                <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline btn-sm">Iniciar Sesión</a>
                <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary btn-sm">Registrarse</a>
            </div>
        </nav>
        
        <!-- Hero -->
        <section class="landing-hero">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fa-solid fa-graduation-cap"></i> Semestre 8 · Finanzas del Grupo
                </div>
                
                <h2>
                    Tu dinero del grupo,<br>
                    <span>seguro y organizado</span>
                </h2>
                
                <p>
                    Sistema financiero diseñado para el 8vo semestre. 
                    Deposita, retira, transfiere y lleva un control completo 
                    de las finanzas del grupo como un banco de verdad.
                </p>
                
                <div class="hero-buttons">
                    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary btn-lg">
                        <i class="fa-solid fa-rocket"></i> Crear mi Cuenta
                    </a>
                    <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline btn-lg">
                        Ya tengo cuenta
                    </a>
                </div>
            </div>
        </section>
        
        <!-- Features -->
        <section class="landing-features">
            <div class="features-title">
                <h3>Todo lo que necesitas</h3>
                <p class="text-muted mt-1">Funcionalidades diseñadas para tu grupo</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                    <h4>Depósitos Seguros</h4>
                    <p>Registra tus aportaciones con aprobación del administrador y recibo digital.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-rotate"></i></div>
                    <h4>Transferencias</h4>
                    <p>Envía dinero a cualquier miembro del grupo al instante.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-chart-simple"></i></div>
                    <h4>Dashboard en Tiempo Real</h4>
                    <p>Visualiza tu saldo, movimientos y estadísticas actualizadas.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                    <h4>Historial Completo</h4>
                    <p>Consulta todos tus movimientos con referencias únicas.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-lock"></i></div>
                    <h4>Seguridad Bancaria</h4>
                    <p>Contraseñas encriptadas, tokens CSRF y auditoría completa de acciones.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-mobile-screen"></i></div>
                    <h4>100% Responsivo</h4>
                    <p>Accede desde tu celular, tablet o computadora sin problemas.</p>
                </div>
            </div>
        </section>
        
        <!-- Footer -->
        <footer style="text-align:center;padding:40px;border-top:1px solid var(--border-color);color:var(--text-muted);font-size:13px;">
            <p><i class="fa-solid fa-building-columns"></i> OctaBank &copy; <?= date('Y') ?> · Sistema Financiero del 8vo Semestre</p>
            <p class="mt-1">Hecho con 💜 para el grupo</p>
        </footer>
    </div>
    
    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
