<?php
$modulo_actual = "reporte";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$resultados = [];
$tipo = $_POST["tipo"] ?? "";
$fecha_inicio = $_POST["fecha_inicio"] ?? "";
$fecha_final = $_POST["fecha_final"] ?? "";
$consultado = false;

if ($_SERVER["REQUEST_METHOD"] === "POST" && $fecha_inicio && $fecha_final && $tipo) {
    $consultado = true;

    if ($tipo === "ventas") {
        $stmt = $pdo->prepare("SELECT * FROM factura WHERE fecha_fac BETWEEN ? AND ? ORDER BY fecha_fac");
        $stmt->execute([$fecha_inicio, $fecha_final]);
    } elseif ($tipo === "gastos") {
        $stmt = $pdo->prepare("SELECT * FROM gastos_detalles WHERE fecha BETWEEN ? AND ? ORDER BY fecha");
        $stmt->execute([$fecha_inicio, $fecha_final]);
    } else { // compras
        $stmt = $pdo->prepare("SELECT * FROM compras WHERE fecha BETWEEN ? AND ? ORDER BY fecha");
        $stmt->execute([$fecha_inicio, $fecha_final]);
    }
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$titulo_pagina = "REPORTE";
require_once __DIR__ . "/includes/layout_top.php";
?>

<p class="titulo-modulo">Reporte</p>

<form method="POST">
    <div class="fila">
        <label>Fecha Inicio:</label>
        <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
    </div>
    <div class="fila">
        <label>Fecha Final:</label>
        <input type="date" name="fecha_final" value="<?php echo htmlspecialchars($fecha_final); ?>" required>
    </div>
    <div class="fila">
        <label>Tipo:</label>
        <select name="tipo" required>
            <option value="ventas"  <?php echo $tipo === "ventas"  ? "selected" : ""; ?>>Ventas (Facturas)</option>
            <option value="gastos"  <?php echo $tipo === "gastos"  ? "selected" : ""; ?>>Gastos</option>
            <option value="compras" <?php echo $tipo === "compras" ? "selected" : ""; ?>>Compras</option>
        </select>
    </div>

    <button type="submit">Ingresar</button>
</form>

<?php if ($consultado): ?>
<h3>Resultados</h3>
<div class="caja-blanca">
<?php if (count($resultados) === 0): ?>
    <p>No se encontraron registros en ese rango de fechas.</p>
<?php else: ?>
<table>
<tr>
<?php foreach (array_keys($resultados[0]) as $col): ?>
    <th><?php echo htmlspecialchars($col); ?></th>
<?php endforeach; ?>
</tr>
<?php foreach ($resultados as $fila): ?>
<tr>
<?php foreach ($fila as $valor): ?>
    <td><?php echo htmlspecialchars($valor); ?></td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
