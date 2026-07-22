<?php
// includes/layout_top.php
// Variables esperadas antes de incluir este archivo: $titulo_pagina
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($titulo_pagina ?? "Casa Latina"); ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="topbar">
    <?php if (!isset($mostrar_volver) || $mostrar_volver): ?>
        <a href="index.php" class="volver">&larr; Inicio</a>
    <?php endif; ?>
    <?php echo htmlspecialchars($titulo_pagina ?? "CASA LATINA SMART SYSTEM"); ?>
</div>

<?php if (!isset($sin_container) || !$sin_container): ?>
<div class="container">
<?php if (!isset($sin_logo) || !$sin_logo): ?>
<div class="logo-box">
    <div class="marca">CASA<br>LATINA</div>
</div>
<?php endif; ?>
<?php endif; ?>
