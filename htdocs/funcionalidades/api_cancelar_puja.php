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
        $id_mercado = (int)$data->id_mercado;

        $conexion->beginTransaction();

        // 1. Verificar que la puja existe y que el usuario es el dueño legítimo
        $stmtPuja = $conexion->prepare("SELECT P.id_puja, P.monto 
                                        FROM pujas P 
                                        INNER JOIN equipos_fantasy EF ON P.id_equipo_fantasy = EF.id_equipo_fantasy 
                                        INNER JOIN usuarios U ON EF.id_usuario = U.id_usuario 
                                        WHERE P.id_mercado = :id_m AND EF.id_equipo_fantasy = :id_ef AND U.nombre = :nombre");
        $stmtPuja->execute([
            ':id_m' => $id_mercado, 
            ':id_ef' => $id_equipo, 
            ':nombre' => $_SESSION['usuario']
        ]);
        
        $puja = $stmtPuja->fetch(PDO::FETCH_ASSOC);

        if (!$puja) {
            throw new Exception("No se encontró la puja o no tienes permisos.");
        }

        // 2. Devolver el dinero al presupuesto del equipo
        $stmtDevolver = $conexion->prepare("UPDATE equipos_fantasy SET presupuesto_disponible = presupuesto_disponible + :monto WHERE id_equipo_fantasy = :id_ef");
        $stmtDevolver->execute([
            ':monto' => $puja['monto'], 
            ':id_ef' => $id_equipo
        ]);

        // 3. Eliminar la puja del registro
        $stmtDelete = $conexion->prepare("DELETE FROM pujas WHERE id_puja = :id_p");
        $stmtDelete->execute([':id_p' => $puja['id_puja']]);

        $conexion->commit();
        echo json_encode(["status" => "success"]);

    } catch (Exception $e) {
        if (isset($conexion) && $conexion->inTransaction()) $conexion->rollBack();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>