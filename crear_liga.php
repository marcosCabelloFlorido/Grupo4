<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login/login.php");
    exit();
}
$nombre_usuario_actual = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Operación — VALTASY</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Barlow+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Mismos estilos base de VALTASY */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0d0d0f; color: #e8e8ee; font-family: 'Barlow Condensed', sans-serif; min-height: 100vh; padding: 40px; background-image: radial-gradient(ellipse 50% 50% at 50% 50%, rgba(140, 8, 19, 0.12) 0%, transparent 70%), linear-gradient(rgba(29, 242, 221, 0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(29, 242, 221, 0.02) 1px, transparent 1px); background-size: cover, 60px 60px, 60px 60px;}
        h1, h2 { font-family: 'Orbitron', monospace; text-transform: uppercase; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; border-bottom: 2px solid #A63247; padding-bottom: 20px;}
        .card { background: #141418; border: 1px solid rgba(29, 242, 221, 0.12); padding: 40px; max-width: 500px; margin: 0 auto; }
        input[type="text"], select { width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); color: #e8e8ee; margin-bottom: 20px; font-family: 'Barlow Condensed'; }
        label { display: block; font-size: 0.8rem; letter-spacing: 0.1em; color: #6b6b7a; margin-bottom: 8px; text-transform: uppercase;}
        button { width: 100%; padding: 14px; background: linear-gradient(135deg, #8C0813, #A63247); color: #fff; font-weight: 700; text-transform: uppercase; border: none; cursor: pointer; clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%); }
        button:hover { box-shadow: 0 6px 24px rgba(140, 8, 19, 0.5); }
        .btn-volver { color: #1DF2DD; text-decoration: none; font-weight: bold; font-size: 1.1rem;}
        #mensaje { margin-top: 20px; padding: 15px; display: none; text-align: center; font-weight: bold;}
        .exito { color: #1DF2DD; border: 1px solid #1DF2DD; background: rgba(29, 242, 221, 0.1); }
        .error { color: #A63247; border: 1px solid #A63247; background: rgba(140, 8, 19, 0.2); }
    </style>
</head>
<body>
    <div class="header">
        <h1>VALTASY <span style="font-size: 0.5em; color: gray;">CREACIÓN</span></h1>
        <a href="cliente.php" class="btn-volver">← VOLVER AL DASHBOARD</a>
    </div>

    <div class="card">
        <h2>Crear Nueva Operación (Liga)</h2><br>
        <form id="formCrearLiga">
            <input type="hidden" id="nombreUsuario" value="<?php echo htmlspecialchars($nombre_usuario_actual); ?>">
            <label>Nombre de la Liga (Operación):</label>
            <input type="text" id="nombreLiga" required>
            <label>Nombre de tu Escuadrón (Equipo):</label>
            <input type="text" id="nombreEquipo" required>
            <label>Nivel de Acceso (Tipo):</label>
            <select id="tipoLiga">
                <option value="Privada">Clasificada (Privada)</option>
                <option value="Publica">No Clasificada (Pública)</option>
            </select>
            <button type="submit">Iniciar Operación</button>
        </form>
        <div id="mensaje"></div>
    </div>

    <script>
        document.getElementById("formCrearLiga").addEventListener("submit", async (e) => {
            e.preventDefault();
            const divMensaje = document.getElementById("mensaje");
            const datos = {
                nombre_usuario: document.getElementById("nombreUsuario").value,
                nombre_liga: document.getElementById("nombreLiga").value,
                nombre_equipo: document.getElementById("nombreEquipo").value,
                tipo: document.getElementById("tipoLiga").value
            };

            try {
                const res = await fetch("api_ligas.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(datos)
                });
                const json = await res.json();
                divMensaje.style.display = "block";

                if (res.status === 201 || json.status === "success") {
                    divMensaje.className = "exito";
                    divMensaje.textContent = "¡Operación autorizada! Liga creada con éxito.";
                    document.getElementById("formCrearLiga").reset();
                } else {
                    divMensaje.className = "error";
                    divMensaje.textContent = "Error: " + json.message;
                }
            } catch (error) {
                divMensaje.style.display = "block";
                divMensaje.className = "error";
                divMensaje.textContent = "Error de conexión con los servidores.";
            }
        });
    </script>
</body>
</html>