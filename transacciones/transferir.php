<?php
/**
 * OctaBank - Transferir Fondos a otro miembro
 */
$pageTitle = 'Transferir Fondos';
$pageSubtitle = 'Envía dinero instantáneamente a un compañero del grupo';
$currentPage = 'transferir';

require_once dirname(__DIR__) . '/includes/auth.php';
$user = getCurrentUser();

$db = Database::getInstance()->getConnection();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf)) {
        $error = 'Token de seguridad inválido.';
    } else {
        $monto_crudo = filter_input(INPUT_POST, 'monto', FILTER_VALIDATE_FLOAT);
        $monto = $monto_crudo !== false && $monto_crudo !== null ? round((float)$monto_crudo, 2) : 0;
        $descripcion = trim($_POST['descripcion'] ?? '');
        $numero_cuenta = trim($_POST['numero_cuenta'] ?? '');
        
        $saldoActual = round((float)$user['saldo'], 2); // Seguridad extra anti-float
        
        // Parche de Alta Seguridad Matemática (Anti-bypass F12, números negativos)
        if ($monto < 1.00) {
            $error = 'El monto debe ser numérico positivo, con un mínimo de ' . formatMoney(1);
        } elseif ($monto > $saldoActual) {
            $error = 'Fondos insuficientes. Tu saldo disponible es ' . formatMoney($saldoActual);
        } elseif (empty($numero_cuenta)) {
            $error = 'Debes ingresar un número de cuenta válido.';
        } else {
            // Verificar que el destinatario existe y tiene cuenta
            $stmt = $db->prepare("SELECT u.id as user_id, c.id, c.saldo FROM usuarios u JOIN cuentas c ON u.id = c.usuario_id WHERE c.numero_cuenta = ? AND u.activo = 1");
            $stmt->execute([$numero_cuenta]);
            $cuenta_destino = $stmt->fetch();
            
            if (!$cuenta_destino) {
                $error = 'El número de cuenta no existe o está inactivo.';
            } elseif ($cuenta_destino['user_id'] == $user['id']) {
                $error = 'No puedes transferir fondos a tu propia cuenta.';
            } else {
                // Obtener mi cuenta
                $stmt = $db->prepare("SELECT id FROM cuentas WHERE usuario_id = ?");
                $stmt->execute([$user['id']]);
                $mi_cuenta_id = $stmt->fetchColumn();
                
                try {
                    $db->beginTransaction();
                    $referencia = generateReference();
                    
                    // 1. Restar de mi cuenta
                    $saldoAnteriorOrigen = $saldoActual;
                    $saldoPosteriorOrigen = $saldoActual - $monto;
                    
                    $stmt = $db->prepare("UPDATE cuentas SET saldo = ? WHERE id = ? AND saldo >= ?");
                    $stmt->execute([$saldoPosteriorOrigen, $mi_cuenta_id, $monto]);
                    
                    if ($stmt->rowCount() === 0) {
                        throw new Exception("Error al procesar el cargo. Verifica tu saldo.");
                    }
                    
                    // 2. Sumar a cuenta destino
                    $saldoAnteriorDestino = $cuenta_destino['saldo'];
                    $saldoPosteriorDestino = $saldoAnteriorDestino + $monto;
                    
                    $stmt = $db->prepare("UPDATE cuentas SET saldo = ? WHERE id = ?");
                    $stmt->execute([$saldoPosteriorDestino, $cuenta_destino['id']]);
                    
                    // 3. Registrar transacción de envío (para mi historial)
                    $stmt = $db->prepare("
                        INSERT INTO transacciones 
                        (cuenta_origen_id, cuenta_destino_id, tipo, monto, saldo_anterior, saldo_posterior, descripcion, referencia, estado, aprobado_por, fecha_creacion, fecha_aprobacion)
                        VALUES (?, ?, 'transferencia_envio', ?, ?, ?, ?, ?, 'aprobada', ?, NOW(), NOW())
                    ");
                    $stmt->execute([$mi_cuenta_id, $cuenta_destino['id'], $monto, $saldoAnteriorOrigen, $saldoPosteriorOrigen, $descripcion, $referencia . '-E', $user['id']]);
                    
                    // 4. Registrar transacción recibida (para el historial del otro)
                    $stmt = $db->prepare("
                        INSERT INTO transacciones 
                        (cuenta_origen_id, cuenta_destino_id, tipo, monto, saldo_anterior, saldo_posterior, descripcion, referencia, estado, aprobado_por, fecha_creacion, fecha_aprobacion)
                        VALUES (?, ?, 'transferencia_recibida', ?, ?, ?, ?, ?, 'aprobada', ?, NOW(), NOW())
                    ");
                    $stmt->execute([$mi_cuenta_id, $cuenta_destino['id'], $monto, $saldoAnteriorDestino, $saldoPosteriorDestino, $descripcion, $referencia . '-R', $user['id']]);
                    
                    logAudit($user['id'], 'TRANSFERENCIA', 'transacciones', "Ref: $referencia, A: Cuenta $numero_cuenta, Monto: $monto");
                    
                    $db->commit();
                    
                    setFlashMessage('success', 'Transferencia por ' . formatMoney($monto) . ' enviada exitosamente.');
                    redirect('/dashboard/');
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Error en la transacción: ' . $e->getMessage();
                }
            }
        }
    }
}

$csrfToken = generateCSRFToken();
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="grid-2">
    <div>
        <div class="card mb-3 fade-in">
            <div class="card-header">
                <h3>Transferencia Directa</h3>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error mb-2"><i class="fa-solid fa-xmark"></i> <?= sanitize($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                
                <div class="form-group mb-2">
                    <label for="numero_cuenta">Número de Cuenta Destino *</label>
                    <div class="input-group">
                        <span class="input-prefix"><i class="fa-solid fa-address-card"></i></span>
                        <input type="text" id="numero_cuenta" name="numero_cuenta" class="form-control" 
                               placeholder="Ej. OB-00000001" required 
                               value="<?= sanitize($_POST['numero_cuenta'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group mb-2">
                    <label for="monto">Monto a Transferir (<?= APP_CURRENCY ?>) *</label>
                    <div class="input-group">
                        <span class="input-prefix"><?= APP_CURRENCY_SYMBOL ?></span>
                        <input type="number" id="monto" name="monto" class="form-control" 
                               step="0.01" min="1" max="<?= $user['saldo'] ?>" 
                               placeholder="0.00" required>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="descripcion">Concepto / Mensaje (opcional)</label>
                    <input type="text" id="descripcion" name="descripcion" class="form-control" 
                           placeholder="Ej. Te pago la mitad del material" maxlength="100">
                </div>
                
                <button type="submit" class="btn btn-info btn-block" style="background:var(--color-info);color:#fff;"
                        onclick="return confirmAction('¿Confirmas que deseas enviar esta transferencia? La acción es inmediata e irreversible.')"
                        <?= $user['saldo'] <= 0 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
                    <i class="fa-solid fa-rotate"></i> Enviar Transferencia
                </button>
            </form>
        </div>
    </div>
    
    <div>
        <div class="card bg-info fade-in" style="animation-delay: 0.1s; background: var(--color-success-bg); border-color: rgba(16, 185, 129, 0.2);">
            <div class="card-header border-0">
                <h3 class="text-success"><i class="fa-solid fa-bolt"></i> Transferencias Flash</h3>
            </div>
            <div style="padding: 0 24px 24px; color: var(--text-secondary); line-height: 1.8;">
                <p>Las transferencias de fondos entre miembros del grupo son <strong>Instantáneas</strong> y <strong>no requieren aprobación del administrador.</strong></p>
                
                <ul style="margin-top: 16px; margin-left: 16px; list-style-type: square;">
                    <li class="mb-1">El monto se descuenta de tu saldo de inmediato.</li>
                    <li class="mb-1">El saldo del destinatario se incrementa de inmediato.</li>
                    <li class="text-warning font-weight-bold">Esta acción no se puede deshacer. Verifica bien a quién le envías el dinero.</li>
                </ul>
            </div>
        </div>
        
        <div class="stat-card mt-3 fade-in" style="animation-delay: 0.2s;">
            <div class="stat-icon blue"><i class="fa-solid fa-sack-dollar"></i></div>
            <div class="stat-label">Tu saldo disponible</div>
            <div class="stat-value money"><?= formatMoney($user['saldo']) ?></div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
