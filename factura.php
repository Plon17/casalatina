<?php
$modulo_actual = "factura";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$mensaje = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {

    if ($_POST["accion"] === "generar") {
        // Traemos los totales del pedido para armar la factura
        $stmt = $pdo->prepare("SELECT * FROM pedido WHERE ID_Pedido = ?");
        $stmt->execute([$_POST["id_pedido"]]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) {
            $error = "No existe un pedido con ese número.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO factura
                (ID_Factura, nombre_cliente, cod_empleado, ID_Pedido, fecha_fac, impuesto, total)
                VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([
                $_POST["id_factura"],
                $_POST["nombre_cliente"],
                $_POST["cod_empleado"],
                $_POST["id_pedido"],
                $_POST["fecha"],
                $pedido["impuesto"],
                $pedido["total"]
            ]);
            $mensaje = "Factura generada correctamente.";
        }
    }

    if ($_POST["accion"] === "borrar") {
        $stmt = $pdo->prepare("DELETE FROM factura WHERE ID_Factura = ?");
        $stmt->execute([$_POST["id_factura"]]);
        $mensaje = "Factura eliminada.";
    }
}

$titulo_pagina = "FACTURA";
require_once __DIR__ . "/includes/layout_top.php";
?>

<p class="titulo-modulo">Factura</p>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo $mensaje; ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo $error; ?></p><?php endif; ?>

<form method="POST" id="formFactura">
    <input type="hidden" name="accion" id="accion" value="generar">

    <div class="fila">
        <label>Número de Factura:</label>
        <input type="text" name="id_factura" required>
    </div>
    <div class="fila">
        <label>Nombre del Cliente:</label>
        <input type="text" name="nombre_cliente" required>
    </div>
    <div class="fila">
        <label>Código Cajero:</label>
        <input type="text" name="cod_empleado" value="<?php echo htmlspecialchars($_SESSION['ID_usuario'] ?? ''); ?>" required>
    </div>
    <div class="fila">
        <label>Número del Pedido:</label>
        <input type="text" name="id_pedido" required>
    </div>
    <div class="fila">
        <label>Fecha:</label>
        <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
    </div>

    <br>
    <button type="submit" onclick="document.getElementById('accion').value='generar'">Generar</button>
    <button type="button" onclick="document.getElementById('formFactura').reset()">Limpiar</button>
    <button type="submit" onclick="document.getElementById('accion').value='borrar'; return confirm('Vas a borrar esta factura, ¿continuar?')">Borrar</button>
</form>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
