<?php
/**
 * OctaBank - Aprobar Transacciones
 */
$pageTitle = 'Aprobar Movimientos';
$pageSubtitle = 'Rehabilita o rechaza depósitos y retiros';
$currentPage = 'aprobar';

require_once dirname(__DIR__) . '/includes/auth.php';

if (!isAdmin()) {
    redirect('/dashboard/');
}

$db = Database::getInstance()->getConnection();

// Procesar acciones (Aprobar / Rechazar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (verifyCSRFToken($csrf)) {
        $txn_id = filter_input(INPUT_POST, 'txn_id', FILTER_VALIDATE_INT);
        $accion = filter_input(INPUT_POST, 'accion', FILTER_SANITIZE_STRING); // 'aprobar' o 'rechazar'
        
        if ($txn_id && in_array($accion, ['aprobar', 'rechazar'])) {
            try {
                $db->beginTransaction();
                
                // Obtener detalles de la transacción (con bloqueo para concurrencia)
                $stmt = $db->prepare("SELECT * FROM transacciones WHERE id = ? AND estado = 'pendiente' FOR UPDATE");
                $stmt->execute([$txn_id]);
                $txn = $stmt->fetch();
                
                if (!$txn) {
                    throw new Exception("La transacción no existe o ya fue procesada.");
                }
                
                $nuevo_estado = ($accion === 'aprobar') ? 'aprobada' : 'rechazada';
                
                if ($txn['tipo'] === 'deposito') {
                    if ($accion === 'aprobar') {
                        // Incrementar saldo
                        $stmt = $db->prepare("UPDATE cuentas SET saldo = saldo + ? WHERE id = ?");
                        $stmt->execute([$txn['monto'], $txn['cuenta_destino_id']]);
                        
                        // Obtener saldo posterior para historial
                        $stmt2 = $db->prepare("SELECT saldo FROM cuentas WHERE id = ?");
                        $stmt2->execute([$txn['cuenta_destino_id']]);
                        $saldoFinal = $stmt2->fetchColumn();
                        
                        $stmt = $db->prepare("UPDATE transacciones SET estado = ?, aprobado_por = ?, fecha_aprobacion = NOW(), saldo_posterior = ?, saldo_anterior = ? WHERE id = ?");
                        $stmt->execute([$nuevo_estado, $_SESSION['user_id'], $saldoFinal, $saldoFinal - $txn['monto'], $txn_id]);
                    } else {
                        // Rechazar depósito: no se hace nada con el saldo, solo cambia el estado
                        $stmt = $db->prepare("UPDATE transacciones SET estado = ?, aprobado_por = ?, fecha_aprobacion = NOW() WHERE id = ?");
                        $stmt->execute([$nuevo_estado, $_SESSION['user_id'], $txn_id]);
                    }
                    
                    logAudit($_SESSION['user_id'], 'DEPOSITO_' . strtoupper($accion), 'transacciones', "ID: $txn_id, Ref: {$txn['referencia']}");
                    
                } elseif ($txn['tipo'] === 'retiro') {
                    if ($accion === 'aprobar') {
                        // Aprobar retiro (descontar del retenido permanentemente)
                        $stmt = $db->prepare("UPDATE cuentas SET saldo_retenido = saldo_retenido - ? WHERE id = ?");
                        $stmt->execute([$txn['monto'], $txn['cuenta_origen_id']]);
                        
                        $stmt = $db->prepare("UPDATE transacciones SET estado = ?, aprobado_por = ?, fecha_aprobacion = NOW(), saldo_posterior = saldo_anterior - ? WHERE id = ?");
                        $stmt->execute([$nuevo_estado, $_SESSION['user_id'], $txn['monto'], $txn_id]);
                    } else {
                        // Rechazar retiro: Liberar fondos retenidos (regresan al saldo disponible)
                        $stmt = $db->prepare("UPDATE cuentas SET saldo = saldo + ?, saldo_retenido = saldo_retenido - ? WHERE id = ?");
                        $stmt->execute([$txn['monto'], $txn['monto'], $txn['cuenta_origen_id']]);
                        
                        $stmt = $db->prepare("UPDATE transacciones SET estado = ?, aprobado_por = ?, fecha_aprobacion = NOW() WHERE id = ?");
                        $stmt->execute([$nuevo_estado, $_SESSION['user_id'], $txn_id]);
                    }
                    
                    logAudit($_SESSION['user_id'], 'RETIRO_' . strtoupper($accion), 'transacciones', "ID: $txn_id, Ref: {$txn['referencia']}");
                }
                
                $db->commit();
                $msg_tipo = $accion === 'aprobar' ? 'éxito' : 'rechazada';
                setFlashMessage('success', "Transacción $msg_tipo correctamente.");
                redirect('/admin/aprobar_transacciones.php');
                
            } catch (Exception $e) {
                $db->rollBack();
                setFlashMessage('error', $e->getMessage());
            }
        }
    }
}

// Cargar todas las transacciones pendientes
$stmt = $db->query("
    SELECT t.*, 
        u.nombre, u.apellido, c.numero_cuenta
    FROM transacciones t
    JOIN cuentas c ON (t.tipo = 'deposito' AND t.cuenta_destino_id = c.id) 
                   OR (t.tipo = 'retiro' AND t.cuenta_origen_id = c.id)
    JOIN usuarios u ON c.usuario_id = u.id
    WHERE t.estado = 'pendiente'
    ORDER BY t.fecha_creacion ASC
");
$pendientes = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="card fade-in">
    <div class="card-header">
        <h3>Lista de Operaciones por Aprobar</h3>
    </div>
    
    <div class="alert alert-info">
        <i class="fa-solid fa-circle-info"></i> Verifica siempre haber recibido el efectivo antes de aprobar depósitos. Al aprobar retiros, asegúrate de entregar el dinero al compañero primero.
    </div>

    <?php if (empty($pendientes)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fa-solid fa-check"></i></div>
            <h4>Todo al día</h4>
            <p>No hay depósitos ni retiros pendientes por revisar.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Fecha/Ref</th>
                        <th>Tipo</th>
                        <th>Miembro</th>
                        <th>Descripción</th>
                        <th>Monto</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendientes as $p): ?>
                    <tr>
                        <td style="white-space:nowrap;font-size:12px;">
                            <strong><?= formatDate($p['fecha_creacion'], 'd M, Y') ?></strong><br>
                            <span class="text-muted"><?= sanitize(explode('-', $p['referencia'])[1] ?? $p['referencia']) ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $p['tipo'] === 'deposito' ? 'badge-success' : 'badge-error' ?>">
                                <?= ucfirst(sanitize($p['tipo'])) ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= sanitize($p['nombre'] . ' ' . $p['apellido']) ?></strong><br>
                            <span class="text-muted" style="font-size:11px;font-family:monospace;"><?= sanitize($p['numero_cuenta']) ?></span>
                        </td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;">
                            <?= sanitize($p['descripcion'] ?: '--') ?>
                        </td>
                        <td class="font-weight-bold" style="font-size:16px;">
                            <?= formatMoney($p['monto']) ?>
                        </td>
                        <td style="text-align:center;white-space:nowrap;">
                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('¿Aprobar esta transacción por <?= str_replace(',', '', formatMoney($p['monto'])) ?>?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="txn_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="accion" value="aprobar">
                                <button type="submit" class="btn btn-success btn-sm" title="Aprobar"><i class="fa-solid fa-check"></i> Aprobar</button>
                            </form>
                            
                            <form method="POST" style="display:inline-block; margin-left:8px;" onsubmit="return confirm('¿Estás seguro de RECHAZAR esta transacción? Esta acción no se puede deshacer.')">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="txn_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="accion" value="rechazar">
                                <button type="submit" class="btn btn-danger btn-sm text-white" title="Rechazar"><i class="fa-solid fa-xmark"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
