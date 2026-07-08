<?php
$modulo_actual = "pedido";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

// Ajusta este número según cuántas mesas físicas tenga el local
$totalMesas = 12;

// Pedidos de mesa activos, indexados por número de mesa
$mesasActivas = [];
foreach ($pdo->query("SELECT ID_Pedido, num_mesa, estado, total FROM pedido
                       WHERE tipo_ped='Mesa' AND estado IN ('Abierto','EnCocina')")->fetchAll(PDO::FETCH_ASSOC) as $m) {
    $mesasActivas[(int) $m["num_mesa"]] = $m;
}

// Pedidos de envío activos (no ocupan una mesa física)
$envios = $pdo->query("SELECT ID_Pedido, estado, total, fecha FROM pedido
                        WHERE tipo_ped='Envio' AND estado IN ('Abierto','EnCocina')
                        ORDER BY fecha DESC, ID_Pedido DESC")->fetchAll(PDO::FETCH_ASSOC);

// Historial de hoy (pagados/cancelados), para verificar que se están guardando
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

.mesas-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:12px;}
.mesa-box{display:flex;flex-direction:column;align-items:center;justify-content:center;
    padding:16px 8px;border-radius:10px;text-decoration:none;color:#fff;font-weight:600;
    min-height:80px;transition:transform .1s ease;}
.mesa-box:hover{transform:scale(1.05);}
.mesa-num{font-size:15px;}
.mesa-estado{font-size:12px;font-weight:400;margin-top:2px;}
.mesa-total{font-size:12px;margin-top:4px;opacity:.9;}
.mesa-libre{background:#5cb85c;}
.mesa-armando{background:#f0ad4e;}
.mesa-cocina{background:#4a90d9;}

.mesas-leyenda{display:flex;gap:20px;margin-top:16px;font-size:13px;color:#444;flex-wrap:wrap;}
.dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:5px;}
.dot-libre{background:#5cb85c;}
.dot-armando{background:#f0ad4e;}
.dot-cocina{background:#4a90d9;}
</style>

<p class="titulo-modulo">Mesas / Pedidos activos</p>

<p><a href="pedido_paso1.php">+ Nuevo pedido</a></p>

<div class="pd-card">
<h3 style="margin-top:0;">Mesas</h3>
<div class="mesas-grid">
<?php for ($i = 1; $i <= $totalMesas; $i++):
    $activo = $mesasActivas[$i] ?? null;
    if (!$activo) {
        $clase = "mesa-libre";
        $href = "pedido_paso1.php?mesa=" . $i;
        $texto = "Libre";
    } elseif ($activo["estado"] === "Abierto") {
        $clase = "mesa-armando";
        $href = "pedido_paso1.php?id=" . urlencode($activo["ID_Pedido"]);
        $texto = "Armando pedido";
    } else {
        $clase = "mesa-cocina";
        $href = "pedido_cobro.php?id=" . urlencode($activo["ID_Pedido"]);
        $texto = "En cocina";
    }
?>
<a class="mesa-box <?php echo $clase; ?>" href="<?php echo $href; ?>">
    <span class="mesa-num">Mesa <?php echo $i; ?></span>
    <span class="mesa-estado"><?php echo $texto; ?></span>
    <?php if ($activo): ?><span class="mesa-total">L. <?php echo number_format((float) $activo["total"], 2); ?></span><?php endif; ?>
</a>
<?php endfor; ?>
</div>

<div class="mesas-leyenda">
    <span><i class="dot dot-libre"></i>Libre — toca para tomar un pedido nuevo</span>
    <span><i class="dot dot-armando"></i>Armando pedido — toca para seguir agregando</span>
    <span><i class="dot dot-cocina"></i>En cocina — toca para cobrar</span>
</div>
</div>

<?php if (count($envios) > 0): ?>
<div class="pd-card">
<h3 style="margin-top:0;">Pedidos para envío (activos)</h3>
<table class="pd-tabla">
<tr><th>N° Pedido</th><th>Total</th><th>Estado</th><th></th></tr>
<?php foreach ($envios as $e): ?>
<tr>
    <td><?php echo htmlspecialchars($e["ID_Pedido"]); ?></td>
    <td><?php echo number_format((float) $e["total"], 2); ?></td>
    <td><?php echo $e["estado"] === "Abierto" ? "Armando pedido" : "En cocina"; ?></td>
    <td>
        <?php if ($e["estado"] === "Abierto"): ?>
            <a href="pedido_paso1.php?id=<?php echo urlencode($e['ID_Pedido']); ?>">Continuar →</a>
        <?php else: ?>
            <a href="pedido_cobro.php?id=<?php echo urlencode($e['ID_Pedido']); ?>">Cobrar →</a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>

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