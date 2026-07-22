<?php
$modulo_actual = "gasto_det";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "registrar") {
    $stmt = $pdo->prepare("INSERT INTO gastos_detalles (ID_detg, fecha, monto, ID_gastos)
                            VALUES (?,?,?,?)");
    $stmt->execute([$_POST["id_detg"], $_POST["fecha"], $_POST["monto"], $_POST["id_gastos"]]);
    $mensaje = "Detalle de gasto registrado.";
}

$gastos = $pdo->query("SELECT ID_gastos, nombre FROM gastos")->fetchAll(PDO::FETCH_ASSOC);
$detalles = $pdo->query("SELECT * FROM gastos_detalles ORDER BY fecha DESC")->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "DETALLE DE GASTOS";
require_once __DIR__ . "/includes/layout_top.php";
?>

<p class="titulo-modulo">Detalle de Gasto</p>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo $mensaje; ?></p><?php endif; ?>

<form method="POST">
    <input type="hidden" name="accion" value="registrar">

    <div class="fila">
        <label>ID Detalle:</label>
        <input type="text" name="id_detg" required>
    </div>
    <div class="fila">
        <label>ID Gasto:</label>
        <select name="id_gastos" required>
            <?php foreach ($gastos as $g): ?>
            <option value="<?php echo htmlspecialchars($g["ID_gastos"]); ?>">
                <?php echo htmlspecialchars($g["ID_gastos"] . " - " . $g["nombre"]); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="fila">
        <label>Fecha:</label>
        <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
    </div>
    <div class="fila">
        <label>Monto:</label>
        <input type="number" step="0.01" name="monto" required>
    </div>

    <button type="submit">REGISTRAR</button>
</form>

<h3>Tabla de gastos (detalle)</h3>
<div class="caja-blanca">
<table>
<tr><th>ID Detalle</th><th>ID Gasto</th><th>Fecha</th><th>Monto</th></tr>
<?php foreach ($detalles as $d): ?>
<tr>
    <td><?php echo htmlspecialchars($d["ID_detg"]); ?></td>
    <td><?php echo htmlspecialchars($d["ID_gastos"]); ?></td>
    <td><?php echo htmlspecialchars($d["fecha"]); ?></td>
    <td><?php echo htmlspecialchars($d["monto"]); ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
