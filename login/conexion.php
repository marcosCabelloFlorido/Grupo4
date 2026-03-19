<?php
// 1. Forzamos la visualización de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$_servidor = "localhost";
$_bd = "fantasyesports";
$_usuario = "root"; 
$_contrasena = "";

try {
    // 2. Intentamos la conexión
    $conexion = new PDO(
        "mysql:host=$_servidor;dbname=$_bd;charset=utf8mb4",
        $_usuario,
        $_contrasena
    );
    
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    

} catch (PDOException $e) {
    // 4. Si falla, nos dirá el motivo exacto
    echo "<h1>Error de Conexión</h1>";
    echo "Detalles: " . $e->getMessage();
}
?>
