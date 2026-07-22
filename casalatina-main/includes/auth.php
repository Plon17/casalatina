<?php
// includes/auth.php
session_start();

// Si no hay sesion, manda al login
if (!isset($_SESSION["rol"])) {
    header('Location: login.php');
    exit;
}

// Modulos que SI puede ver el empleado (los demas son solo para administrador)
$modulos_empleado = ["inicio", "menu", "pedido", "factura", "stock", "gasto_det"];

// $modulo_actual se define en cada pagina antes de incluir este archivo
if (isset($modulo_actual)) {
    $es_admin = ($_SESSION["rol"] === "administrador");
    $permitido = $es_admin || in_array($modulo_actual, $modulos_empleado);

    if (!$permitido) {
        header('Location: index.php');
        exit;
    }
}
?>
