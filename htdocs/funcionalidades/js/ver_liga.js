/* ═══════════════════════════════════════════════════════════
   ver_liga.js — JavaScript de la Vista de Liga
   Requiere: ID_EQUIPO_FANTASY y TORNEO_LIGA definidos inline en el PHP
   ═══════════════════════════════════════════════════════════ */

/* ── Modal de estadísticas del jugador ──────────────────── */
function openPlayerStats(nick, rol, equipo, color, kills, deaths, assists, aces, clutches) {
    document.getElementById('spName').textContent  = nick;
    document.getElementById('spRol').textContent   = rol;
    document.getElementById('spRol').style.color   = color;
    document.getElementById('spTeam').textContent  = equipo;
    document.getElementById('spKills').textContent   = kills;
    document.getElementById('spDeaths').textContent  = deaths;
    document.getElementById('spAssists').textContent = assists;
    document.getElementById('spAces').textContent    = aces;
    document.getElementById('spClutches').textContent = clutches;
    document.getElementById('playerStatsModal').classList.add('active');
}

function closePlayerStats(e) {
    if (e) e.stopPropagation();
    document.getElementById('playerStatsModal').classList.remove('active');
}

/* ── Copiar código de acceso al portapapeles ────────────── */
async function copiarCodigo(codigo) {
    try {
        await navigator.clipboard.writeText(codigo);
        const msg = document.getElementById('copiadoMsg');
        msg.style.display = 'block';
        setTimeout(() => { msg.style.display = 'none'; }, 2500);
    } catch (_) {
        alert('No se pudo copiar el código.');
    }
}

/* ── Cambiar titular / reserva ──────────────────────────── */
async function cambiarEstado(id, est, num) {
    if (est === 1 && num >= 5) { alert('Equipo titular lleno (5/5).'); return; }
    try {
        const res  = await fetch('api_plantilla.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ id_equipo: ID_EQUIPO_FANTASY, id_jugador: id, titular: est })
        });
        const json = await res.json();
        if (json.status === 'success') location.reload();
        else alert('Error: ' + json.message);
    } catch (_) { alert('Error de conexión.'); }
}

/* ── Vender jugador ─────────────────────────────────────── */
async function venderJugador(id, nom) {
    if (!confirm(`¿Vender a ${nom}? Recuperarás 80% del valor.`)) return;
    try {
        const res  = await fetch('api_venta.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ id_equipo: ID_EQUIPO_FANTASY, id_jugador: id })
        });
        const json = await res.json();
        if (json.status === 'success') location.reload();
        else alert('Error: ' + json.message);
    } catch (_) { alert('Error de conexión.'); }
}

/* ── Simular partido ────────────────────────────────────── */
async function simularPartido() {
    const btn              = document.getElementById('btnSim');
    const jornadaSeleccionada = document.getElementById('jornadaSelect').value;

    btn.innerHTML  = '⏳ SIMULANDO...';
    btn.disabled   = true;

    try {
        const res  = await fetch('api_simulacion.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ torneo: TORNEO_LIGA, jornada: jornadaSeleccionada })
        });
        const json = await res.json();

        if (json.status === 'success') {
            document.getElementById('simMatchup').innerHTML = json.partidos.map(p =>
                `<div style="font-size:0.95rem;margin-bottom:5px;padding:8px;background:rgba(255,255,255,0.05);border-left:2px solid #1DF2DD;">
                    ${p.local} <span style="color:#A63247;font-weight:bold;padding:0 5px;">VS</span> ${p.visitante}
                    <span style="color:#c084fc;float:right;font-family:'Orbitron';">Ganador: ${p.ganador}</span>
                </div>`
            ).join('');

            const topPlayers = json.stats.slice(0, 10);
            document.getElementById('simPlayerList').innerHTML = topPlayers.map(j =>
                `<div class="sim-player">
                    <div>
                        <div class="sim-player-nick">${j.nickname} <span style="font-size:0.7rem;color:gray;font-family:'Barlow Condensed'">${j.equipo}</span></div>
                        <div style="font-size:0.7rem;color:gray">K/D/A: ${j.kda}</div>
                    </div>
                    <div class="sim-player-pts">${j.puntos} pts</div>
                </div>`
            ).join('');

            document.getElementById('modalSimulacion').classList.add('visible');
        } else {
            alert('Error: ' + json.message);
        }
    } catch (e) {
        alert('Error al simular.');
    } finally {
        btn.innerHTML = '▶ SIMULAR';
        btn.disabled  = false;
    }
}
