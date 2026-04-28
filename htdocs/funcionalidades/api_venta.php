<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    if (!isset($_SESSION['usuario'])) { echo json_encode(["status" => "error", "message" => "Sesión caducada."]); exit(); }
    try {
        $id_equipo = (int)$data->id_equipo; $id_jugador = (int)$data->id_jugador;
        $conexion->beginTransaction();
        $stmtValidar = $conexion->prepare("SELECT J.precio_mercado FROM alineaciones A INNER JOIN jugadores J ON A.id_jugador = J.id_jugador INNER JOIN equipos_fantasy EF ON A.id_equipo_fantasy = EF.id_equipo_fantasy INNER JOIN usuarios U ON EF.id_usuario = U.id_usuario WHERE U.nombre = :nombre AND EF.id_equipo_fantasy = :id_ef AND A.id_jugador = :id_j");
        $stmtValidar->execute([':nombre' => $_SESSION['usuario'], ':id_ef' => $id_equipo, ':id_j' => $id_jugador]);
        $jugador = $stmtValidar->fetch(PDO::FETCH_ASSOC);
        if (!$jugador) throw new Exception("No posees a este jugador.");
        
        $stmtConteo = $conexion->prepare("SELECT COUNT(*) FROM alineaciones WHERE id_equipo_fantasy = :id_ef");
        $stmtConteo->execute([':id_ef' => $id_equipo]);
        if ((int)$stmtConteo->fetchColumn() <= 1) throw new Exception("No puedes vender a tu último jugador.");
        
        $precio_venta = $jugador['precio_mercado'] * 0.80;
        $conexion->prepare("UPDATE equipos_fantasy SET presupuesto_disponible = presupuesto_disponible + :ingreso WHERE id_equipo_fantasy = :id_ef")->execute([':ingreso' => $precio_venta, ':id_ef' => $id_equipo]);
        $conexion->prepare("DELETE FROM alineaciones WHERE id_equipo_fantasy = :id_ef AND id_jugador = :id_j")->execute([':id_ef' => $id_equipo, ':id_j' => $id_jugador]);
        $conexion->commit();
        echo json_encode(["status" => "success"]);
    } catch (Exception $e) { if ($conexion->inTransaction()) $conexion->rollBack(); echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
}
?>