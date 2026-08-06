<?php
session_start();
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auditoria.php";

// Registramos el cierre de sesión ANTES de destruirla (si no, ya no tenemos
// $_SESSION["usuario"] a mano para saber quién fue).
if (isset($_SESSION["usuario"])) {
    registrarAuditoria($pdo, "login", "Cierre de sesión", "", $_SESSION["usuario"], $_SESSION["cod_empleado"] ?? null);
}

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
?>