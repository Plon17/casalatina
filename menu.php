<?php
$modulo_actual = "menu";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auditoria.php";

$mensaje = "";
$error = "";
$esAdmin = ($_SESSION["rol"] ?? "") === "administrador";

const PRECIO_MAX = 5000;
const CANTIDAD_INSUMO_MAX = 500;

// Valida el precio del plato y las cantidades de cada insumo de la receta.
// Devuelve un mensaje de error, o "" si todo está bien.
function validarMenu($precio, string $ingredientesJson): string {
    if (!is_numeric($precio) || (float) $precio < 0 || (float) $precio > PRECIO_MAX) {
        return "El precio debe ser un número entre 0 y " . number_format(PRECIO_MAX, 0) . ".";
    }
    $ingredientes = json_decode($ingredientesJson, true) ?: [];
    foreach ($ingredientes as $ing) {
        $cantidad = (float) ($ing["cantidad"] ?? 0);
        if ($cantidad <= 0 || $cantidad > CANTIDAD_INSUMO_MAX) {
            return "La cantidad de cada insumo debe ser mayor a 0 y no pasar de " . CANTIDAD_INSUMO_MAX . ".";
        }
    }
    return "";
}

// Reemplaza los insumos de stock (para descuento automático) de un item del menú a partir de un JSON.
function guardarIngredientes(PDO $pdo, string $idMenu, string $json): void {
    $ingredientes = json_decode($json, true) ?: [];
    $pdo->prepare("DELETE FROM menu_ingredientes WHERE ID_Menu = ?")->execute([$idMenu]);
    $stmt = $pdo->prepare("INSERT INTO menu_ingredientes (ID_Menu, ID_Producto, cantidad_necesaria) VALUES (?,?,?)");
    foreach ($ingredientes as $ing) {
        if (!empty($ing["id_producto"]) && (float) ($ing["cantidad"] ?? 0) > 0) {
            $stmt->execute([$idMenu, $ing["id_producto"], $ing["cantidad"]]);
        }
    }
}

// Receta de cocina (texto libre): pasos de preparación, tiempo, porciones y notas.
// Los ingredientes ya no se repiten aquí — se usan directo los "Insumos de stock"
// (menu_ingredientes) como la única lista de ingredientes, así no hay que escribirlos dos veces.
function guardarReceta(PDO $pdo, string $idMenu, string $preparacion, string $tiempo, string $porciones, string $notas): void {
    $preparacion = trim($preparacion);
    $tiempo = trim($tiempo);
    $porciones = trim($porciones);
    $notas = trim($notas);

    if ($preparacion === "" && $tiempo === "" && $porciones === "" && $notas === "") {
        $pdo->prepare("DELETE FROM menu_receta WHERE ID_Menu = ?")->execute([$idMenu]);
        return;
    }

    $stmt = $pdo->prepare("INSERT INTO menu_receta (ID_Menu, preparacion, tiempo_preparacion, porciones, notas)
                            VALUES (?,?,?,?,?)
                            ON CONFLICT(ID_Menu) DO UPDATE SET
                                preparacion = excluded.preparacion,
                                tiempo_preparacion = excluded.tiempo_preparacion,
                                porciones = excluded.porciones,
                                notas = excluded.notas");
    $stmt->execute([$idMenu, $preparacion ?: null, $tiempo ?: null, $porciones ?: null, $notas ?: null]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {

    if (!$esAdmin && in_array($_POST["accion"], ["guardar", "editar", "eliminar", "reactivar"], true)) {
        $error = "Solo un administrador puede modificar el menú.";
        registrarAuditoria($pdo, "menu", "Intento de modificación denegado", "Acción: " . $_POST["accion"] . " (rol: " . ($_SESSION["rol"] ?? "?") . ")");
    } else {

    if ($_POST["accion"] === "guardar") {
        $validacion = validarMenu($_POST["precio"] ?? null, $_POST["ingredientes_json"] ?? "[]");
        if ($validacion !== "") {
            $error = $validacion;
        } else {
        // El ID es la llave primaria de todas formas, pero lo revisamos antes para
        // dar un mensaje claro en vez de una excepción críptica de MySQL.
        $stmtExiste = $pdo->prepare("SELECT 1 FROM menu WHERE ID_Menu = ?");
        $stmtExiste->execute([$_POST["id_menu"]]);
        if ($stmtExiste->fetch()) {
            $error = "Ya existe un plato con el ID \"" . htmlspecialchars($_POST["id_menu"]) . "\". Usa uno distinto.";
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO menu (ID_Menu, nombre, precio, tipo, descripcion_men, ID_Producto)
                                        VALUES (?,?,?,?,?,NULL)");
                $stmt->execute([$_POST["id_menu"], $_POST["nombre"], $_POST["precio"], $_POST["tipo"], $_POST["descripcion"]]);
                guardarIngredientes($pdo, $_POST["id_menu"], $_POST["ingredientes_json"] ?? "[]");
                guardarReceta(
                    $pdo, $_POST["id_menu"],
                    $_POST["receta_preparacion"] ?? "",
                    $_POST["receta_tiempo"] ?? "", $_POST["receta_porciones"] ?? "", $_POST["receta_notas"] ?? ""
                );
                $pdo->commit();
                $mensaje = "Item agregado correctamente.";
                registrarAuditoria($pdo, "menu", "Item agregado", $_POST["id_menu"] . " - " . $_POST["nombre"]);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error al agregar: " . $e->getMessage();
            }
        }
        }
    }

    if ($_POST["accion"] === "editar") {
        $validacion = validarMenu($_POST["precio"] ?? null, $_POST["ingredientes_json"] ?? "[]");
        if ($validacion !== "") {
            $error = $validacion;
        } else {
        $pdo->beginTransaction();
        try {
            // Traemos precio y receta anteriores para poder marcarlo en la auditoría si cambiaron
            $stmtAnterior = $pdo->prepare("SELECT precio FROM menu WHERE ID_Menu = ?");
            $stmtAnterior->execute([$_POST["id_menu"]]);
            $precioAnterior = $stmtAnterior->fetch()["precio"] ?? null;

            $stmtRecetaAnterior = $pdo->prepare("SELECT ID_Producto, cantidad_necesaria FROM menu_ingredientes WHERE ID_Menu = ? ORDER BY ID_Producto");
            $stmtRecetaAnterior->execute([$_POST["id_menu"]]);
            $recetaAnteriorNorm = [];
            foreach ($stmtRecetaAnterior->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $recetaAnteriorNorm[$r["ID_Producto"]] = (float) $r["cantidad_necesaria"];
            }
            ksort($recetaAnteriorNorm);

            $stmtCocinaAnterior = $pdo->prepare("SELECT preparacion, tiempo_preparacion, porciones, notas FROM menu_receta WHERE ID_Menu = ?");
            $stmtCocinaAnterior->execute([$_POST["id_menu"]]);
            $cocinaAnterior = $stmtCocinaAnterior->fetch(PDO::FETCH_ASSOC) ?: ["preparacion" => null, "tiempo_preparacion" => null, "porciones" => null, "notas" => null];

            $stmt = $pdo->prepare("UPDATE menu SET nombre=?, precio=?, tipo=?, descripcion_men=? WHERE ID_Menu=?");
            $stmt->execute([$_POST["nombre"], $_POST["precio"], $_POST["tipo"], $_POST["descripcion"], $_POST["id_menu"]]);
            guardarIngredientes($pdo, $_POST["id_menu"], $_POST["ingredientes_json"] ?? "[]");
            guardarReceta(
                $pdo, $_POST["id_menu"],
                $_POST["receta_preparacion"] ?? "",
                $_POST["receta_tiempo"] ?? "", $_POST["receta_porciones"] ?? "", $_POST["receta_notas"] ?? ""
            );
            $pdo->commit();
            $mensaje = "Item actualizado correctamente.";

            $detalleAudit = $_POST["id_menu"] . " - " . $_POST["nombre"];
            if ($precioAnterior !== null && (float) $precioAnterior !== (float) $_POST["precio"]) {
                $detalleAudit .= " — precio cambiado de L. $precioAnterior a L. " . $_POST["precio"];
            }

            $recetaNuevaNorm = [];
            foreach (json_decode($_POST["ingredientes_json"] ?? "[]", true) ?: [] as $ing) {
                if (!empty($ing["id_producto"])) {
                    $recetaNuevaNorm[$ing["id_producto"]] = (float) ($ing["cantidad"] ?? 0);
                }
            }
            ksort($recetaNuevaNorm);
            if ($recetaAnteriorNorm !== $recetaNuevaNorm) {
                $detalleAudit .= " — insumos de stock modificados";
            }

            $cocinaCambio = trim($cocinaAnterior["preparacion"] ?? "") !== trim($_POST["receta_preparacion"] ?? "")
                || trim($cocinaAnterior["tiempo_preparacion"] ?? "") !== trim($_POST["receta_tiempo"] ?? "")
                || trim($cocinaAnterior["porciones"] ?? "") !== trim($_POST["receta_porciones"] ?? "")
                || trim($cocinaAnterior["notas"] ?? "") !== trim($_POST["receta_notas"] ?? "");
            if ($cocinaCambio) {
                $detalleAudit .= " — receta de cocina modificada";
            }

            registrarAuditoria($pdo, "menu", "Item editado", $detalleAudit);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al actualizar: " . $e->getMessage();
        }
        }
    }

    if ($_POST["accion"] === "eliminar") {
        // Ya no se elimina ningún item del menú, solo se desactiva: se conserva el
        // historial de pedidos que ya lo usaron, y deja de aparecer al armar pedidos nuevos.
        $pdo->prepare("UPDATE menu SET activo = 0 WHERE ID_Menu=?")->execute([$_POST["id_menu"]]);
        $mensaje = "Item desactivado: ya no aparece al armar pedidos nuevos.";
        registrarAuditoria($pdo, "menu", "Item desactivado", $_POST["id_menu"]);
    }

    if ($_POST["accion"] === "reactivar") {
        $pdo->prepare("UPDATE menu SET activo = 1 WHERE ID_Menu=?")->execute([$_POST["id_menu"]]);
        $mensaje = "Item reactivado.";
        registrarAuditoria($pdo, "menu", "Item reactivado", $_POST["id_menu"]);
    }
    }
}

$buscar = trim($_GET["buscar"] ?? "");
$verInactivos = ($_GET["ver"] ?? "") === "inactivos";

$condiciones = ["activo = ?"];
$params = [$verInactivos ? 0 : 1];
if ($buscar !== "") {
    $condiciones[] = "(nombre LIKE ? OR ID_Menu LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}
$stmt = $pdo->prepare("SELECT * FROM menu WHERE " . implode(" AND ", $condiciones));
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Adjuntamos los insumos de stock de cada plato (para mostrarlos y para poder editarlos)
$ingStmt = $pdo->prepare("SELECT i.ID_Producto, i.cantidad_necesaria, p.nombre_pro
                           FROM menu_ingredientes i JOIN producto p ON p.ID_Producto = i.ID_Producto
                           WHERE i.ID_Menu = ?");
// Adjuntamos la receta de cocina (texto libre) de cada plato, para prellenar el form al editar
$recetaStmt = $pdo->prepare("SELECT ingredientes, preparacion, tiempo_preparacion, porciones, notas
                              FROM menu_receta WHERE ID_Menu = ?");
foreach ($items as &$it) {
    $ingStmt->execute([$it["ID_Menu"]]);
    $it["ingredientes"] = $ingStmt->fetchAll(PDO::FETCH_ASSOC);

    $recetaStmt->execute([$it["ID_Menu"]]);
    $receta = $recetaStmt->fetch(PDO::FETCH_ASSOC);
    $it["receta_preparacion"] = $receta["preparacion"] ?? "";
    $it["receta_tiempo"] = $receta["tiempo_preparacion"] ?? "";
    $it["receta_porciones"] = $receta["porciones"] ?? "";
    $it["receta_notas"] = $receta["notas"] ?? "";
    $it["tiene_receta"] = ($it["receta_preparacion"] !== "" || count($it["ingredientes"]) > 0);
}
unset($it);

// Productos de stock disponibles para armar recetas (solo activos: no tiene sentido
// armar una receta nueva con un insumo que ya no se compra)
$productos = $pdo->query("SELECT ID_Producto, nombre_pro, cantidad_pro FROM producto WHERE activo = 1")->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "MENÚ";
require_once __DIR__ . "/includes/layout_top.php";
?>

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.pd-row{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;}
.pd-field{display:flex;flex-direction:column;gap:4px;}
.pd-field label{font-size:13px;color:#444;}
.pd-field input,.pd-field select,.pd-field textarea{padding:6px 8px;border:1px solid #ccc;border-radius:4px;min-width:150px;}
.pd-field.chico input{min-width:70px;}
.pd-tabla{width:100%;border-collapse:collapse;}
.pd-tabla th,.pd-tabla td{border:1px solid #ddd;padding:6px 10px;text-align:left;font-size:14px;}
.pd-tabla th{background:var(--color-surface-alt);}
.pd-resultados{max-height:150px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;}
.pd-actions{margin-top:14px;display:flex;gap:10px;}
.receta-badge{background:var(--color-success-bg);color:var(--color-success);padding:1px 8px;border-radius:10px;font-size:12px;}
.receta-vacia{background:var(--color-surface-alt);color:#888;padding:1px 8px;border-radius:10px;font-size:12px;}
.toggle-inactivos{background:none;border:1px solid #ccc;padding:6px 12px;border-radius:4px;cursor:pointer;text-decoration:none;color:#333;font-size:13px;}
.toggle-inactivos.activo{background:var(--color-surface-alt);font-weight:bold;}
</style>

<p class="titulo-modulo">Menú</p>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<?php if ($esAdmin): ?>
<div class="pd-card">
<h3 style="margin-top:0;" id="tituloForm">Agregar item nuevo</h3>
<form method="POST" id="formMenu">
    <input type="hidden" name="accion" id="accion" value="guardar">
    <input type="hidden" name="ingredientes_json" id="ingredientes_json">

    <div class="pd-row">
        <div class="pd-field">
            <label>ID Menú</label>
            <input type="text" name="id_menu" id="id_menu" required autocomplete="off">
        </div>
        <div class="pd-field" style="flex:1; min-width:180px;">
            <label>Nombre</label>
            <input type="text" name="nombre" id="nombre" required>
        </div>
        <div class="pd-field chico">
            <label>Precio (máx. <?php echo number_format(PRECIO_MAX, 0); ?>)</label>
            <input type="number" step="0.01" name="precio" id="precio" min="0" max="<?php echo PRECIO_MAX; ?>" required>
        </div>
        <div class="pd-field">
            <label>Tipo</label>
            <select name="tipo" id="tipo" required>
                <option value="Platillo">Platillo</option>
                <option value="Bebida">Bebida</option>
            </select>
        </div>
        <div class="pd-field" style="flex:1; min-width:200px;">
            <label>Descripción</label>
            <input type="text" name="descripcion" id="descripcion">
        </div>
    </div>

    <hr style="margin:18px 0; border:none; border-top:1px solid #eee;">

    <h4 style="margin:0 0 8px 0;">Preparación</h4>
    <div class="pd-row">
        <div class="pd-field" style="flex:1; min-width:280px;">
            <label>Pasos de preparación</label>
            <textarea name="receta_preparacion" id="receta_preparacion" rows="5" style="width:100%;"></textarea>
        </div>
    </div>
    <div class="pd-row" style="margin-top:10px;">
        <div class="pd-field chico">
            <label>Tiempo de preparación</label>
            <input type="text" name="receta_tiempo" id="receta_tiempo" placeholder="ej. 20 min">
        </div>
        <div class="pd-field chico">
            <label>Porciones</label>
            <input type="text" name="receta_porciones" id="receta_porciones" placeholder="ej. 1 persona">
        </div>
        <div class="pd-field" style="flex:1; min-width:220px;">
            <label>Notas</label>
            <input type="text" name="receta_notas" id="receta_notas">
        </div>
    </div>

    <hr style="margin:18px 0; border:none; border-top:1px solid #eee;">

    <h4 style="margin:0 0 8px 0;">Ingredientes (también descuentan del stock automáticamente)</h4>
    <p style="margin:0 0 10px 0; color:#777; font-size:13px;">Esta lista es la única fuente de ingredientes: se usa tanto para la receta impresa como para descontar inventario — no hace falta escribirlos en otro lado. Cantidad máxima por insumo: <?php echo CANTIDAD_INSUMO_MAX; ?>.</p>
    <div class="pd-row">
        <div class="pd-field" style="flex:1; min-width:220px;">
            <label>Buscar insumo</label>
            <input type="text" id="buscar_insumo" placeholder="Nombre o ID" autocomplete="off">
        </div>
        <div class="pd-field chico">
            <label>Cantidad usada</label>
            <input type="number" step="0.01" id="cantidad_insumo" value="1" min="0.01" max="<?php echo CANTIDAD_INSUMO_MAX; ?>">
        </div>
        <button type="button" onclick="agregarIngrediente()">Agregar a la receta</button>
    </div>

    <div class="pd-resultados" style="margin-top:10px;">
        <table class="pd-tabla" id="tablaResultadosInsumo">
        <tr><th>ID</th><th>Nombre</th><th>Stock actual</th></tr>
        </table>
    </div>

    <table class="pd-tabla" id="tablaReceta" style="margin-top:12px;">
    <tr><th>Insumo</th><th>Cantidad necesaria</th><th></th></tr>
    </table>

    <div class="pd-actions">
        <button type="submit" id="btnGuardar" onclick="return prepararEnvio('guardar')">GUARDAR</button>
        <button type="submit" id="btnEditar" style="display:none;" onclick="return prepararEnvio('editar')">GUARDAR EDICIÓN</button>
        <button type="button" id="btnNuevo" style="display:none;" onclick="nuevoItem()">+ NUEVO ITEM</button>
    </div>
</form>
</div>
<?php else: ?>
<div class="pd-card">
<p style="margin:0; color:#777;">Vista de solo lectura. Solo un administrador puede crear, editar o desactivar items del menú.</p>
</div>
<?php endif; ?>

<div class="pd-card">
<form method="GET" class="pd-row" style="margin-bottom:0;">
    <input type="hidden" name="ver" value="<?php echo $verInactivos ? "inactivos" : ""; ?>">
    <div class="pd-field" style="flex:1; min-width:220px;">
        <label>Buscar</label>
        <input type="text" name="buscar" placeholder="Buscar por nombre o ID" value="<?php echo htmlspecialchars($buscar); ?>">
    </div>
    <button type="submit">BUSCAR</button>
    <?php if ($buscar): ?><a class="btn" href="menu.php<?php echo $verInactivos ? '?ver=inactivos' : ''; ?>">Limpiar</a><?php endif; ?>
    <div style="flex:1;"></div>
    <a class="toggle-inactivos <?php echo !$verInactivos ? 'activo' : ''; ?>" href="menu.php<?php echo $buscar ? '?buscar=' . urlencode($buscar) : ''; ?>">Activos</a>
    <a class="toggle-inactivos <?php echo $verInactivos ? 'activo' : ''; ?>" href="menu.php?ver=inactivos<?php echo $buscar ? '&buscar=' . urlencode($buscar) : ''; ?>">Ver desactivados</a>
</form>
</div>

<div class="pd-card">
<table class="pd-tabla">
<tr><th>ID_Menu</th><th>Nombre</th><th>Precio</th><th>Tipo</th><th>Descripción</th><th>Insumos (stock)</th><th>Estado</th><th></th></tr>
<?php if (count($items) === 0): ?>
<tr><td colspan="8"><?php echo $verInactivos ? "No hay items desactivados." : "No se encontraron items."; ?></td></tr>
<?php endif; ?>
<?php foreach ($items as $it): ?>
<tr<?php echo !$it["activo"] ? ' style="opacity:.55;"' : ''; ?>>
    <td><?php echo htmlspecialchars($it["ID_Menu"]); ?></td>
    <td><?php echo htmlspecialchars($it["nombre"]); ?></td>
    <td><?php echo number_format((float) $it["precio"], 2); ?></td>
    <td><?php echo htmlspecialchars($it["tipo"]); ?></td>
    <td><?php echo htmlspecialchars($it["descripcion_men"]); ?></td>
    <td>
        <?php if (count($it["ingredientes"]) > 0): ?>
            <span class="receta-badge" title="<?php
                echo htmlspecialchars(implode(", ", array_map(
                    fn($i) => $i["nombre_pro"] . " x" . $i["cantidad_necesaria"],
                    $it["ingredientes"]
                )));
            ?>"><?php echo count($it["ingredientes"]); ?> insumo(s)</span>
        <?php else: ?>
            <span class="receta-vacia">sin insumos</span>
        <?php endif; ?>
    </td>
    <td><?php echo $it["activo"] ? "Activo" : "Inactivo"; ?></td>
    <td>
        <a href="receta_pdf.php?id=<?php echo urlencode($it['ID_Menu']); ?>" target="_blank">
            <button type="button">Receta (PDF)</button>
        </a>
        <?php if ($esAdmin): ?>
        <button type="button" onclick="cargarFila(<?php echo htmlspecialchars(json_encode($it)); ?>)">EDITAR</button>
        <?php if ($it["activo"]): ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Desactivar este item?');">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id_menu" value="<?php echo htmlspecialchars($it["ID_Menu"]); ?>">
            <button type="submit">DESACTIVAR</button>
        </form>
        <?php else: ?>
        <form method="POST" style="display:inline">
            <input type="hidden" name="accion" value="reactivar">
            <input type="hidden" name="id_menu" value="<?php echo htmlspecialchars($it["ID_Menu"]); ?>">
            <button type="submit">REACTIVAR</button>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<script>
const productos = <?php echo json_encode($productos); ?>;
let idInsumoSeleccionado = "";
let nombreInsumoSeleccionado = "";
let receta = [];

document.getElementById("buscar_insumo")?.addEventListener("keyup", function () {
    const texto = this.value.toLowerCase();
    const tabla = document.getElementById("tablaResultadosInsumo");
    tabla.innerHTML = "<tr><th>ID</th><th>Nombre</th><th>Stock actual</th></tr>";
    if (texto === "") return;

    productos.filter(p => p.nombre_pro.toLowerCase().includes(texto) || p.ID_Producto.toLowerCase().includes(texto))
        .forEach(p => {
            const fila = tabla.insertRow();
            fila.style.cursor = "pointer";
            fila.innerHTML = `<td>${p.ID_Producto}</td><td>${p.nombre_pro}</td><td>${p.cantidad_pro}</td>`;
            fila.onclick = () => {
                idInsumoSeleccionado = p.ID_Producto;
                nombreInsumoSeleccionado = p.nombre_pro;
                document.getElementById("buscar_insumo").value = p.nombre_pro + " (" + p.ID_Producto + ")";
                tabla.innerHTML = "<tr><th>ID</th><th>Nombre</th><th>Stock actual</th></tr>";
            };
        });
});

// Valida y agrega el insumo seleccionado a la receta de stock del item del menú en edición.
function agregarIngrediente() {
    const cantidad = parseFloat(document.getElementById("cantidad_insumo").value);
    const CANTIDAD_INSUMO_MAX = <?php echo CANTIDAD_INSUMO_MAX; ?>;
    if (!idInsumoSeleccionado || isNaN(cantidad) || cantidad <= 0) {
        alert("Busca y selecciona un insumo, y pon una cantidad válida.");
        return;
    }
    if (cantidad > CANTIDAD_INSUMO_MAX) {
        alert("La cantidad no puede pasar de " + CANTIDAD_INSUMO_MAX + ".");
        return;
    }
    if (receta.some(r => r.id_producto === idInsumoSeleccionado)) {
        alert("Ese insumo ya está en la receta. Quítalo primero si quieres cambiar la cantidad.");
        return;
    }
    receta.push({ id_producto: idInsumoSeleccionado, nombre: nombreInsumoSeleccionado, cantidad: cantidad });
    idInsumoSeleccionado = "";
    document.getElementById("buscar_insumo").value = "";
    document.getElementById("cantidad_insumo").value = 1;
    renderReceta();
}

function quitarIngrediente(idx) {
    receta.splice(idx, 1);
    renderReceta();
}

function renderReceta() {
    const tabla = document.getElementById("tablaReceta");
    if (!tabla) return;
    tabla.innerHTML = "<tr><th>Insumo</th><th>Cantidad necesaria</th><th></th></tr>";
    if (receta.length === 0) {
        const fila = tabla.insertRow();
        fila.innerHTML = `<td colspan="3">Sin insumos agregados (el plato no descontará stock automáticamente).</td>`;
        return;
    }
    receta.forEach((r, idx) => {
        const fila = tabla.insertRow();
        fila.innerHTML = `<td>${r.nombre}</td><td>${r.cantidad}</td><td><button type="button" onclick="quitarIngrediente(${idx})">Quitar</button></td>`;
    });
}

function prepararEnvio(accion) {
    document.getElementById("accion").value = accion;
    document.getElementById("ingredientes_json").value = JSON.stringify(receta);
    return true;
}

// Carga los datos de un item del menú (y su receta de cocina) en el formulario para editarlo.
function cargarFila(item) {
    document.getElementById("tituloForm").textContent = "Editando " + item.ID_Menu;
    document.getElementById("id_menu").value = item.ID_Menu;
    document.getElementById("id_menu").readOnly = true;
    document.getElementById("nombre").value = item.nombre;
    document.getElementById("precio").value = item.precio;
    document.getElementById("tipo").value = item.tipo;
    document.getElementById("descripcion").value = item.descripcion_men;

    document.getElementById("receta_preparacion").value = item.receta_preparacion || "";
    document.getElementById("receta_tiempo").value = item.receta_tiempo || "";
    document.getElementById("receta_porciones").value = item.receta_porciones || "";
    document.getElementById("receta_notas").value = item.receta_notas || "";

    receta = (item.ingredientes || []).map(i => ({
        id_producto: i.ID_Producto, nombre: i.nombre_pro, cantidad: parseFloat(i.cantidad_necesaria)
    }));
    renderReceta();

    document.getElementById("btnGuardar").style.display = "none";
    document.getElementById("btnEditar").style.display = "inline-block";
    document.getElementById("btnNuevo").style.display = "inline-block";
    document.getElementById("formMenu").scrollIntoView({ behavior: "smooth", block: "center" });
}

function nuevoItem() {
    document.getElementById("tituloForm").textContent = "Agregar item nuevo";
    document.getElementById("formMenu").reset();
    document.getElementById("id_menu").readOnly = false;
    receta = [];
    renderReceta();
    document.getElementById("btnGuardar").style.display = "inline-block";
    document.getElementById("btnEditar").style.display = "none";
    document.getElementById("btnNuevo").style.display = "none";
}

renderReceta();
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>