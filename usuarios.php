<?php
$modulo_actual = "usuarios";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auditoria.php";

// Solo Administrador entra aquí (defensa extra además de includes/auth.php,
// por si algún día se agrega "usuarios" a modulos_empleado por error)
if (($_SESSION["rol"] ?? "") !== "administrador") {
    header("Location: index.php");
    exit;
}

$mensaje = "";
$error = "";

// ---------- EMPLEADOS ----------

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "guardar_empleado") {
    $puestoFinal = ($_POST["puesto_empl"] === "Otro" && trim($_POST["puesto_otro"] ?? "") !== "")
        ? trim($_POST["puesto_otro"]) : $_POST["puesto_empl"];
    $max = (int) $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTR(cod_empleado,2) AS INTEGER)),0) AS m FROM empleado")->fetch()["m"];
    $codEmpleado = "E" . str_pad($max + 1, 3, "0", STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO empleado (cod_empleado, nombre_empl, puesto_empl, salario_empl) VALUES (?,?,?,?)");
    $stmt->execute([$codEmpleado, $_POST["nombre_empl"], $puestoFinal, $_POST["salario_empl"] ?: null]);
    $mensaje = "Empleado agregado ($codEmpleado).";
    registrarAuditoria($pdo, "usuarios", "Empleado agregado", "$codEmpleado - " . $_POST["nombre_empl"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "editar_empleado") {
    $puestoFinal = ($_POST["puesto_empl"] === "Otro" && trim($_POST["puesto_otro"] ?? "") !== "")
        ? trim($_POST["puesto_otro"]) : $_POST["puesto_empl"];
    $stmt = $pdo->prepare("UPDATE empleado SET nombre_empl=?, puesto_empl=?, salario_empl=? WHERE cod_empleado=?");
    $stmt->execute([$_POST["nombre_empl"], $puestoFinal, $_POST["salario_empl"] ?: null, $_POST["cod_empleado"]]);
    $mensaje = "Empleado actualizado.";
    registrarAuditoria($pdo, "usuarios", "Empleado editado", $_POST["cod_empleado"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "eliminar_empleado") {
    $cod = $_POST["cod_empleado"];
    // pedido, factura y usuarios referencian a empleado — si ya tiene actividad
    // asociada, lo desactivamos en vez de borrarlo (mismo patrón que menu/producto)
    $usos = [];
    $stmt1 = $pdo->prepare("SELECT COUNT(*) AS c FROM pedido WHERE cod_empleado=?"); $stmt1->execute([$cod]);
    if ((int) $stmt1->fetch()["c"] > 0) $usos[] = "pedidos";
    $stmt2 = $pdo->prepare("SELECT COUNT(*) AS c FROM factura WHERE cod_empleado=?"); $stmt2->execute([$cod]);
    if ((int) $stmt2->fetch()["c"] > 0) $usos[] = "facturas";
    $stmt3 = $pdo->prepare("SELECT COUNT(*) AS c FROM usuarios WHERE cod_empleado=?"); $stmt3->execute([$cod]);
    if ((int) $stmt3->fetch()["c"] > 0) $usos[] = "una cuenta de usuario";

    if ($usos) {
        $pdo->prepare("UPDATE empleado SET activo = 0 WHERE cod_empleado=?")->execute([$cod]);
        registrarAuditoria($pdo, "usuarios", "Empleado desactivado", $cod);
    } else {
        $pdo->prepare("DELETE FROM empleado WHERE cod_empleado=?")->execute([$cod]);
        $mensaje = "Empleado eliminado.";
        registrarAuditoria($pdo, "usuarios", "Empleado eliminado", $cod);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "reactivar_empleado") {
    $pdo->prepare("UPDATE empleado SET activo = 1 WHERE cod_empleado=?")->execute([$_POST["cod_empleado"]]);
    $mensaje = "Empleado reactivado.";
    registrarAuditoria($pdo, "usuarios", "Empleado reactivado", $_POST["cod_empleado"]);
}

// ---------- USUARIOS ----------

function contarAdmins(PDO $pdo, $excluirId = null) {
    if ($excluirId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM usuarios WHERE rol='administrador' AND ID_usuario != ?");
        $stmt->execute([$excluirId]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM usuarios WHERE rol='administrador'");
    }
    return (int) $stmt->fetch()["c"];
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "guardar_usuario") {
    if (empty($_POST["contrasena"])) {
        $error = "La contraseña es obligatoria para crear un usuario nuevo.";
    } else {
        $max = (int) $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTR(ID_usuario,2) AS INTEGER)),0) AS m FROM usuarios")->fetch()["m"];
        $idUsuario = "U" . str_pad($max + 1, 3, "0", STR_PAD_LEFT);
        $hash = password_hash($_POST["contrasena"], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (ID_usuario, usuario, contrasena, rol, cod_empleado) VALUES (?,?,?,?,?)");
        $stmt->execute([$idUsuario, $_POST["usuario"], $hash, $_POST["rol"], $_POST["cod_empleado"] ?: null]);
        $mensaje = "Usuario creado ($idUsuario).";
        registrarAuditoria($pdo, "usuarios", "Usuario creado", "$idUsuario (" . $_POST["usuario"] . ") rol: " . $_POST["rol"]);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "editar_usuario") {
    $idUsuario = $_POST["id_usuario"];

    // No permitir quitarle el rol de administrador al último admin que queda
    $stmtActual = $pdo->prepare("SELECT rol FROM usuarios WHERE ID_usuario = ?");
    $stmtActual->execute([$idUsuario]);
    $rolActual = $stmtActual->fetch()["rol"] ?? "";

    if ($idUsuario === ($_SESSION["ID_usuario"] ?? "") && $rolActual === "administrador" && $_POST["rol"] !== "administrador") {
        $error = "No puedes quitarte tu propio rol de administrador.";
    } elseif ($rolActual === "administrador" && $_POST["rol"] !== "administrador" && contarAdmins($pdo, $idUsuario) === 0) {
        $error = "No puedes quitarle el rol de administrador: es el único administrador que queda.";
    } else {
        // El empleado vinculado NO se puede editar aquí a propósito (se fija al crear
        // la cuenta y ya). Por eso el UPDATE no toca cod_empleado, aunque venga en el POST.
        if (!empty($_POST["contrasena"])) {
            $hash = password_hash($_POST["contrasena"], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET usuario=?, contrasena=?, rol=? WHERE ID_usuario=?");
            $stmt->execute([$_POST["usuario"], $hash, $_POST["rol"], $idUsuario]);
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET usuario=?, rol=? WHERE ID_usuario=?");
            $stmt->execute([$_POST["usuario"], $_POST["rol"], $idUsuario]);
        }
        $mensaje = "Usuario actualizado.";
        $detalleAudit = $idUsuario . " (" . $_POST["usuario"] . ")";
        if ($rolActual !== $_POST["rol"]) {
            $detalleAudit .= " — rol cambiado de $rolActual a " . $_POST["rol"];
        }
        registrarAuditoria($pdo, "usuarios", "Usuario editado", $detalleAudit);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "eliminar_usuario") {
    $idUsuario = $_POST["id_usuario"];

    if ($idUsuario === ($_SESSION["ID_usuario"] ?? "")) {
        $error = "No puedes eliminar tu propia cuenta mientras tienes la sesión iniciada.";
    } else {
        $stmtActual = $pdo->prepare("SELECT rol FROM usuarios WHERE ID_usuario = ?");
        $stmtActual->execute([$idUsuario]);
        $rolActual = $stmtActual->fetch()["rol"] ?? "";

        if ($rolActual === "administrador" && contarAdmins($pdo, $idUsuario) === 0) {
            $error = "No puedes eliminar este usuario: es el único administrador que queda.";
        } else {
            $pdo->prepare("DELETE FROM usuarios WHERE ID_usuario=?")->execute([$idUsuario]);
            $mensaje = "Usuario eliminado.";
            registrarAuditoria($pdo, "usuarios", "Usuario eliminado", "$idUsuario (rol: $rolActual)");
        }
    }
}

// ---------- Datos para mostrar ----------

$empleados = $pdo->query("SELECT * FROM empleado ORDER BY activo DESC, cod_empleado")->fetchAll(PDO::FETCH_ASSOC);

$usuarios = $pdo->query("SELECT u.*, e.nombre_empl FROM usuarios u
                          LEFT JOIN empleado e ON e.cod_empleado = u.cod_empleado
                          ORDER BY u.ID_usuario")->fetchAll(PDO::FETCH_ASSOC);

// Solo empleados activos como opción para vincular una cuenta nueva
$empleadosActivos = $pdo->query("SELECT cod_empleado, nombre_empl FROM empleado WHERE activo = 1 ORDER BY nombre_empl")->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = "USUARIOS Y EMPLEADOS";
require_once __DIR__ . "/includes/layout_top.php";
?>

<style>
.pd-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:18px;}
.pd-row{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;}
.pd-field{display:flex;flex-direction:column;gap:4px;}
.pd-field label{font-size:13px;color:#444;}
.pd-field input,.pd-field select{padding:6px 8px;border:1px solid #ccc;border-radius:4px;min-width:150px;}
.pd-field select:disabled{background:var(--color-surface-alt);color:#888;cursor:not-allowed;}
.pd-hint{font-size:12px;color:#888;margin:2px 0 0 0;}
.pd-tabla{width:100%;border-collapse:collapse;}
.pd-tabla th,.pd-tabla td{border:1px solid #ddd;padding:6px 10px;text-align:left;font-size:14px;}
.pd-tabla th{background:var(--color-surface-alt);}
.pd-actions{margin-top:14px;display:flex;gap:10px;}
.badge-admin{background:var(--color-danger-bg);color:var(--color-danger);padding:1px 8px;border-radius:10px;font-size:12px;}
.badge-empleado{background:var(--color-info-bg);color:var(--color-info);padding:1px 8px;border-radius:10px;font-size:12px;}
</style>

<p class="titulo-modulo">Usuarios y Empleados</p>

<?php if ($mensaje): ?><p class="mensaje-ok"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<!-- EMPLEADOS -->
<div class="pd-card">
<h3 style="margin-top:0;">Empleados</h3>
<table class="pd-tabla">
<tr><th>Código</th><th>Nombre</th><th>Puesto</th><th>Salario</th><th>Estado</th><th></th></tr>
<?php foreach ($empleados as $e): ?>
<tr<?php echo !$e["activo"] ? ' style="opacity:.55;"' : ''; ?>>
    <td><?php echo htmlspecialchars($e["cod_empleado"]); ?></td>
    <td><?php echo htmlspecialchars($e["nombre_empl"]); ?></td>
    <td><?php echo htmlspecialchars($e["puesto_empl"]); ?></td>
    <td><?php echo $e["salario_empl"] !== null ? number_format((float) $e["salario_empl"], 2) : "—"; ?></td>
    <td><?php echo $e["activo"] ? "Activo" : "Inactivo"; ?></td>
    <td>
        <button type="button" onclick="cargarEmpleado(<?php echo htmlspecialchars(json_encode($e)); ?>)">EDITAR</button>
        <?php if ($e["activo"]): ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este empleado?');">
            <input type="hidden" name="accion" value="eliminar_empleado">
            <input type="hidden" name="cod_empleado" value="<?php echo htmlspecialchars($e["cod_empleado"]); ?>">
            <button type="submit">ELIMINAR</button>
        </form>
        <?php else: ?>
        <form method="POST" style="display:inline">
            <input type="hidden" name="accion" value="reactivar_empleado">
            <input type="hidden" name="cod_empleado" value="<?php echo htmlspecialchars($e["cod_empleado"]); ?>">
            <button type="submit">REACTIVAR</button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>

<h4 style="margin:18px 0 8px 0;" id="tituloEmpleado">+ Nuevo empleado</h4>
<form method="POST" id="formEmpleado">
    <input type="hidden" name="accion" id="accionEmpleado" value="guardar_empleado">
    <input type="hidden" name="cod_empleado" id="cod_empleado">
    <div class="pd-row">
        <div class="pd-field"><label>Nombre</label><input type="text" name="nombre_empl" id="nombre_empl" required></div>
        <div class="pd-field">
            <label>Puesto</label>
            <select name="puesto_empl" id="puesto_empl" onchange="togglePuestoOtro()">
                <option value="Administrador">Administrador</option>
                <option value="Gerente">Gerente</option>
                <option value="Cajero">Cajero</option>
                <option value="Mesero">Mesero</option>
                <option value="Cocinero">Cocinero</option>
                <option value="Ayudante de Cocina">Ayudante de Cocina</option>
                <option value="Bodega / Inventario">Bodega / Inventario</option>
                <option value="Repartidor">Repartidor</option>
                <option value="Limpieza">Limpieza</option>
                <option value="Otro">Otro</option>
            </select>
        </div>
        <div class="pd-field" id="fila_puesto_otro" style="display:none;">
            <label>Especifica el puesto</label>
            <input type="text" name="puesto_otro" id="puesto_otro" placeholder="Escribe el puesto">
        </div>
        <div class="pd-field"><label>Salario</label><input type="number" step="0.01" name="salario_empl" id="salario_empl"></div>
    </div>
    <div class="pd-actions">
        <button type="submit" id="btnGuardarEmpleado">GUARDAR</button>
        <button type="submit" id="btnEditarEmpleado" style="display:none;" onclick="document.getElementById('accionEmpleado').value='editar_empleado'">GUARDAR EDICIÓN</button>
        <button type="button" id="btnNuevoEmpleado" style="display:none;" onclick="nuevoEmpleado()">+ NUEVO EMPLEADO</button>
    </div>
</form>
</div>

<!-- USUARIOS -->
<div class="pd-card">
<h3 style="margin-top:0;">Usuarios (accesos al sistema)</h3>
<table class="pd-tabla">
<tr><th>ID</th><th>Usuario</th><th>Rol</th><th>Empleado vinculado</th><th></th></tr>
<?php foreach ($usuarios as $u): ?>
<tr>
    <td><?php echo htmlspecialchars($u["ID_usuario"]); ?></td>
    <td><?php echo htmlspecialchars($u["usuario"]); ?></td>
    <td><span class="<?php echo $u['rol'] === 'administrador' ? 'badge-admin' : 'badge-empleado'; ?>"><?php echo htmlspecialchars(ucfirst($u["rol"])); ?></span></td>
    <td><?php echo htmlspecialchars($u["nombre_empl"] ?? "— sin vincular —"); ?></td>
    <td>
        <button type="button" onclick="cargarUsuario(<?php echo htmlspecialchars(json_encode($u)); ?>)">EDITAR</button>
        <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este usuario? Ya no podrá iniciar sesión.');">
            <input type="hidden" name="accion" value="eliminar_usuario">
            <input type="hidden" name="id_usuario" value="<?php echo htmlspecialchars($u["ID_usuario"]); ?>">
            <button type="submit">ELIMINAR</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>

<h4 style="margin:18px 0 8px 0;" id="tituloUsuario">+ Nuevo usuario</h4>
<form method="POST" id="formUsuario">
    <input type="hidden" name="accion" id="accionUsuario" value="guardar_usuario">
    <input type="hidden" name="id_usuario" id="id_usuario">
    <input type="hidden" name="cod_empleado" id="cod_empleado_hidden">
    <div class="pd-row">
        <div class="pd-field"><label>Usuario (login)</label><input type="text" name="usuario" id="usuario" required></div>
        <div class="pd-field">
            <label id="labelContrasena">Contraseña</label>
            <input type="password" name="contrasena" id="contrasena" placeholder="Vacio para no cambiar" autocomplete="new-password">
        </div>
        <div class="pd-field">
            <label>Rol</label>
            <select name="rol" id="rol" required>
                <option value="empleado">Empleado</option>
                <option value="administrador">Administrador</option>
            </select>
        </div>
        <div class="pd-field">
            <label>Empleado vinculado</label>
            <select id="cod_empleado_usuario">
                <option value="">-- ninguno --</option>
                <?php foreach ($empleadosActivos as $e): ?>
                <option value="<?php echo htmlspecialchars($e["cod_empleado"]); ?>">
                    <?php echo htmlspecialchars($e["cod_empleado"] . " - " . $e["nombre_empl"]); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <p class="pd-hint" id="hintEmpleado" style="display:none;">No se puede cambiar una vez creada la cuenta.</p>
        </div>
    </div>
    <div class="pd-actions">
        <button type="submit" id="btnGuardarUsuario">CREAR USUARIO</button>
        <button type="submit" id="btnEditarUsuario" style="display:none;" onclick="document.getElementById('accionUsuario').value='editar_usuario'">GUARDAR EDICIÓN</button>
        <button type="button" id="btnNuevoUsuario" style="display:none;" onclick="nuevoUsuario()">+ NUEVO USUARIO</button>
    </div>
</form>
</div>

<script>
const PUESTOS_ESTANDAR = [
    "Administrador", "Gerente", "Cajero", "Mesero", "Cocinero",
    "Ayudante de Cocina", "Bodega / Inventario", "Repartidor", "Limpieza"
];

function togglePuestoOtro() {
    const esOtro = document.getElementById("puesto_empl").value === "Otro";
    document.getElementById("fila_puesto_otro").style.display = esOtro ? "flex" : "none";
}

function cargarEmpleado(e) {
    document.getElementById("tituloEmpleado").textContent = "Editando " + e.cod_empleado;
    document.getElementById("cod_empleado").value = e.cod_empleado;
    document.getElementById("nombre_empl").value = e.nombre_empl;

    if (PUESTOS_ESTANDAR.includes(e.puesto_empl)) {
        document.getElementById("puesto_empl").value = e.puesto_empl;
        document.getElementById("puesto_otro").value = "";
    } else {
        document.getElementById("puesto_empl").value = "Otro";
        document.getElementById("puesto_otro").value = e.puesto_empl || "";
    }
    togglePuestoOtro();

    document.getElementById("salario_empl").value = e.salario_empl;
    document.getElementById("btnGuardarEmpleado").style.display = "none";
    document.getElementById("btnEditarEmpleado").style.display = "inline-block";
    document.getElementById("btnNuevoEmpleado").style.display = "inline-block";
    document.getElementById("formEmpleado").scrollIntoView({ behavior: "smooth", block: "center" });
}
function nuevoEmpleado() {
    document.getElementById("tituloEmpleado").textContent = "+ Nuevo empleado";
    document.getElementById("formEmpleado").reset();
    document.getElementById("accionEmpleado").value = "guardar_empleado";
    togglePuestoOtro();
    document.getElementById("btnGuardarEmpleado").style.display = "inline-block";
    document.getElementById("btnEditarEmpleado").style.display = "none";
    document.getElementById("btnNuevoEmpleado").style.display = "none";
}

// Mantiene el hidden sincronizado con el select visible (el select se deshabilita
// al editar, y un select disabled no manda su valor en el POST — por eso el hidden).
document.getElementById("cod_empleado_usuario").addEventListener("change", function () {
    document.getElementById("cod_empleado_hidden").value = this.value;
});

function cargarUsuario(u) {
    document.getElementById("tituloUsuario").textContent = "Editando " + u.usuario;
    document.getElementById("id_usuario").value = u.ID_usuario;
    document.getElementById("usuario").value = u.usuario;
    document.getElementById("contrasena").value = "";
    document.getElementById("rol").value = u.rol;

    const selectEmpleado = document.getElementById("cod_empleado_usuario");
    selectEmpleado.value = u.cod_empleado || "";
    selectEmpleado.disabled = true;
    document.getElementById("cod_empleado_hidden").value = u.cod_empleado || "";
    document.getElementById("hintEmpleado").style.display = "block";

    document.getElementById("btnGuardarUsuario").style.display = "none";
    document.getElementById("btnEditarUsuario").style.display = "inline-block";
    document.getElementById("btnNuevoUsuario").style.display = "inline-block";
    document.getElementById("formUsuario").scrollIntoView({ behavior: "smooth", block: "center" });
}
function nuevoUsuario() {
    document.getElementById("tituloUsuario").textContent = "+ Nuevo usuario";
    document.getElementById("formUsuario").reset();
    document.getElementById("accionUsuario").value = "guardar_usuario";

    const selectEmpleado = document.getElementById("cod_empleado_usuario");
    selectEmpleado.disabled = false;
    document.getElementById("cod_empleado_hidden").value = "";
    document.getElementById("hintEmpleado").style.display = "none";

    document.getElementById("btnGuardarUsuario").style.display = "inline-block";
    document.getElementById("btnEditarUsuario").style.display = "none";
    document.getElementById("btnNuevoUsuario").style.display = "none";
}
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>