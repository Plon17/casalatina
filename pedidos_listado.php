<?php
$modulo_actual = "pedido";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

// Ajusta este número según cuántas mesas físicas tenga el local
$totalMesas = 12;

// Posiciones [izquierda%, arriba%] de cada mesa dentro del plano.
// Si agregas más mesas que las que tienen posición definida aquí,
// se acomodan solas en una cuadrícula de respaldo más abajo.
$posicionesMesas = [
    1 => [19, 15],  2 => [35, 15],  3 => [51, 15],  4 => [67, 15],
    5 => [19, 42],  6 => [35, 42],  7 => [51, 42],  8 => [67, 42],
    9 => [19, 68], 10 => [35, 68], 11 => [51, 68], 12 => [67, 68],
];

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

.mesas-leyenda{display:flex;gap:20px;margin-top:16px;font-size:13px;color:#444;flex-wrap:wrap;}
.dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:5px;}
.dot-libre{background:#5cb85c;}
.dot-armando{background:#f0ad4e;}
.dot-cocina{background:#4a90d9;}

.floor-plan{position:relative;width:100%;min-height:520px;margin-top:6px;
    background:repeating-linear-gradient(45deg,#ecdfc4,#ecdfc4 20px,#e2d3ae 20px,#e2d3ae 40px);
    border:10px solid #7a5230;border-radius:8px;overflow:hidden;
    box-shadow:inset 0 0 40px rgba(0,0,0,.15);}

.zona-cocina{position:absolute;top:0;right:0;width:18%;height:100%;
    background:repeating-linear-gradient(135deg,#c7c7c7,#c7c7c7 10px,#b0b0b0 10px,#b0b0b0 20px);
    border-left:6px solid #7a5230;display:flex;align-items:center;justify-content:center;}
.zona-cocina span{writing-mode:vertical-rl;font-weight:700;color:#555;letter-spacing:3px;font-size:13px;}

.zona-barra{position:absolute;top:6%;left:0;width:8%;height:40%;
    background:#8b6f4e;border-radius:0 10px 10px 0;box-shadow:2px 0 6px rgba(0,0,0,.2);
    display:flex;align-items:center;justify-content:center;}
.zona-barra span{writing-mode:vertical-rl;color:#fff;font-weight:700;font-size:12px;letter-spacing:2px;}

.zona-entrada{position:absolute;bottom:0;left:42%;width:16%;height:14px;background:#7a5230;border-radius:6px 6px 0 0;}
.zona-entrada-label{position:absolute;bottom:16px;left:42%;width:16%;text-align:center;font-size:11px;color:#7a5230;font-weight:700;}

.mesa-wrap{position:absolute;width:90px;height:90px;transform:translate(-50%,-50%);}
.mesa-wrap .silla{position:absolute;width:14px;height:14px;border-radius:3px;background:#7a5230;opacity:.75;}
.silla.n{top:-10px;left:38px;}
.silla.s{bottom:-10px;left:38px;}
.silla.e{right:-10px;top:38px;}
.silla.w{left:-10px;top:38px;}

.mesa-box{position:absolute;inset:0;border-radius:50%;display:flex;flex-direction:column;
    align-items:center;justify-content:center;color:#fff;font-weight:700;text-decoration:none;
    box-shadow:0 3px 6px rgba(0,0,0,.3);font-size:12px;text-align:center;line-height:1.3;
    transition:transform .12s ease, box-shadow .12s ease;}
.mesa-box:hover{transform:scale(1.08);box-shadow:0 6px 12px rgba(0,0,0,.4);}
.mesa-box .mesa-num{font-size:15px;}
.mesa-box .mesa-estado{font-size:10px;font-weight:400;}
.mesa-box .mesa-total{font-size:10px;font-weight:400;opacity:.9;}

.mesa-libre{background:#5cb85c;}
.mesa-armando{background:#f0ad4e;}
.mesa-cocina{background:#4a90d9;}
</style>

<p class="titulo-modulo">Mesas / Pedidos activos</p>

<p><a href="pedido_paso1.php">+ Nuevo pedido</a></p>

<div class="pd-card">
<h3 style="margin-top:0;">Mesas</h3>

<div class="floor-plan">
    <div class="zona-barra"><span>BARRA</span></div>
    <div class="zona-cocina"><span>COCINA</span></div>
    <div class="zona-entrada"></div>
    <div class="zona-entrada-label">Entrada</div>

    <?php for ($i = 1; $i <= $totalMesas; $i++):
        $activo = $mesasActivas[$i] ?? null;

        if (isset($posicionesMesas[$i])) {
            [$left, $top] = $posicionesMesas[$i];
        } else {
            // Respaldo: acomoda en cuadrícula simple las mesas que no tengan posición fija
            $col = ($i - 1) % 4;
            $fila = intdiv($i - 1, 4);
            $left = 20 + $col * 15;
            $top = 15 + $fila * 26;
        }

        if (!$activo) {
            $clase = "mesa-libre";
            $href = "pedido_paso1.php?mesa=" . $i;
            $texto = "Libre";
        } elseif ($activo["estado"] === "Abierto") {
            $clase = "mesa-armando";
            $href = "pedido_paso1.php?id=" . urlencode($activo["ID_Pedido"]);
            $texto = "Armando";
        } else {
            $clase = "mesa-cocina";
            $href = "pedido_cobro.php?id=" . urlencode($activo["ID_Pedido"]);
            $texto = "En cocina";
        }
    ?>
    <div class="mesa-wrap" style="left:<?php echo $left; ?>%; top:<?php echo $top; ?>%;">
        <span class="silla n"></span>
        <span class="silla s"></span>
        <span class="silla e"></span>
        <span class="silla w"></span>
        <a class="mesa-box <?php echo $clase; ?>" href="<?php echo $href; ?>"
           title="Mesa <?php echo $i; ?> — <?php echo $texto; ?><?php echo $activo ? " (L. " . number_format((float) $activo["total"], 2) . ")" : ""; ?>">
            <span class="mesa-num">Mesa <?php echo $i; ?></span>
            <span class="mesa-estado"><?php echo $texto; ?></span>
            <?php if ($activo): ?><span class="mesa-total">L. <?php echo number_format((float) $activo["total"], 2); ?></span><?php endif; ?>
        </a>
    </div>
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