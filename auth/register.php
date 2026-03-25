<?php
/**
 * OctaBank - Registro de nuevos miembros
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

if (isset($_SESSION['user_id'])) {
    redirect('/dashboard/');
}

$error = '';
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf)) {
        $error = 'Token de seguridad inválido.';
    } else {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        $old = compact('nombre', 'apellido', 'email', 'telefono');
        
        // Validaciones
        if (empty($nombre) || empty($apellido) || empty($email) || empty($password)) {
            $error = 'Todos los campos marcados son obligatorios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'El correo electrónico no es válido.';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($password !== $password_confirm) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $db = Database::getInstance()->getConnection();
            
            // Verificar email duplicado
            $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'Ya existe una cuenta con este correo electrónico.';
            } else {
                try {
                    $db->beginTransaction();
                    
                    $numeroCuenta = generateAccountNumber();
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insertar usuario
                    $stmt = $db->prepare("INSERT INTO usuarios (nombre, apellido, email, password, telefono, rol, numero_cuenta) 
                                         VALUES (?, ?, ?, ?, ?, 'miembro', ?)");
                    $stmt->execute([$nombre, $apellido, $email, $hashedPassword, $telefono, $numeroCuenta]);
                    
                    $userId = $db->lastInsertId();
                    
                    // Crear cuenta bancaria
                    $stmt = $db->prepare("INSERT INTO cuentas (usuario_id, numero_cuenta, saldo, estado) 
                                         VALUES (?, ?, 0.00, 'activa')");
                    $stmt->execute([$userId, $numeroCuenta]);
                    
                    // Auditoría
                    logAudit($userId, 'REGISTRO', 'usuarios', 'Nuevo miembro registrado: ' . $email);
                    
                    $db->commit();
                    
                    setFlashMessage('success', '¡Cuenta creada exitosamente! Tu número de cuenta es: ' . $numeroCuenta . '. Inicia sesión para continuar.');
                    redirect('/auth/login.php');
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Error al crear la cuenta. Intenta de nuevo.';
                    if (DEBUG_MODE) {
                        $error .= ' Debug: ' . $e->getMessage();
                    }
                }
            }
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
    <title>Registro | OctaBank</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-logo">
                    <div class="logo-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                    <h1>Crear Cuenta</h1>
                    <p>Únete al sistema financiero del 8vo semestre</p>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-xmark"></i> <?= sanitize($error) ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label for="nombre">Nombre *</label>
                            <input type="text" id="nombre" name="nombre" class="form-control" 
                                   placeholder="Tu nombre" required
                                   value="<?= sanitize($old['nombre'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="apellido">Apellido *</label>
                            <input type="text" id="apellido" name="apellido" class="form-control" 
                                   placeholder="Tu apellido" required
                                   value="<?= sanitize($old['apellido'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><i class="fa-regular fa-envelope"></i> Correo Electrónico *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="tu@correo.com" required
                               value="<?= sanitize($old['email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="telefono"><i class="fa-solid fa-mobile-screen"></i> Teléfono (opcional)</label>
                        <input type="tel" id="telefono" name="telefono" class="form-control" 
                               placeholder="55 1234 5678"
                               value="<?= sanitize($old['telefono'] ?? '') ?>">
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label for="password"><i class="fa-solid fa-lock"></i> Contraseña *</label>
                            <input type="password" id="password" name="password" class="form-control" 
                                   placeholder="Mín. 6 caracteres" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="password_confirm"><i class="fa-solid fa-lock"></i> Confirmar *</label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control" 
                                   placeholder="Repetir contraseña" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block btn-lg mt-1">
                        <i class="fa-solid fa-building-columns"></i> Crear mi Cuenta
                    </button>
                    
                    <p class="form-text text-center mt-2">
                        Se te asignará automáticamente un número de cuenta único
                    </p>
                </form>
                
                <div class="auth-footer">
                    <p class="text-muted">¿Ya tienes cuenta? <a href="<?= BASE_URL ?>/auth/login.php">Inicia sesión</a></p>
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
