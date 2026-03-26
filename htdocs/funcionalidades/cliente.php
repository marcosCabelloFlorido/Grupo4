<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    // login está en sesion/ → ../sesion/login.php
    header("Location: ../sesion/login.php");
    exit();
}
$nombre_usuario_actual = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>VALTASY — Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Barlow+Condensed:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0d0d0f; color: #e8e8ee; font-family: 'Barlow Condensed', sans-serif; padding: 40px; background-image: radial-gradient(ellipse 50% 50% at 50% 50%, rgba(140, 8, 19, 0.12) 0%, transparent 70%); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #A63247; padding-bottom: 20px; margin-bottom: 30px; }
        h1 { font-family: 'Orbitron'; }
        .btn-crear { padding: 12px 24px; background: linear-gradient(135deg, #168C77, #1DF2DD); color: #000; text-decoration: none; font-weight: 700; clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%); }
        .grid-ligas { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .liga-card { background: #141418; border: 1px solid rgba(29, 242, 221, 0.2); padding: 20px; border-top: 2px solid #1DF2DD; }
    </style>
</head>
<body>
    <div class="header">
        <h1>VALTASY DASHBOARD</h1>
        <div>
            <p>Agente: <span style="color:#1DF2DD"><?php echo htmlspecialchars($nombre_usuario_actual); ?></span></p>
            <!-- cerrar.php está en sesion/ -->
            <a href="../sesion/cerrar.php" style="color:#6b6b7a">Desconectar</a>
        </div>
    </div>

    <!-- crear_liga.php está en la misma carpeta funcionalidades/ -->
    <a href="crear_liga.php" class="btn-crear">+ Crear Liga</a>
    <br><br>
    <div id="contenedorLigas" class="grid-ligas">Cargando datos...</div>

    <script>
        document.addEventListener("DOMContentLoaded", async () => {
            const contenedor = document.getElementById("contenedorLigas");
            const usuario    = "<?php echo htmlspecialchars($nombre_usuario_actual, ENT_QUOTES); ?>";
            try {
                // api_ligas.php está en la misma carpeta funcionalidades/
                const res  = await fetch(`api_ligas.php?usuario=${encodeURIComponent(usuario)}`);
                const json = await res.json();
                contenedor.innerHTML = "";
                if (json.status === "success" && json.total_ligas > 0) {
                    json.data.forEach(liga => {
                        contenedor.innerHTML += `
                            <div class="liga-card">
                                <h3>${liga.nombre_liga}</h3>
                                <p>EQUIPO: ${liga.nombre_equipo}</p>
                                <p>PUNTOS: ${liga.puntos_equipo}</p>
                                <p>PRESUPUESTO: $${new Intl.NumberFormat('es-ES').format(liga.presupuesto_disponible)}</p>
                            </div>`;
                    });
                } else {
                    contenedor.innerHTML = "<p>No tienes ligas activas.</p>";
                }
            } catch (e) { contenedor.innerHTML = "Error de conexión."; }
        });
    </script>
</body>
</html>
