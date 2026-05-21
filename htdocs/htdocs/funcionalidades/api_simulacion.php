<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../conexion.php';

if (!isset($_SESSION['usuario'])) { echo json_encode(["status" => "error", "message" => "Sesión caducada."]); exit(); }

$data = json_decode(file_get_contents("php://input"));
$torneo = (isset($data->torneo) && !empty($data->torneo)) ? $data->torneo : 'VCT EMEA';
// Recogemos la jornada seleccionada en el desplegable (por defecto 1)
$jornada = (isset($data->jornada) && !empty($data->jornada)) ? (int)$data->jornada : 1;

try {
    $conexion->beginTransaction();
    $stmtEquipos = $conexion->query("SELECT id_equipo_profesional, nombre_equipo_profesional FROM equipos_profesionales WHERE region = 'EMEA'");
    $equipos = $stmtEquipos->fetchAll(PDO::FETCH_ASSOC);
    if (count($equipos) < 2) throw new Exception("No hay suficientes equipos profesionales europeos.");
    
    shuffle($equipos); 
    $partidos_simulados = []; $reporte_jugadores = []; $total_equipos = count($equipos);

    for ($i = 0; $i < $total_equipos - 1; $i += 2) {
        $equipo_local = $equipos[$i]; $equipo_visitante = $equipos[$i+1];
        $ganador = (rand(0, 1) == 0) ? $equipo_local : $equipo_visitante;

        $stmtPartido = $conexion->prepare("INSERT INTO partidos (id_equipo_local, id_equipo_visitante, fecha, torneo, ganador) VALUES (:local, :visitante, NOW(), :torneo, :ganador)");
        $stmtPartido->execute([':local' => $equipo_local['id_equipo_profesional'], ':visitante' => $equipo_visitante['id_equipo_profesional'], ':torneo' => $torneo, ':ganador' => $ganador['id_equipo_profesional']]);
        $id_partido = $conexion->lastInsertId();
        $partidos_simulados[] = ["local" => $equipo_local['nombre_equipo_profesional'], "visitante" => $equipo_visitante['nombre_equipo_profesional'], "ganador" => $ganador['nombre_equipo_profesional']];

        $stmtJugadores = $conexion->prepare("SELECT id_jugador, nickname, id_equipo_profesional FROM jugadores WHERE id_equipo_profesional IN (:eq1, :eq2)");
        $stmtJugadores->execute([':eq1' => $equipo_local['id_equipo_profesional'], ':eq2' => $equipo_visitante['id_equipo_profesional']]);
        $jugadores = $stmtJugadores->fetchAll(PDO::FETCH_ASSOC);

        foreach ($jugadores as $jugador) {
            $kills = rand(5, 28); $deaths = rand(8, 25); $assists = rand(2, 15);
            $ace = (rand(1, 100) > 90) ? 1 : 0; $clutch = rand(0, 2);
            $puntos = ($kills * 2) + ($assists * 2) + ($ace * 5) + ($clutch * 4);
            if ($jugador['id_equipo_profesional'] == $ganador['id_equipo_profesional']) $puntos += 5;
            if ($deaths > 20) $puntos -= 7;

            $stmtStats = $conexion->prepare("INSERT INTO estadisticas (id_jugador, id_partido, kills, deaths, assist, ace, clutch, punto_fantasy) VALUES (:id_j, :id_p, :k, :d, :a, :ace, :clutch, :pts)");
            $stmtStats->execute([':id_j' => $jugador['id_jugador'], ':id_p' => $id_partido, ':k' => $kills, ':d' => $deaths, ':a' => $assists, ':ace' => $ace, ':clutch' => $clutch, ':pts' => $puntos]);

            // AQUÍ ESTÁ EL CAMBIO: Los puntos NO se suman (+), se SOBREESCRIBEN (=) para que reflejen solo los de esta jornada.
            $conexion->prepare("UPDATE alineaciones SET puntos_jornada = :pts, jornada = :jornada WHERE id_jugador = :id_j AND titular = 1")->execute([':pts' => $puntos, ':jornada' => $jornada, ':id_j' => $jugador['id_jugador']]);
            
            // Los puntos totales del equipo SÍ se siguen acumulando.
            $conexion->prepare("UPDATE equipos_fantasy EF INNER JOIN alineaciones A ON EF.id_equipo_fantasy = A.id_equipo_fantasy SET EF.puntos_equipo = EF.puntos_equipo + :pts WHERE A.id_jugador = :id_j AND A.titular = 1")->execute([':pts' => $puntos, ':id_j' => $jugador['id_jugador']]);

            $reporte_jugadores[] = ["nickname" => $jugador['nickname'], "equipo" => ($jugador['id_equipo_profesional'] == $equipo_local['id_equipo_profesional']) ? $equipo_local['nombre_equipo_profesional'] : $equipo_visitante['nombre_equipo_profesional'], "kda" => "$kills/$deaths/$assists", "puntos" => $puntos];
        }
    }
    $conexion->commit();
    usort($reporte_jugadores, function($a, $b) { return $b['puntos'] <=> $a['puntos']; });
    echo json_encode(["status" => "success", "partidos" => $partidos_simulados, "stats" => $reporte_jugadores]);
} catch (Exception $e) { if (isset($conexion) && $conexion->inTransaction()) $conexion->rollBack(); echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
?>