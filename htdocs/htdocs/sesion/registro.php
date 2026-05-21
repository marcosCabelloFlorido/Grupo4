<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/../PHPMailer/Exception.php';
require __DIR__ . '/../PHPMailer/PHPMailer.php';
require __DIR__ . '/../PHPMailer/SMTP.php';

require __DIR__ . '/../conexion.php';

$mensaje = "";

// Mostrar mensaje si venimos de una redirección exitosa
if (isset($_SESSION['mensaje_registro'])) {
    $mensaje = $_SESSION['mensaje_registro'];
    unset($_SESSION['mensaje_registro']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario  = htmlspecialchars(trim($_POST['usuario']));
    $email    = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $telefono = htmlspecialchars(trim($_POST['telefono']));
    $pass_raw = $_POST['contrasena'];

    $errores = [];
    if (strlen($usuario) <= 4)
        $errores[] = "El nombre de agente debe tener más de 4 caracteres.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errores[] = "El email introducido no es válido.";
    if (!preg_match("/^[0-9]{9}$/", $telefono))
        $errores[] = "El teléfono debe contener exactamente 9 dígitos.";
    if (!preg_match("/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/", $pass_raw))
        $errores[] = "La contraseña necesita mínimo 8 caracteres, una mayúscula, un número y un símbolo.";

    if (empty($errores)) {
        try {
            $pass_hash = password_hash($pass_raw, PASSWORD_DEFAULT);
            $sql  = "INSERT INTO usuarios (nombre, email, telefono, contrasena) VALUES (:u, :e, :t, :p)";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':u' => $usuario, ':e' => $email, ':t' => $telefono, ':p' => $pass_hash]);

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'javiermartingarcia235@gmail.com';
                $mail->Password   = 'oczf xgsg demx jibv';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('javiermartingarcia235@gmail.com', 'Agencia VALTASY');
                $mail->addAddress($email, $usuario);

                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = "VALTASY — Acceso Concedido, Agente $usuario";

                $mail->Body = "
<!DOCTYPE html>
<html lang='es'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='margin:0;padding:0;background-color:#0d0d0f;font-family:Arial,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#0d0d0f;padding:40px 20px;'>
    <tr>
      <td align='center'>
        <table width='480' cellpadding='0' cellspacing='0' style='background:#141418;border:1px solid rgba(166,50,71,0.3);border-top:3px solid #A63247;'>
          <tr>
            <td style='padding:36px 40px 24px;border-bottom:1px solid rgba(255,255,255,0.06);'>
              <p style='margin:0 0 4px;font-size:11px;letter-spacing:4px;color:#A63247;text-transform:uppercase;'>Agencia Confidencial</p>
              <h1 style='margin:0;font-size:28px;font-weight:900;color:#ffffff;letter-spacing:3px;text-transform:uppercase;'>VALTASY</h1>
            </td>
          </tr>
          <tr>
            <td style='padding:32px 40px;'>
              <p style='margin:0 0 8px;font-size:11px;letter-spacing:3px;color:#1DF2DD;text-transform:uppercase;'>Transmisión entrante — clasificado</p>
              <h2 style='margin:0 0 24px;font-size:20px;font-weight:700;color:#ffffff;'>Acceso concedido, Agente <span style='color:#1DF2DD;'>$usuario</span></h2>
              <p style='margin:0 0 20px;font-size:15px;color:#a0a0b0;line-height:1.7;'>Tu identidad ha sido verificada y tu registro en la red operativa de VALTASY procesado con éxito. A partir de este momento tienes acceso al sistema.</p>
              <table width='100%' cellpadding='0' cellspacing='0' style='background:rgba(255,255,255,0.03);border:1px solid rgba(29,242,221,0.15);margin:0 0 28px;'>
                <tr><td style='padding:6px 20px 2px;'><p style='margin:0;font-size:10px;letter-spacing:3px;color:#1DF2DD;text-transform:uppercase;'>Credenciales de acceso</p></td></tr>
                <tr>
                  <td style='padding:12px 20px;border-top:1px solid rgba(29,242,221,0.1);'>
                    <table width='100%' cellpadding='0' cellspacing='0'>
                      <tr>
                        <td style='font-size:12px;color:#606070;padding:6px 0;width:90px;text-transform:uppercase;letter-spacing:1px;'>Usuario</td>
                        <td style='font-size:14px;color:#e8e8ee;padding:6px 0;font-weight:bold;'>$usuario</td>
                      </tr>
                      <tr>
                        <td style='font-size:12px;color:#606070;padding:6px 0;width:90px;text-transform:uppercase;letter-spacing:1px;border-top:1px solid rgba(255,255,255,0.04);'>Email</td>
                        <td style='font-size:14px;color:#e8e8ee;padding:6px 0;border-top:1px solid rgba(255,255,255,0.04);'>$email</td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
              <p style='margin:0 0 28px;font-size:14px;color:#606070;line-height:1.6;'>Mantén tus credenciales en secreto. Si no has iniciado este registro, contacta con el equipo de operaciones de inmediato.</p>
              <table cellpadding='0' cellspacing='0'>
                <tr>
                  <td style='background:linear-gradient(135deg,#8C0813,#A63247);padding:14px 32px;'>
                    <a href='https://tudominio.com/sesion/login.php' style='color:#ffffff;text-decoration:none;font-size:13px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;'>Acceder al sistema →</a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style='padding:20px 40px;border-top:1px solid rgba(255,255,255,0.06);'>
              <p style='margin:0;font-size:11px;color:#3a3a4a;line-height:1.6;'>Este mensaje es confidencial y está destinado exclusivamente al agente indicado. © VALTASY — Todos los derechos reservados.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>";

                $mail->AltBody = "Agente $usuario,\n\nTu registro en VALTASY ha sido procesado con éxito.\n\nUsuario: $usuario\nEmail: $email\n\nFin de la transmisión.\n— Agencia VALTASY";
                $mail->send();
                $_SESSION['mensaje_registro'] = "<div class='mensaje exito'><span class='msg-icon'>✓</span> ¡Agente <strong>$usuario</strong> registrado con éxito! Revisa tu correo para confirmar el acceso.</div>";
            } catch (\Throwable $e) {
                // Si el correo falla por lo que sea, el registro sigue siendo válido
                $_SESSION['mensaje_registro'] = "<div class='mensaje exito'><span class='msg-icon'>✓</span> ¡Agente <strong>$usuario</strong> registrado con éxito! (El correo de confirmación no pudo enviarse)</div>";
            }

            // Forzamos el guardado de la sesión antes de redirigir
            session_write_close();
            header("Location: registro.php");
            exit();

        } catch (PDOException $e) {
            // Evaluamos si es un error de duplicidad o un error real de la base de datos
            if ($e->getCode() == 23000) {
                $mensaje = "<div class='mensaje error'><span class='msg-icon'>✕</span> Ese usuario o email ya está registrado en el sistema.</div>";
            } else {
                $mensaje = "<div class='mensaje error'><span class='msg-icon'>✕</span> Error interno del sistema: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    } else {
        $lista = implode('</li><li>', array_map('htmlspecialchars', $errores));
        $mensaje = "<div class='mensaje error'><span class='msg-icon'>✕</span><ul><li>$lista</li></ul></div>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VALTASY — Registro de Agente</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Barlow+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cyan: #1DF2DD;
            --cyan-dark: #168C77;
            --red-deep: #8C0813;
            --red-soft: #A63247;
            --bg: #0d0d0f;
            --bg-card: #141418;
            --border: rgba(29,242,221,0.12);
            --text: #e8e8ee;
            --muted: #6b6b7a;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Barlow Condensed', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-image: radial-gradient(ellipse 60% 60% at 50% 50%, rgba(140,8,19,0.10) 0%, transparent 70%);
            padding: 24px;
        }

        .back-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 48px;
            background: rgba(13,13,15,0.95);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 24px;
            z-index: 100;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--cyan); }
        .back-link:hover svg { transform: translateX(-3px); }
        .back-link svg { transition: transform 0.2s; }

        .back-logo {
            margin-left: auto;
            font-family: 'Orbitron', monospace;
            font-weight: 900;
            font-size: 0.85rem;
            letter-spacing: 0.15em;
            color: #fff;
            text-decoration: none;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-top: 3px solid var(--red-soft);
            padding: 40px 36px;
            width: 100%;
            max-width: 440px;
            margin-top: 48px;
        }

        .card-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .card-tag {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--red-soft);
            margin-bottom: 8px;
        }

        h2 {
            font-family: 'Orbitron', monospace;
            font-weight: 900;
            font-size: 1.6rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #fff;
        }

        .mensaje {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 16px;
            margin-bottom: 24px;
            font-size: 0.88rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            border-left: 3px solid;
            line-height: 1.5;
        }
        .mensaje.error {
            background: rgba(140,8,19,0.15);
            border-color: var(--red-soft);
            color: #ff7a8a;
        }
        .mensaje.exito {
            background: rgba(29,242,221,0.08);
            border-color: var(--cyan);
            color: var(--cyan);
        }
        .mensaje ul { margin: 0; padding-left: 18px; }
        .mensaje ul li { margin-bottom: 3px; }
        .msg-icon { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }

        .field { margin-bottom: 18px; }

        label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 7px;
        }

        .field-hint {
            font-size: 0.68rem;
            color: rgba(107,107,122,0.7);
            margin-top: 5px;
            letter-spacing: 0.04em;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-bottom: 1px solid rgba(255,255,255,0.15);
            color: var(--text);
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1rem;
            letter-spacing: 0.05em;
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }

        input:focus {
            border-color: var(--red-soft);
            background: rgba(166,50,71,0.04);
        }

        input::placeholder { color: rgba(255,255,255,0.2); font-size: 0.9rem; }

        .btn-submit {
            width: 100%;
            padding: 15px;
            margin-top: 8px;
            background: linear-gradient(135deg, var(--red-deep), var(--red-soft));
            color: #fff;
            border: none;
            cursor: pointer;
            font-family: 'Barlow Condensed', sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
            transition: box-shadow 0.2s, opacity 0.2s;
        }
        
        /* Efecto desactivado cuando procesa */
        .btn-submit:disabled {
            background: #3f3f46;
            color: #a1a1aa;
            cursor: not-allowed;
            box-shadow: none !important;
        }

        .btn-submit:hover:not(:disabled) { box-shadow: 0 6px 28px rgba(140,8,19,0.5); }
        .btn-submit:active:not(:disabled) { opacity: 0.85; }

        .links {
            margin-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }

        .link-login {
            color: var(--cyan);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            transition: color 0.2s;
        }
        .link-login:hover { color: #fff; }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.1);
            font-size: 0.7rem;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.07);
        }

        @media (max-width: 480px) {
            .card { padding: 28px 20px; }
        }
    </style>
</head>
<body>

    <div class="back-bar">
        <a href="../index.html" class="back-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            Volver al inicio
        </a>
        <a href="../index.html" class="back-logo">VALTASY</a>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-tag">— Nuevo Agente —</div>
            <h2>Registro</h2>
        </div>

        <?php echo $mensaje; ?>

        <form id="formRegistro" method="POST" action="registro.php" autocomplete="on">
            <div class="field">
                <label for="usuario">Nombre de Agente</label>
                <input type="text" id="usuario" name="usuario" placeholder="Mínimo 5 caracteres" required autocomplete="username" value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>">
            </div>
            <div class="field">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" placeholder="agente@dominio.com" required autocomplete="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="field">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono" placeholder="9 dígitos" required autocomplete="tel" maxlength="9" value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
            </div>
            <div class="field">
                <label for="contrasena">Contraseña</label>
                <input type="password" id="contrasena" name="contrasena" placeholder="••••••••" required autocomplete="new-password">
                <p class="field-hint">Mínimo 8 caracteres · una mayúscula · un número · un símbolo</p>
            </div>
            <button type="submit" id="btnSubmit" class="btn-submit">Activar Acceso</button>
        </form>

        <div class="links">
            <div class="divider">o</div>
            <a href="login.php" class="link-login">¿Ya tienes cuenta? Accede al sistema</a>
        </div>
    </div>

    <!-- Script para evitar doble envío -->
    <script>
        document.getElementById('formRegistro').addEventListener('submit', function() {
            var btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerHTML = 'PROCESANDO ENLACE...';
        });
    </script>
</body>
</html>