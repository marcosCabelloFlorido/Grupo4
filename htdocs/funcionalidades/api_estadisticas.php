<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Verificar sesión
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['exito' => false, 'mensaje' => 'Sesión no válida']);
    exit();
}

require_once '../conexion.php';

try {
    // Verificar si el usuario es premium
    $stmt = $conexion->prepare("SELECT es_premium FROM usuarios WHERE nombre = ?");
    $stmt->execute([$_SESSION['usuario']]);
    $usuario = $stmt->fetch();
    
    if (!$usuario || !$usuario['es_premium']) {
        echo json_encode(['exito' => false, 'mensaje' => 'Acceso denegado. Requiere suscripción premium.']);
        exit();
    }

    // Obtener estadísticas de todos los jugadores
    $query = "
        SELECT 
            j.id_jugador,
            j.nickname,
            j.nombre_real,
            j.rol,
            j.precio_mercado,
            j.media_punto,
            ep.nombre_equipo_profesional AS equipo,
            COALESCE(SUM(e.kills), 0) AS kills,
            COALESCE(SUM(e.deaths), 0) AS deaths,
            COALESCE(SUM(e.assist), 0) AS assists,
            COALESCE(SUM(e.ace), 0) AS aces,
            COALESCE(SUM(e.clutch), 0) AS clutches,
            COALESCE(SUM(e.punto_fantasy), 0) AS puntos_totales
        FROM jugadores j
        LEFT JOIN equipos_profesionales ep ON j.id_equipo_profesional = ep.id_equipo_profesional
        LEFT JOIN estadisticas e ON j.id_jugador = e.id_jugador
        GROUP BY j.id_jugador, j.nickname, j.nombre_real, j.rol, j.precio_mercado, j.media_punto, ep.nombre_equipo_profesional
        ORDER BY j.media_punto DESC
    ";

    $stmt = $conexion->prepare($query);
    $stmt->execute();
    $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear datos
    $jugadoresFormateados = array_map(function($jugador) {
        return [
            'id_jugador' => $jugador['id_jugador'],
            'nickname' => $jugador['nickname'],
            'nombre_real' => $jugador['nombre_real'],
            'rol' => $jugador['rol'],
            'equipo' => $jugador['equipo'] ?? 'Sin equipo',
            'precio_mercado' => floatval($jugador['precio_mercado'] ?? 0),
            'media_punto' => floatval($jugador['media_punto'] ?? 0),
            'kills' => intval($jugador['kills']),
            'deaths' => intval($jugador['deaths']),
            'assists' => intval($jugador['assists']),
            'aces' => intval($jugador['aces']),
            'clutches' => intval($jugador['clutches']),
            'puntos_totales' => floatval($jugador['puntos_totales'])
        ];
    }, $jugadores);

    echo json_encode([
        'exito' => true,
        'jugadores' => $jugadoresFormateados,
        'total' => count($jugadoresFormateados)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        'exito' => false,
        'mensaje' => 'Error al obtener estadísticas: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
