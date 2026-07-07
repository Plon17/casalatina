<?php
$modulo_actual = "inicio"; // el inicio lo ve cualquier rol
require_once __DIR__ . "/includes/auth.php";

$es_admin = ($_SESSION["rol"] === "administrador");

// Lista de todos los modulos: clave => [icono, texto, archivo]
$modulos = [
    "menu"      => ["📖", "MENÚ",            "menu.php"],
    "factura"   => ["🧾", "FACTURA",         "factura.php"],
    "compras"   => ["🛒", "COMPRAS",         "compras.php"],
    "gasto_det" => ["💲", "DETALLE DE GASTO","detalle_gasto.php"],
    "prov"      => ["🚚", "PROVEEDORES",     "proveedores.php"],
    "pedido"    => ["🍳", "PEDIDO",          "pedido_paso1.php"],
    "stock"     => ["📋", "STOCK",           "stock.php"],
    "gastos"    => ["💵", "GASTOS",          "gastos.php"],
    "reporte"   => ["📊", "REPORTE",         "reportes.php"],
    "mesas"   => ["🍽️", "MESAS",         "pedidos_listado.php"],
];

// Los que puede ver el empleado
$modulos_empleado = ["menu", "pedido", "factura", "stock"];

$titulo_pagina = "CASA LATINA SMART SYSTEM";
require_once __DIR__ . "/includes/layout_top.php";
?>

<p class="bienvenida">
    BIENVENIDO AL SISTEMA DE CASA LATINA<br>
    ¿QUÉ DESEA HACER?
</p>

<div class="menu-grid">
<?php foreach ($modulos as $clave => $info):
    if (!$es_admin && !in_array($clave, $modulos_empleado)) continue;
    [$icono, $texto, $archivo] = $info;
?>
    <div class="menu-item">
        <span class="icono"><?php echo $icono; ?></span>
        <a class="btn" href="<?php echo $archivo; ?>"><?php echo $texto; ?></a>
    </div>
<?php endforeach; ?>
</div>

<p style="margin-top:40px;">
    Sesión iniciada como: <b><?php echo htmlspecialchars($_SESSION["usuario"]); ?></b>
    (<?php echo htmlspecialchars($_SESSION["rol"]); ?>)
    &nbsp;|&nbsp; <a href="logout.php">Cerrar sesión</a>
</p>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
