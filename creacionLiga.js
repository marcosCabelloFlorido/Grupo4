// 1. Esperamos a que todo el HTML cargue en pantalla
document.addEventListener("DOMContentLoaded", () => {
    
    // 2. Capturamos el formulario y la caja de mensajes
    const formulario = document.getElementById("formCrearLiga");
    const divMensaje = document.getElementById("mensaje");

    // 3. Le decimos al formulario qué hacer cuando el usuario haga clic en "Crear Liga"
    formulario.addEventListener("submit", async (evento) => {
        
        // ¡IMPORTANTE! Evitamos que la página se recargue (comportamiento por defecto de HTML)
        evento.preventDefault();

        // 4. Recogemos los valores que el usuario escribió en los inputs
        const idUsuario = document.getElementById("idUsuarioLogueado").value;
        const nombreLiga = document.getElementById("nombreLiga").value;
        const tipoLiga = document.getElementById("tipoLiga").value;
        const maxParticipantes = document.getElementById("maxParticipantes").value;

        // 5. Empaquetamos los datos en un objeto (como preparar la carta)
        const datosPaquete = {
            id_usuario: idUsuario,
            nombre: nombreLiga,
            tipo: tipoLiga,
            max_participantes: maxParticipantes
        };

        try {
            // 6. Enviamos la petición a PHP usando Fetch (el cartero)
            // Asegúrate de que la URL apunta correctamente a tu archivo PHP
            const respuesta = await fetch("api_ligas.php", {
                method: "POST", // Mandamos datos nuevos
                headers: {
                    "Content-Type": "application/json" // Avisamos que el formato es JSON
                },
                body: JSON.stringify(datosPaquete) // Convertimos el objeto a texto JSON
            });

            // 7. Abrimos la respuesta que nos devuelve PHP
            const resultado = await respuesta.json();

            // 8. Mostramos un mensaje al usuario dependiendo de lo que dijo PHP
            divMensaje.style.display = "block"; // Hacemos visible la caja

            if (respuesta.status === 201 || resultado.status === "success") {
                // Todo salió bien
                divMensaje.className = "exito";
                divMensaje.textContent = `¡Felicidades! ${resultado.message} (ID: ${resultado.id_liga})`;
                
                // Opcional: Limpiar el formulario
                formulario.reset();
            } else {
                // PHP devolvió un error (ej. faltaron datos)
                divMensaje.className = "error";
                divMensaje.textContent = `Error: ${resultado.message}`;
            }

        } catch (error) {
            // Error de red (ej. el servidor PHP está apagado)
            console.error("Error en la conexión:", error);
            divMensaje.style.display = "block";
            divMensaje.className = "error";
            divMensaje.textContent = "Error crítico: No se pudo conectar con el servidor.";
        }
    });
});