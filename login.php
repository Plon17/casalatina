<?php
session_start();
require_once __DIR__ . "/includes/db.php";

// Si ya hay sesion iniciada, mandamos directo al inicio
if (isset($_SESSION["rol"]) && isset($_SESSION["usuario"])) {
    header('Location: index.php');
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario    = trim($_POST["usuario"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";

    if ($usuario === "" || $contrasena === "") {
        $error = "Debes ingresar usuario y contraseña.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ?");
        $stmt->execute([$usuario]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($u) {
            $storedPassword = $u["contrasena"];
            $passwordMatches = password_verify($contrasena, $storedPassword) || $contrasena === $storedPassword;

            if ($passwordMatches) {
                // Regenera la sesión para seguridad
                session_regenerate_id(true);
                
                $_SESSION["ID_usuario"] = $u["ID_usuario"];
                $_SESSION["usuario"]    = $u["usuario"];
                $_SESSION["rol"]        = $u["rol"];
                $_SESSION["cod_empleado"] = $u["cod_empleado"] ?? "";
                
                // Fuerza la escritura de la sesión antes de redirigir
                session_write_close();
                
                header('Location: index.php');
                exit;
            }
        }

        $error = "Usuario o contraseña incorrectos.";
    }
}

$titulo_pagina = "Iniciar sesión";
$mostrar_volver = false;
$sin_container = true;
$sin_logo = true;
require_once __DIR__ . "/includes/layout_top.php";
?>

<div class="login-page">
    <div class="login-box">
        <div class="logo-box" style="float:none; margin: 0 auto 20px auto;">
            <div class="marca">CASA<br>LATINA</div>
        </div>

        <h2>Iniciar sesión</h2>

        <?php if ($error): ?>
            <p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST">
            <label>Usuario:</label>
            <input type="text" name="usuario" required autofocus>

            <label>Contraseña:</label>
            <input type="password" name="contrasena" required>

            <button type="submit">INGRESAR</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
            <input type="text" name="usuario" required autofocus>

            <label>Contraseña:</label>
            <input type="password" name="contrasena" required>

            <button type="submit">INGRESAR</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>    <div class="logo-box" style="float:none; margin: 0 auto 20px auto;">
        <div class="marca">CASA<br>LATINA</div>
    </div>

    <h2>Iniciar sesión</h2>

    <?php if ($error): ?>
        <p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Usuario:</label>
        <input type="text" name="usuario" required autofocus>

        <label>Contraseña:</label>
        <input type="password" name="contrasena" required>

        <button type="submit">INGRESAR</button>
    </form>
</div>

</body>
</html>
