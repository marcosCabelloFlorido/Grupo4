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
    <title>VALTASY — Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Barlow+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0d0d0f; color: #e8e8ee; font-family: 'Barlow Condensed', sans-serif; min-height: 100vh; padding: 40px; background-image: radial-gradient(ellipse 50% 50% at 50% 50%, rgba(140, 8, 19, 0.12) 0%, transparent 70%), linear-gradient(rgba(29, 242, 221, 0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(29, 242, 221, 0.02) 1px, transparent 1px); background-size: cover, 60px 60px, 60px 60px;}
        h1, h2 { font-family: 'Orbitron', monospace; text-transform: uppercase; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; border-bottom: 2px solid #A63247; padding-bottom: 20px;}
        .bienvenida span { color: #1DF2DD; }
        
        .panel-acciones { margin-bottom: 30px; display: flex; gap: 15px;}
        .btn-crear { padding: 12px 24px; background: linear-gradient(135deg, #168C77, #1DF2DD); color: #000; text-decoration: none; font-family: 'Barlow Condensed'; font-weight: 700; text-transform: uppercase; border: none; cursor: pointer; clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%); transition: transform 0.2s;}
        .btn-crear:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(29, 242, 221, 0.3); }

        /* Estilo para las tarjetas de las Ligas */
        .grid-ligas { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .liga-card { background: #141418; border: 1px solid rgba(29, 242, 221, 0.2); padding: 25px; position: relative; }
        .liga-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, #1DF2DD, #168C77); }
        .liga-card h3 { color: #fff; margin-bottom: 10px; font-size: 1.5rem; }
        .liga-card p { color: #6b6b7a; font-size: 1rem; margin-bottom: 5px; }
        .liga-card .dato { color: #1DF2DD; font-weight: bold; }
        .liga-card .presupuesto { color: #28a745; font-weight: bold; }
        .no-ligas { color: #A63247; font-size: 1.2rem; }
    </style>
</head>
<body>

    <div class="header">
        <h1>VALTASY <span style="font-size: 0.5em; color: gray;">DASHBOARD</span></h1>
        <div class="bienvenida">
            <h2>Agente: <span id="nombreUsuarioActual"><?php echo htmlspecialchars($nombre_usuario_actual); ?></span></h2>
            <a href="login/cerrar.php" style="color: #6b6b7a; text-decoration: none;">Desconectar</a>
        </div>
    </div>

    <div class="panel-acciones">
        <a href="crear_liga.php" class="btn-crear">+ Nueva Operación (Crear Liga)</a>
        </div>

    <h2>Tus Operaciones Activas</h2>
    <br>
    
    <div id="contenedorLigas" class="grid-ligas">
        <p style="color: #6b6b7a;">Cargando datos de los servidores...</p>
    </div>

    <script>
        // Al cargar la página, consumimos la API
        document.addEventListener("DOMContentLoaded", async () => {
            const contenedor = document.getElementById("contenedorLigas");
            const usuario = document.getElementById("nombreUsuarioActual").innerText;

            try {
                // Hacemos un GET a la API pasando el nombre del usuario
                const res = await fetch(`api_ligas.php?usuario=${usuario}`);
                const json = await res.json();

                contenedor.innerHTML = ""; // Limpiamos el texto de "Cargando..."

                if (json.status === "success" && json.total_ligas > 0) {
                    // Recorremos las ligas y creamos el HTML para cada una
                    json.data.forEach(liga => {
                        // Formateamos el presupuesto (ej: 1000000 -> 1.000.000)
                        let presuFormat = new Intl.NumberFormat('es-ES').format(liga.presupuesto_disponible);

                        const htmlCard = `
                            <div class="liga-card">
                                <h3>${liga.nombre_liga}</h3>
                                <p>TIPO: <span class="dato">${liga.tipo}</span></p>
                                <hr style="border-color: rgba(255,255,255,0.05); margin: 15px 0;">
                                <p>ESCUADRÓN: <span style="color:#fff;">${liga.nombre_equipo}</span></p>
                                <p>PUNTOS: <span class="dato">${liga.puntos_equipo}</span></p>
                                <p>POSICIÓN: <span class="dato">#${liga.posicion_actual}</span></p>
                                <p>PRESUPUESTO: <span class="presupuesto">$${presuFormat}</span></p>
                            </div>
                        `;
                        contenedor.innerHTML += htmlCard;
                    });
                } else {
                    contenedor.innerHTML = `<p class="no-ligas">No estás asignado a ninguna operación actualmente. ¡Crea una para empezar!</p>`;
                }
            } catch (error) {
                contenedor.innerHTML = `<p class="error">Error al conectar con la base de datos de Kingdom.</p>`;
                console.error(error);
            }
        });
    </script>
</body>
</html>