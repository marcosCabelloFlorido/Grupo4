/* ═══════════════════════════════════════════════════════════
   estadisticas.js — Funcionalidad de página de Estadísticas Premium
   ═══════════════════════════════════════════════════════════ */

let datosJugadores = [];
let datosFiltrados = [];

// ── Inicialización ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    cargarEstadisticas();
    configurarEventos();
    configurarBotonVolverArriba();
});

// ── Configurar eventos ────────────────────────────────────────
function configurarEventos() {
    const btnRefresh = document.getElementById('btnRefresh');
    const btnResetFiltros = document.getElementById('btnResetFiltros');
    const filtroEquipo = document.getElementById('filtroEquipo');
    const filtroRol = document.getElementById('filtroRol');
    const ordenarPor = document.getElementById('ordenarPor');
    const ordenDireccion = document.getElementById('ordenDireccion');

    if (btnRefresh) {
        btnRefresh.addEventListener('click', () => {
            cargarEstadisticas();
        });
    }

    if (btnResetFiltros) {
        btnResetFiltros.addEventListener('click', () => {
            filtroEquipo.value = '';
            filtroRol.value = '';
            ordenarPor.value = 'media_punto';
            ordenDireccion.value = 'desc';
            aplicarFiltros();
        });
    }

    // Eventos de filtros
    [filtroEquipo, filtroRol, ordenarPor, ordenDireccion].forEach(elem => {
        if (elem) {
            elem.addEventListener('change', aplicarFiltros);
        }
    });

    // Eventos de ordenamiento en headers de tabla
    document.querySelectorAll('.stats-table th.sortable').forEach(th => {
        th.addEventListener('click', () => {
            const sortBy = th.dataset.sort;
            const ordenarPorSelect = document.getElementById('ordenarPor');
            const ordenDireccionSelect = document.getElementById('ordenDireccion');
            
            if (ordenarPorSelect.value === sortBy) {
                // Cambiar dirección
                ordenDireccionSelect.value = ordenDireccionSelect.value === 'asc' ? 'desc' : 'asc';
            } else {
                // Nuevo campo de ordenamiento
                ordenarPorSelect.value = sortBy;
                ordenDireccionSelect.value = 'desc';
            }
            
            aplicarFiltros();
        });
    });
}

// ── Cargar estadísticas desde el servidor ─────────────────────
async function cargarEstadisticas() {
    const btnRefresh = document.getElementById('btnRefresh');
    const refreshIcon = document.getElementById('refreshIcon');
    
    if (btnRefresh) btnRefresh.disabled = true;
    if (refreshIcon) refreshIcon.classList.add('spin');

    try {
        const response = await fetch('api_estadisticas.php');
        const data = await response.json();

        if (data.exito) {
            datosJugadores = data.jugadores || [];
            datosFiltrados = [...datosJugadores];
            
            // Poblar filtro de equipos
            poblarFiltroEquipos();
            
            // Aplicar filtros y renderizar
            aplicarFiltros();
            
            mostrarNotificacion('Estadísticas actualizadas', 'exito');
        } else {
            mostrarError(data.mensaje || 'Error al cargar estadísticas');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error de conexión al cargar estadísticas');
    } finally {
        if (btnRefresh) btnRefresh.disabled = false;
        if (refreshIcon) refreshIcon.classList.remove('spin');
    }
}

// ── Poblar filtro de equipos ──────────────────────────────────
function poblarFiltroEquipos() {
    const filtroEquipo = document.getElementById('filtroEquipo');
    if (!filtroEquipo) return;

    // Obtener equipos únicos
    const equipos = [...new Set(datosJugadores.map(j => j.equipo).filter(e => e))].sort();
    
    // Limpiar opciones existentes (excepto "Todos")
    filtroEquipo.innerHTML = '<option value="">Todos los equipos</option>';
    
    // Agregar opciones de equipos
    equipos.forEach(equipo => {
        const option = document.createElement('option');
        option.value = equipo;
        option.textContent = equipo;
        filtroEquipo.appendChild(option);
    });
}

// ── Aplicar filtros y ordenamiento ────────────────────────────
function aplicarFiltros() {
    const filtroEquipo = document.getElementById('filtroEquipo').value;
    const filtroRol = document.getElementById('filtroRol').value;
    const ordenarPor = document.getElementById('ordenarPor').value;
    const ordenDireccion = document.getElementById('ordenDireccion').value;

    // Filtrar datos
    datosFiltrados = datosJugadores.filter(jugador => {
        const cumpleEquipo = !filtroEquipo || jugador.equipo === filtroEquipo;
        const cumpleRol = !filtroRol || jugador.rol === filtroRol;
        return cumpleEquipo && cumpleRol;
    });

    // Ordenar datos
    datosFiltrados.sort((a, b) => {
        let valorA = a[ordenarPor];
        let valorB = b[ordenarPor];

        // Convertir a números si es necesario
        if (typeof valorA === 'string' && !isNaN(valorA)) valorA = parseFloat(valorA);
        if (typeof valorB === 'string' && !isNaN(valorB)) valorB = parseFloat(valorB);

        // Manejar valores null/undefined
        if (valorA == null) valorA = 0;
        if (valorB == null) valorB = 0;

        // Comparar
        if (typeof valorA === 'string') {
            return ordenDireccion === 'asc' 
                ? valorA.localeCompare(valorB)
                : valorB.localeCompare(valorA);
        } else {
            return ordenDireccion === 'asc' 
                ? valorA - valorB
                : valorB - valorA;
        }
    });

    // Actualizar indicadores visuales de ordenamiento
    actualizarIndicadoresOrdenamiento(ordenarPor, ordenDireccion);

    // Renderizar tabla
    renderizarTabla();
    
    // Actualizar resumen
    actualizarResumen();
}

// ── Actualizar indicadores de ordenamiento ────────────────────
function actualizarIndicadoresOrdenamiento(campo, direccion) {
    document.querySelectorAll('.stats-table th.sortable').forEach(th => {
        th.classList.remove('sorted-asc', 'sorted-desc');
        if (th.dataset.sort === campo) {
            th.classList.add(direccion === 'asc' ? 'sorted-asc' : 'sorted-desc');
        }
    });
}

// ── Renderizar tabla de estadísticas ──────────────────────────
function renderizarTabla() {
    const tbody = document.getElementById('statsTableBody');
    if (!tbody) return;

    if (datosFiltrados.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="empty-state">
                    <div class="empty-state-icon">📊</div>
                    <p>No se encontraron jugadores con los filtros aplicados</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = datosFiltrados.map(jugador => `
        <tr>
            <td><strong>${escapeHtml(jugador.nickname)}</strong></td>
            <td>${escapeHtml(jugador.equipo || '-')}</td>
            <td><span class="rol-badge rol-${(jugador.rol || '').toLowerCase().replace('é','e').replace('á','a')}">${escapeHtml(jugador.rol || '-')}</span></td>
            <td>${formatearNumero(jugador.kills)}</td>
            <td>${formatearNumero(jugador.deaths)}</td>
            <td>${formatearNumero(jugador.assists)}</td>
            <td>${formatearNumero(jugador.aces)}</td>
            <td>${formatearNumero(jugador.clutches)}</td>
            <td><strong>${formatearDecimal(jugador.media_punto)}</strong></td>
            <td>${formatearPrecio(jugador.precio_mercado)}</td>
        </tr>
    `).join('');
}

// ── Actualizar resumen de estadísticas ────────────────────────
function actualizarResumen() {
    const totalJugadores = datosFiltrados.length;
    const totalKills = datosFiltrados.reduce((sum, j) => sum + (parseInt(j.kills) || 0), 0);
    const totalAces = datosFiltrados.reduce((sum, j) => sum + (parseInt(j.aces) || 0), 0);
    const totalClutches = datosFiltrados.reduce((sum, j) => sum + (parseInt(j.clutches) || 0), 0);

    document.getElementById('totalJugadores').textContent = formatearNumero(totalJugadores);
    document.getElementById('totalKills').textContent = formatearNumero(totalKills);
    document.getElementById('totalAces').textContent = formatearNumero(totalAces);
    document.getElementById('totalClutches').textContent = formatearNumero(totalClutches);
}

// ── Funciones de formateo ─────────────────────────────────────
function formatearNumero(num) {
    const n = parseInt(num);
    return isNaN(n) ? '0' : n.toLocaleString('es-ES');
}

function formatearDecimal(num) {
    const n = parseFloat(num);
    return isNaN(n) ? '0.00' : n.toFixed(2);
}

function formatearPrecio(precio) {
    const p = parseFloat(precio);
    if (isNaN(p)) return '-';
    
    if (p >= 1000000) {
        return (p / 1000000).toFixed(2) + 'M €';
    } else if (p >= 1000) {
        return (p / 1000).toFixed(0) + 'K €';
    } else {
        return p.toFixed(0) + ' €';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ── Mostrar notificación ──────────────────────────────────────
function mostrarNotificacion(mensaje, tipo = 'exito') {
    const notif = document.getElementById('notificacion');
    if (!notif) return;

    notif.textContent = mensaje;
    notif.className = tipo;
    notif.style.display = 'block';

    setTimeout(() => {
        notif.style.display = 'none';
    }, 3000);
}

function mostrarError(mensaje) {
    mostrarNotificacion(mensaje, 'error');
}


// ── Configurar botón volver arriba ────────────────────────────
function configurarBotonVolverArriba() {
    const btnVolverArriba = document.getElementById('btnVolverArriba');
    if (!btnVolverArriba) return;

    // Mostrar/ocultar botón según scroll
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            btnVolverArriba.classList.add('visible');
        } else {
            btnVolverArriba.classList.remove('visible');
        }
    });

    // Hacer scroll suave al hacer clic
    btnVolverArriba.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}
