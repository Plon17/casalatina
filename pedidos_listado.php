<?php
$modulo_actual = "pedido";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

// Pedidos que todavía están en curso (no pagados ni cancelados)
$stmt = $pdo->query("SELECT ID_Pedido, num_mesa, tipo_ped, cod_empleado, fecha, total, estado
                      FROM pedido
                      WHERE estado IN ('Abierto', 'EnCocina')
                      ORDER BY fecha DESC, ID_Pedido DESC");
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "MESAS ACTIVAS";
require_once __DIR__ . "/includes/layout_top.php";
?>

<p class="titulo-modulo">Mesas / Pedidos activos</p>

<p><a href="pedido_paso1.php">+ Nuevo pedido</a></p>

<div class="caja-blanca">
<table>
<tr>
    <th>N° Pedido</th>
    <th>Mesa</th>
    <th>Tipo</th>
    <th>Cajero</th>
    <th>Total</th>
    <th>Estado</th>
    <th></th>
</tr>
<?php if (count($pedidos) === 0): ?>
<tr><td colspan="7">No hay pedidos activos en este momento.</td></tr>
<?php endif; ?>
<?php foreach ($pedidos as $p): ?>
<tr>
    <td><?php echo htmlspecialchars($p["ID_Pedido"]); ?></td>
    <td><?php echo htmlspecialchars($p["num_mesa"] ?: "N/A"); ?></td>
    <td><?php echo htmlspecialchars($p["tipo_ped"]); ?></td>
    <td><?php echo htmlspecialchars($p["cod_empleado"]); ?></td>
    <td><?php echo number_format((float) $p["total"], 2); ?></td>
    <td>
        <?php if ($p["estado"] === "Abierto"): ?>
            <span class="mensaje-error" style="padding:2px 8px;">Armando pedido</span>
        <?php else: ?>
            <span class="mensaje-ok" style="padding:2px 8px;">En cocina</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($p["estado"] === "Abierto"): ?>
            <a href="pedido_paso2.php?id=<?php echo urlencode($p['ID_Pedido']); ?>">Continuar →</a>
        <?php else: ?>
            <a href="pedido_cobro.php?id=<?php echo urlencode($p['ID_Pedido']); ?>">Cobrar →</a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>