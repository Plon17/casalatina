<?php
$modulo_actual = "gastos";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auditoria.php";

$mensaje = "";
$error = "";
$esAdmin = ($_SESSION["rol"] ?? "") === "administrador";

// Registrar una categoría de gasto nueva (solo Administrador)
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "registrar_gasto") {
    if (!$esAdmin) {
        $error = "Solo un administrador puede crear categorías de gasto.";
    } else {
        $n = (int) $pdo->query("SELECT COUNT(*) AS c FROM gastos")->fetch()["c"] + 1;
        $idGasto = "G" . str_pad($n, 6, "0", STR_PAD_LEFT);
        $stmt = $pdo->prepare("INSERT INTO gastos (ID_gastos, nombre, tipo, descripcion) VALUES (?,?,?,?)");
        $stmt->execute([$idGasto, $_POST["nombre"], $_POST["tipo"], $_POST["descripcion"]]);
        $mensaje = "Categoría de gasto registrada.";
        registrarAuditoria($pdo, "gastos", "Categoría de gasto creada", "$idGasto - " . $_POST["nombre"]);
    }
}

// Registrar un detalle (ocurrencia real de un gasto) — disponible para cualquier rol con acceso al módulo
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "registrar_detalle") {
    $idGastos = $_POST["id_gastos"] ?? "";
    if (!$idGastos) {
        $error = "Selecciona a qué gasto pertenece este detalle.";
    } else {
        $d = (int) $pdo->query("SELECT COUNT(*) AS c FROM gastos_detalles")->fetch()["c"] + 1;
        $idDetg = "GD" . str_pad($d, 6, "0", STR_PAD_LEFT);
        $stmt = $pdo->prepare("INSERT INTO gastos_detalles (ID_detg, fecha, monto, ID_gastos) VALUES (?,?,?,?)");
        $stmt->execute([$idDetg, $_POST["fecha"], $_POST["monto"], $idGastos]);
        $mensaje = "Detalle de gasto registrado.";
        registrarAuditoria($pdo, "gastos", "Gasto registrado", "$idDetg — $idGastos (L. " . $_POST["monto"] . ")");
    }
}

// Categorías de gasto, con el total acumulado de cada una
$gastos = $pdo->query("SELECT g.*, COALESCE(SUM(gd.monto), 0) AS total
                        FROM gastos g
                        LEFT JOIN gastos_detalles gd ON gd.ID_gastos = g.ID_gastos
                        GROUP BY g.ID_gastos
                        ORDER BY g.nombre")->fetchAll(PDO::FETCH_ASSOC);

// Cuál categoría está seleccionada (por defecto, la primera de la lista)
$seleccionado = $_GET["id"] ?? ($gastos[0]["ID_gastos"] ?? null);
$gastoActual = null;
foreach ($gastos as $g) {
    if ($g["ID_gastos"] === $seleccionado) { $gastoActual = $g; break; }
}

$detalles = [];
if ($gastoActual) {
    $stmt = $pdo->prepare("SELECT * FROM gastos_detalles WHERE ID_gastos = ? ORDER BY fecha DESC, ID_detg DESC");
    $stmt->execute([$gastoActual["ID_gastos"]]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$titulo_pagina = "GASTOS";
require_once __DIR__ . "/includes/layout_top.php";
?>

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.pd-row{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;}
.pd-field{display:flex;flex-direction:column;gap:4px;}
.pd-field label{font-size:13px;color:#444;}
.pd-field input,.pd-field select,.pd-field textarea{padding:6px 8px;border:1px solid #ccc;border-radius:4px;min-width:160px;}
.pd-field.chico input{min-width:80px;}
.pd-tabla{width:100%;border-collapse:collapse;}
.pd-tabla th,.pd-tabla td{border:1px solid #ddd;padding:6px 10px;text-align:left;font-size:14px;}
.pd-tabla th{background:var(--color-surface-alt);}
.pd-actions{margin-top:14px;display:flex;gap:10px;}
.fila-seleccionada{background:var(--color-primary-light);}
.fila-clic{cursor:pointer;}
.fila-clic:hover{background:var(--color-primary-light);}
</style>

<p class="titulo-modulo">Gastos</p>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<!-- MITAD DE ARRIBA: categorías de gasto -->
<div class="pd-card">
<h3 style="margin-top:0;">Categorías de gasto</h3>
<table class="pd-tabla">
<tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>Descripción</th><th>Total acumulado</th></tr>
<?php if (count($gastos) === 0): ?>
<tr><td colspan="5">Todavía no hay categorías de gasto registradas.</td></tr>
<?php endif; ?>
<?php foreach ($gastos as $g): ?>
<tr class="fila-clic <?php echo ($gastoActual && $g['ID_gastos'] === $gastoActual['ID_gastos']) ? 'fila-seleccionada' : ''; ?>"
    onclick="window.location.href='gastos.php?id=<?php echo urlencode($g['ID_gastos']); ?>'">
    <td><?php echo htmlspecialchars($g["ID_gastos"]); ?></td>
    <td><?php echo htmlspecialchars($g["nombre"]); ?></td>
    <td><?php echo htmlspecialchars($g["tipo"]); ?></td>
    <td style="max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars($g["descripcion"] ?? ''); ?>">
        <?php echo htmlspecialchars($g["descripcion"] ?: "—"); ?>
    </td>
    <td><?php echo number_format((float) $g["total"], 2); ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php if ($esAdmin): ?>
<h4 style="margin:18px 0 8px 0;">+ Nueva categoría de gasto</h4>
<form method="POST" class="pd-row">
    <input type="hidden" name="accion" value="registrar_gasto">
    <div class="pd-field" style="flex:1; min-width:160px;">
        <label>Nombre</label>
        <input type="text" name="nombre" required>
    </div>
    <div class="pd-field">
        <label>Tipo</label>
        <select name="tipo" required>
            <option value="Publico">Público</option>
            <option value="Operativo">Operativo</option>
        </select>
    </div>
    <div class="pd-field" style="flex:1; min-width:200px;">
        <label>Descripción</label>
        <input type="text" name="descripcion">
    </div>
    <button type="submit">REGISTRAR CATEGORÍA</button>
</form>
<?php endif; ?>
</div>

<!-- MITAD DE ABAJO: detalle del gasto seleccionado -->
<div class="pd-card">
<?php if (!$gastoActual): ?>
    <p>Selecciona una categoría de gasto arriba para ver y registrar su detalle.</p>
<?php else: ?>
    <h3 style="margin-top:0;">
        Detalle de: <?php echo htmlspecialchars($gastoActual["nombre"]); ?>
        <span style="font-weight:400; color:#777; font-size:14px;">(<?php echo htmlspecialchars($gastoActual["tipo"]); ?>)</span>
    </h3>
    <?php if ($gastoActual["descripcion"]): ?>
        <p style="color:#666; margin-top:-6px;"><?php echo htmlspecialchars($gastoActual["descripcion"]); ?></p>
    <?php endif; ?>

    <form method="POST" class="pd-row">
        <input type="hidden" name="accion" value="registrar_detalle">
        <input type="hidden" name="id_gastos" value="<?php echo htmlspecialchars($gastoActual["ID_gastos"]); ?>">
        <div class="pd-field">
            <label>Fecha</label>
            <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="pd-field chico">
            <label>Monto</label>
            <input type="number" step="0.01" name="monto" required>
        </div>
        <button type="submit">REGISTRAR DETALLE</button>
    </form>

    <table class="pd-tabla" style="margin-top:14px;">
    <tr><th>ID Detalle</th><th>Fecha</th><th>Monto</th></tr>
    <?php if (count($detalles) === 0): ?>
    <tr><td colspan="3">Todavía no hay movimientos registrados para esta categoría.</td></tr>
    <?php endif; ?>
    <?php foreach ($detalles as $d): ?>
    <tr>
        <td><?php echo htmlspecialchars($d["ID_detg"]); ?></td>
        <td><?php echo htmlspecialchars($d["fecha"]); ?></td>
        <td><?php echo number_format((float) $d["monto"], 2); ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (count($detalles) > 0): ?>
    <tr>
        <td colspan="2" style="text-align:right; font-weight:600;">Total:</td>
        <td style="font-weight:600;"><?php echo number_format((float) $gastoActual["total"], 2); ?></td>
    </tr>
    <?php endif; ?>
    </table>
<?php endif; ?>
</div>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>