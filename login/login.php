<?php
// Iniciamos sesión para poder guardar los datos del usuario si el acceso es correcto
session_start();
require 'conexion.php';
 
$mensaje = "";
 
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];
 
    try {
        $consulta = "SELECT * FROM usuarios WHERE nombre = :u";
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
        if ($usuario_encontrado && password_verify($contrasena, $usuario_encontrado['contraseña'])) {
 
            // Guardamos datos basicos en la sesión
            $_SESSION['usuario'] = $usuario_encontrado['nombre'];
 
            header("Location: ../cliente.php");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VALTASY — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Barlow+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
 
        body {
            background: #0d0d0f;
            color: #e8e8ee;
            font-family: 'Barlow Condensed', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image:
                radial-gradient(ellipse 50% 50% at 50% 50%, rgba(140, 8, 19, 0.12) 0%, transparent 70%),
                linear-gradient(rgba(29, 242, 221, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(29, 242, 221, 0.02) 1px, transparent 1px);
            background-size: cover, 60px 60px, 60px 60px;
        }
 
        .card {
            background: #141418;
            border: 1px solid rgba(29, 242, 221, 0.12);
            padding: 48px 40px;
            width: 100%;
            max-width: 400px;
            position: relative;
        }
 
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #8C0813, #A63247, #168C77);
        }
 
        h2 {
            font-family: 'Orbitron', monospace;
            font-size: 1.5rem;
            font-weight: 900;
            text-transform: uppercase;
            color: #fff;
            margin-bottom: 28px;
        }
 
        p.mensaje {
            background: rgba(140, 8, 19, 0.2);
            border: 1px solid rgba(166, 50, 71, 0.4);
            color: #A63247;
            padding: 10px 14px;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }
 
        label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #6b6b7a;
            margin-bottom: 8px;
        }
 
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #e8e8ee;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1rem;
            outline: none;
            margin-bottom: 20px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
 
        input:focus {
            border-color: rgba(29, 242, 221, 0.4);
            box-shadow: 0 0 0 3px rgba(29, 242, 221, 0.06);
        }
 
        button[type="submit"] {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #8C0813, #A63247);
            color: #fff;
            font-family: 'Barlow Condensed', sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
            transition: box-shadow 0.2s, transform 0.2s;
        }
 
        button[type="submit"]:hover {
            box-shadow: 0 6px 24px rgba(140, 8, 19, 0.5);
            transform: translateY(-2px);
        }
 
        hr {
            border: none;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            margin: 24px 0;
        }
 
        p {
            text-align: center;
            color: #6b6b7a;
            font-size: 0.9rem;
        }
 
        a {
            color: #1DF2DD;
            font-weight: 700;
            text-decoration: none;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.08em;
        }
 
        a:hover {
            text-shadow: 0 0 10px rgba(29, 242, 221, 0.5);
        }
    </style>
 
<body>
    <div class="card">
        <h2>Acceso al Sistema</h2>
        <?php echo $mensaje; ?>
        <form method="POST" action="login.php">
            <label>Nombre de Usuario:</label>
            <input type="text" name="usuario" required>
            <label>Contraseña:</label>
            <input type="password" name="contrasena" required>
            <button type="submit">Iniciar Sesión</button>
        </form>
        <hr>
        <p>¿Aún no tienes cuenta? <a href="registro.php">Regístrate aquí</a></p>
    </div>
 
</body>
 
</html>