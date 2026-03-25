<?php
/**
 * OctaBank - Login
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Si ya tiene sesión
if (isset($_SESSION['user_id'])) {
    redirect('/dashboard/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf)) {
        $error = 'Token de seguridad inválido. Recarga la página.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Todos los campos son obligatorios.';
    } else {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Regenerar sesión
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['rol'];
            $_SESSION['user_name'] = $user['nombre'] . ' ' . $user['apellido'];
            
            // Actualizar último acceso
            $stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Auditoría
            logAudit($user['id'], 'LOGIN', 'usuarios', 'Inicio de sesión exitoso');
            
            setFlashMessage('success', '¡Bienvenido de vuelta, ' . sanitize($user['nombre']) . '!');
            redirect('/dashboard/');
        } else {
            $error = 'Correo electrónico o contraseña incorrectos.';
            logAudit(null, 'LOGIN_FAILED', 'usuarios', 'Intento fallido con email: ' . $email);
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | OctaBank</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-logo">
                    <div class="logo-icon"><i class="fa-solid fa-building-columns"></i></div>
                    <h1>OctaBank</h1>
                    <p>Sistema Financiero del 8vo Semestre</p>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-xmark"></i> <?= sanitize($error) ?>
                </div>
                <?php endif; ?>
                
                <?php 
                $flash = getFlashMessage();
                if ($flash): 
                ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <?= sanitize($flash['message']) ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="form-group">
                        <label for="email"><i class="fa-regular fa-envelope"></i> Correo Electrónico</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="tu@correo.com" required autocomplete="email"
                               value="<?= sanitize($email ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="fa-solid fa-lock"></i> Contraseña</label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="••••••••" required autocomplete="current-password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fa-solid fa-rocket"></i> Iniciar Sesión
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p class="text-muted">¿No tienes cuenta? <a href="<?= BASE_URL ?>/auth/register.php">Regístrate aquí</a></p>
                </div>
            </div>
            
            <p class="text-center text-muted mt-2" style="font-size:12px;">
                <a href="<?= BASE_URL ?>/" style="color:var(--text-muted);">← Volver al inicio</a>
            </p>
        </div>
    </div>
    
    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
