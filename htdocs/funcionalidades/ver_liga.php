<?php
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: ../sesion/login.php"); exit(); }
require_once __DIR__ . '/../conexion.php';
$nombre_usuario_actual = $_SESSION['usuario']; $error_pagina = null; $liga_info = null; $plantilla = []; $titulares = []; $reservas = []; $es_primer_acceso = false;

if (!isset($_GET['id_liga']) || !ctype_digit($_GET['id_liga'])) { header("Location: cliente.php"); exit(); }
$id_liga = (int) $_GET['id_liga'];

try {
    $stmtUser = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = :nombre");
    $stmtUser->execute([':nombre' => $nombre_usuario_actual]);
    $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) throw new Exception("Sesión inválida.");
    $id_usuario = $usuario['id_usuario'];

    // Datos generales de la liga y el equipo del usuario
    $queryLiga = "SELECT L.id_liga, L.nombre AS nombre_liga, L.tipo, L.torneo, L.codigo_acceso, EF.id_equipo_fantasy, EF.nombre_equipo, EF.presupuesto_disponible, EF.puntos_equipo FROM ligas L INNER JOIN participaciones P ON L.id_liga = P.id_liga INNER JOIN equipos_fantasy EF ON P.id_usuario = EF.id_usuario AND P.id_liga = EF.id_liga WHERE L.id_liga = :id_liga AND P.id_usuario = :id_usuario";
    $stmtLiga = $conexion->prepare($queryLiga); $stmtLiga->execute([':id_liga' => $id_liga, ':id_usuario' => $id_usuario]);
    $liga_info = $stmtLiga->fetch(PDO::FETCH_ASSOC);
    if (!$liga_info) throw new Exception("No tienes acceso a esta liga.");
    $id_equipo_fantasy = (int)$liga_info['id_equipo_fantasy'];

    // Obtener la última jornada registrada para este equipo para mostrarla en la UI
    $stmtJornada = $conexion->prepare("SELECT MAX(jornada) FROM alineaciones WHERE id_equipo_fantasy = :id_ef");
    $stmtJornada->execute([':id_ef' => $id_equipo_fantasy]);
    $jornada_actual = $stmtJornada->fetchColumn();
    $jornada_actual = $jornada_actual ? $jornada_actual : 1; // Fallback a 1 por defecto

    // COMPROBACIÓN DE PRIMER ACCESO (ASIGNACIÓN DE JUGADORES)
    $stmtCheck = $conexion->prepare("SELECT COUNT(*) FROM alineaciones WHERE id_equipo_fantasy = :id_ef");
    $stmtCheck->execute([':id_ef' => $id_equipo_fantasy]);
    
    if ($stmtCheck->fetchColumn() == 0) {
        $es_primer_acceso = true; $conexion->beginTransaction();
        $roles_requeridos = ['Duelista' => 1, 'Iniciador' => 1, 'Centinela' => 2, 'Smoker' => 1]; $asignados = [];
        $queryRol = "SELECT J.id_jugador, J.nickname, J.rol, J.precio_mercado, J.media_punto FROM jugadores J INNER JOIN equipos_profesionales EP ON J.id_equipo_profesional = EP.id_equipo_profesional WHERE J.rol = :rol AND EP.region = 'EMEA' AND J.id_jugador NOT IN (SELECT A.id_jugador FROM alineaciones A INNER JOIN equipos_fantasy EF ON A.id_equipo_fantasy = EF.id_equipo_fantasy WHERE EF.id_liga = :id_liga) AND J.id_jugador NOT IN (SELECT id_jugador FROM mercado_liga WHERE id_liga = :id_liga) ORDER BY RAND() LIMIT :limite";
        $stmtRol = $conexion->prepare($queryRol);

        foreach ($roles_requeridos as $rol => $cantidad) {
            $stmtRol->bindValue(':rol', $rol, PDO::PARAM_STR);
            $stmtRol->bindValue(':id_liga', $id_liga, PDO::PARAM_INT);
            $stmtRol->bindValue(':limite', $cantidad, PDO::PARAM_INT);
            $stmtRol->execute();
            
            $jugadores_rol = $stmtRol->fetchAll(PDO::FETCH_ASSOC);
            if (count($jugadores_rol) < $cantidad) { 
                $conexion->rollBack(); 
                throw new Exception("Mercado inicial agotado para el rol '$rol' en Europa."); 
            }
            $asignados = array_merge($asignados, $jugadores_rol);
        }

        $stmtIns = $conexion->prepare("INSERT INTO alineaciones (id_equipo_fantasy, id_jugador, jornada, titular, puntos_jornada) VALUES (:id_ef, :id_j, 1, 1, 0)");
        foreach ($asignados as $aj) { $stmtIns->execute([':id_ef' => $id_equipo_fantasy, ':id_j' => $aj['id_jugador']]); }
        $conexion->commit();
        
        foreach ($asignados as $aj) { 
            $aj['titular'] = 1; 
            $aj['puntos_jornada'] = 0; 
            $aj['nombre_equipo_profesional'] = 'Libre'; 
            $aj['total_kills'] = 0; $aj['total_deaths'] = 0; $aj['total_assists'] = 0; $aj['total_aces'] = 0; $aj['total_clutches'] = 0;
            $plantilla[] = $aj; 
        }
    } else {
        // Carga de alineación normal + Estadísticas agrupadas para el Tooltip
        $queryMios = "SELECT A.id_jugador, J.nickname, J.rol, J.precio_mercado, A.titular, A.puntos_jornada, EP.nombre_equipo_profesional,
                      COALESCE(SUM(E.kills), 0) as total_kills, COALESCE(SUM(E.deaths), 0) as total_deaths, 
                      COALESCE(SUM(E.assist), 0) as total_assists, COALESCE(SUM(E.ace), 0) as total_aces, COALESCE(SUM(E.clutch), 0) as total_clutches
                      FROM alineaciones A 
                      INNER JOIN jugadores J ON A.id_jugador = J.id_jugador 
                      LEFT JOIN equipos_profesionales EP ON J.id_equipo_profesional = EP.id_equipo_profesional 
                      LEFT JOIN estadisticas E ON J.id_jugador = E.id_jugador
                      WHERE A.id_equipo_fantasy = :id_ef 
                      GROUP BY A.id_jugador, J.nickname, J.rol, J.precio_mercado, A.titular, A.puntos_jornada, EP.nombre_equipo_profesional
                      ORDER BY A.titular DESC, FIELD(J.rol, 'Duelista', 'Iniciador', 'Smoker', 'Centinela')";
        $stmtMios = $conexion->prepare($queryMios); $stmtMios->execute([':id_ef' => $id_equipo_fantasy]);
        $plantilla = $stmtMios->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $titulares = array_filter($plantilla, function($j) { return $j['titular'] == 1; });
    $reservas  = array_filter($plantilla, function($j) { return $j['titular'] == 0; });
    $puntos_ultima_jornada = array_sum(array_column($titulares, 'puntos_jornada'));

    // RANKING POR JORNADA
    $queryRankJornada = "SELECT EF.id_equipo_fantasy, EF.nombre_equipo, U.nombre AS manager, COALESCE(SUM(A.puntos_jornada), 0) AS puntos_jornada_total 
                         FROM equipos_fantasy EF 
                         INNER JOIN usuarios U ON EF.id_usuario = U.id_usuario
                         LEFT JOIN alineaciones A ON EF.id_equipo_fantasy = A.id_equipo_fantasy AND A.titular = 1
                         WHERE EF.id_liga = :id_liga
                         GROUP BY EF.id_equipo_fantasy, EF.nombre_equipo, U.nombre
                         ORDER BY puntos_jornada_total DESC";
    $stmtRankJ = $conexion->prepare($queryRankJornada);
    $stmtRankJ->execute([':id_liga' => $id_liga]);
    $clasificacion_jornada = $stmtRankJ->fetchAll(PDO::FETCH_ASSOC);

    // RANKING GLOBAL
    $stmtRanking = $conexion->prepare("SELECT EF.id_equipo_fantasy, U.nombre AS manager, EF.nombre_equipo, EF.puntos_equipo FROM equipos_fantasy EF INNER JOIN usuarios U ON EF.id_usuario = U.id_usuario WHERE EF.id_liga = :id_liga ORDER BY EF.puntos_equipo DESC");
    $stmtRanking->execute([':id_liga' => $id_liga]); 
    $clasificacion_global = $stmtRanking->fetchAll(PDO::FETCH_ASSOC);

    // ROSTERS PARA LA TABLA GLOBAL
    $stmtRosters = $conexion->prepare("SELECT A.id_equipo_fantasy, J.nickname, A.puntos_jornada, J.rol FROM alineaciones A INNER JOIN jugadores J ON A.id_jugador = J.id_jugador INNER JOIN equipos_fantasy EF ON A.id_equipo_fantasy = EF.id_equipo_fantasy WHERE EF.id_liga = :id_liga AND A.titular = 1");
    $stmtRosters->execute([':id_liga' => $id_liga]); $rosters_raw = $stmtRosters->fetchAll(PDO::FETCH_ASSOC);
    $rosters = []; foreach ($rosters_raw as $r) { $rosters[$r['id_equipo_fantasy']][] = $r; }

} catch (Exception $e) { if (isset($conexion) && $conexion->inTransaction()) $conexion->rollBack(); $error_pagina = $e->getMessage(); }

function getIcon($rol) { $iconos = ['Duelista'=>'⚔', 'Iniciador'=>'🔍', 'Centinela'=>'🛡', 'Smoker'=>'🌀']; return $iconos[$rol] ?? '•'; }
function getColor($rol) { $colores = ['Duelista'=>'#ff4d6d', 'Iniciador'=>'#4dffb8', 'Centinela'=>'#4d9fff', 'Smoker'=>'#c084fc']; return $colores[$rol] ?? '#6b6b7a'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>VALTASY — <?php echo htmlspecialchars($liga_info['nombre_liga'] ?? 'Error'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Barlow+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --cyan: #1DF2DD; --cyan-dark: #168C77; 
            --red-soft: #A63247; --bg: #0d0d0f; 
            --bg-card: #141418; --border: rgba(29,242,221,0.12); 
            --text: #e8e8ee; --muted: #6b6b7a; --purple: #c084fc; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: var(--bg); color: var(--text); 
            font-family: 'Barlow Condensed', sans-serif; 
            min-height: 100vh; 
            background-image: radial-gradient(ellipse 60% 50% at 50% 0%, rgba(140,8,19,0.1) 0%, transparent 60%), 
                              linear-gradient(rgba(29,242,221,0.02) 1px, transparent 1px), 
                              linear-gradient(90deg, rgba(29,242,221,0.02) 1px, transparent 1px); 
            background-size: cover, 60px 60px, 60px 60px; 
            overflow-x: hidden;
        }
        
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 16px 36px; border-bottom: 1px solid rgba(255,255,255,0.06); background: rgba(13,13,15,0.9); backdrop-filter: blur(8px); position: sticky; top: 0; z-index: 100; }
        .nav-sub { display: flex; gap: 4px; padding: 0 36px; border-bottom: 1px solid rgba(29, 242, 221, 0.12); background: rgba(13,13,15,0.9); position: sticky; top: 57px; z-index: 99; }
        .nav-tab { font-family: 'Barlow Condensed', sans-serif; font-size: 0.85rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #6b6b7a; text-decoration: none; padding: 10px 22px; border: 1px solid transparent; border-bottom: none; clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%); transition: color 0.2s, background 0.2s; position: relative; top: 1px; }
        .nav-tab:hover { color: #1DF2DD; }
        .nav-tab.activo { color: #1DF2DD; background: #141418; border-color: rgba(29, 242, 221, 0.25); border-bottom-color: #141418; }
        .topbar-left { display: flex; align-items: center; gap: 20px; }
        .btn-back { color: var(--muted); text-decoration: none; font-size: 0.78rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; }
        .topbar-title { font-family: 'Orbitron'; font-size: 0.85rem; color: var(--text); letter-spacing: 0.1em; }
        .topbar-right { display: flex; align-items: center; gap: 12px; }

        .torneo-label { padding: 9px 12px; background: rgba(29,242,221,0.05); border: 1px solid var(--cyan); color: var(--cyan); font-family: 'Barlow Condensed'; font-weight: 700; text-transform: uppercase; font-size: 0.9rem;}
        .select-jornada { padding: 9px 12px; background: rgba(0,0,0,0.6); border: 1px solid var(--purple); color: var(--purple); font-family: 'Barlow Condensed'; font-weight: 700; text-transform: uppercase; outline: none; cursor: pointer; transition: 0.2s;}
        .select-jornada:hover { background: rgba(192, 132, 252, 0.1); }
        .btn-simular { padding: 9px 20px; background: rgba(192, 132, 252, 0.1); border: 1px solid var(--purple); color: var(--purple); font-weight: 900; font-size: 0.82rem; text-transform: uppercase; cursor: pointer; clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%); transition: 0.2s; }
        .btn-simular:hover { background: var(--purple); color: #000; box-shadow: 0 0 15px rgba(192,132,252,0.4); }
        .btn-mercado-top { padding: 9px 20px; background: linear-gradient(135deg, var(--cyan-dark), var(--cyan)); color: #000; font-weight: 700; font-size: 0.82rem; text-decoration: none; clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%); }

        .liga-hero { padding: 32px 36px 0; }
        .liga-nombre { font-family: 'Orbitron'; font-size: 1.8rem; font-weight: 900; text-transform: uppercase;}
        
        .liga-codigo-container { display: inline-flex; align-items: center; gap: 10px; background: rgba(29,242,221,0.05); border: 1px dashed var(--cyan); padding: 8px 20px; margin-top: 15px; cursor: pointer; transition: 0.2s; }
        .liga-codigo-container:hover { background: rgba(29,242,221,0.1); }
        .codigo-label { font-size: 0.8rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }
        .codigo-val { font-family: 'Orbitron'; font-size: 1.2rem; color: var(--cyan); font-weight: 900; letter-spacing: 2px; }

        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1px; background: rgba(255,255,255,0.06); margin-top: 28px; }
        .stat-box { background: var(--bg-card); padding: 18px 20px; text-align: center; }
        .stat-label { font-size: 0.68rem; letter-spacing: 0.12em; color: var(--muted); text-transform: uppercase; margin-bottom: 6px; }
        .stat-val { font-family: 'Orbitron'; font-size: 1.3rem; color: var(--cyan); }

        .ranking-jornada-container { display: flex; gap: 15px; padding: 0 36px; overflow-x: auto; margin-top: 20px; padding-bottom: 15px; scrollbar-width: thin; scrollbar-color: var(--purple) var(--bg-card); }
        .card-jornada { min-width: 200px; background: rgba(192, 132, 252, 0.05); border: 1px solid rgba(192, 132, 252, 0.1); padding: 15px; border-left: 3px solid var(--purple); position: relative;}
        .card-jornada.top-1 { background: rgba(29, 242, 221, 0.05); border-color: var(--cyan); border-left-color: var(--cyan); box-shadow: inset 0 0 20px rgba(29, 242, 221, 0.05); }
        .cj-pos { font-family: 'Orbitron'; font-size: 0.7rem; color: var(--purple); margin-bottom: 5px; display: block;}
        .top-1 .cj-pos { color: var(--cyan); }
        .cj-nombre { font-family: 'Orbitron'; font-size: 0.9rem; color: #fff; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
        .cj-pts { font-family: 'Orbitron'; font-size: 1.4rem; color: #fff; font-weight: 900; }
        .cj-pts span { font-size: 0.7rem; color: var(--muted); margin-left: 5px;}

        .panel-total { background: linear-gradient(90deg, rgba(166,50,71,0.2), transparent); border-left: 4px solid var(--red-soft); padding: 25px 36px; margin: 25px 36px; display: flex; justify-content: space-between; align-items: center; border-radius: 2px; }
        .panel-total-titulo { font-size: 0.8rem; color: var(--muted); letter-spacing: 0.1em; text-transform: uppercase; }
        .panel-total-sub { font-family: 'Orbitron'; font-size: 1.6rem; color: #fff; margin-top: 5px; }
        .panel-total-puntos { font-family: 'Orbitron'; font-size: 2.8rem; color: var(--red-soft); font-weight: 900; }

        .seccion-titulo { padding: 0 36px; font-family: 'Orbitron'; font-size: 1.1rem; color: #fff; text-transform: uppercase; margin-bottom: 16px; margin-top: 30px; border-bottom: 1px solid var(--border); padding-bottom: 10px;}
        
        .grid-jugadores { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 36px; padding: 0 36px;}
        .card-jugador { 
            flex: 1 1 200px; max-width: 260px; background: var(--bg-card); 
            border: 1px solid rgba(255,255,255,0.06); padding: 18px; 
            position: relative; transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; 
            cursor: pointer; 
        }
        .card-jugador:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.5); }
        .card-jugador.titular { border-top: 3px solid var(--border-color, var(--cyan)); }
        .card-jugador.reserva { border-top: 3px solid var(--border-color, var(--red-soft)); opacity: 0.85; }
        .card-jugador.reserva:hover { opacity: 1; }
        
        .jugador-rol { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 6px; }
        .jugador-nick { font-family: 'Orbitron'; font-size: 1.2rem; margin-bottom: 4px; color: #fff; }
        .jugador-equipo-pro { font-size: 0.78rem; color: var(--muted); margin-bottom: 15px; }
        
        .jugador-puntos-badge { display: inline-block; background: rgba(29,242,221,0.1); border: 1px solid var(--cyan); padding: 5px 10px; color: var(--cyan); font-family: 'Orbitron'; font-weight: 900; font-size: 1.1rem; border-radius: 3px; margin-bottom: 15px; width: 100%; text-align: center;}
        .jugador-puntos-badge.cero { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: var(--muted); }

        .btn-accion { width: 100%; padding: 8px; background: transparent; cursor: pointer; font-family: 'Barlow Condensed'; font-weight: 700; text-transform: uppercase; margin-top: 6px; transition: 0.2s; font-size: 0.85rem;}
        .btn-mover { border: 1px solid rgba(29,242,221,0.4); color: var(--cyan); }
        .btn-mover:hover { background: rgba(29,242,221,0.1); }
        .btn-vender { border: 1px solid rgba(255,255,255,0.08); color: var(--muted); }
        .btn-vender:hover { border-color: var(--red-soft); color: var(--red-soft); background: rgba(166,50,71,0.1); }

        /* Modal Tooltip de Estadísticas */
        .stats-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); display: none; align-items: center; justify-content: center; z-index: 9999; backdrop-filter: blur(4px); opacity: 0; transition: opacity 0.2s; }
        .stats-overlay.active { display: flex; opacity: 1; }
        .stats-panel { background: #0d0d0f; border: 1px solid var(--cyan); width: 400px; max-width: 90%; position: relative; clip-path: polygon(15px 0%, 100% 0%, 100% calc(100% - 15px), calc(100% - 15px) 100%, 0% 100%, 0% 15px); padding: 30px; box-shadow: 0 0 30px rgba(29,242,221,0.15); transform: scale(0.95); transition: transform 0.2s; }
        .stats-overlay.active .stats-panel { transform: scale(1); }
        .sp-close { position: absolute; top: 15px; right: 20px; color: var(--muted); cursor: pointer; font-family: 'Orbitron'; font-size: 1.2rem; transition: 0.2s; }
        .sp-close:hover { color: var(--red-soft); }
        .sp-header { border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; margin-bottom: 20px; }
        .sp-rol { font-size: 0.8rem; letter-spacing: 2px; text-transform: uppercase; font-weight: bold; margin-bottom: 5px; }
        .sp-name { font-family: 'Orbitron'; font-size: 2rem; color: #fff; line-height: 1; }
        .sp-team { font-size: 0.9rem; color: var(--muted); margin-top: 5px; }
        .sp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .sp-data-box { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); padding: 12px; text-align: center; }
        .sp-data-label { font-size: 0.7rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }
        .sp-data-val { font-family: 'Orbitron'; font-size: 1.5rem; color: var(--cyan); font-weight: bold; margin-top: 5px; }

        .ranking-container { padding: 0 36px 50px; margin-top: 20px;}
        .rank-row { display: flex; align-items: center; background: var(--bg-card); border: 1px solid rgba(255,255,255,0.05); margin-bottom: 12px; padding: 15px 20px; transition: transform 0.2s; }
        .rank-row:hover { transform: translateX(5px); border-color: rgba(255,255,255,0.2); }
        .rank-row.is-me { border-left: 4px solid var(--cyan); background: rgba(29,242,221,0.03); }
        .rank-pos { font-family: 'Orbitron'; font-size: 1.6rem; font-weight: 900; width: 50px; color: var(--muted); }
        .rank-pos.gold { color: #FFD700; text-shadow: 0 0 10px rgba(255,215,0,0.4); }
        .rank-pos.silver { color: #C0C0C0; }
        .rank-pos.bronze { color: #CD7F32; }
        .rank-info { flex: 1; }
        .rank-equipo { font-family: 'Orbitron'; font-size: 1.1rem; color: #fff; margin-bottom: 2px;}
        .rank-manager { font-size: 0.8rem; color: var(--cyan); letter-spacing: 0.1em; text-transform: uppercase;}
        .rank-roster { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;}
        .roster-pill { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 3px; font-size: 0.75rem; display: flex; align-items: center; gap: 6px; }
        .roster-pill span { color: var(--cyan); font-weight: bold; font-family: 'Orbitron'; }
        .rank-pts { font-family: 'Orbitron'; font-size: 1.8rem; color: var(--cyan); font-weight: 900; text-align: right; }
        
        .modal-sim { position: fixed; inset: 0; background: rgba(0,0,0,0.9); display: none; align-items: center; justify-content: center; z-index: 9999; backdrop-filter: blur(5px); padding: 20px;}
        .modal-sim.visible { display: flex; }
        .sim-box { background: #141418; border: 2px solid #c084fc; max-width: 600px; width: 100%; padding: 30px; max-height: 85vh; overflow-y: auto; }
        .sim-title { font-family: 'Orbitron'; font-size: 1.5rem; color: #c084fc; text-align: center; margin-bottom: 20px;}
        .sim-matchup { display: flex; flex-direction: column; gap: 8px; margin-bottom: 25px;}
        .sim-player { display: flex; justify-content: space-between; background: rgba(255,255,255,0.05); padding: 10px 15px; border-left: 3px solid #c084fc; margin-bottom: 8px;}
        .sim-player-nick { font-family: 'Orbitron'; color: #fff; font-size: 1rem;}
        .sim-player-pts { font-family: 'Orbitron'; font-size: 1.1rem; color: #1DF2DD; font-weight: 700; }
        
        #copiadoMsg { position: fixed; top: 100px; left: 50%; transform: translateX(-50%); background: var(--cyan); color: #000; padding: 8px 15px; font-weight: 900; font-family: 'Orbitron'; font-size: 0.9rem; z-index: 1000; display: none; }
    </style>
</head>
<body>
<div id="copiadoMsg">¡CÓDIGO COPIADO!</div>

<?php if ($error_pagina): ?><div style="text-align:center; padding:100px; color:#A63247"><?php echo $error_pagina; ?></div><?php else: ?>

<div class="stats-overlay" id="playerStatsModal" onclick="closePlayerStats(event)">
    <div class="stats-panel" onclick="event.stopPropagation()">
        <div class="sp-close" onclick="closePlayerStats(event)">✕</div>
        <div class="sp-header">
            <div class="sp-rol" id="spRol">ROL</div>
            <div class="sp-name" id="spName">NICKNAME</div>
            <div class="sp-team" id="spTeam">EQUIPO</div>
        </div>
        <div class="sp-grid">
            <div class="sp-data-box">
                <div class="sp-data-label">Kills Totales</div>
                <div class="sp-data-val" id="spKills">0</div>
            </div>
            <div class="sp-data-box">
                <div class="sp-data-label">Muertes Totales</div>
                <div class="sp-data-val" id="spDeaths" style="color: var(--red-soft);">0</div>
            </div>
            <div class="sp-data-box">
                <div class="sp-data-label">Asistencias</div>
                <div class="sp-data-val" id="spAssists">0</div>
            </div>
            <div class="sp-data-box">
                <div class="sp-data-label">Aces logrados</div>
                <div class="sp-data-val" id="spAces" style="color: #FFD700;">0</div>
            </div>
            <div class="sp-data-box" style="grid-column: span 2;">
                <div class="sp-data-label">Clutches ganados</div>
                <div class="sp-data-val" id="spClutches" style="color: var(--purple);">0</div>
            </div>
        </div>
    </div>
</div>

<div class="topbar">
    <div class="topbar-left"><a href="cliente.php" class="btn-back">Dashboard</a> <span class="topbar-title"><?php echo htmlspecialchars($liga_info['nombre_liga']); ?></span></div>
    <div class="topbar-right">
        <span class="torneo-label">🏆 <?php echo htmlspecialchars($liga_info['torneo']); ?></span>
        <select id="jornadaSelect" class="select-jornada">
            <option value="1">Jornada 1</option>
            <option value="2">Jornada 2</option>
            <option value="3">Jornada 3</option>
            <option value="4">Jornada 4</option>
            <option value="5">Jornada 5</option>
        </select>
        <button class="btn-simular" onclick="simularPartido()" id="btnSim">▶ SIMULAR</button>
        <a href="mercado.php?id_liga=<?php echo $id_liga; ?>" class="btn-mercado-top">🛒 MERCADO</a>
    </div>
</div>

<nav class="nav-sub">
    <a href="cliente.php"    class="nav-tab">Dashboard</a>
    <a href="crear_liga.php" class="nav-tab">Nueva Liga</a>
    <a href="noticias.php"   class="nav-tab">Noticias</a>
</nav>

<div class="liga-hero">
    <h1 class="liga-nombre"><?php echo htmlspecialchars($liga_info['nombre_liga']); ?></h1>
    
    <?php if ($liga_info['tipo'] === 'Privada' && !empty($liga_info['codigo_acceso'])): ?>
    <div class="liga-codigo-container" onclick="copiarCodigo('<?php echo htmlspecialchars($liga_info['codigo_acceso'], ENT_QUOTES); ?>')" title="Haz clic para copiar">
        <span class="codigo-label">INVITAR AGENTES:</span>
        <span class="codigo-val"><?php echo htmlspecialchars($liga_info['codigo_acceso']); ?> 📋</span>
    </div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-box"><div class="stat-label">Mi Escuadrón</div><div class="stat-val" style="color:#fff"><?php echo htmlspecialchars($liga_info['nombre_equipo']); ?></div></div>
        <div class="stat-box"><div class="stat-label">Presupuesto</div><div class="stat-val"><?php echo number_format($liga_info['presupuesto_disponible'], 0, ',', '.'); ?> €</div></div>
        <div class="stat-box"><div class="stat-label">Mis Puntos (Jornada <?php echo $jornada_actual; ?>)</div><div class="stat-val" style="color:var(--purple)"><?php echo $puntos_ultima_jornada; ?> PTS</div></div>
    </div>
</div>

<div class="seccion-titulo" style="color: var(--purple); border-color: rgba(192, 132, 252, 0.2);">Líderes de la Jornada <?php echo $jornada_actual; ?></div>
<div class="ranking-jornada-container">
    <?php 
    $posJ = 1;
    foreach($clasificacion_jornada as $cj): 
        $claseTop = ($posJ == 1) ? 'top-1' : '';
    ?>
    <div class="card-jornada <?php echo $claseTop; ?>">
        <span class="cj-pos">RANK #<?php echo $posJ; ?></span>
        <div class="cj-nombre"><?php echo htmlspecialchars($cj['nombre_equipo']); ?></div>
        <div class="cj-pts"><?php echo $cj['puntos_jornada_total']; ?> <span>PTS</span></div>
    </div>
    <?php $posJ++; endforeach; ?>
</div>

<div class="panel-total">
    <div>
        <div class="panel-total-titulo">Clasificación General</div>
        <div class="panel-total-sub">PUNTOS TOTALES DE LA COMPETICIÓN</div>
    </div>
    <div class="panel-total-puntos"><?php echo $liga_info['puntos_equipo']; ?> <span style="font-size: 1.2rem; color: #6b6b7a;">PTS</span></div>
</div>

<div class="seccion-titulo">Mis Titulares (<?php echo count($titulares); ?>/5)</div>
<div class="grid-jugadores">
    <?php foreach ($titulares as $j): 
        $colorRol = getColor($j['rol']);
    ?>
    <div class="card-jugador titular" style="--border-color: <?php echo $colorRol; ?>;" onclick="openPlayerStats('<?php echo htmlspecialchars($j['nickname']); ?>', '<?php echo $j['rol']; ?>', '<?php echo htmlspecialchars($j['nombre_equipo_profesional'] ?? 'Libre'); ?>', '<?php echo $colorRol; ?>', <?php echo $j['total_kills']; ?>, <?php echo $j['total_deaths']; ?>, <?php echo $j['total_assists']; ?>, <?php echo $j['total_aces']; ?>, <?php echo $j['total_clutches']; ?>)">
        <div class="jugador-rol" style="color:<?php echo $colorRol; ?>"><?php echo getIcon($j['rol']); ?> <?php echo strtoupper($j['rol']); ?></div>
        <div class="jugador-nick"><?php echo htmlspecialchars($j['nickname']); ?></div>
        <div class="jugador-equipo-pro">🏢 <?php echo htmlspecialchars($j['nombre_equipo_profesional'] ?? 'Libre'); ?></div>
        <div class="jugador-puntos-badge <?php echo ($j['puntos_jornada'] == 0) ? 'cero' : ''; ?>"><?php echo $j['puntos_jornada']; ?> PTS</div>
        
        <button class="btn-accion btn-mover" style="color:var(--red-soft); border-color:rgba(166,50,71,0.4);" onclick="event.stopPropagation(); cambiarEstado(<?php echo $j['id_jugador']; ?>, 0, <?php echo count($titulares); ?>)">↓ Mover a Reservas</button>
        <button class="btn-accion btn-vender" onclick="event.stopPropagation(); venderJugador(<?php echo $j['id_jugador']; ?>, '<?php echo htmlspecialchars($j['nickname'], ENT_QUOTES); ?>')">Vender (-20%)</button>
    </div>
    <?php endforeach; ?>
</div>

<div class="seccion-titulo" style="color: var(--red-soft); border-color: rgba(166,50,71,0.2);">Banquillo (Reservas)</div>
<div class="grid-jugadores">
    <?php if(empty($reservas)): ?><p style="color:var(--muted); font-size:0.9rem; margin-left: 5px;">Tu banquillo está vacío.</p><?php endif; ?>
    <?php foreach ($reservas as $j): 
        $colorRol = getColor($j['rol']);
    ?>
    <div class="card-jugador reserva" style="--border-color: var(--red-soft);" onclick="openPlayerStats('<?php echo htmlspecialchars($j['nickname']); ?>', '<?php echo $j['rol']; ?>', '<?php echo htmlspecialchars($j['nombre_equipo_profesional'] ?? 'Libre'); ?>', '<?php echo $colorRol; ?>', <?php echo $j['total_kills']; ?>, <?php echo $j['total_deaths']; ?>, <?php echo $j['total_assists']; ?>, <?php echo $j['total_aces']; ?>, <?php echo $j['total_clutches']; ?>)">
        <div class="jugador-rol" style="color:<?php echo $colorRol; ?>"><?php echo getIcon($j['rol']); ?> <?php echo strtoupper($j['rol']); ?></div>
        <div class="jugador-nick"><?php echo htmlspecialchars($j['nickname']); ?></div>
        <div class="jugador-equipo-pro">🏢 <?php echo htmlspecialchars($j['nombre_equipo_profesional'] ?? 'Libre'); ?></div>
        <div class="jugador-puntos-badge cero">BANQUILLO</div>
        
        <button class="btn-accion btn-mover" onclick="event.stopPropagation(); cambiarEstado(<?php echo $j['id_jugador']; ?>, 1, <?php echo count($titulares); ?>)">↑ Subir a Titular</button>
        <button class="btn-accion btn-vender" onclick="event.stopPropagation(); venderJugador(<?php echo $j['id_jugador']; ?>, '<?php echo htmlspecialchars($j['nickname'], ENT_QUOTES); ?>')">Vender (-20%)</button>
    </div>
    <?php endforeach; ?>
</div>

<div class="seccion-titulo" style="color: #FFD700; border-color: rgba(255,215,0,0.2); margin-top: 50px;">Clasificación Global de la Liga</div>
<div class="ranking-container">
    <?php $posicion = 1; foreach ($clasificacion_global as $c): 
        $clase_pos = ($posicion == 1) ? 'gold' : (($posicion == 2) ? 'silver' : (($posicion == 3) ? 'bronze' : ''));
        $is_me = ($c['id_equipo_fantasy'] == $id_equipo_fantasy) ? 'is-me' : '';
    ?>
    <div class="rank-row <?php echo $is_me; ?>">
        <div class="rank-pos <?php echo $clase_pos; ?>">#<?php echo $posicion; ?></div>
        <div class="rank-info">
            <div class="rank-equipo"><?php echo htmlspecialchars($c['nombre_equipo']); ?> <?php if($is_me) echo '<span style="font-size:0.7rem; background:var(--cyan); color:#000; padding:2px 5px; border-radius:3px; vertical-align:middle; margin-left:5px;">TÚ</span>'; ?></div>
            <div class="rank-manager">Mánager: <?php echo htmlspecialchars($c['manager']); ?></div>
            <div class="rank-roster">
                <?php if (isset($rosters[$c['id_equipo_fantasy']])) {
                    foreach ($rosters[$c['id_equipo_fantasy']] as $rj) { echo "<div class='roster-pill'>" . getIcon($rj['rol']) . " " . htmlspecialchars($rj['nickname']) . " <span style='color:#1DF2DD'>" . $rj['puntos_jornada'] . " pts</span></div>"; }
                } else echo "<div class='roster-pill' style='color:gray'>Sin jugadores titulares</div>"; ?>
            </div>
        </div>
        <div class="rank-pts"><?php echo $c['puntos_equipo']; ?></div>
    </div>
    <?php $posicion++; endforeach; ?>
</div>

<div class="modal-sim" id="modalSimulacion">
    <div class="sim-box">
        <div class="sim-title">⚡ REPORTE DE LA JORNADA</div>
        <div style="font-size:0.8rem; color:var(--muted); text-transform:uppercase; margin-bottom:5px; text-align:center;">Resultados de los partidos</div>
        <div class="sim-matchup" id="simMatchup"></div>
        <div style="font-size:0.8rem; color:var(--muted); text-transform:uppercase; margin-top:20px; margin-bottom:10px; text-align:center;">Top Jugadores de la Jornada</div>
        <div id="simPlayerList"></div>
        <button class="btn-accion btn-mover" style="margin-top: 20px; width:100%; border: 1px solid #c084fc; color:#c084fc;" onclick="location.reload()">CERRAR Y ACTUALIZAR TABLA</button>
    </div>
</div>

<script>
    // --- SISTEMA DE STATS (TOOLTIP MODAL) ---
    function openPlayerStats(nick, rol, equipo, color, kills, deaths, assists, aces, clutches) {
        document.getElementById('spName').textContent = nick;
        document.getElementById('spRol').textContent = rol;
        document.getElementById('spRol').style.color = color;
        document.getElementById('spTeam').textContent = equipo;
        
        document.getElementById('spKills').textContent = kills;
        document.getElementById('spDeaths').textContent = deaths;
        document.getElementById('spAssists').textContent = assists;
        document.getElementById('spAces').textContent = aces;
        document.getElementById('spClutches').textContent = clutches;
        
        document.getElementById('playerStatsModal').classList.add('active');
    }

    function closePlayerStats(e) {
        if(e) e.stopPropagation();
        document.getElementById('playerStatsModal').classList.remove('active');
    }

    // --- ACCIONES GENERALES ---
    async function copiarCodigo(codigo) {
        try {
            await navigator.clipboard.writeText(codigo);
            const msg = document.getElementById("copiadoMsg");
            msg.style.display = "block";
            setTimeout(() => { msg.style.display = "none"; }, 2500);
        } catch (_) { alert("No se pudo copiar el código."); }
    }

    async function cambiarEstado(id, est, num) {
        if (est === 1 && num >= 5) { alert('Equipo titular lleno (5/5).'); return; }
        try {
            const res = await fetch('api_plantilla.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id_equipo: <?php echo $id_equipo_fantasy; ?>, id_jugador: id, titular: est }) });
            const json = await res.json(); if (json.status === 'success') location.reload(); else alert("Error: " + json.message);
        } catch (_) { alert("Error de conexión."); }
    }
    
    async function venderJugador(id, nom) {
        if (!confirm(`¿Vender a ${nom}? Recuperarás 80% del valor.`)) return;
        try {
            const res = await fetch('api_venta.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id_equipo: <?php echo $id_equipo_fantasy; ?>, id_jugador: id }) });
            const json = await res.json(); if (json.status === 'success') location.reload(); else alert("Error: " + json.message);
        } catch (_) { alert("Error de conexión."); }
    }

    async function simularPartido() {
        const btn = document.getElementById('btnSim'); 
        const torneoSeleccionado = "<?php echo addslashes($liga_info['torneo'] ?? 'VCT EMEA - Fase Regular'); ?>";
        const jornadaSeleccionada = document.getElementById('jornadaSelect').value;
        
        btn.innerHTML = "⏳ SIMULANDO..."; btn.disabled = true;
        try {
            const res = await fetch('api_simulacion.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ torneo: torneoSeleccionado, jornada: jornadaSeleccionada }) }); 
            const json = await res.json();
            
            if (json.status === 'success') {
                // Restaurada la visión de Partidos
                document.getElementById('simMatchup').innerHTML = json.partidos.map(p => `<div style="font-size:0.95rem; margin-bottom:5px; padding:8px; background:rgba(255,255,255,0.05); border-left:2px solid #1DF2DD;">${p.local} <span style="color:#A63247; font-weight:bold; padding:0 5px;">VS</span> ${p.visitante} <span style="color:#c084fc; float:right; font-family:'Orbitron';">Ganador: ${p.ganador}</span></div>`).join('');
                
                // Restaurada la visión de Jugadores en el reporte
                const topPlayers = json.stats.slice(0, 10);
                document.getElementById('simPlayerList').innerHTML = topPlayers.map(j => `<div class="sim-player"><div><div class="sim-player-nick">${j.nickname} <span style="font-size:0.7rem;color:gray;font-family:'Barlow Condensed'">${j.equipo}</span></div><div style="font-size:0.7rem;color:gray">K/D/A: ${j.kda}</div></div><div class="sim-player-pts">${j.puntos} pts</div></div>`).join('');
                
                document.getElementById('modalSimulacion').classList.add('visible');
            } else alert("Error: " + json.message);
        } catch (e) { alert("Error al simular."); } finally { btn.innerHTML = "▶ SIMULAR"; btn.disabled = false; }
    }
</script>
<?php endif; ?>
</body>
</html>