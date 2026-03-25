<?php
/**
 * OctaBank - Solicitar Retiro
 */
$pageTitle = 'Retirar Fondos';
$pageSubtitle = 'Solicita un retiro de tu saldo disponible';
$currentPage = 'retirar';

require_once dirname(__DIR__) . '/includes/auth.php';
$user = getCurrentUser();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf)) {
        $error = 'Token de seguridad inválido.';
    } else {
        $monto_crudo = filter_input(INPUT_POST, 'monto', FILTER_VALIDATE_FLOAT);
        $monto = $monto_crudo !== false && $monto_crudo !== null ? round((float)$monto_crudo, 2) : 0;
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        $saldoActual = round((float)$user['saldo'], 2); // Seguridad extra anti-float
        
        // Anti-bypass de F12 (Cantidades negativas o decimales infinitos)
        if ($monto < 1.00) {
            $error = 'El monto debe ser numérico positivo, con un mínimo de ' . formatMoney(1);
        } elseif ($monto > $saldoActual) {
            $error = 'Fondos insuficientes. Tu saldo disponible es ' . formatMoney($saldoActual);
        } else {
            $db = Database::getInstance()->getConnection();
            
            // Obtener ID de la cuenta
            $stmt = $db->prepare("SELECT id FROM cuentas WHERE usuario_id = ?");
            $stmt->execute([$user['id']]);
            $cuenta_id = $stmt->fetchColumn();
            
            try {
                $db->beginTransaction();
                
                $referencia = generateReference();
                
                // Retener saldo inmediatamente para evitar dobles gastos
                $stmt = $db->prepare("UPDATE cuentas SET saldo = saldo - ?, saldo_retenido = saldo_retenido + ? WHERE id = ? AND saldo >= ?");
                $stmt->execute([$monto, $monto, $cuenta_id, $monto]);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception("Error al retener los fondos. Verifica tu saldo.");
                }
                
                // Crear solicitud pendiente
                $stmt = $db->prepare("
                    INSERT INTO transacciones 
                    (cuenta_origen_id, tipo, monto, saldo_anterior, descripcion, referencia, estado, fecha_creacion)
                    VALUES (?, 'retiro', ?, ?, ?, ?, 'pendiente', NOW())
                ");
                $stmt->execute([$cuenta_id, $monto, $saldoActual, $descripcion, $referencia]);
                
                logAudit($user['id'], 'SOLICITUD_RETIRO', 'transacciones', "Ref: $referencia, Monto: $monto");
                
                $db->commit();
                
                setFlashMessage('success', 'Solicitud de retiro enviada. Los fondos han sido retenidos temporalmente. Referencia: ' . $referencia);
                redirect('/dashboard/');
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = $e->getMessage();
                if (DEBUG_MODE && !str_contains($error, 'Error al retener')) {
                     $error = 'Error del sistema: ' . $error;
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
                <h3>Formulario de Retiro</h3>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error mb-2"><i class="fa-solid fa-xmark"></i> <?= sanitize($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                
                <div class="form-group mb-2">
                    <label for="monto">Monto a Retirar (<?= APP_CURRENCY ?>) *</label>
                    <div class="input-group">
                        <span class="input-prefix"><?= APP_CURRENCY_SYMBOL ?></span>
                        <input type="number" id="monto" name="monto" class="form-control" 
                               step="0.01" min="1" max="<?= $user['saldo'] ?>" 
                               placeholder="0.00" required>
                    </div>
                    <div class="form-text mt-1 text-muted">
                        Saldo disponible: <strong><?= formatMoney($user['saldo']) ?></strong>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="descripcion">Motivo (opcional)</label>
                    <input type="text" id="descripcion" name="descripcion" class="form-control" 
                           placeholder="Para qué utilizarás este retiro" maxlength="100">
                </div>
                
                <button type="submit" class="btn btn-danger btn-block" 
                        onclick="return confirmAction('¿Confirmas que deseas solicitar un retiro por este monto?')"
                        <?= $user['saldo'] <= 0 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
                    <i class="fa-solid fa-money-check-dollar"></i> Solicitar Retiro
                </button>
            </form>
        </div>
    </div>
    
    <div>
        <div class="card bg-warning fade-in" style="animation-delay: 0.1s; background: var(--color-warning-bg); border-color: rgba(245, 158, 11, 0.2);">
            <div class="card-header border-0">
                <h3 class="text-warning"><i class="fa-solid fa-triangle-exclamation"></i> Información Importante</h3>
            </div>
            <div style="padding: 0 24px 24px; color: var(--text-secondary); line-height: 1.8;">
                <p class="mb-2">Al solicitar un retiro, los fondos se pondrán inmediatamente en modo <strong>Retenido</strong> para que no puedas gastarlos en otra cosa. El saldo disponible disminuirá enseguida.</p>
                
                <p>El administrador revisará tu solicitud y te entregará el efectivo (o transferencia externa). Una vez que confirmes de recibido, el administrador <strong>Aprobará</strong> la solicitud en el sistema y el saldo retenido se descontará definitivamente.</p>
                
                <p class="mt-2 text-warning font-weight-bold">
                    Si el administrador rechaza el retiro, los fondos regresarán a tu saldo disponible automáticamente.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
