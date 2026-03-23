<?php
// Configuración de errores para depuración (puedes quitarlos cuando todo funcione)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Datos extraídos de tu panel de InfinityFree
$_servidor = "sql207.infinityfree.com";
$_usuario = "if0_41429834";
$_contrasena = "jIQdCLMr4zTn6B";
$_bd = "if0_41429834_fantasyesports"; // Nombre completo que aparece en tu phpMyAdmin

try {
    // Intentamos la conexión usando PDO
    $conexion = new PDO(
        "mysql:host=$_servidor;dbname=$_bd;charset=utf8mb4",
        $_usuario,
        $_contrasena
    );
    
    // Configuración de seguridad y errores
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Si la conexión falla, muestra el error detallado
    die("<h1>Fallo de Conexión</h1>Detalles: " . $e->getMessage());
}
?>
