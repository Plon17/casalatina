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
    "auditoria"   => ["Auditoría", "Bitácora de acciones, con filtros opcionales"],
    "inventario"  => ["Inventario Actual", "Foto del stock ahora mismo: cantidades y valor total"],
];

$modulosDisponibles = $pdo->query("SELECT DISTINCT modulo FROM auditoria ORDER BY modulo")->fetchAll(PDO::FETCH_COLUMN);
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
                <input type="radio" name="tipo" id="tipo_<?php echo $clave; ?>" value="<?php echo $clave; ?>"
                    onchange="actualizarFiltrosExtra()" <?php echo ($clave === $tipo) ? "checked" : ""; ?>>
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
        <div id="notaInventario" style="display:none; color:#666; font-size:13px; margin-bottom:10px;">
            Este reporte es una foto del inventario actual, no depende del rango de fechas — puedes dejarlo como esté.
        </div>
        <div class="pd-row">
            <div class="pd-field">
                <label>Desde</label>
                <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
            </div>
            <div class="pd-field">
                <label>Hasta</label>
                <input type="date" name="fecha_final" value="<?php echo htmlspecialchars($fecha_final); ?>" required>
            </div>
        </div>

        <div id="filtrosAuditoria" class="pd-row" style="margin-top:14px; display:none; border-top:1px solid #eee; padding-top:14px;">
            <div class="pd-field">
                <label>Usuario (opcional)</label>
                <input type="text" name="usuario" value="<?php echo htmlspecialchars($_GET['usuario'] ?? ''); ?>">
            </div>
            <div class="pd-field">
                <label>Módulo (opcional)</label>
                <select name="modulo">
                    <option value="">-- todos --</option>
                    <?php foreach ($modulosDisponibles as $m): ?>
                    <option value="<?php echo htmlspecialchars($m); ?>" <?php echo (($_GET['modulo'] ?? '') === $m) ? "selected" : ""; ?>><?php echo htmlspecialchars($m); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pd-field" style="flex:1; min-width:200px;">
                <label>Buscar en acción/detalle (opcional)</label>
                <input type="text" name="texto" value="<?php echo htmlspecialchars($_GET['texto'] ?? ''); ?>">
            </div>
        </div>

        <div class="pd-actions" style="margin-top:14px;">
            <button type="submit">Generar Reporte (PDF)</button>
        </div>
    </div>
</form>

<script>
function actualizarFiltrosExtra() {
    const tipo = document.querySelector('input[name="tipo"]:checked')?.value;
    document.getElementById("filtrosAuditoria").style.display = (tipo === "auditoria") ? "flex" : "none";
    document.getElementById("notaInventario").style.display = (tipo === "inventario") ? "block" : "none";
}
actualizarFiltrosExtra();
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>