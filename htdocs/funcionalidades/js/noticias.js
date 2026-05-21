/* ═══════════════════════════════════════════════════════════
   noticias.js — JavaScript de la página de Noticias
   Requiere: USUARIO definido inline en el PHP
   ═══════════════════════════════════════════════════════════ */

const CATS   = { vct: 'VCT', patch: 'PARCHE', esports: 'ESPORTS', general: 'GENERAL' };
const TCLASS = { vct: 'tag-vct', patch: 'tag-patch', esports: 'tag-esports', general: 'tag-general' };
const EMOJIS = { vct: '🎯', patch: '⚙️', esports: '🏆', general: '📡' };

// ── Imágenes por categoría ───────────────────────────────
const CAT_IMGS = {
    vct:     '../img/01b05233883ae5d4fd3b62bee00b4a6654d83f48-1920x1080.jpg',
    patch:   '../img/ValorantWallpaper_Reaver.width-1000.format-webp.webp',
    esports: '../img/red-bull-campus-clutch-valorant-agents-phoenix-jett.avif',
    general: '../img/descarga (1).jpeg'
};

let todas = [], filtradas = [], catActiva = 'all', busy = false;

/* ── Toast de notificación ──────────────────────────────── */
function notif(msg, err = false, ms = 3200) {
    const el = document.getElementById('notif');
    el.textContent = msg;
    el.className   = 'show' + (err ? ' err' : '');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.className = '', ms);
}

/* ── Escapar HTML ───────────────────────────────────────── */
function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/* ── Skeleton de carga ──────────────────────────────────── */
function skeleton(n = 6) {
    return Array.from({ length: n }, () => `
        <div class="skeleton">
            <div class="sk-ico sk-line" style="border-radius:0;height:150px;"></div>
            <div class="sk-body">
                <div class="sk-line" style="width:38%;"></div>
                <div class="sk-line" style="width:72%;height:14px;"></div>
                <div class="sk-line"></div>
                <div class="sk-line" style="width:82%;"></div>
            </div>
        </div>`).join('');
}

/* ── HTML de una tarjeta de noticia ─────────────────────── */
function cardHTML(art, idx) {
    const feat = idx === 0 && catActiva === 'all';
    const cls  = ['news-card', feat ? 'featured' : '', art.breaking ? 'breaking' : ''].filter(Boolean).join(' ');
    const img  = CAT_IMGS[art.categoria] || CAT_IMGS.general;
    return `
    <article class="${cls}" onclick="abrirModal(${idx})">
        <div class="card-icon">
            <img src="${img}" alt="${esc(art.categoria)}" class="card-img" loading="lazy">
        </div>
        <div class="news-body">
            <div class="news-meta">
                <span class="news-source">${esc(art.fuente)}</span>
                <span>·</span><span>${esc(art.fecha)}</span>
                <span class="news-tag ${TCLASS[art.categoria]}">${CATS[art.categoria]}</span>
            </div>
            <h3 class="news-title">${esc(art.titulo)}</h3>
            <p class="news-desc">${esc(art.resumen)}</p>
        </div>
        <div class="news-footer"><button class="leer-btn">Leer más</button></div>
    </article>`;
}

/* ── Renderizar tarjetas ────────────────────────────────── */
function renderCards() {
    const g = document.getElementById('grid');
    if (!filtradas.length) {
        g.innerHTML = `<div class="vacio"><div class="vacio-ico">📡</div><p>Sin señal en esta frecuencia</p></div>`;
        return;
    }
    g.className = 'news-grid animate';
    g.innerHTML = filtradas.map((a, i) => cardHTML(a, i)).join('');
    setTimeout(() => g.classList.remove('animate'), 400);
}

/* ── Modal de artículo ──────────────────────────────────── */
function abrirModal(idx) {
    const art = filtradas[idx];
    if (!art) return;
    document.getElementById('mMeta').innerHTML  = `<span class="news-tag ${TCLASS[art.categoria]}">${CATS[art.categoria]}</span><span style="font-size:0.76rem;color:#6b6b7a;text-transform:uppercase;letter-spacing:0.05em;">${esc(art.fecha)}</span>`;
    document.getElementById('mTitle').textContent = art.titulo;
    document.getElementById('mBody').textContent  = art.contenido || art.resumen;
    document.getElementById('mFoot').textContent  = 'Fuente: ' + art.fuente;
    document.getElementById('overlay').classList.add('open');
}

document.getElementById('mClose').onclick = () => document.getElementById('overlay').classList.remove('open');
document.getElementById('overlay').onclick = e => {
    if (e.target === e.currentTarget) document.getElementById('overlay').classList.remove('open');
};

/* ── Filtros por categoría ──────────────────────────────── */
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('activo'));
        btn.classList.add('activo');
        catActiva = btn.dataset.cat;
        filtradas = catActiva === 'all' ? [...todas] : todas.filter(a => a.categoria === catActiva);
        renderCards();
    });
});

/* ── Carga de noticias via Claude API ───────────────────── */
async function cargarNoticias() {
    if (busy) return;
    busy = true;

    const g   = document.getElementById('grid');
    const btn = document.getElementById('btnRef');
    const ico = document.getElementById('ico');

    g.className   = 'news-grid';
    g.innerHTML   = skeleton(6);
    btn.disabled  = true;
    ico.className = 'spin';

    const hoy    = new Date().toLocaleDateString('es-ES', { day: '2-digit', month: 'long', year: 'numeric' });
    const prompt = `Eres un periodista experto en VALORANT y VCT esports.
Genera exactamente 9 noticias ficticias pero muy realistas y detalladas sobre VALORANT/VCT, con fecha ${hoy}.
Mezcla estas categorías (mínimo 2 de cada):
- vct: torneos VCT EMEA/Americas/Pacific, Masters, Champions
- patch: parches, balance, agentes nuevos, mapas
- esports: fichajes, resultados, organizaciones, jugadores pro
- general: skins, eventos, lore, meta, guías

Responde SOLO con JSON válido, sin texto adicional:
{"noticias":[{"titulo":"string","resumen":"1-2 frases","contenido":"3-5 frases detalladas","fuente":"nombre medio","fecha":"${hoy}","categoria":"vct|patch|esports|general","breaking":true|false}]}
Solo 1-2 noticias pueden tener breaking:true.`;

    try {
        const res = await fetch('https://api.anthropic.com/v1/messages', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                model:      'claude-sonnet-4-20250514',
                max_tokens: 1000,
                messages:   [{ role: 'user', content: prompt }]
            })
        });
        if (!res.ok) throw new Error('status ' + res.status);

        const data  = await res.json();
        const texto = (data.content || []).map(b => b.text || '').join('');
        const m     = texto.match(/\{[\s\S]*\}/);
        if (!m) throw new Error('json not found');

        const parsed = JSON.parse(m[0]);
        if (!parsed.noticias?.length) throw new Error('empty');

        todas     = parsed.noticias;
        filtradas = catActiva === 'all' ? [...todas] : todas.filter(a => a.categoria === catActiva);
        renderCards();
        notif('FEED ACTUALIZADO — ' + todas.length + ' transmisiones recibidas');

    } catch (err) {
        console.error(err);
        todas     = fallback();
        filtradas = catActiva === 'all' ? [...todas] : todas.filter(a => a.categoria === catActiva);
        renderCards();
        notif('Modo caché activado', true, 5000);
    } finally {
        busy          = false;
        btn.disabled  = false;
        ico.className = '';
        ico.textContent = '↻';
    }
}

/* ── Noticias de respaldo (fallback) ────────────────────── */
function fallback() {
    const h = new Date().toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
    return [
        { titulo: 'VCT EMEA 2025: Fnatic y Team Liquid lideran la fase regular', resumen: 'Ambos equipos se mantienen invictos tras tres semanas de competición intensa.', contenido: 'Fnatic y Team Liquid han demostrado ser los equipos a batir en VCT EMEA 2025. Con rachas ganadoras que impresionan a analistas, ambas organizaciones parecen destinadas a competir por el primer puesto. Los analistas destacan la cohesión táctica de Fnatic y el dominio del mid-game de Liquid.', fuente: 'VCT Official', fecha: h, categoria: 'vct', breaking: true },
        { titulo: 'Parche 10.09: Jett recibe buffs y Killjoy ajustes de balance', resumen: 'Riot ajusta varias habilidades para equilibrar el meta competitivo actual.', contenido: 'El parche 10.09 introduce cambios en Jett aumentando el daño de Bladestorm y reduciendo su cooldown. Killjoy ve reducido el radio de su Lockdown en 15%, una respuesta directa a su dominancia en mapas cerrados.', fuente: 'Valorant Official', fecha: h, categoria: 'patch', breaking: false },
        { titulo: 'TenZ firma con LOUD en una transferencia histórica del VCT', resumen: 'El icónico jugador canadiense da el salto a la franquicia brasileña.', contenido: 'Tyson "TenZ" Ngo se une oficialmente a LOUD tras semanas de rumores. La organización brasileña apuesta por su talento individual para elevar su nivel en VCT Americas.', fuente: 'Dot Esports', fecha: h, categoria: 'esports', breaking: true },
        { titulo: 'Filtrado nuevo agente iniciador con poderes sónicos', resumen: 'Dataminers encuentran referencias a un agente coreano con habilidades de sonido.', contenido: 'Los dataminers encontraron referencias a un nuevo agente iniciador en los archivos del último parche, con poderes sónicos para revelar enemigos y aturdir con ondas de sonido expansivas.', fuente: 'ValorantLeaks', fecha: h, categoria: 'general', breaking: false },
        { titulo: 'Paper Rex domina VCT Pacific con ACS histórico de f0rsaken', resumen: 'El duelista establece un récord personal de ACS 318 ante DRX.', contenido: 'Paper Rex continúa su racha en VCT Pacific tras vencer a DRX 2-0. Jason "f0rsaken" Susanto fue el MVP con ACS 318 y 34% de headshots.', fuente: 'Liquipedia Valorant', fecha: h, categoria: 'vct', breaking: false },
        { titulo: 'Colección de skins "Nebula": precios y fecha de lanzamiento', resumen: 'Riot desvela la nueva línea de cosméticos que llega en la próxima actualización.', contenido: 'La colección Nebula incluye skins para Vandal, Phantom, Operator, Frenzy y cuchillo con efectos de galaxias en movimiento. El precio de la colección completa será de 8.700 VP.', fuente: 'Riot Games', fecha: h, categoria: 'general', breaking: false },
        { titulo: 'Evil Geniuses reorganiza su roster para el segundo split', resumen: 'La organización confirma dos salidas y busca nuevos talentos en Challenger.', contenido: 'Evil Geniuses anunció que dos jugadores principales no continuarán para el segundo split de VCT Americas. La directiva está en negociaciones avanzadas con prospectos de la escena Challenger.', fuente: 'HLTV Valorant', fecha: h, categoria: 'esports', breaking: false },
        { titulo: 'Parche 10.09: nuevo mapa Drift llega al pool competitivo', resumen: 'El nuevo mapa de temática industrial se incorpora oficialmente a la rotación profesional.', contenido: 'Drift, el nuevo mapa ambientado en instalaciones industriales, entra oficialmente en el pool competitivo con el parche 10.09. El mapa presenta una estructura asimétrica con numerosas verticalidades.', fuente: 'Valorant Official', fecha: h, categoria: 'patch', breaking: false },
        { titulo: 'VCT Masters Bangkok 2025: formato, fechas y clasificados', resumen: 'Todo lo que necesitas saber sobre el siguiente gran evento internacional.', contenido: 'Riot Games confirma todos los detalles del VCT Masters Bangkok 2025. El torneo contará con 12 equipos y el prize pool total asciende a 1.000.000 de dólares.', fuente: 'VCT Official', fecha: h, categoria: 'vct', breaking: false }
    ];
}

/* ── Badge de premium en noticias ───────────────────────── */
async function checkPremium() {
    try {
        const res = await fetch(`api_premium.php?usuario=${encodeURIComponent(USUARIO)}`);
        const j   = await res.json();
        const el  = document.getElementById('header-badge-premium');
        if (!el) return;
        if (j.es_premium) {
            el.innerHTML = '<span class="badge-premium">⚡ PREMIUM</span>';
        } else {
            el.innerHTML = '<a href="crear_liga.php" class="badge-activar-premium">⚡ Activar Premium</a>';
        }
    } catch (_) {}
}

/* ── Botón refresh ──────────────────────────────────────── */
document.getElementById('btnRef').onclick = () => cargarNoticias();

/* ── Inicio ─────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    cargarNoticias();
    checkPremium();
});
