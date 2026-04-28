<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ../sesion/login.php");
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0d0d0f; color: #e8e8ee; font-family: 'Barlow Condensed', sans-serif; min-height: 100vh; padding: 40px; background-image: radial-gradient(ellipse 50% 50% at 50% 50%, rgba(140, 8, 19, 0.12) 0%, transparent 70%), linear-gradient(rgba(29, 242, 221, 0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(29, 242, 221, 0.02) 1px, transparent 1px); background-size: cover, 60px 60px, 60px 60px; }
        h1, h2 { font-family: 'Orbitron', monospace; text-transform: uppercase; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; border-bottom: 2px solid #A63247; padding-bottom: 20px; }
        .card { background: #141418; border: 1px solid rgba(29, 242, 221, 0.12); padding: 40px; max-width: 500px; margin: 0 auto; }
        input[type="text"], select { width: 100%; padding: 12px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); color: #e8e8ee; margin-bottom: 20px; font-family: 'Barlow Condensed'; font-size: 1rem; }
        label { display: block; font-size: 0.8rem; letter-spacing: 0.1em; color: #6b6b7a; margin-bottom: 8px; text-transform: uppercase; }
        button { width: 100%; padding: 14px; background: linear-gradient(135deg, #8C0813, #A63247); color: #fff; font-weight: 700; text-transform: uppercase; border: none; cursor: pointer; clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%); font-family: 'Barlow Condensed'; font-size: 1rem; letter-spacing: 0.08em; }
        button:hover { box-shadow: 0 6px 24px rgba(140, 8, 19, 0.5); }
        .btn-volver { color: #1DF2DD; text-decoration: none; font-weight: bold; font-size: 1.1rem; }
        #mensaje { margin-top: 20px; padding: 15px; display: none; text-align: center; font-weight: bold; }
        .exito { color: #1DF2DD; border: 1px solid #1DF2DD; background: rgba(29, 242, 221, 0.1); }
        .error { color: #A63247; border: 1px solid #A63247; background: rgba(140, 8, 19, 0.2); }

        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); display: flex; align-items: center; justify-content: center; z-index: 9999; }
        .modal-overlay.hidden { display: none; }
        .modal-box { background: #141418; border: 2px solid #1DF2DD; padding: 40px; max-width: 440px; width: 90%; text-align: center; }
        .modal-box h2 { font-family: 'Orbitron'; color: #1DF2DD; margin-bottom: 10px; font-size: 1rem; letter-spacing: 0.1em; }
        .modal-box p { color: #6b6b7a; margin-bottom: 20px; font-size: 0.95rem; }
        .codigo-display { font-family: 'Orbitron'; font-size: 2rem; letter-spacing: 0.3em; color: #fff; background: rgba(29, 242, 221, 0.08); border: 2px dashed #1DF2DD; padding: 18px 24px; margin-bottom: 24px; cursor: pointer; user-select: all; transition: background 0.2s; }
        .codigo-display:hover { background: rgba(29, 242, 221, 0.15); }
        .aviso-codigo { font-size: 0.8rem; color: #A63247; margin-bottom: 20px; letter-spacing: 0.05em; }
        .btn-modal { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #168C77, #1DF2DD); color: #000; font-weight: 700; text-decoration: none; text-transform: uppercase; cursor: pointer; border: none; font-family: 'Barlow Condensed'; font-size: 1rem; clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%); }
        .copiado-msg { font-size: 0.8rem; color: #1DF2DD; min-height: 1.2em; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>VALTASY <span style="font-size:0.5em; color:gray;">CREACIÓN</span></h1>
        <a href="cliente.php" class="btn-volver">← VOLVER AL DASHBOARD</a>
    </div>

    <div class="card">
        <h2>Crear Liga</h2><br>
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

            <label>Torneo a simular:</label>
            <select id="torneoLiga">
                <option value="VCT EMEA - Kickoff">VCT EMEA - Kickoff</option>
                <option value="VCT EMEA - Fase Regular">VCT EMEA - Fase Regular</option>
                <option value="VCT EMEA - Playoffs">VCT EMEA - Playoffs</option>
                <option value="VCT EMEA - Last Chance">VCT EMEA - LCQ</option>
            </select>

            <button type="submit">Iniciar Operación</button>
        </form>
        <div id="mensaje"></div>
    </div>

    <div id="modalCodigo" class="modal-overlay hidden">
        <div class="modal-box">
            <h2>⚡ OPERACIÓN AUTORIZADA</h2>
            <p>Tu liga privada ha sido creada. Comparte este <strong style="color:#fff;">código de acceso</strong> con los agentes que quieras reclutar.</p>
            <div class="codigo-display" id="codigoTexto" title="Haz clic para copiar"></div>
            <p class="copiado-msg" id="copiadoMsg"></p>
            <p class="aviso-codigo">⚠ Guarda este código. Solo podrás verlo aquí.</p>
            <button class="btn-modal" onclick="irAlDashboard()">IR AL DASHBOARD</button>
        </div>
    </div>

    <script>
        let idLigaCreada = null;

        document.getElementById("formCrearLiga").addEventListener("submit", async (e) => {
            e.preventDefault();
            const divMensaje = document.getElementById("mensaje");
            const datos = {
                nombre_usuario: document.getElementById("nombreUsuario").value,
                nombre_liga:    document.getElementById("nombreLiga").value,
                nombre_equipo:  document.getElementById("nombreEquipo").value,
                tipo:           document.getElementById("tipoLiga").value,
                torneo:         document.getElementById("torneoLiga").value // Enviamos el torneo
            };

            try {
                const res  = await fetch("api_ligas.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(datos)
                });
                const json = await res.json();

                if (res.status === 201 || json.status === "success") {
                    idLigaCreada = json.id_liga;
                    document.getElementById("formCrearLiga").reset();

                    if (json.codigo_acceso) {
                        document.getElementById("codigoTexto").textContent = json.codigo_acceso;
                        document.getElementById("modalCodigo").classList.remove("hidden");
                    } else {
                        divMensaje.style.display = "block";
                        divMensaje.className     = "exito";
                        divMensaje.textContent   = "¡Operación autorizada! Liga pública creada con éxito.";
                        setTimeout(() => { window.location.href = "cliente.php"; }, 1500);
                    }
                } else {
                    divMensaje.style.display = "block";
                    divMensaje.className     = "error";
                    divMensaje.textContent   = "Error: " + json.message;
                }
            } catch (error) {
                divMensaje.style.display = "block";
                divMensaje.className     = "error";
                divMensaje.textContent   = "Error de conexión con los servidores.";
            }
        });

        document.getElementById("codigoTexto").addEventListener("click", async () => {
            const codigo = document.getElementById("codigoTexto").textContent;
            try {
                await navigator.clipboard.writeText(codigo);
                const msg = document.getElementById("copiadoMsg");
                msg.textContent = "✓ Código copiado al portapapeles";
                setTimeout(() => { msg.textContent = ""; }, 2500);
            } catch (_) {}
        });

        function irAlDashboard() { window.location.href = "cliente.php"; }
    </script>
</body>
</html>