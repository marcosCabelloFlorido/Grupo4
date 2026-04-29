// Este archivo es la versión original del creador de liga.
// En el flujo actual la lógica está inline en sesion/crear_liga.php.
// Se conserva aquí como referencia o para uso futuro con crearLiga.html.

document.addEventListener("DOMContentLoaded", () => {

    const formulario = document.getElementById("formCrearLiga");
    const divMensaje = document.getElementById("mensaje");

    formulario.addEventListener("submit", async (evento) => {
        evento.preventDefault();

        const idUsuario       = document.getElementById("idUsuarioLogueado").value;
        const nombreLiga      = document.getElementById("nombreLiga").value;
        const tipoLiga        = document.getElementById("tipoLiga").value;
        const maxParticipantes = document.getElementById("maxParticipantes").value;

        const datosPaquete = {
            id_usuario:        idUsuario,
            nombre:            nombreLiga,
            tipo:              tipoLiga,
            max_participantes: maxParticipantes
        };

        try {
            // Apunta a api_ligas.php dentro de sesion/
            const respuesta = await fetch("../funcionalidades/api_ligas.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datosPaquete)
            });

            const resultado = await respuesta.json();
            divMensaje.style.display = "block";

            if (respuesta.status === 201 || resultado.status === "success") {
                divMensaje.className   = "exito";
                divMensaje.textContent = `¡Felicidades! ${resultado.message}`;
                formulario.reset();
            } else {
                divMensaje.className   = "error";
                divMensaje.textContent = `Error: ${resultado.message}`;
            }

        } catch (error) {
            console.error("Error en la conexión:", error);
            divMensaje.style.display   = "block";
            divMensaje.className       = "error";
            divMensaje.textContent     = "Error crítico: No se pudo conectar con el servidor.";
        }
    });
});
