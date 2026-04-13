<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($_SESSION['usuario'])) {
        echo json_encode(["status" => "error", "message" => "Sesión caducada."]);
        exit();
    }

    try {
        $id_equipo = (int)$data->id_equipo;
        $id_jugador = (int)$data->id_jugador;

        $conexion->beginTransaction();

        // 1. Validar que el usuario es dueño del equipo y que el jugador está en su plantilla
        $stmtValidar = $conexion->prepare("SELECT J.precio_mercado 
                                           FROM alineaciones A 
                                           INNER JOIN jugadores J ON A.id_jugador = J.id_jugador 
                                           INNER JOIN equipos_fantasy EF ON A.id_equipo_fantasy = EF.id_equipo_fantasy 
                                           INNER JOIN usuarios U ON EF.id_usuario = U.id_usuario 
                                           WHERE U.nombre = :nombre AND EF.id_equipo_fantasy = :id_ef AND A.id_jugador = :id_j");
        $stmtValidar->execute([':nombre' => $_SESSION['usuario'], ':id_ef' => $id_equipo, ':id_j' => $id_jugador]);
        $jugador = $stmtValidar->fetch(PDO::FETCH_ASSOC);

        if (!$jugador) throw new Exception("No posees a este jugador o no tienes permisos.");

        // 2. Calcular precio de venta (80% del valor de mercado)
        $precio_venta = $jugador['precio_mercado'] * 0.80;

        // 3. Sumar el dinero al equipo
        $stmtIngreso = $conexion->prepare("UPDATE equipos_fantasy SET presupuesto_disponible = presupuesto_disponible + :ingreso WHERE id_equipo_fantasy = :id_ef");
        $stmtIngreso->execute([':ingreso' => $precio_venta, ':id_ef' => $id_equipo]);

        // 4. Despedir al jugador (Borrar de alineaciones)
        $stmtDelete = $conexion->prepare("DELETE FROM alineaciones WHERE id_equipo_fantasy = :id_ef AND id_jugador = :id_j");
        $stmtDelete->execute([':id_ef' => $id_equipo, ':id_j' => $id_jugador]);

        $conexion->commit();
        echo json_encode(["status" => "success"]);

    } catch (Exception $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>