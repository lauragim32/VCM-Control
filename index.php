<?php
session_start();
require_once 'conexion.php';

$mensaje = '';
$exito = '';
$modoRegistro = isset($_GET['accion']) && $_GET['accion'] === 'registrar';

// Lógica de Registro Seguro con Clave Maestra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_usuario'])) {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);
    $rol = intval($_POST['id_rol']); // 1: Gerencia, 2: Contabilidad
    $codigoSeguridad = trim($_POST['codigo_seguridad'] ?? '');
    $claveMaestra = "VCM2026"; 

    if ($codigoSeguridad !== $claveMaestra) {
        $mensaje = "Código de seguridad o clave maestra de autorización incorrecta.";
        $modoRegistro = true;
    } else {
        $stmtCheck = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :user");
        $stmtCheck->execute(['user' => $user]);
        if ($stmtCheck->fetch()) {
            $mensaje = "El nombre de usuario ya está registrado.";
            $modoRegistro = true;
        } else {
            $passEnc = md5($pass);
            $stmtIns = $pdo->prepare("INSERT INTO usuarios (id_rol, username, password, estado) VALUES (:rol, :user, :pass, 1)");
            $stmtIns->execute(['rol' => $rol, 'user' => $user, 'pass' => $passEnc]);
            $exito = "¡Usuario registrado con éxito! Ya puedes iniciar sesión.";
            $modoRegistro = false;
        }
    }
}

// Lógica de Inicio de Sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['registrar_usuario'])) {
    $user = trim($_POST['username']);
    $pass = md5(trim($_POST['password']));
    $rol_seleccionado = intval($_POST['id_rol']);

    // Validamos el usuario, la contraseña y opcionalmente el rol seleccionado
    $stmt = $pdo->prepare("SELECT u.*, r.nombre_rol FROM usuarios u JOIN roles r ON u.id_rol = r.id_rol WHERE u.username = :user AND u.password = :pass AND u.id_rol = :rol");
    $stmt->execute(['user' => $user, 'pass' => $pass, 'rol' => $rol_seleccionado]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $_SESSION['usuario_id'] = $usuario['id_usuario'];
        $_SESSION['username'] = $usuario['username'];
        $_SESSION['rol'] = $usuario['nombre_rol'];

        if ($usuario['nombre_rol'] === 'Gerencia') {
            header('Location: gerencia/dashboard.php');
        } else {
            header('Location: contabilidad/dashboard.php');
        }
        exit;
    } else {
        $mensaje = 'Credenciales incorrectas o el rol seleccionado no coincide con este usuario.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>VCM Control - Acceso al Sistema</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: #ffffff; padding: 35px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); width: 380px; }
        .brand { text-align: center; margin-bottom: 25px; }
        .brand h2 { margin: 0; color: #1e293b; font-size: 24px; }
        .brand span { color: #f59e0b; font-weight: bold; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; }
        .input-group { margin-bottom: 18px; }
        .input-group label { display: block; margin-bottom: 6px; color: #475569; font-size: 13px; font-weight: 600; }
        .input-group input, .input-group select { width: 100%; padding: 10px 12px; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .input-group input:focus, .input-group select:focus { border-color: #f59e0b; }
        button { width: 100%; padding: 12px; background: #f59e0b; color: #0f172a; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; transition: background 0.2s; }
        button:hover { background: #d97706; color: white; }
        .error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; font-size: 13px; text-align: center; margin-bottom: 15px; border: 1px solid #f87171; }
        .success { background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; font-size: 13px; text-align: center; margin-bottom: 15px; border: 1px solid #bbf7d0; }
        .switch-link { margin-top: 20px; font-size: 13px; text-align: center; color: #64748b; }
        .switch-link a { color: #f59e0b; text-decoration: none; font-weight: 600; }
        .switch-link a:hover { text-decoration: underline; }
        .hint { margin-top: 15px; font-size: 11px; color: #94a3b8; text-align: center; line-height: 1.4; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            <h2>VCM Control</h2>
            <span>Sistema de Gestión Administrativa</span>
        </div>

        <?php if ($mensaje): ?>
            <div class="error"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="success"><?= htmlspecialchars($exito) ?></div>
        <?php endif; ?>

        <?php if (!$modoRegistro): ?>
            <!-- Formulario de Inicio de Sesión con Selector de Rol -->
            <form method="POST">
                <div class="input-group">
                    <label>Seleccionar Rol</label>
                    <select name="id_rol" required>
                        <option value="2">Contabilidad</option>
                        <option value="1">Gerencia</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Nombre de Usuario</label>
                    <input type="text" name="username" placeholder="Ej: your name" required>
                </div>
                <div class="input-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit">Acceder al Sistema</button>
            </form>
            <div class="switch-link">
                ¿No tienes cuenta? <a href="index.php?accion=registrar">Regístrate aquí</a>
            </div>
        <?php else: ?>
            <!-- Formulario de Registro Seguro -->
            <form method="POST">
                <input type="hidden" name="registrar_usuario" value="1">
                <div class="input-group">
                    <label>Asignar Rol</label>
                    <select name="id_rol" required>
                        <option value="2">Contabilidad</option>
                        <option value="1">Gerencia</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Nuevo Nombre de Usuario</label>
                    <input type="text" name="username" placeholder="Ej: your name" required>
                </div>
                <div class="input-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <div class="input-group">
                    <label>Código de Seguridad (Clave Maestra)</label>
                    <input type="password" name="codigo_seguridad" placeholder="Autorización requerida" required>
                    <small style="font-size: 10px; color: #64748b; display:block; margin-top:4px;">* Demo: VCM2026</small>
                </div>
                <button type="submit" style="background: #0f172a; color: white;">Crear Cuenta Segura</button>
            </form>
            <div class="switch-link">
                ¿Ya tienes cuenta? <a href="index.php">Iniciar Sesión</a>
            </div>
        <?php endif; ?>

        <div class="hint">
            Vargas Cocinas y Muebles C.A. • Barquisimeto
        </div>
    </div>
</body>
</html>