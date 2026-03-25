<?php
/**
 * OctaBank - Solicitar Depósito
 */
$pageTitle = 'Depositar Fondos';
$pageSubtitle = 'Registra un pago o aportación a tu cuenta';
$currentPage = 'depositar';

require_once dirname(__DIR__) . '/includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf)) {
        $error = 'Token de seguridad inválido.';
    } else {
        $monto_crudo = filter_input(INPUT_POST, 'monto', FILTER_VALIDATE_FLOAT);
        $monto = $monto_crudo !== false && $monto_crudo !== null ? round((float)$monto_crudo, 2) : 0;
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        // Bloqueo estricto anti-negativos y micro-centavos
        if ($monto < 1.00) {
            $error = 'El monto debe ser numérico positivo, con un mínimo de ' . formatMoney(1);
        } else {
            $db = Database::getInstance()->getConnection();
            $user = getCurrentUser();
            
            // Obtener ID de la cuenta
            $stmt = $db->prepare("SELECT id FROM cuentas WHERE usuario_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $cuenta_id = $stmt->fetchColumn();
            
            if (!$cuenta_id) {
                $error = 'No tienes una cuenta bancaria asignada.';
            } else {
                try {
                    $db->beginTransaction();
                    
                    $referencia = generateReference();
                    
                    $stmt = $db->prepare("
                        INSERT INTO transacciones 
                        (cuenta_destino_id, tipo, monto, descripcion, referencia, estado, fecha_creacion)
                        VALUES (?, 'deposito', ?, ?, ?, 'pendiente', NOW())
                    ");
                    $stmt->execute([$cuenta_id, $monto, $descripcion, $referencia]);
                    $txn_id = $db->lastInsertId();
                    
                    logAudit($_SESSION['user_id'], 'SOLICITUD_DEPOSITO', 'transacciones', "Ref: $referencia, Monto: $monto");
                    
                    $db->commit();
                    
                    setFlashMessage('success', 'Solicitud de depósito enviada. Referencia: ' . $referencia . '. Un administrador la revisará.');
                    redirect('/dashboard/');
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Error al procesar la solicitud.';
                    if (DEBUG_MODE) $error .= ' ' . $e->getMessage();
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
                <h3>Formulario de Depósito</h3>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error mb-2"><i class="fa-solid fa-xmark"></i> <?= sanitize($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                
                <div class="form-group mb-2">
                    <label for="monto">Monto a Depositar (<?= APP_CURRENCY ?>) *</label>
                    <div class="input-group">
                        <span class="input-prefix"><?= APP_CURRENCY_SYMBOL ?></span>
                        <input type="number" id="monto" name="monto" class="form-control" 
                               step="0.01" min="1" placeholder="0.00" required>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="descripcion">Descripción (opcional)</label>
                    <input type="text" id="descripcion" name="descripcion" class="form-control" 
                           placeholder="Ej. Cuota mensualidad abril, pago de pizza, etc." maxlength="100">
                </div>
                
                <button type="submit" class="btn btn-success btn-block" onclick="return confirmAction('¿Confirmas que deseas registrar este depósito?')">
                    <i class="fa-solid fa-money-bill-wave"></i> Registrar Depósito
                </button>
            </form>
        </div>
    </div>
    
    <div>
        <div class="card bg-info fade-in" style="animation-delay: 0.1s; background: var(--color-info-bg); border-color: rgba(59, 130, 246, 0.2);">
            <div class="card-header border-0">
                <h3 class="text-info"><i class="fa-solid fa-circle-info"></i> ¿Cómo funciona?</h3>
            </div>
            <div style="padding: 0 24px 24px;">
                <ol style="margin-left: 16px; color: var(--text-secondary); line-height: 1.8;">
                    <li>Entrega el efectivo (o transferencia real) al tesorero/administrador.</li>
                    <li>Registra el monto exacto en este formulario.</li>
                    <li>El estado aparecerá como <span class="badge badge-warning">Pendiente</span>.</li>
                    <li>El administrador validará la recepción del dinero y aprobará la transacción.</li>
                    <li>Tu saldo disponible se actualizará automáticamente.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
