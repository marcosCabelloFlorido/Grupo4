<?php
// Iniciamos sesión para poder guardar los datos del usuario si el acceso es correcto
session_start();
require 'conexion.php'; 

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];

    try {
        $consulta = "SELECT * FROM usuarios WHERE usuario = :u";
        $stmt = $conexion->prepare($consulta);

        $stmt->execute([':u' => $usuario]);

        /**
         * 
         * Explicacion que le he pedido a la IA para tener un mayor entendimiento del codigo
         * 
         * En la variable $usuario_encontrado se guarda un array asociativo que contiene 
         * todos los datos de la fila de la tabla 'usuarios' (id, usuario, email, telefono, 
         * contrasena y rol). Si el usuario no existe en la base de datos, fetch() devolverá 'false'.
         */
        $usuario_encontrado = $stmt->fetch();

        //Comprueba si la contraseña y el usuario concuerda con la base de datos
        if ($usuario_encontrado && password_verify($contrasena, $usuario_encontrado['contrasena'])) {
            
            // Guardamos datos basicos en la sesión
            $_SESSION['usuario'] = $usuario_encontrado['usuario'];
            $_SESSION['rol'] = $usuario_encontrado['rol'];

            //Depende del rol toma una pagina u otra
            if ($usuario_encontrado['rol'] === 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: cliente.php");
            }
            exit();

        } else {
            $mensaje = "<p style='color:red;'>Usuario o contraseña incorrectos.</p>";
        }

    } catch (PDOException $e) {
        $mensaje = "<p style='color:red;'>Error crítico: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login de Usuario</title>
</head>
<body>
    <h2>Acceso al Sistema</h2>
    
    <?php echo $mensaje; ?>

    <form method="POST" action="login.php">
        <label>Nombre de Usuario:</label><br>
        <input type="text" name="usuario" required><br><br>

        <label>Contraseña:</label><br>
        <input type="password" name="contrasena" required><br><br>
        
        <button type="submit">Iniciar Sesión</button>
    </form>

    <hr>
    <p>¿Aún no tienes cuenta? <a href="registro.php">Regístrate aquí</a></p>

</body>
</html>