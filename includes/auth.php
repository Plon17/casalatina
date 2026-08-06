<?php
// includes/auth.php
session_start();

// auth.php se incluye ANTES que db.php en cada página, así que si queremos auditar
// aquí mismo necesitamos traer la conexión y el helper nosotros. require_once evita
// que se vuelva a incluir cuando la página los pida de nuevo más abajo.
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auditoria.php";

// Si no hay sesion, manda al login
if (!isset($_SESSION["rol"])) {
    header('Location: login.php');
    exit;
}
// Modulos que SI puede ver el empleado (los demas son solo para administrador)
// Nota: "gastos" reemplaza al antiguo "gasto_det" — gastos.php y detalle_gasto.php
// se fusionaron en una sola pantalla. El formulario para crear categorías nuevas
// de gasto sigue restringido a administrador dentro de gastos.php.
// "perfil" es "Mi Perfil" (cambiar la propia contraseña) — accesible para cualquiera.
$modulos_empleado = ["inicio", "menu", "pedido", "factura", "stock", "gastos", "perfil"];
// $modulo_actual se define en cada pagina antes de incluir este archivo
if (isset($modulo_actual)) {
    $es_admin = ($_SESSION["rol"] === "administrador");
    $permitido = $es_admin || in_array($modulo_actual, $modulos_empleado);
    if (!$permitido) {
        registrarAuditoria($pdo, $modulo_actual, "Acceso denegado", "Intentó entrar al módulo sin permisos (rol: " . $_SESSION["rol"] . ")");
        header('Location: index.php');
        exit;
    }
}
?>