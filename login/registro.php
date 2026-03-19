<?php
require 'conexion.php';
$mensaje = "";
 
 
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitización (Limpieza)
    $usuario  = htmlspecialchars(trim($_POST['usuario']));
    $email    = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $telefono = htmlspecialchars(trim($_POST['telefono']));
    $pass_raw = $_POST['contrasena'];
 
    // 2. Reglas de Validación
    $errores = [];
 
    if (strlen($usuario) <= 4) {
        $errores[] = "El usuario debe tener más de 4 caracteres.";
    }
 
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El formato del email no es válido (ejemplo: nombre@correo.com).";
    }
 
    if (!preg_match("/^[0-9]{9}$/", $telefono)) {
        $errores[] = "El teléfono debe contener exactamente 9 números.";
    }
 
    if (!preg_match("/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/", $pass_raw)) {
        $errores[] = "La contraseña debe tener 8 caracteres, una mayúscula, un número y un símbolo.";
    }
 
    // Insercion en la base de datos los datos recibidos y ya comprobados
    if (empty($errores)) {
        try {
            $pass_hash = password_hash($pass_raw, PASSWORD_DEFAULT);
 
            $sql = "INSERT INTO usuarios (nombre, email, contraseña) VALUES (:u, :e, :p)";
            $stmt = $conexion->prepare($sql);
 
            $stmt->execute([
                ':u' => $usuario,
                ':e' => $email,
                ':p' => $pass_hash,
            ]);
 
            $mensaje = "<div class='mensaje' style='color:green'>¡Registro realizado con éxito!</div>";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $mensaje = "<div class='mensaje' style='color:red'>Error: El usuario o email ya están en uso.</div>";
            } else {
                $mensaje = "<div class='mensaje' style='color:red'>Error: " . $e->getMessage() . "</div>";
            }
        }
    } else {
        // Mostrar lista de errores si los hay
        $mensaje = "<div class='mensaje' style='color:red'><ul>";
        foreach ($errores as $error) {
            $mensaje .= "<li>$error</li>";
        }
        $mensaje .= "</ul></div>";
    }
}
?>
 
<!DOCTYPE html>
<html lang="es">
 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VALTASY — Registro</title>
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
 
        .mensaje {
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
        input[type="email"],
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
    </style>
</head>
 
<body>
 
    <div class="card">
        <h2>Registro de Usuario</h2>
        <?php echo $mensaje; ?>
        <form method="POST">
            <input type="text" name="usuario" placeholder="Usuario" value="<?php echo $usuario ?? ''; ?>" required>
            <input type="email" name="email" placeholder="Email (nombre@correo.com)" value="<?php echo $email ?? ''; ?>" required>
            <input type="text" name="telefono" placeholder="Teléfono (9 dígitos)" value="<?php echo $telefono ?? ''; ?>" required>
            <input type="password" name="contrasena" placeholder="Contraseña segura" required>
            <button type="submit">Registrar</button>
        </form>
    </div>
 
</body>
 
</html>