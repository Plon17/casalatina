<?php
// Cualquier usuario logueado (Empleado o Administrador) puede cambiar su propia
// contraseña aquí. No gestiona la de nadie más — para eso está Usuarios (solo Admin).
$modulo_actual = "perfil";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auditoria.php";

$mensaje = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "cambiar_contrasena") {
    $actual = $_POST["contrasena_actual"] ?? "";
    $nueva = $_POST["contrasena_nueva"] ?? "";
    $confirmar = $_POST["contrasena_confirmar"] ?? "";

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE ID_usuario = ?");
    $stmt->execute([$_SESSION["ID_usuario"]]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Igual que en login.php: password_verify() es lo normal; la comparación directa
    // es solo respaldo por si alguna cuenta vieja todavía tuviera clave sin encriptar.
    $actualCorrecta = $usuario && (password_verify($actual, $usuario["contrasena"]) || $actual === $usuario["contrasena"]);

    if (!$actualCorrecta) {
        $error = "La contraseña actual no es correcta.";
    } elseif (strlen($nueva) < 6) {
        $error = "La nueva contraseña debe tener al menos 6 caracteres.";
    } elseif ($nueva !== $confirmar) {
        $error = "La confirmación no coincide con la nueva contraseña.";
    } elseif ($actual === $nueva) {
        $error = "La nueva contraseña debe ser distinta a la actual.";
    } else {
        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE usuarios SET contrasena=? WHERE ID_usuario=?")->execute([$hash, $_SESSION["ID_usuario"]]);
        $mensaje = "Contraseña actualizada correctamente.";
        registrarAuditoria($pdo, "usuarios", "Contraseña cambiada", $_SESSION["usuario"] . " cambió su propia contraseña");
    }
}

$titulo_pagina = "MI PERFIL";
require_once __DIR__ . "/includes/layout_top.php";
?>

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin:0 auto 18px auto;max-width:480px;}
.pd-field{display:flex;flex-direction:column;align-items:center;gap:4px;margin-bottom:14px;}
.pd-field label{font-size:13px;color:#444;}
.pd-field input{padding:6px 8px;border:1px solid #ccc;border-radius:4px;width:100%;max-width:300px;}
.perfil-dato{display:flex;justify-content:space-between;padding:4px 0;font-size:14px;}
.perfil-dato span:first-child{color:#777;}
</style>

<p class="titulo-modulo">Mi Perfil</p>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="pd-card">
<h3 style="margin-top:0;">Mi cuenta</h3>
<div class="perfil-dato"><span>Usuario</span><span><?php echo htmlspecialchars($_SESSION["usuario"]); ?></span></div>
<div class="perfil-dato"><span>Rol</span><span><?php echo htmlspecialchars(ucfirst($_SESSION["rol"])); ?></span></div>
<div class="perfil-dato"><span>Código de empleado</span><span><?php echo htmlspecialchars($_SESSION["cod_empleado"] ?? "—"); ?></span></div>
</div>

<div class="pd-card" style="text-align:center;">
<h3 style="margin-top:0;">Cambiar contraseña</h3>
<form method="POST">
    <input type="hidden" name="accion" value="cambiar_contrasena">
    <div class="pd-field">
        <label>Contraseña actual</label>
        <input type="password" name="contrasena_actual" required autocomplete="current-password">
    </div>
    <div class="pd-field">
        <label>Nueva contraseña (mínimo 6 caracteres)</label>
        <input type="password" name="contrasena_nueva" required minlength="6" autocomplete="new-password">
    </div>
    <div class="pd-field">
        <label>Confirmar nueva contraseña</label>
        <input type="password" name="contrasena_confirmar" required minlength="6" autocomplete="new-password">
    </div>
    <button type="submit">GUARDAR NUEVA CONTRASEÑA</button>
</form>
</div>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>