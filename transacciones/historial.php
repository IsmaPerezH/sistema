<?php
/**
 * OctaBank - Historial de Transacciones
 */
$pageTitle = 'Estado de Cuenta';
$pageSubtitle = 'Historial completo de tus movimientos interbancarios';
$currentPage = 'historial';

require_once dirname(__DIR__) . '/includes/auth.php';
$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

// Traer la cuenta del usuario para las comparaciones
$stmt = $db->prepare("SELECT id FROM cuentas WHERE usuario_id = ?");
$stmt->execute([$user['id']]);
$cuenta_id = $stmt->fetchColumn();

// Paginación y Filtrado simple
$filtro = $_GET['tipo'] ?? 'todos';
$where = "WHERE (t.cuenta_origen_id = :cuenta_origen AND t.tipo != 'transferencia_recibida') OR (t.cuenta_destino_id = :cuenta_destino AND t.tipo != 'transferencia_envio')";

if ($filtro === 'ingresos') {
    $where .= " AND (t.tipo = 'deposito' OR t.tipo = 'transferencia_recibida')";
} elseif ($filtro === 'egresos') {
    $where .= " AND (t.tipo = 'retiro' OR t.tipo = 'transferencia_envio')";
} elseif ($filtro === 'pendientes') {
    $where .= " AND t.estado = 'pendiente'";
}

$sql = "
    SELECT t.*, 
        CASE 
            WHEN t.tipo = 'transferencia_envio' THEN (SELECT CONCAT(nombre, ' ', apellido) FROM usuarios u JOIN cuentas c ON c.usuario_id = u.id WHERE c.id = t.cuenta_destino_id)
            WHEN t.tipo = 'transferencia_recibida' THEN (SELECT CONCAT(nombre, ' ', apellido) FROM usuarios u JOIN cuentas c ON c.usuario_id = u.id WHERE c.id = t.cuenta_origen_id)
            ELSE '-'
        END as contraparte
    FROM transacciones t
    $where
    ORDER BY t.fecha_creacion DESC
    LIMIT 100
";

$stmt = $db->prepare($sql);
$stmt->bindValue(':cuenta_origen', $cuenta_id, PDO::PARAM_INT);
$stmt->bindValue(':cuenta_destino', $cuenta_id, PDO::PARAM_INT);
$stmt->execute();
$transacciones = $stmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- HTML2PDF Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="card fade-in">
    <div class="card-header" style="flex-wrap: wrap; gap: 16px;">
        <h3>Movimientos Recientes</h3>
        <div class="d-flex gap-1" style="flex-wrap: wrap;">
            <a href="?tipo=todos" class="btn btn-sm <?= $filtro === 'todos' ? 'btn-primary' : 'btn-outline' ?>">Todos</a>
            <a href="?tipo=ingresos" class="btn btn-sm <?= $filtro === 'ingresos' ? 'btn-success' : 'btn-outline' ?>">Ingresos</a>
            <a href="?tipo=egresos" class="btn btn-sm <?= $filtro === 'egresos' ? 'btn-danger' : 'btn-outline' ?>">Egresos</a>
            <a href="?tipo=pendientes" class="btn btn-sm <?= $filtro === 'pendientes' ? 'btn-outline' : 'btn-outline' ?>" <?= $filtro === 'pendientes' ? 'style="border-color:var(--color-warning);color:var(--color-warning);"' : '' ?>>Pendientes</a>
            <button onclick="window.print()" class="btn btn-sm btn-outline text-muted"><i class="fa-solid fa-print"></i> Imprimir</button>
            <button onclick="exportarHistorialPDF()" class="btn btn-sm btn-outline"><i class="fa-solid fa-file-pdf text-error"></i> PDF</button>
        </div>
    </div>

    <?php if (empty($transacciones)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fa-regular fa-folder-open"></i></div>
            <h4>No hay datos</h4>
            <p>No se encontraron movimientos para este filtro.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Referencia</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Monto</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transacciones as $t): 
                        // Determinar color de ingreso o egreso
                        $is_sender = ($t['tipo'] === 'retiro' || $t['tipo'] === 'transferencia_envio');
                        $amount_class = $is_sender ? 'text-error' : 'text-success';
                        $amount_prefix = $is_sender ? '-' : '+';
                        
                        // Iconos
                        $icon = '<i class="fa-regular fa-file"></i>';
                        if ($t['tipo'] == 'deposito') $icon = '<i class="fa-solid fa-money-bill-wave"></i>';
                        if ($t['tipo'] == 'retiro') $icon = '<i class="fa-solid fa-money-check-dollar"></i>';
                        if (strpos($t['tipo'], 'transferencia') !== false) $icon = '<i class="fa-solid fa-rotate"></i>';

                        // Labels limpios
                        $type_label = match($t['tipo']) {
                            'deposito' => 'Depósito',
                            'retiro' => 'Retiro',
                            'transferencia_envio' => 'Envío',
                            'transferencia_recibida' => 'Recibido',
                            default => $t['tipo']
                        };
                        
                        // Estado
                        $status_class = match($t['estado']) {
                            'aprobada' => 'badge-success',
                            'pendiente' => 'badge-warning',
                            'rechazada' => 'badge-error',
                            default => 'badge-info'
                        };
                    ?>
                    <tr>
                        <td style="white-space:nowrap;font-size:12px;">
                            <strong><?= formatDate($t['fecha_creacion'], 'd M, Y') ?></strong><br>
                            <span class="text-muted"><?= formatDate($t['fecha_creacion'], 'H:i A') ?></span>
                        </td>
                        <td>
                            <span class="text-muted" style="font-family:monospace;font-size:12px;">
                                <?= sanitize(explode('-', $t['referencia'])[1] ?? $t['referencia']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-center gap-1">
                                <span class="transaction-icon <?= sanitize(explode('_', $t['tipo'])[0]) ?>" style="width:30px;height:30px;font-size:14px;"><?= $icon ?></span>
                                <span><?= $type_label ?></span>
                            </div>
                        </td>
                        <td>
                            <?= sanitize($t['descripcion'] ?: '--') ?>
                            <?php if ($t['contraparte'] !== '-'): ?>
                                <br><small class="text-muted">Con: <?= sanitize($t['contraparte']) ?></small>
                            <?php endif; ?>
                            <?php if ($t['estado'] === 'rechazada'): ?>
                                <br><small class="text-error font-weight-bold">Rechazada por el administrador</small>
                            <?php endif; ?>
                        </td>
                        <td class="<?= $amount_class ?> font-weight-bold" style="white-space:nowrap;">
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
            
            <p class="text-center text-muted mt-2" style="font-size:12px;">Mostrando los últimos 100 movimientos</p>
        </div>
    <?php endif; ?>
</div>

<style>
@media print {
    body { background: white !important; color: black !important; }
    .sidebar, .top-header, .header-actions, .mobile-toggle, .btn { display: none !important; }
    .main-content { margin: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
    table { width: 100% !important; border-collapse: collapse !important; }
    th, td { border-bottom: 1px solid #ddd !important; padding: 8px !important; }
    @page { margin: 2cm; size: auto; }
}
</style>

<script>
function exportarHistorialPDF() {
    const btn = event.currentTarget;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>...';
    btn.disabled = true;
    
    var opt = {
        margin:       10,
        filename:     'Estado_Cuenta_Octabank.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2 },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    
    var tableContainer = document.querySelector('.table-container').cloneNode(true);
    var printContent = document.createElement('div');
    printContent.innerHTML = `
        <div style="text-align:center; padding-bottom:15px; border-bottom:1px solid #ddd; margin-bottom:15px;">
            <h2 style="font-family:sans-serif; margin:0;">OctaBank</h2>
            <p style="font-family:sans-serif; margin:5px 0 0 0; color:#555;">Estado de Cuenta - <?= formatMoney($user['saldo']) ?></p>
            <p style="font-family:sans-serif; margin:5px 0 0 0; font-size:12px; color:#777;">Emitido: <?= date('d/m/Y H:i') ?></p>
        </div>
    ` + tableContainer.outerHTML;
    
    html2pdf().set(opt).from(printContent).save().then(function() {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
