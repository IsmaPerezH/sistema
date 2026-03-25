<?php
/**
 * OctaBank - Dashboard Principal
 */
$pageTitle = 'Dashboard';
$pageSubtitle = 'Resumen de tu cuenta';
$currentPage = 'dashboard';

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Obtener últimas 5 transacciones
$stmt = $db->prepare("
    SELECT t.*, 
        CASE 
            WHEN t.tipo = 'transferencia_envio' THEN (SELECT CONCAT(nombre, ' ', apellido) FROM usuarios u JOIN cuentas c ON c.usuario_id = u.id WHERE c.id = t.cuenta_destino_id)
            WHEN t.tipo = 'transferencia_recibida' THEN (SELECT CONCAT(nombre, ' ', apellido) FROM usuarios u JOIN cuentas c ON c.usuario_id = u.id WHERE c.id = t.cuenta_origen_id)
            ELSE '-'
        END as contraparte
    FROM transacciones t
    WHERE (t.cuenta_origen_id = (SELECT id FROM cuentas WHERE usuario_id = ?) AND t.tipo != 'transferencia_recibida')
       OR (t.cuenta_destino_id = (SELECT id FROM cuentas WHERE usuario_id = ?) AND t.tipo != 'transferencia_envio')
    ORDER BY t.fecha_creacion DESC LIMIT 5
");
$stmt->execute([$user_id, $user_id]);
$recent_transactions = $stmt->fetchAll();

// Obtener totales
$stmt = $db->prepare("
    SELECT 
        SUM(CASE WHEN tipo IN ('deposito', 'transferencia_recibida') AND estado = 'aprobada' THEN monto ELSE 0 END) as total_ingresos,
        SUM(CASE WHEN tipo IN ('retiro', 'transferencia_envio') AND estado = 'aprobada' THEN monto ELSE 0 END) as total_egresos
    FROM transacciones t
    WHERE (t.cuenta_origen_id = (SELECT id FROM cuentas WHERE usuario_id = ?) AND t.tipo != 'transferencia_recibida')
       OR (t.cuenta_destino_id = (SELECT id FROM cuentas WHERE usuario_id = ?) AND t.tipo != 'transferencia_envio')
");
$stmt->execute([$user_id, $user_id]);
$totals = $stmt->fetch();

?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-sack-dollar"></i></div>
        <div class="stat-label">Saldo Disponible</div>
        <div class="stat-value money"><?= formatMoney($user['saldo'] ?? 0) ?></div>
        <?php if (($user['saldo_retenido'] ?? 0) > 0): ?>
        <div class="form-text text-warning mt-1">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= formatMoney($user['saldo_retenido']) ?> en proceso
        </div>
        <?php endif; ?>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-arrow-trend-up"></i></div>
        <div class="stat-label">Total Ingresos</div>
        <div class="stat-value text-success"><?= formatMoney($totals['total_ingresos'] ?? 0) ?></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon red"><i class="fa-solid fa-arrow-trend-down"></i></div>
        <div class="stat-label">Total Egresos</div>
        <div class="stat-value text-error"><?= formatMoney($totals['total_egresos'] ?? 0) ?></div>
    </div>
</div>



<div class="quick-actions">
    <a href="<?= BASE_URL ?>/transacciones/depositar.php" class="quick-action-card">
        <div class="action-icon" style="background:var(--color-success-bg);color:var(--color-success);"><i class="fa-solid fa-money-bill-wave"></i></div>
        <div class="action-text">
            <h4>Depositar</h4>
            <p>Abonar a mi cuenta</p>
        </div>
    </a>
    
    <a href="<?= BASE_URL ?>/transacciones/transferir.php" class="quick-action-card">
        <div class="action-icon" style="background:var(--color-info-bg);color:var(--color-info);"><i class="fa-solid fa-rotate"></i></div>
        <div class="action-text">
            <h4>Transferir</h4>
            <p>Enviar a compañero</p>
        </div>
    </a>
    
    <a href="<?= BASE_URL ?>/transacciones/retirar.php" class="quick-action-card">
        <div class="action-icon" style="background:var(--color-error-bg);color:var(--color-error);"><i class="fa-solid fa-money-check-dollar"></i></div>
        <div class="action-text">
            <h4>Retirar</h4>
            <p>Sacar fondos</p>
        </div>
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Últimos Movimientos</h3>
        <a href="<?= BASE_URL ?>/transacciones/historial.php" class="btn btn-outline btn-sm">Ver todos</a>
    </div>
    
    <?php if (empty($recent_transactions)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fa-regular fa-file-lines"></i></div>
            <h4>No hay movimientos</h4>
            <p>Aún no has realizado ninguna transacción.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Monto</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_transactions as $t): 
                        // Determinar color y prefijo según tipo y si es origen o destino
                        $is_sender = ($t['tipo'] === 'retiro' || $t['tipo'] === 'transferencia_envio');
                        $amount_class = $is_sender ? 'text-error' : 'text-success';
                        $amount_prefix = $is_sender ? '-' : '+';
                        
                        // Icono por tipo
                        $icon = '<i class="fa-regular fa-file"></i>';
                        if ($t['tipo'] == 'deposito') $icon = '<i class="fa-solid fa-money-bill-wave"></i>';
                        if ($t['tipo'] == 'retiro') $icon = '<i class="fa-solid fa-money-check-dollar"></i>';
                        if (strpos($t['tipo'], 'transferencia') !== false) $icon = '<i class="fa-solid fa-rotate"></i>';
                        
                        // Estado
                        $status_class = [
                            'aprobada' => 'badge-success',
                            'pendiente' => 'badge-warning',
                            'rechazada' => 'badge-error'
                        ][$t['estado']];
                    ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= formatDate($t['fecha_creacion'], 'd M, Y H:i') ?></td>
                        <td>
                            <div class="d-flex align-center gap-1">
                                <span class="transaction-icon <?= sanitize(explode('_', $t['tipo'])[0]) ?>"><?= $icon ?></span>
                                <span style="text-transform:capitalize;">
                                    <?= str_replace('_', ' ', sanitize($t['tipo'])) ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <?= sanitize($t['descripcion'] ?: 'Sin descripción') ?>
                            <?php if ($t['contraparte'] !== '-'): ?>
                                <br><small class="text-muted">Con: <?= sanitize($t['contraparte']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="<?= $amount_class ?> font-weight-bold">
                            <?= $amount_prefix ?><?= formatMoney($t['monto']) ?>
                        </td>
                        <td>
                            <span class="badge <?= $status_class ?>">
                                <?= ucfirst(sanitize($t['estado'])) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
