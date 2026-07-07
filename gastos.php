<?php
$modulo_actual = "gastos";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "registrar") {
    $stmt = $pdo->prepare("INSERT INTO gastos (ID_gastos, nombre, tipo, descripcion) VALUES (?,?,?,?)");
    $stmt->execute([$_POST["id_gasto"], $_POST["nombre"], $_POST["tipo"], $_POST["descripcion"]]);
    $mensaje = "Gasto registrado correctamente.";
}

$titulo_pagina = "GASTOS";
require_once __DIR__ . "/includes/layout_top.php";
?>

<p class="titulo-modulo">Gastos</p>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo $mensaje; ?></p><?php endif; ?>

<form method="POST">
    <input type="hidden" name="accion" value="registrar">

    <div class="fila">
        <label>ID Gasto:</label>
        <input type="text" name="id_gasto" required>
    </div>
    <div class="fila">
        <label>Nombre:</label>
        <input type="text" name="nombre" required>
    </div>
    <div class="fila">
        <label>Tipo:</label>
        <select name="tipo" required>
            <option value="Publico">Público</option>
            <option value="Privado">Privado</option>
            <option value="Operativo">Operativo</option>
        </select>
    </div>
    <div class="fila">
        <label>Descripción:</label>
        <textarea name="descripcion" rows="4" style="width:300px;"></textarea>
    </div>

    <button type="submit">REGISTRAR</button>
</form>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
