<?php
$modulo_actual = "auditoria";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

// Solo Administrador (defensa extra además de includes/auth.php)
if (($_SESSION["rol"] ?? "") !== "administrador") {
    header("Location: index.php");
    exit;
}

$usuario = trim($_GET["usuario"] ?? "");
$modulo = trim($_GET["modulo"] ?? "");
$texto = trim($_GET["texto"] ?? "");
$desde = $_GET["desde"] ?? "";
$hasta = $_GET["hasta"] ?? "";

$condiciones = [];
$params = [];
if ($usuario !== "") { $condiciones[] = "usuario LIKE ?"; $params[] = "%$usuario%"; }
if ($modulo !== "") { $condiciones[] = "modulo = ?"; $params[] = $modulo; }
if ($texto !== "") { $condiciones[] = "(accion LIKE ? OR detalle LIKE ?)"; $params[] = "%$texto%"; $params[] = "%$texto%"; }
if ($desde !== "") { $condiciones[] = "fecha_hora >= ?"; $params[] = $desde . " 00:00:00"; }
if ($hasta !== "") { $condiciones[] = "fecha_hora <= ?"; $params[] = $hasta . " 23:59:59"; }

$sql = "SELECT * FROM auditoria" . ($condiciones ? " WHERE " . implode(" AND ", $condiciones) : "") . " ORDER BY fecha_hora DESC LIMIT 300";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$modulosDisponibles = $pdo->query("SELECT DISTINCT modulo FROM auditoria ORDER BY modulo")->fetchAll(PDO::FETCH_COLUMN);

$titulo_pagina = "AUDITORÍA";
require_once __DIR__ . "/includes/layout_top.php";
?>

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.pd-row{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;}
.pd-field{display:flex;flex-direction:column;gap:4px;}
.pd-field label{font-size:13px;color:#444;}
.pd-field input,.pd-field select{padding:6px 8px;border:1px solid #ccc;border-radius:4px;min-width:150px;}
.pd-tabla{width:100%;border-collapse:collapse;}
.pd-tabla th,.pd-tabla td{border:1px solid #ddd;padding:6px 10px;text-align:left;font-size:13px;}
.pd-tabla th{background:var(--color-surface-alt);}
.badge-modulo{background:var(--color-info-bg);color:var(--color-info);padding:1px 8px;border-radius:10px;font-size:12px;}
</style>

<p class="titulo-modulo">Auditoría</p>
<p style="color:#777; font-size:13px;">Registro de solo lectura — muestra los últimos 300 movimientos que coincidan con el filtro.</p>

<div class="pd-card">
<form method="GET" class="pd-row">
    <div class="pd-field"><label>Usuario</label><input type="text" name="usuario" value="<?php echo htmlspecialchars($usuario); ?>"></div>
    <div class="pd-field">
        <label>Módulo</label>
        <select name="modulo">
            <option value="">-- todos --</option>
            <?php foreach ($modulosDisponibles as $m): ?>
            <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $modulo === $m ? "selected" : ""; ?>><?php echo htmlspecialchars($m); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pd-field" style="flex:1; min-width:200px;"><label>Buscar en acción/detalle</label><input type="text" name="texto" value="<?php echo htmlspecialchars($texto); ?>"></div>
    <div class="pd-field"><label>Desde</label><input type="date" name="desde" value="<?php echo htmlspecialchars($desde); ?>"></div>
    <div class="pd-field"><label>Hasta</label><input type="date" name="hasta" value="<?php echo htmlspecialchars($hasta); ?>"></div>
    <button type="submit">BUSCAR</button>
    <?php if ($usuario || $modulo || $texto || $desde || $hasta): ?><a href="auditoria.php" style="align-self:center;">Limpiar</a><?php endif; ?>
</form>
</div>

<div class="pd-card">
<table class="pd-tabla">
<tr><th>Fecha/Hora</th><th>Usuario</th><th>Empleado</th><th>Módulo</th><th>Acción</th><th>Detalle</th></tr>
<?php if (count($registros) === 0): ?>
<tr><td colspan="6">No hay movimientos que coincidan con el filtro.</td></tr>
<?php endif; ?>
<?php foreach ($registros as $r): ?>
<tr>
    <td><?php echo htmlspecialchars($r["fecha_hora"]); ?></td>
    <td><?php echo htmlspecialchars($r["usuario"] ?? "—"); ?></td>
    <td><?php echo htmlspecialchars($r["cod_empleado"] ?? "—"); ?></td>
    <td><span class="badge-modulo"><?php echo htmlspecialchars($r["modulo"]); ?></span></td>
    <td><?php echo htmlspecialchars($r["accion"]); ?></td>
    <td><?php echo htmlspecialchars($r["detalle"]); ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>