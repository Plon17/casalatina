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

// Historial de hoy (pagados/cancelados), solo para verificar que se están guardando
$stmtHist = $pdo->prepare("SELECT ID_Pedido, num_mesa, tipo_ped, total, estado
                            FROM pedido
                            WHERE estado IN ('Pagado', 'Cancelado') AND fecha = ?
                            ORDER BY ID_Pedido DESC");
$stmtHist->execute([date("Y-m-d")]);
$historial = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "MESAS ACTIVAS";
require_once __DIR__ . "/includes/layout_top.php";
?>

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.pd-tabla{width:100%;border-collapse:collapse;}
.pd-tabla th,.pd-tabla td{border:1px solid #ddd;padding:6px 10px;text-align:left;font-size:14px;}
.pd-tabla th{background:#f5f5f5;}
.pd-badge{padding:2px 8px;border-radius:4px;font-size:13px;}
</style>

<p class="titulo-modulo">Mesas / Pedidos activos</p>

<p><a href="pedido_paso1.php">+ Nuevo pedido</a></p>

<div class="pd-card">
<table class="pd-tabla">
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
            <span class="pd-badge mensaje-error">Armando pedido</span>
        <?php else: ?>
            <span class="pd-badge mensaje-ok">En cocina</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($p["estado"] === "Abierto"): ?>
            <a href="pedido_paso1.php?id=<?php echo urlencode($p['ID_Pedido']); ?>">Continuar →</a>
        <?php else: ?>
            <a href="pedido_cobro.php?id=<?php echo urlencode($p['ID_Pedido']); ?>">Cobrar →</a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<details>
<summary>Historial de hoy (pagados / cancelados) — <?php echo count($historial); ?></summary>
<div class="pd-card" style="margin-top:10px;">
<table class="pd-tabla">
<tr><th>N° Pedido</th><th>Mesa</th><th>Tipo</th><th>Total</th><th>Estado</th></tr>
<?php if (count($historial) === 0): ?>
<tr><td colspan="5">Todavía no hay pedidos cerrados hoy.</td></tr>
<?php endif; ?>
<?php foreach ($historial as $h): ?>
<tr>
    <td><?php echo htmlspecialchars($h["ID_Pedido"]); ?></td>
    <td><?php echo htmlspecialchars($h["num_mesa"] ?: "N/A"); ?></td>
    <td><?php echo htmlspecialchars($h["tipo_ped"]); ?></td>
    <td><?php echo number_format((float) $h["total"], 2); ?></td>
    <td><?php echo htmlspecialchars($h["estado"]); ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
</details>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>