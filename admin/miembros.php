<?php
/**
 * OctaBank - Gestión de Miembros
 */
$pageTitle = 'Directorio de Miembros';
$pageSubtitle = 'Administra los usuarios del sistema y sus saldos';
$currentPage = 'miembros';

require_once dirname(__DIR__) . '/includes/auth.php';

if (!isAdmin()) {
    redirect('/dashboard/');
}

$db = Database::getInstance()->getConnection();

// Lógica para suspender/activar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verifyCSRFToken($csrf)) {
        $usuario_id = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);
        $nuevo_estado = filter_input(INPUT_POST, 'nuevo_estado', FILTER_VALIDATE_INT);
        
        if ($usuario_id && $usuario_id != $_SESSION['user_id']) {
            $estado_str = $nuevo_estado ? 'activa' : 'suspendida';
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
                $stmt->execute([$nuevo_estado, $usuario_id]);
                
                $stmt = $db->prepare("UPDATE cuentas SET estado = ? WHERE usuario_id = ?");
                $stmt->execute([$estado_str, $usuario_id]);
                
                logAudit($_SESSION['user_id'], 'TOGGLE_USUARIO', 'usuarios', "Usuario $usuario_id cambiado a estado $nuevo_estado");
                $db->commit();
                setFlashMessage('success', 'Estado del miembro actualizado correctamente.');
                redirect('/admin/miembros.php');
            } catch (Exception $e) {
                $db->rollBack();
                setFlashMessage('error', 'Error al actualizar: ' . $e->getMessage());
            }
        }
    }
}


// Traer lista completa de miembros con cuentas
$stmt = $db->query("
    SELECT u.id, u.nombre, u.apellido, u.email, u.telefono, u.rol, u.activo, u.fecha_registro, u.ultimo_acceso,
           c.numero_cuenta, c.saldo, c.saldo_retenido, c.estado as estado_cuenta
    FROM usuarios u
    JOIN cuentas c ON u.id = c.usuario_id
    ORDER BY c.saldo DESC, u.nombre ASC
");
$miembros = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- HTML2PDF Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="card fade-in">
    <div class="card-header" style="flex-wrap: wrap; gap: 10px;">
        <h3>Todos los Miembros del Grupo</h3>
        <div>
            <span class="badge badge-info text-white mr-2"><?= count($miembros) ?> Cuentas</span>
            <button onclick="exportarPDF()" class="btn btn-sm btn-outline"><i class="fa-solid fa-file-pdf text-error"></i> Exportar PDF</button>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Miembro</th>
                    <th>Contacto</th>
                    <th>Cuenta</th>
                    <th style="text-align:right;">Saldo Disp.</th>
                    <th style="text-align:right;">Retenido</th>
                    <th>Estado</th>
                    <th style="text-align:center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($miembros as $m): ?>
                <tr>
                    <td>
                        <div class="d-flex align-center gap-1">
                            <span class="transaction-icon" style="background:var(--accent-gradient);color:white;width:32px;height:32px;">
                                <?= substr(sanitize($m['nombre']), 0, 1) ?><?= substr(sanitize($m['apellido']), 0, 1) ?>
                            </span>
                            <div>
                                <strong><?= sanitize($m['nombre'] . ' ' . $m['apellido']) ?></strong>
                                <?php if ($m['rol'] === 'admin'): ?>
                                    <span class="badge badge-info" style="font-size:10px;padding:2px 6px;">Admin</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12px;">
                        <div><i class="fa-regular fa-envelope"></i> <?= sanitize($m['email']) ?></div>
                        <?php if ($m['telefono']): ?>
                        <div class="text-muted mt-1"><i class="fa-solid fa-mobile-screen"></i> <?= sanitize($m['telefono']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="font-family:monospace;font-size:13px;"><?= sanitize($m['numero_cuenta']) ?></span>
                        <div class="text-muted" style="font-size:11px;">
                            Registrado: <?= formatDate($m['fecha_registro'], 'd M, Y') ?>
                        </div>
                    </td>
                    <td class="text-right text-success font-weight-bold">
                        <?= formatMoney($m['saldo']) ?>
                    </td>
                    <td class="text-right text-warning">
                        <?= $m['saldo_retenido'] > 0 ? formatMoney($m['saldo_retenido']) : '--' ?>
                    </td>
                    <td>
                        <?php if ($m['activo']): ?>
                            <span class="badge badge-success">Activo</span>
                        <?php else: ?>
                            <span class="badge badge-error">Suspendido</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center; padding: 4px;">
                        <?php if ($m['id'] !== $_SESSION['user_id']): ?>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('¿<?= $m['activo'] ? 'Suspender' : 'Activar' ?> la cuenta de este usuario?')">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="toggle_status" value="1">
                            <input type="hidden" name="usuario_id" value="<?= $m['id'] ?>">
                            <input type="hidden" name="nuevo_estado" value="<?= $m['activo'] ? '0' : '1' ?>">
                            <button type="submit" class="btn btn-sm <?= $m['activo'] ? 'btn-danger' : 'btn-success' ?>" style="padding:4px 8px; font-size:11px;" title="<?= $m['activo'] ? 'Suspender' : 'Activar' ?>">
                                <i class="fa-solid <?= $m['activo'] ? 'fa-ban' : 'fa-check' ?>"></i>
                            </button>
                        </form>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:11px;">Administrador</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card bg-info mt-3 fade-in no-print" style="animation-delay: 0.2s; background: rgba(99, 102, 241, 0.05);">
    <div class="card-header border-0 pb-0">
        <h3 class="text-info"><i class="fa-solid fa-file-pdf"></i> Panel de Exportación</h3>
    </div>
    <div style="padding: 16px 24px 24px; color: var(--text-secondary); font-size:13px;">
        Haz clic en "Exportar PDF" en la parte superior para generar un reporte contable de todo el grupo y sus saldos. El documento PDF se generará localmente en tu navegador sin necesidad de internet adicional. Las cuentas suspendidas aparecen indicadas con su estado.
    </div>
</div>

<script>
function exportarPDF() {
    const btn = event.currentTarget;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generando...';
    btn.disabled = true;
    
    // Configuración para el tamaño y calidad del PDF
    var opt = {
        margin:       10,
        filename:     'Directorio_Octabank.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2 },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
    };
    
    // Clonar la tabla para remover temporalmente la columna de acciones
    var tableContainer = document.querySelector('.table-container').cloneNode(true);
    var rows = tableContainer.querySelectorAll('tr');
    rows.forEach(row => {
        if(row.children.length > 5) row.removeChild(row.lastElementChild); // Remover 'Acciones'
    });
    
    // Contenedor temporal para generar el PDF
    var printContent = document.createElement('div');
    printContent.innerHTML = '<h2 style="font-family:sans-serif; text-align:center; padding-bottom:10px;">OctaBank - Directorio de Cuentas y Saldos</h2>' + tableContainer.outerHTML;
    
    html2pdf().set(opt).from(printContent).save().then(function() {
        btn.innerHTML = '<i class="fa-solid fa-file-pdf text-error"></i> Exportar PDF';
        btn.disabled = false;
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
