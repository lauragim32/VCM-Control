<?php
session_start();
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'Gerencia') {
    header('Location: ../index.php');
    exit;
}

$periodoFiltro = isset($_GET['periodo']) ? trim($_GET['periodo']) : '';

$stmtTotal = $pdo->query("SELECT SUM(monto_impuesto) as total FROM impuesto_pago");
$totalImpuestos = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmtConteo = $pdo->query("SELECT COUNT(*) as cantidad FROM impuesto_pago");
$conteoImpuestos = $stmtConteo->fetch(PDO::FETCH_ASSOC)['cantidad'] ?? 0;

if (!empty($periodoFiltro)) {
    $stmt = $pdo->prepare("SELECT i.*, u.username FROM impuesto_pago i JOIN usuarios u ON i.id_usuario = u.id_usuario WHERE i.periodo LIKE :periodo ORDER BY i.fecha_registro_impuesto DESC");
    $stmt->execute(['periodo' => "%$periodoFiltro%"]);
} else {
    $stmt = $pdo->prepare("SELECT i.*, u.username FROM impuesto_pago i JOIN usuarios u ON i.id_usuario = u.id_usuario ORDER BY i.fecha_registro_impuesto DESC");
    $stmt->execute();
}
$impuestos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtResumen = $pdo->query("SELECT tipo_impuesto, SUM(monto_impuesto) as subtotal, COUNT(*) as cantidad FROM impuesto_pago GROUP BY tipo_impuesto");
$resumenTipos = $stmtResumen->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>VCM Control - Reportes Gerenciales</title>
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

        /* Tarjetas Métricas (KPIs estilo Figma) */
        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: var(--card-bg); padding: 20px; border-radius: 10px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .kpi-card .kpi-title { font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px; }
        .kpi-card .kpi-value { font-size: 26px; font-weight: bold; color: var(--text-main); margin: 10px 0 5px; }
        .kpi-card .kpi-desc { font-size: 12px; color: var(--text-muted); }

        /* Tablas y Secciones */
        .grid-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .card { background: var(--card-bg); padding: 20px; border-radius: 10px; border: 1px solid var(--border-color); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .card-header h3 { margin: 0; font-size: 16px; color: var(--text-main); }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 13px; }
        th { background: #f8fafc; color: var(--text-muted); font-weight: 600; }
        td { color: var(--text-main); }
        
        .badge-status { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        
        .btn-action { background: #0f172a; color: white; border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-action:hover { background: #1e293b; }
        .logout-link { color: #ef4444; text-decoration: none; font-size: 13px; font-weight: 600; margin-left: 15px; }

        @media print {
            .sidebar, .topbar, .no-print { display: none !important; }
            body { background: white; height: auto; overflow: visible; }
            .main-content { padding: 0; }
            .grid-layout { display: block; }
            .card { border: none; box-shadow: none; padding: 0; }
        }
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
                <li><a href="#" class="active">📊 Reportes Gerenciales</a></li>
                <li><a href="../contabilidad/dashboard.php">💼 Módulo Contable</a></li>
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
                <input type="text" placeholder="Buscar proyectos, movimientos o empleados..." disabled>
            </div>
            <div class="user-profile">
                <div class="user-avatar">LV</div>
                <div class="user-info">
                    <span>Laura Vargas</span>
                    <small>Gerencia</small>
                </div>
                <a href="../logout.php" class="logout-link">Salir</a>
            </div>
        </div>

        <!-- Cuerpo -->
        <div class="content-body">
            <div class="page-header">
                <div>
                    <h1>Reportes Gerenciales</h1>
                    <p>Revisa reportes financieros y operativos preparados para gerencia.</p>
                </div>
                <button class="btn-action no-print" onclick="window.print()">🖨️ Exportar / Imprimir PDF</button>
            </div>

            <!-- KPIs Estilo Figma -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-title">Reportes Disponibles</div>
                    <div class="kpi-value"><?= $conteoImpuestos ?></div>
                    <div class="kpi-desc">Datos del período seleccionado</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Actualizados</div>
                    <div class="kpi-value"><?= $conteoImpuestos ?></div>
                    <div class="kpi-desc">Sincronizados en tiempo real</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Total Pasivos (Bs.)</div>
                    <div class="kpi-value">Bs. <?= number_format($totalImpuestos, 2, ',', '.') ?></div>
                    <div class="kpi-desc">Monto global acumulado</div>
                </div>
            </div>

            <!-- Grids de Contenido -->
            <div class="grid-layout">
                <!-- Tabla Principal -->
                <div class="card">
                    <div class="card-header">
                        <h3>Registros de Reportes Gerenciales</h3>
                        <span style="font-size: 12px; color: var(--text-muted);"><?= $conteoImpuestos ?> registros en el sistema</span>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>REPORTE / TIPO</th>
                                <th>PERÍODO / COBERTURA</th>
                                <th>MONTO (BS.)</th>
                                <th>ESTADO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($impuestos) > 0): ?>
                                <?php foreach ($impuestos as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['tipo_impuesto']) ?></strong><br><small style="color:var(--text-muted)">Reg. por: <?= htmlspecialchars($row['username']) ?></small></td>
                                        <td><?= htmlspecialchars($row['periodo']) ?></td>
                                        <td><strong>Bs. <?= number_format($row['monto_impuesto'], 2, ',', '.') ?></strong></td>
                                        <td><span class="badge-status">Actualizado</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-muted);">No hay reportes registrados actualmente.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Resumen Lateral -->
                <div class="card">
                    <div class="card-header">
                        <h3>Resumen por Categoría</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th>Total (Bs.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($resumenTipos) > 0): ?>
                                <?php foreach ($resumenTipos as $res): ?>
                                    <tr>
                                        <td><small><?= htmlspecialchars($res['tipo_impuesto']) ?></small></td>
                                        <td><strong>Bs. <?= number_format($res['subtotal'], 2, ',', '.') ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" style="text-align: center; color: var(--text-muted);">Sin datos.</td>
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