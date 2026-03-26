<?php
// Quitar en producción
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$_servidor = "sql207.infinityfree.com";
$_usuario  = "if0_41429834";
$_contrasena = "jIQdCLMr4zTn6B";
$_bd       = "if0_41429834_fantasyesports";

try {
    $conexion = new PDO(
        "mysql:host=$_servidor;dbname=$_bd;charset=utf8mb4",
        $_usuario,
        $_contrasena
    );
    $conexion->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<h1>Fallo de Conexión</h1>Detalles: " . $e->getMessage());
}
?>
