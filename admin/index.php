<?php
/**
 * OctaBank - Dashboard Administrador
 */
$pageTitle = 'Panel de Control';
$pageSubtitle = 'Estadísticas generales del grupo';
$currentPage = 'admin_dashboard';

require_once dirname(__DIR__) . '/includes/auth.php';

// Verificar que sea admin
if (!isAdmin()) {
    setFlashMessage('error', 'No tienes permisos para acceder a esta sección.');
    redirect('/dashboard/');
}

require_once dirname(__DIR__) . '/includes/header.php';
$stats = getSystemStats();

$db = Database::getInstance()->getConnection();

// Traer últimas 5 transacciones pendientes
$stmt = $db->query("
    SELECT t.*, u.nombre, u.apellido, c.numero_cuenta
    FROM transacciones t
    JOIN cuentas c ON t.cuenta_origen_id = c.id OR t.cuenta_destino_id = c.id
    JOIN usuarios u ON c.usuario_id = u.id
    WHERE t.estado = 'pendiente'
    GROUP BY t.id
    ORDER BY t.fecha_creacion ASC LIMIT 5
");
$pendientes = $stmt->fetchAll();

// Datos para la gráfica: Depósitos y Retiros de los últimos 6 meses
$stmt = $db->query("
    SELECT 
        DATE_FORMAT(fecha_creacion, '%Y-%m') as mes,
        SUM(CASE WHEN tipo = 'deposito' THEN monto ELSE 0 END) as ingresos,
        SUM(CASE WHEN tipo = 'retiro' THEN monto ELSE 0 END) as egresos
    FROM transacciones
    WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 5 MONTH) AND estado = 'aprobada'
    GROUP BY mes
    ORDER BY mes ASC
");
$chartData = $stmt->fetchAll();

$meses = [];
$ingresos = [];
$egresos = [];

// Para asegurar que si no hay datos, de todos modos se vea bien
if (empty($chartData)) {
    $meses = [date('M Y')];
    $ingresos = [0];
    $egresos = [0];
} else {
    foreach ($chartData as $d) {
        $meses[] = date('M Y', strtotime($d['mes'] . '-01'));
        $ingresos[] = (float)$d['ingresos'];
        $egresos[] = (float)$d['egresos'];
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-building-columns"></i></div>
        <div class="stat-label">Fondos Totales (Banco)</div>
        <div class="stat-value money"><?= formatMoney($stats['balance_total']) ?></div>
        <div class="form-text text-muted mt-1">Suma de todas las cuentas activas</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-arrow-trend-up"></i></div>
        <div class="stat-label">Ingresos de Hoy</div>
        <div class="stat-value text-success">+ <?= formatMoney($stats['depositos_hoy']) ?></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple" style="background:rgba(139, 92, 246, 0.1);color:#8b5cf6;"><i class="fa-solid fa-users"></i></div>
        <div class="stat-label">Miembros Activos</div>
        <div class="stat-value" style="color:#a78bfa;"><?= number_format($stats['total_miembros']) ?></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="stat-label">Trámites Pendientes</div>
        <div class="stat-value text-warning"><?= number_format($stats['pendientes']) ?></div>
    </div>
</div>

<div class="grid-2">
    <!-- Atajos Rápidos -->
    <div class="quick-actions">
        <a href="<?= BASE_URL ?>/admin/aprobar_transacciones.php" class="quick-action-card">
            <div class="action-icon" style="background:var(--color-warning-bg);color:var(--color-warning);"><i class="fa-solid fa-check"></i></div>
            <div class="action-text">
                <h4>Aprobar Movimientos</h4>
                <p>Revisar depósitos y retiros</p>
            </div>
        </a>
        
        <a href="<?= BASE_URL ?>/admin/miembros.php" class="quick-action-card">
            <div class="action-icon" style="background:var(--color-info-bg);color:var(--color-info);"><i class="fa-solid fa-users"></i></div>
            <div class="action-text">
                <h4>Gestionar Grupo</h4>
                <p>Ver cuentas y saldos</p>
            </div>
        </a>
    </div>

    <!-- Pendientes -->
    <div class="card fade-in" style="animation-delay:0.1s;">
        <div class="card-header">
            <h3>Solcitudes Urgentes</h3>
            <?php if (!empty($pendientes)): ?>
                <a href="<?= BASE_URL ?>/admin/aprobar_transacciones.php" class="btn btn-warning btn-sm">Revisar todas</a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($pendientes)): ?>
            <div class="empty-state">
                <div class="empty-icon text-success"><i class="fa-solid fa-check"></i></div>
                <h4>¡Todo al día!</h4>
                <p>No hay transacciones pendientes de aprobación.</p>
            </div>
        <?php else: ?>
            <table style="font-size: 13px;">
                <tbody>
                    <?php foreach ($pendientes as $p): ?>
                    <tr>
                        <td width="40">
                            <span class="transaction-icon <?= $p['tipo'] === 'deposito' ? 'deposito' : 'retiro' ?>" style="width:32px;height:32px;font-size:14px;">
                                <?= $p['tipo'] === 'deposito' ? '<i class="fa-solid fa-money-bill-wave"></i>' : '<i class="fa-solid fa-money-check-dollar"></i>' ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= sanitize($p['nombre'] . ' ' . $p['apellido']) ?></strong><br>
                            <span class="text-muted"><?= ucfirst(sanitize($p['tipo'])) ?></span>
                        </td>
                        <td class="text-right font-weight-bold <?= $p['tipo'] === 'deposito' ? 'text-success' : 'text-error' ?>">
                            <?= formatMoney($p['monto']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-3 fade-in" style="animation-delay:0.2s;">
    <div class="card-header">
        <h3><i class="fa-solid fa-chart-line"></i> Flujo de Efectivo Global (Últimos 6 meses)</h3>
    </div>
    <div style="position: relative; height: 300px; width: 100%;">
        <canvas id="flujoChart"></canvas>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('flujoChart').getContext('2d');
    
    // Configuración para dark mode y gradientes
    let gradientIngresos = ctx.createLinearGradient(0, 0, 0, 400);
    gradientIngresos.addColorStop(0, 'rgba(16, 185, 129, 0.5)'); // Verde success
    gradientIngresos.addColorStop(1, 'rgba(16, 185, 129, 0.0)');
    
    let gradientEgresos = ctx.createLinearGradient(0, 0, 0, 400);
    gradientEgresos.addColorStop(0, 'rgba(239, 68, 68, 0.5)'); // Rojo error
    gradientEgresos.addColorStop(1, 'rgba(239, 68, 68, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($meses) ?>,
            datasets: [
                {
                    label: 'Ingresos Externos (Depósitos)',
                    data: <?= json_encode($ingresos) ?>,
                    borderColor: '#10b981',
                    backgroundColor: gradientIngresos,
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Egresos Externos (Retiros)',
                    data: <?= json_encode($egresos) ?>,
                    borderColor: '#ef4444',
                    backgroundColor: gradientEgresos,
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: '#9ca3af', font: { family: 'Inter' } } }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#9ca3af' }
                },
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#9ca3af' }
                }
            }
        }
    });
});
</script>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
