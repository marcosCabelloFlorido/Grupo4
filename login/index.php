<?php
session_start();
require 'conexion.php';
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $pass = $_POST['contrasena'];

    try {
        // Tabla 'usuarios' en minúscula
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE nombre = :u");
        $stmt->execute([':u' => $usuario]);
        $user = $stmt->fetch();

        // Columna 'contrasena' con N
        if ($user && password_verify($pass, $user['contrasena'])) {
            $_SESSION['usuario'] = $user['nombre'];
            $_SESSION['rol'] = $user['rol'];
            header("Location: cliente.php");
            exit();
        } else {
            $mensaje = "<div class='mensaje' style='color:#ff4d4d;'>Credenciales inválidas.</div>";
        }
    } catch (PDOException $e) {
        $mensaje = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>VALTASY — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Barlow+Condensed:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0d0d0f; color: #e8e8ee; font-family: 'Barlow Condensed', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background-image: radial-gradient(ellipse 50% 50% at 50% 50%, rgba(140, 8, 19, 0.12) 0%, transparent 70%); }
        .card { background: #141418; border: 1px solid rgba(29, 242, 221, 0.12); padding: 40px; width: 100%; max-width: 400px; border-top: 2px solid #168C77; }
        h2 { font-family: 'Orbitron', sans-serif; text-transform: uppercase; margin-bottom: 20px; text-align: center; }
        .mensaje { padding: 10px; margin-bottom: 20px; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.1); }
        input { width: 100%; padding: 12px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); color: #fff; margin-bottom: 15px; }
        button { width: 100%; padding: 14px; background: linear-gradient(135deg, #168C77, #1DF2DD); color: #000; border: none; cursor: pointer; font-weight: bold; clip-path: polygon(5% 0, 100% 0, 95% 100%, 0 100%); }
        .link { display: block; text-align: center; margin-top: 15px; color: #A63247; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Login</h2>
        <?php echo $mensaje; ?>
        <form method="POST" action="index.php">
            <input type="text" name="usuario" placeholder="USUARIO" required>
            <input type="password" name="contrasena" placeholder="CONTRASEÑA" required>
            <button type="submit">ACCEDER</button>
        </form>
        <a href="registro.php" class="link">¿No tienes cuenta? Regístrate</a>
    </div>
</body>
</html>