<?php
session_start();
require_once '../conexion.php';

// Validar que sea el usuario de Contabilidad
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'Contabilidad') {
    header('Location: ../index.php');
    exit;
}

$mensaje = '';

// 1. Lógica para Registrar o Editar Impuesto (Create / Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_impuesto = trim($_POST['tipo_impuesto']);
    $periodo = trim($_POST['periodo']);
    $monto = floatval($_POST['monto_impuesto']);
    $id_usuario = $_SESSION['usuario_id'];
    
    if (isset($_POST['id_impuesto']) && !empty($_POST['id_impuesto'])) {
        // Actualizar (Update)
        $id_impuesto = $_POST['id_impuesto'];
        $stmt = $pdo->prepare("UPDATE impuesto_pago SET tipo_impuesto = :tipo, periodo = :periodo, monto_impuesto = :monto WHERE id_impuesto = :id");
        $stmt->execute(['tipo' => $tipo_impuesto, 'periodo' => $periodo, 'monto' => $monto, 'id' => $id_impuesto]);
        $mensaje = "¡Impuesto actualizado con éxito!";
    } else {
        // Insertar (Create) con estado = 1 (Activo)
        $stmt = $pdo->prepare("INSERT INTO impuesto_pago (id_usuario, tipo_impuesto, periodo, monto_impuesto, estado) VALUES (:usuario, :tipo, :periodo, :monto, 1)");
        $stmt->execute(['usuario' => $id_usuario, 'tipo' => $tipo_impuesto, 'periodo' => $periodo, 'monto' => $monto]);
        $mensaje = "¡Impuesto registrado con éxito!";
    }
}

// 2. Lógica para BORRADO LÓGICO (Soft Delete)
if (isset($_GET['eliminar'])) {
    $id_impuesto = $_GET['eliminar'];
    // En lugar de DELETE, hacemos UPDATE del estado a 0 (false)
    $stmt = $pdo->prepare("UPDATE impuesto_pago SET estado = 0 WHERE id_impuesto = :id");
    $stmt->execute(['id' => $id_impuesto]);
    header('Location: dashboard.php');
    exit;
}

// 3. Obtener registro para Editar si se solicitó
$impuestoEdit = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM impuesto_pago WHERE id_impuesto = :id AND estado = 1");
    $stmt->execute(['id' => $_GET['editar']]);
    $impuestoEdit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4. Leer todos los registros ACTIVOS (Read - Solo estado = 1)
$stmt = $pdo->prepare("SELECT i.*, u.username FROM impuesto_pago i JOIN usuarios u ON i.id_usuario = u.id_usuario WHERE i.estado = 1 ORDER BY i.fecha_registro_impuesto DESC");
$stmt->execute();
$impuestos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>VCM Control - Módulo Contable</title>
    <style>
        :root {
            --sidebar-bg: #0f172a;
            --sidebar-color: #94a3b8;
            --sidebar-active-bg: #f59e0b;
            --sidebar-active-color: #0f172a;
            --main-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--main-bg); margin: 0; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar Estilo Figma */
        .sidebar { width: 260px; background: var(--sidebar-bg); color: white; display: flex; flex-direction: column; justify-content: space-between; padding: 20px; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .sidebar-brand .logo-box { background: #f59e0b; color: #0f172a; font-weight: bold; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 16px; }
        .sidebar-brand h2 { font-size: 16px; margin: 0; color: #ffffff; }
        .sidebar-brand small { font-size: 11px; color: var(--sidebar-color); display: block; }

        .menu-label { font-size: 11px; text-transform: uppercase; color: var(--sidebar-color); letter-spacing: 1px; margin-bottom: 10px; font-weight: 600; }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; flex: 1; }
        .sidebar-menu li { margin-bottom: 5px; }
        .sidebar-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; color: var(--sidebar-color); text-decoration: none; border-radius: 6px; font-size: 14px; transition: 0.2s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: var(--sidebar-active-bg); color: var(--sidebar-active-color); font-weight: 600; }

        .sidebar-footer-card { background: rgba(255,255,255,0.05); padding: 12px; border-radius: 8px; font-size: 12px; color: var(--sidebar-color); }
        .sidebar-footer-card progress { width: 100%; height: 6px; margin-top: 8px; accent-color: #f59e0b; }

        /* Contenido Principal */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        
        /* Topbar Estilo Figma */
        .topbar { background: var(--card-bg); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); }
        .search-box { display: flex; align-items: center; background: #f1f5f9; padding: 8px 15px; border-radius: 8px; width: 350px; gap: 10px; }
        .search-box input { border: none; background: transparent; outline: none; width: 100%; font-size: 14px; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .user-avatar { background: #e2e8f0; color: var(--text-main); font-weight: bold; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .user-info span { display: block; font-size: 14px; font-weight: 600; color: var(--text-main); }
        .user-info small { font-size: 12px; color: var(--text-muted); }

        /* Contenedor de la Vista */
        .content-body { padding: 30px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h1 { margin: 0; font-size: 24px; color: var(--text-main); }
        .page-header p { margin: 5px 0 0; color: var(--text-muted); font-size: 14px; }

        .container-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
        .card { background: var(--card-bg); padding: 20px; border-radius: 10px; border: 1px solid var(--border-color); }
        .card h3 { margin-top: 0; font-size: 16px; color: var(--text-main); margin-bottom: 15px; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: var(--text-main); font-size: 13px; font-weight: 600; }
        .form-group input, .form-group select { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: #f59e0b; }

        button { background: #0f172a; color: white; border: none; padding: 10px 15px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; width: 100%; }
        button:hover { background: #1e293b; }

        .btn-warning { background: #f59e0b; color: #0f172a; padding: 5px 10px; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block; }
        .btn-danger { background: #ef4444; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block; }
        .btn-warning:hover { background: #d97706; }
        .btn-danger:hover { background: #dc2626; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 13px; }
        th { background: #f8fafc; color: var(--text-muted); font-weight: 600; }
        td { color: var(--text-main); }

        .alert { background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; border: 1px solid #bbf7d0; }
        .logout-link { color: #ef4444; text-decoration: none; font-size: 13px; font-weight: 600; margin-left: 15px; }
    </style>
</head>
<body>

    <!-- Sidebar Estilo Figma -->
    <div class="sidebar">
        <div>
            <div class="sidebar-brand">
                <div class="logo-box">VC</div>
                <div>
                    <h2>VCM Control</h2>
                    <small>Gestión administrativa</small>
                </div>
            </div>

            <div class="menu-label">Menú Principal</div>
            <ul class="sidebar-menu">
                <li><a href="../gerencia/dashboard.php">📊 Reportes Gerenciales</a></li>
                <li><a href="dashboard.php" class="active">💼 Módulo Contable</a></li>
            </ul>
        </div>

        <div class="sidebar-footer-card">
            <span>Cierre mensual</span>
            <small style="display:block; color:#f59e0b; margin-top:4px;">Faltan 7 días</small>
            <progress value="75" max="100"></progress>
        </div>
    </div>

    <!-- Contenido Principal -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div class="search-box">
                <span>🔍</span>
                <input type="text" placeholder="Buscar registros fiscales o impuestos..." disabled>
            </div>
            <div class="user-profile">
                <div class="user-avatar">C</div>
                <div class="user-info">
                    <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <small>Contabilidad</small>
                </div>
                <a href="../logout.php" class="logout-link">Salir</a>
            </div>
        </div>

        <!-- Cuerpo -->
        <div class="content-body">
            <div class="page-header">
                <div>
                    <h1>Módulo Contable: Ingresar Impuestos</h1>
                    <p>Registro de obligaciones tributarias (IVA, ISLR y Retenciones).</p>
                </div>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>

            <div class="container-grid">
                <!-- Formulario de Creación / Edición -->
                <div class="card">
                    <h3><?= $impuestoEdit ? 'Editar Impuesto Fiscal' : 'Registrar Nuevo Impuesto' ?></h3>
                    <form method="POST">
                        <?php if ($impuestoEdit): ?>
                            <input type="hidden" name="id_impuesto" value="<?= $impuestoEdit['id_impuesto'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label>Tipo de Impuesto</label>
                            <select name="tipo_impuesto" required>
                                <option value="">Seleccione...</option>
                                <option value="IVA Débito Fiscal" <?= ($impuestoEdit && $impuestoEdit['tipo_impuesto'] == 'IVA Débito Fiscal') ? 'selected' : '' ?>>IVA Débito Fiscal</option>
                                <option value="IVA Crédito Fiscal" <?= ($impuestoEdit && $impuestoEdit['tipo_impuesto'] == 'IVA Crédito Fiscal') ? 'selected' : '' ?>>IVA Crédito Fiscal</option>
                                <option value="Impuesto Sobre la Renta (ISLR)" <?= ($impuestoEdit && $impuestoEdit['tipo_impuesto'] == 'Impuesto Sobre la Renta (ISLR)') ? 'selected' : '' ?>>Impuesto Sobre la Renta (ISLR)</option>
                                <option value="Retención Municipal" <?= ($impuestoEdit && $impuestoEdit['tipo_impuesto'] == 'Retención Municipal') ? 'selected' : '' ?>>Retención Municipal</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Período Fiscal</label>
                            <input type="text" name="periodo" placeholder="Ej: Octubre 2026" value="<?= $impuestoEdit ? htmlspecialchars($impuestoEdit['periodo']) : '' ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Monto del Impuesto (Bs.)</label>
                            <input type="number" step="0.01" name="monto_impuesto" placeholder="0.00" value="<?= $impuestoEdit ? $impuestoEdit['monto_impuesto'] : '' ?>" required>
                        </div>

                        <button type="submit"><?= $impuestoEdit ? 'Actualizar Impuesto' : 'Guardar Impuesto' ?></button>
                        <?php if ($impuestoEdit): ?>
                            <div style="text-align: center; margin-top: 10px;">
                                <a href="dashboard.php" style="color: var(--text-muted); font-size: 13px; text-decoration: none;">Cancelar edición</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Tabla de Historial -->
                <div class="card">
                    <h3>Historial de Pasivos Tributarios</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Período</th>
                                <th>Monto</th>
                                <th>Registrado por</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($impuestos) > 0): ?>
                                <?php foreach ($impuestos as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['tipo_impuesto']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['periodo']) ?></td>
                                        <td><strong>Bs. <?= number_format($row['monto_impuesto'], 2, ',', '.') ?></strong></td>
                                        <td><small><?= htmlspecialchars($row['username']) ?></small></td>
                                        <td>
                                            <a href="dashboard.php?editar=<?= $row['id_impuesto'] ?>" class="btn-warning">Editar</a>
                                            <a href="dashboard.php?eliminar=<?= $row['id_impuesto'] ?>" class="btn-danger" onclick="return confirm('¿Estás segura de eliminar este registro fiscal?');">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-muted);">No hay impuestos registrados en el sistema.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</body>
</html>