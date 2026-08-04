<?php
$modulo_actual = "inicio"; // el inicio lo ve cualquier rol
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$es_admin = ($_SESSION["rol"] === "administrador");

// Lista de todos los modulos: clave => [icono, texto, archivo]
$modulos = [
    "mesas"   => ["🍽️", "MESAS",       "pedidos_listado.php"],
    "pedido"  => ["🍳", "PEDIDO",      "pedido_paso1.php"],
    "menu"    => ["📖", "MENU",        "menu.php"],
    "factura" => ["🧾", "FACTURA",     "factura.php"],
    "stock"   => ["📋", "STOCK",       "stock.php"],
    "compras" => ["🛒", "COMPRAS",     "compras.php"],
    "prov"    => ["🚚", "PROVEEDORES", "proveedores.php"],
    "gastos"  => ["💵", "GASTOS",      "gastos.php"],
    "reporte" => ["📊", "REPORTE",     "reportes.php"],
    "usuarios"=> ["👤", "USUARIOS",    "usuarios.php"],
    "auditoria"=> ["🕵️", "AUDITORÍA", "auditoria.php"],
];

// Los que puede ver el empleado
$modulos_empleado = ["mesas", "menu", "pedido", "factura", "stock"];

// ---------- Datos para el dashboard ----------

// Mesas: mismo total configurado en pedidos_listado.php (mantenerlos sincronizados)
$totalMesas = 12;
$mesasStmt = $pdo->query("SELECT estado FROM pedido WHERE tipo_ped='Mesa' AND estado IN ('Abierto','EnCocina')")->fetchAll(PDO::FETCH_ASSOC);
$mesasArmando = 0; $mesasCocina = 0;
foreach ($mesasStmt as $m) {
    if ($m["estado"] === "Abierto") $mesasArmando++; else $mesasCocina++;
}
$mesasLibres = max(0, $totalMesas - $mesasArmando - $mesasCocina);

// Stock bajo (activos únicamente)
$stockBajo = $pdo->query("SELECT ID_Producto, nombre_pro, cantidad_pro FROM producto
                           WHERE cantidad_pro <= 5 AND activo = 1
                           ORDER BY cantidad_pro ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$stockBajoTotal = (int) $pdo->query("SELECT COUNT(*) AS c FROM producto WHERE cantidad_pro <= 5 AND activo = 1")->fetch()["c"];

if ($es_admin) {
    $ventasHoy = $pdo->query("SELECT COALESCE(SUM(total),0) AS t, COUNT(*) AS c FROM factura WHERE fecha_fac = CURDATE()")->fetch();
    $gastosHoy = (float) $pdo->query("SELECT COALESCE(SUM(monto),0) AS t FROM gastos_detalles WHERE fecha = CURDATE()")->fetch()["t"];
    $comprasHoy = (float) $pdo->query("SELECT COALESCE(SUM(monto_total),0) AS t FROM compras WHERE fecha = CURDATE()")->fetch()["t"];
    $utilidadHoy = (float) $ventasHoy["t"] - $gastosHoy - $comprasHoy;

    // Ventas de los últimos 7 días, para la mini gráfica de barras
    $stmt = $pdo->prepare("SELECT fecha_fac, SUM(total) AS total FROM factura
                            WHERE fecha_fac BETWEEN ? AND ? GROUP BY fecha_fac");
    $hace7 = date("Y-m-d", strtotime("-6 days"));
    $stmt->execute([$hace7, date("Y-m-d")]);
    $porDia = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $porDia[$r["fecha_fac"]] = (float) $r["total"]; }
    $ultimos7 = [];
    for ($i = 6; $i >= 0; $i--) {
        $f = date("Y-m-d", strtotime("-$i days"));
        $ultimos7[] = ["fecha" => $f, "total" => $porDia[$f] ?? 0];
    }
    $maxVenta = max(array_column($ultimos7, "total")) ?: 1;
}

$titulo_pagina = "CASA LATINA SMART SYSTEM";
require_once __DIR__ . "/includes/layout_top.php";
?>

<style>
.dash-layout{display:flex; gap:20px; align-items:flex-start;}
.dash-sidebar{width:220px; flex-shrink:0; background:#fff; border:1px solid #ddd; border-radius:8px; padding:12px;
    display:flex; flex-direction:column;}
.dash-sidebar-links{overflow-y:auto; max-height:410px;}
.dash-sidebar .menu-item{margin-bottom:8px;}
.dash-sidebar .btn{display:flex; align-items:center; gap:10px; width:100%; padding:10px 12px; border-radius:6px;
    background:var(--color-primary-light); border:1px solid #eee; text-decoration:none; color:var(--color-text-dark); font-weight:600; font-size:14px;}
.dash-sidebar .btn:hover{background:var(--color-primary-light); border-color:var(--color-primary);}
.dash-sidebar .icono{font-size:18px;}
.dash-main{flex:1; min-width:0;}

.sesion-card{border-top:1px solid #eee; margin-top:14px; padding-top:14px; text-align:center;}
.sesion-avatar{width:44px; height:44px; border-radius:50%; background:var(--color-primary); color:#fff; font-weight:700; font-size:18px;
    display:flex; align-items:center; justify-content:center; margin:0 auto 8px auto;}
.sesion-usuario{font-weight:700; color:var(--color-text-dark); font-size:14px;}
.sesion-rol{display:inline-block; margin-top:3px; font-size:11px; color:var(--color-primary-dark); background:var(--color-primary-light); padding:2px 10px; border-radius:10px;}
.sesion-salir{display:block; margin-top:10px; font-size:13px; color:var(--color-danger); text-decoration:none; font-weight:600;}
.sesion-perfil{display:block; margin-top:6px; font-size:13px; color:var(--color-primary); text-decoration:none; font-weight:600;}
.sesion-perfil:hover{text-decoration:underline;}

.btn-link{display:inline-block; padding:6px 14px; border:1px solid var(--color-primary); border-radius:5px;
    color:var(--color-primary); text-decoration:none; font-size:13px; font-weight:600;}
.btn-link:hover{background:var(--color-primary); color:#fff;}
.sesion-salir:hover{text-decoration:underline;}

.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.kpi-grid{display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin-bottom:18px;}
.kpi-card{background:#fff; border:1px solid #ddd; border-radius:8px; padding:14px 16px;}
.kpi-card .kpi-label{font-size:12px; color:#777; margin-bottom:4px;}
.kpi-card .kpi-valor{font-size:22px; font-weight:700; color:var(--color-text-dark);}
.kpi-card.positivo .kpi-valor{color:var(--color-success);}
.kpi-card.negativo .kpi-valor{color:var(--color-danger);}

.mesas-resumen{display:flex; gap:18px; flex-wrap:wrap;}
.mesas-resumen div{display:flex; align-items:center; gap:6px; font-size:14px;}
.dot{display:inline-block; width:10px; height:10px; border-radius:50%;}
.dot-libre{background:var(--color-mesa-libre);} .dot-armando{background:var(--color-mesa-armando);} .dot-cocina{background:var(--color-mesa-cocina);}

.pd-tabla{width:100%; border-collapse:collapse;}
.pd-tabla th,.pd-tabla td{border:1px solid #ddd; padding:6px 10px; text-align:left; font-size:14px;}
.pd-tabla th{background:var(--color-surface-alt);}

.barra-chart{display:flex; align-items:flex-end; gap:10px; height:120px; margin-top:26px;}
.barra-col{flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; position:relative;}
.barra{width:100%; background:var(--color-primary); border-radius:3px 3px 0 0; min-height:2px; position:relative; cursor:default;}
.barra-fecha{font-size:11px; color:#777; margin-top:4px;}
.barra-tooltip{
    position:absolute; bottom:calc(100% + 6px); left:50%; transform:translateX(-50%) translateY(4px);
    background:var(--color-text-dark); color:#fff; font-size:12px; font-weight:600; white-space:nowrap;
    padding:4px 9px; border-radius:5px; opacity:0; pointer-events:none;
    transition:opacity .12s ease, transform .12s ease;
}
.barra-tooltip::after{
    content:""; position:absolute; top:100%; left:50%; transform:translateX(-50%);
    border:5px solid transparent; border-top-color:var(--color-text-dark);
}
.barra:hover .barra-tooltip{opacity:1; transform:translateX(-50%) translateY(0);}
</style>

<p class="bienvenida">
    BIENVENIDO AL SISTEMA DE CASA LATINA<br>
    ¿QUÉ DESEA HACER?
</p>

<div class="dash-layout">

    <!-- Sidebar de módulos -->
    <div class="dash-sidebar">
        <div class="dash-sidebar-links">
        <?php foreach ($modulos as $clave => $info):
            if (!$es_admin && !in_array($clave, $modulos_empleado)) continue;
            [$icono, $texto, $archivo] = $info;
        ?>
        <div class="menu-item">
            <a class="btn" href="<?php echo $archivo; ?>">
                <span class="icono"><?php echo $icono; ?></span> <?php echo $texto; ?>
            </a>
        </div>
        <?php endforeach; ?>
        </div>

        <div class="sesion-card">
            <div class="sesion-avatar"><?php echo htmlspecialchars(strtoupper(substr($_SESSION["usuario"], 0, 1))); ?></div>
            <div class="sesion-usuario"><?php echo htmlspecialchars($_SESSION["usuario"]); ?></div>
            <span class="sesion-rol"><?php echo htmlspecialchars(ucfirst($_SESSION["rol"])); ?></span>
            <a class="sesion-perfil" href="perfil.php">Mi Perfil</a>
            <a class="sesion-salir" href="logout.php">Cerrar sesión</a>
        </div>
    </div>

    <!-- Dashboard -->
    <div class="dash-main">

        <?php if ($es_admin): ?>
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Ventas de hoy</div>
                <div class="kpi-valor">L. <?php echo number_format((float) $ventasHoy["t"], 2); ?></div>
                <div class="kpi-label"><?php echo (int) $ventasHoy["c"]; ?> factura(s)</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Gastos de hoy</div>
                <div class="kpi-valor">L. <?php echo number_format($gastosHoy, 2); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Compras de hoy</div>
                <div class="kpi-valor">L. <?php echo number_format($comprasHoy, 2); ?></div>
            </div>
            <div class="kpi-card <?php echo $utilidadHoy >= 0 ? 'positivo' : 'negativo'; ?>">
                <div class="kpi-label">Utilidad neta de hoy</div>
                <div class="kpi-valor">L. <?php echo number_format($utilidadHoy, 2); ?></div>
            </div>
        </div>

        <div class="pd-card">
            <h3 style="margin-top:0;">Ventas — últimos 7 días</h3>
            <div class="barra-chart">
                <?php foreach ($ultimos7 as $d): ?>
                <div class="barra-col">
                    <div class="barra" style="height:<?php echo max(4, round(($d['total'] / $maxVenta) * 100)); ?>%;">
                        <span class="barra-tooltip">L. <?php echo number_format($d['total'], 2); ?></span>
                    </div>
                    <div class="barra-fecha"><?php echo date("d/m", strtotime($d["fecha"])); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="pd-card">
            <h3 style="margin-top:0;">Mesas ahora</h3>
            <div class="mesas-resumen">
                <div><span class="dot dot-libre"></span> <?php echo $mesasLibres; ?> libres</div>
                <div><span class="dot dot-armando"></span> <?php echo $mesasArmando; ?> armando pedido</div>
                <div><span class="dot dot-cocina"></span> <?php echo $mesasCocina; ?> en cocina</div>
            </div>
            <p style="margin-top:10px;"><a class="btn-link" href="pedidos_listado.php">Ver mapa de mesas →</a></p>
        </div>

        <div class="pd-card">
            <h3 style="margin-top:0;">Stock bajo <?php echo $stockBajoTotal > 0 ? "($stockBajoTotal)" : ""; ?></h3>
            <?php if (count($stockBajo) === 0): ?>
                <p>Todo el inventario está en buen nivel.</p>
            <?php else: ?>
                <table class="pd-tabla">
                <tr><th>Producto</th><th>Cantidad</th></tr>
                <?php foreach ($stockBajo as $s): ?>
                <tr><td><?php echo htmlspecialchars($s["nombre_pro"]); ?></td><td><?php echo number_format((float) $s["cantidad_pro"], 2); ?></td></tr>
                <?php endforeach; ?>
                </table>
                <p style="margin-top:10px;"><a class="btn-link" href="stock.php">Ver inventario completo →</a></p>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>