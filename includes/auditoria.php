<?php
// includes/auditoria.php
// Helper para registrar acciones importantes en la bitácora de auditoría.
// Llamar SIEMPRE DESPUÉS de que la acción ya se ejecutó con éxito.
function registrarAuditoria(
    PDO $pdo,
    string $modulo,
    string $accion,
    string $detalle = "",
    ?string $usuarioOverride = null,
    ?string $codEmpleadoOverride = null
): void {
    $stmt = $pdo->prepare("INSERT INTO auditoria (usuario, cod_empleado, modulo, accion, detalle) VALUES (?,?,?,?,?)");
    $stmt->execute([
        $usuarioOverride ?? ($_SESSION["usuario"] ?? null),
        $codEmpleadoOverride ?? ($_SESSION["cod_empleado"] ?? null),
        $modulo,
        $accion,
        $detalle,
    ]);
}