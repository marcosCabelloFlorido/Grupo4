/* ═══════════════════════════════════════════════════════════
   mercado.js — JavaScript del Mercado de Fichajes
   Requiere: ID_EQUIPO y FECHA_FIN definidos inline en el PHP
   ═══════════════════════════════════════════════════════════ */

/* ── Cancelar puja ──────────────────────────────────────── */
async function cancelarPuja(idMercado) {
    if (!confirm('¿Retirar tu oferta? Recuperarás el dinero congelado al instante.')) return;
    try {
        const res  = await fetch('api_cancelar_puja.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ id_equipo: ID_EQUIPO, id_mercado: idMercado })
        });
        const json = await res.json();
        if (json.status === 'success') {
            window.location.href = window.location.href; // recarga limpiando POST
        } else {
            alert('Error: ' + json.message);
        }
    } catch (e) {
        alert('Error de conexión al servidor.');
    }
}

/* ── Contador de renovación del mercado ─────────────────── */
(function initCountdown() {
    if (!FECHA_FIN) return;

    const fechaFin = new Date(FECHA_FIN).getTime();
    const el       = document.getElementById('countdown');
    if (!el) return;

    const x = setInterval(function () {
        const ahora    = new Date().getTime();
        const distancia = fechaFin - ahora;

        if (distancia < 0) {
            clearInterval(x);
            el.innerHTML = 'RESOLVIENDO...';
            setTimeout(() => location.reload(), 2500);
            return;
        }

        const horas    = Math.floor(distancia / (1000 * 60 * 60));
        const minutos  = Math.floor((distancia % (1000 * 60 * 60)) / (1000 * 60));
        const segundos = Math.floor((distancia % (1000 * 60)) / 1000);

        el.innerHTML =
            String(horas).padStart(2, '0')    + ':' +
            String(minutos).padStart(2, '0')  + ':' +
            String(segundos).padStart(2, '0');
    }, 1000);
})();
