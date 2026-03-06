<?php
// 1. Forzamos la visualización de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$_servidor = "localhost";
$_bd = "videojuegos_bd";
$_usuario = "MEDAC"; 
$_contrasena = "MEDAC";

try {
    // 2. Intentamos la conexión
    $conexion = new PDO(
        "mysql:host=$_servidor;dbname=$_bd;charset=utf8mb4",
        $_usuario,
        $_contrasena
    );
    
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // 3. Si llega aquí, es que ha funcionado
    echo "<h1>¡Éxito!</h1>";
    echo "<p>Conectado correctamente a la base de datos: <b>$_bd</b></p>";
    
} catch (PDOException $e) {
    // 4. Si falla, nos dirá el motivo exacto
    echo "<h1>Error de Conexión</h1>";
    echo "Detalles: " . $e->getMessage();
}
?>
