<?php
// --- CARGA MANUAL PARA INFINITYFREE (SIN COMPOSER) ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
// --- FIN CARGA MANUAL ---

require 'conexion.php';
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = htmlspecialchars(trim($_POST['usuario']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $telefono = htmlspecialchars(trim($_POST['telefono']));
    $pass_raw = $_POST['contrasena'];

    $errores = [];
    if (strlen($usuario) <= 4)
        $errores[] = "Usuario muy corto.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errores[] = "Email inválido.";
    if (!preg_match("/^[0-9]{9}$/", $telefono))
        $errores[] = "Teléfono debe tener 9 dígitos.";
    if (!preg_match("/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/", $pass_raw)) {
        $errores[] = "Contraseña: 8 caracteres, mayúscula, número y símbolo.";
    }

    if (empty($errores)) {
        try {
            $pass_hash = password_hash($pass_raw, PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios (nombre, email, telefono, contrasena) VALUES (:u, :e, :t, :p)";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':u' => $usuario, ':e' => $email, ':t' => $telefono, ':p' => $pass_hash]);

            // --- CONFIGURACIÓN DE ENVÍO DE EMAIL ---
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'javiermartingarcia235@gmail.com'; // Tu Gmail
                $mail->Password = 'oczf xgsg demx jibv'; // Tu contraseña de aplicación
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('javiermartingarcia235@gmail.com', 'Agencia VALTASY');
                $mail->addAddress($email, $usuario);

                $mail->isHTML(false);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = "VALTASY: Registro de Agente Confirmado";
                $mail->Body = "Saludos Agente $usuario,\n\n"
                    . "Tu registro en la red VALTASY ha sido procesado con éxito.\n\n"
                    . "Tus credenciales de acceso:\n"
                    . "Usuario: $usuario\n"
                    . "Email: $email\n\n"
                    . "Fin de la transmisión.";

                $mail->send();
                $mensaje = "<div class='mensaje' style='color:#1DF2DD; border-color:#1DF2DD; background:rgba(29,242,221,0.1);'>¡Agente registrado con éxito y correo enviado!</div>";
            } catch (Exception $e) {
                $mensaje = "<div class='mensaje' style='color:#1DF2DD; border-color:#1DF2DD; background:rgba(29,242,221,0.1);'>¡Agente registrado con éxito! (Fallo al enviar correo)</div>";
            }

        } catch (PDOException $e) {
            $mensaje = "<div class='mensaje' style='color:#ff4d4d;'>" .
                ($e->getCode() == 23000 ? "Usuario/Email duplicado." : "Error: " . $e->getMessage()) . "</div>";
        }
    } else {
        $mensaje = "<div class='mensaje' style='color:#ff4d4d;'><ul>";
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
    <title>VALTASY — Registro</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Barlow+Condensed:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #0d0d0f;
            color: #e8e8ee;
            font-family: 'Barlow Condensed', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: radial-gradient(ellipse 50% 50% at 50% 50%, rgba(140, 8, 19, 0.12) 0%, transparent 70%);
        }
        .card {
            background: #141418;
            border: 1px solid rgba(29, 242, 221, 0.12);
            padding: 40px;
            width: 100%;
            max-width: 400px;
            border-top: 2px solid #A63247;
        }
        h2 { font-family: 'Orbitron', sans-serif; text-transform: uppercase; margin-bottom: 20px; text-align: center; }
        .mensaje { padding: 10px; margin-bottom: 20px; font-size: 0.85rem; border: 1px solid rgba(255, 255, 255, 0.1); }
        input { width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); color: #fff; margin-bottom: 15px; }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #8C0813, #A63247);
            color: #fff;
            border: none;
            cursor: pointer;
            font-weight: bold;
            clip-path: polygon(5% 0, 100% 0, 95% 100%, 0 100%);
        }
        .link { display: block; text-align: center; margin-top: 15px; color: #1DF2DD; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Registro</h2>
        <?php echo $mensaje; ?>
        <form method="POST">
            <input type="text" name="usuario" placeholder="USUARIO" required>
            <input type="email" name="email" placeholder="EMAIL" required>
            <input type="text" name="telefono" placeholder="TELÉFONO" required>
            <input type="password" name="contrasena" placeholder="CONTRASEÑA" required>
            <button type="submit">REGISTRAR</button>
        </form>
        <a href="index.php" class="link">¿Ya tienes cuenta? Login</a>
    </div>
</body>
</html>