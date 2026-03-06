<?php
require 'conexion.php'; 



if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitización (Limpieza)
    $usuario  = htmlspecialchars(trim($_POST['usuario']));
    $email    = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL); // Limpia caracteres raros del email
    $telefono = htmlspecialchars(trim($_POST['telefono']));
    $rol      = htmlspecialchars(trim($_POST['rol']));
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

    //Insercion en la base de datos los datos recibidos y ya comprobados
    if (empty($errores)) {
        try {
            $pass_hash = password_hash($pass_raw, PASSWORD_BCRYPT);

            $sql = "INSERT INTO usuarios (usuario, email, telefono, contrasena, rol) 
                    VALUES (:u, :e, :t, :c, :r)";
            $stmt = $conexion->prepare($sql);
            
            $stmt->execute([
                ':u' => $usuario,
                ':e' => $email,
                ':t' => $telefono,
                ':c' => $pass_hash,
                ':r' => $rol
            ]);

            $mensaje = "<p style='color:green'>¡Registro realizado con éxito!</p>";

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $mensaje = "<p style='color:red'>Error: El usuario o email ya están en uso.</p>";
            } else {
                $mensaje = "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
            }
        }
    } else {
        // Mostrar lista de errores si los hay
        $mensaje = "<div style='color:red'><ul>";
        foreach ($errores as $error) { $mensaje .= "<li>$error</li>"; }
        $mensaje .= "</ul></div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro con filter_var</title>
</head>
<body>
    <h2>Registro de Usuario</h2>
    <?php echo $mensaje; ?>

    <form method="POST">
        <input type="text" name="usuario" placeholder="Usuario" value="<?php echo $usuario ?? ''; ?>" required><br><br>
        
        <input type="email" name="email" placeholder="Email (nombre@correo.com)" value="<?php echo $email ?? ''; ?>" required><br><br>
        
        <input type="text" name="telefono" placeholder="Teléfono (9 dígitos)" value="<?php echo $telefono ?? ''; ?>" required><br><br>
        <input type="password" name="contrasena" placeholder="Contraseña segura" required><br><br>
        
        <select name="rol">
            <option value="cliente">Cliente</option>
            <option value="admin">Administrador</option>
        </select><br><br>
        
        <button type="submit">Registrar</button>
    </form>
</body>
</html>