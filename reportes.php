<?php
$modulo_actual = "reporte";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$tipo = $_GET["tipo"] ?? "ventas";
$fecha_inicio = $_GET["fecha_inicio"] ?? date("Y-m-01");
$fecha_final = $_GET["fecha_final"] ?? date("Y-m-d");

$titulo_pagina = "REPORTES";
require_once __DIR__ . "/includes/layout_top.php";

$tiposReporte = [
    "ventas"      => ["Ventas", "Ingresos por facturación en el período"],
    "gastos"      => ["Gastos", "Gastos registrados, agrupados por categoría"],
    "compras"     => ["Compras", "Compras hechas a proveedores"],
    "compras_prov"=> ["Compras por Proveedor", "Cuánto le has comprado a cada proveedor"],
    "resumen"     => ["Resumen Financiero", "Ventas vs. Gastos vs. Compras — utilidad del período"],
    "top_platos"  => ["Platos Más Vendidos", "Ranking de platos por cantidad e ingreso generado"],
    "metodo_pago" => ["Cierre de Caja", "Ventas separadas por Efectivo vs. Tarjeta"],
];
?>

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.pd-row{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;}
.pd-field{display:flex;flex-direction:column;gap:4px;}
.pd-field label{font-size:13px;color:#444;}
.pd-field input{padding:6px 8px;border:1px solid #ccc;border-radius:4px;min-width:160px;}

.tipos-grid{display:grid;grid-template-columns:repeat(3, 1fr);gap:12px;margin-bottom:18px;}
@media (max-width: 700px){ .tipos-grid{grid-template-columns:repeat(2, 1fr);} }
.tipo-opcion input{display:none;}
.tipo-opcion label{
    display:block;border:2px solid #ddd;border-radius:8px;padding:14px;cursor:pointer;
    transition:border-color .12s, background .12s;
}
.tipo-opcion label strong{display:block;font-size:15px;color:#333;margin-bottom:4px;}
.tipo-opcion label span{font-size:12px;color:#777;}
.tipo-opcion input:checked + label{border-color:var(--color-primary);background:var(--color-primary-light);}
.tipo-opcion input:checked + label strong{color:var(--color-primary);}
</style>

<p class="titulo-modulo">Reportes</p>

<form method="GET" action="reporte_pdf.php" target="_blank">
    <div class="pd-card">
        <h3 style="margin-top:0;">1. Elige el tipo de reporte</h3>
        <div class="tipos-grid">
        <?php foreach ($tiposReporte as $clave => [$nombre, $desc]): ?>
            <div class="tipo-opcion">
                <input type="radio" name="tipo" id="tipo_<?php echo $clave; ?>" value="<?php echo $clave; ?>" <?php echo ($clave === $tipo) ? "checked" : ""; ?>>
                <label for="tipo_<?php echo $clave; ?>">
                    <strong><?php echo htmlspecialchars($nombre); ?></strong>
                    <span><?php echo htmlspecialchars($desc); ?></span>
                </label>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <div class="pd-card">
        <h3 style="margin-top:0;">2. Elige el rango de fechas</h3>
        <div class="pd-row">
            <div class="pd-field">
                <label>Desde</label>
                <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
            </div>
            <div class="pd-field">
                <label>Hasta</label>
                <input type="date" name="fecha_final" value="<?php echo htmlspecialchars($fecha_final); ?>" required>
            </div>
            <button type="submit">Generar Reporte (PDF)</button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>