<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ../sesion/login.php");
    exit();
}
$nombre_usuario_actual = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VALTASY — Noticias</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Barlow+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #0d0d0f;
            color: #e8e8ee;
            font-family: 'Barlow Condensed', sans-serif;
            padding: 40px;
            min-height: 100vh;
            background-image:
                radial-gradient(ellipse 50% 50% at 50% 50%, rgba(140, 8, 19, 0.12) 0%, transparent 70%),
                linear-gradient(rgba(29, 242, 221, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(29, 242, 221, 0.02) 1px, transparent 1px);
            background-size: cover, 60px 60px, 60px 60px;
        }

        h1, h2, h3 { font-family: 'Orbitron', monospace; text-transform: uppercase; }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #A63247;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header-logo { font-family: 'Orbitron', monospace; font-size: 1.6rem; font-weight: 900; letter-spacing: 0.12em; color: #fff; }
        .header-logo span { color: #1DF2DD; }
        .header-right { text-align: right; }
        .header-right p { font-size: 1rem; color: #6b6b7a; letter-spacing: 0.05em; }
        .header-right span { color: #1DF2DD; font-weight: 700; }
        .header-right a { color: #6b6b7a; text-decoration: none; font-size: 0.85rem; transition: color 0.2s; }
        .header-right a:hover { color: #A63247; }

        .nav-tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 32px;
            border-bottom: 1px solid rgba(29, 242, 221, 0.12);
        }
        .nav-tab {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #6b6b7a;
            text-decoration: none;
            padding: 10px 22px;
            border: 1px solid transparent;
            border-bottom: none;
            clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
            transition: color 0.2s, background 0.2s;
            position: relative;
            top: 1px;
        }
        .nav-tab:hover { color: #1DF2DD; }
        .nav-tab.activo {
            color: #1DF2DD;
            background: #141418;
            border-color: rgba(29, 242, 221, 0.25);
            border-bottom-color: #141418;
        }

        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 28px;
            gap: 20px;
            flex-wrap: wrap;
        }
        .page-title-block h2 { font-size: 1.1rem; color: #6b6b7a; letter-spacing: 0.12em; margin-bottom: 6px; }
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            color: #1DF2DD;
            font-weight: 700;
        }
        .live-dot {
            width: 7px; height: 7px;
            background: #1DF2DD;
            border-radius: 50%;
            animation: pulse-dot 1.6s ease-in-out infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.7); }
        }

        .controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; }
        .filter-btn {
            padding: 7px 18px;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            background: transparent;
            border: 1px solid rgba(29, 242, 221, 0.2);
            color: #6b6b7a;
            cursor: pointer;
            clip-path: polygon(5px 0%, 100% 0%, calc(100% - 5px) 100%, 0% 100%);
            transition: all 0.2s;
        }
        .filter-btn:hover, .filter-btn.activo {
            background: rgba(29, 242, 221, 0.1);
            border-color: #1DF2DD;
            color: #1DF2DD;
        }
        .btn-refresh {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 22px;
            background: transparent;
            border: 1px solid rgba(29,242,221,0.35);
            color: #1DF2DD;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            cursor: pointer;
            clip-path: polygon(5px 0%, 100% 0%, calc(100% - 5px) 100%, 0% 100%);
            transition: all 0.2s;
        }
        .btn-refresh:hover { background: rgba(29,242,221,0.1); }
        .btn-refresh:disabled { opacity: 0.4; cursor: not-allowed; }
        .spin { animation: spin 1s linear infinite; display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── GRID ── */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 20px;
        }
        .news-grid.animate { animation: fadeUp 0.35s ease; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── CARD ── */
        .news-card {
            background: #141418;
            border: 1px solid rgba(29, 242, 221, 0.1);
            border-top: 2px solid #1DF2DD;
            display: flex;
            flex-direction: column;
            transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
            position: relative;
            cursor: pointer;
        }
        .news-card:hover {
            border-color: rgba(29, 242, 221, 0.38);
            box-shadow: 0 6px 28px rgba(29, 242, 221, 0.09);
            transform: translateY(-2px);
        }
        .news-card.breaking { border-top-color: #A63247; }
        .news-card.breaking::after {
            content: 'BREAKING';
            position: absolute;
            top: 14px; right: 0;
            background: #A63247;
            color: #fff;
            font-family: 'Orbitron', monospace;
            font-size: 0.58rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            padding: 3px 10px 3px 8px;
            clip-path: polygon(6px 0%, 100% 0%, 100% 100%, 0% 100%);
        }
        .news-card.featured {
            grid-column: 1 / -1;
            flex-direction: row;
            border-top: none;
            border-left: 3px solid #1DF2DD;
        }
        .news-card.featured .card-icon { width: 260px; min-width: 260px; height: auto; font-size: 4rem; }
        .news-card.featured .news-title { font-size: 1rem; }
        .news-card.featured .news-desc  { -webkit-line-clamp: 4; }

        .card-icon {
            width: 100%; height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            background: #0d0d0f;
            color: rgba(29,242,221,0.15);
            flex-shrink: 0;
        }

        .news-body {
            padding: 16px 18px 12px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .news-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.74rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #6b6b7a;
            flex-wrap: wrap;
        }
        .news-source { color: #1DF2DD; font-weight: 700; }
        .news-tag {
            font-size: 0.68rem;
            letter-spacing: 0.1em;
            padding: 2px 8px;
            clip-path: polygon(4px 0%, 100% 0%, calc(100% - 4px) 100%, 0% 100%);
            font-weight: 700;
        }
        .tag-vct     { background: rgba(29,242,221,0.12); color: #1DF2DD; }
        .tag-patch   { background: rgba(166,50,71,0.15);  color: #A63247; }
        .tag-esports { background: rgba(255,200,0,0.1);   color: #FFC800; }
        .tag-general { background: rgba(255,255,255,0.05);color: #9b9baa; }

        .news-title {
            font-family: 'Orbitron', monospace;
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1.45;
            color: #e8e8ee;
            letter-spacing: 0.03em;
        }
        .news-desc {
            font-size: 0.9rem;
            color: #9b9baa;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }
        .news-footer {
            padding: 10px 18px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .leer-btn {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #1DF2DD;
            background: none;
            border: none;
            cursor: pointer;
            font-family: 'Barlow Condensed', sans-serif;
            padding: 0;
            transition: color 0.2s;
        }
        .leer-btn:hover { color: #fff; }
        .leer-btn::after { content: ' →'; }

        /* ── SKELETON ── */
        .skeleton {
            background: #141418;
            border: 1px solid rgba(29,242,221,0.07);
            border-top: 2px solid rgba(29,242,221,0.15);
        }
        .sk-ico  { width:100%; height:150px; }
        .sk-body { padding:16px 18px; display:flex; flex-direction:column; gap:10px; }
        .sk-line {
            height:11px; border-radius:2px;
            background: linear-gradient(90deg,#1e1e24 25%,#252530 50%,#1e1e24 75%);
            background-size:200% 100%;
            animation: shimmer 1.6s infinite;
        }
        @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

        /* ── MODAL ── */
        .overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.78);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
        }
        .overlay.open { opacity:1; pointer-events:all; }
        .modal-box {
            background: #141418;
            border: 1px solid rgba(29,242,221,0.25);
            border-top: 3px solid #1DF2DD;
            max-width: 680px; width: 100%;
            max-height: 80vh; overflow-y: auto;
            padding: 28px 30px;
            position: relative;
            transform: translateY(20px);
            transition: transform 0.25s;
        }
        .overlay.open .modal-box { transform: translateY(0); }
        .m-close {
            position:absolute; top:16px; right:18px;
            background:none; border:none;
            color:#6b6b7a; font-size:1.1rem;
            cursor:pointer; transition:color 0.2s;
            font-family:'Orbitron',monospace;
        }
        .m-close:hover { color:#A63247; }
        .m-meta { display:flex; gap:10px; align-items:center; margin-bottom:12px; }
        .m-title { font-size:1.05rem; margin-bottom:14px; line-height:1.5; color:#e8e8ee; }
        .m-body  { font-size:0.95rem; color:#9b9baa; line-height:1.7; }
        .m-foot  { margin-top:20px; padding-top:14px; border-top:1px solid rgba(255,255,255,0.07); font-size:0.78rem; color:#6b6b7a; letter-spacing:0.05em; text-transform:uppercase; }

        /* ── VACÍO / ERROR ── */
        .vacio {
            grid-column:1/-1; text-align:center; padding:60px 20px; color:#6b6b7a;
        }
        .vacio-ico { font-size:2.5rem; margin-bottom:14px; opacity:0.3; }
        .vacio p { font-family:'Orbitron',monospace; font-size:0.85rem; letter-spacing:0.1em; }

        /* ── NOTIF ── */
        #notif {
            position:fixed; bottom:28px; right:28px;
            background:#141418; border-left:3px solid #1DF2DD;
            padding:12px 18px;
            font-size:0.82rem; letter-spacing:0.06em; text-transform:uppercase;
            font-weight:700; color:#1DF2DD;
            z-index:9999; transform:translateX(120%);
            transition:transform 0.3s cubic-bezier(0.22,1,0.36,1);
            max-width:320px;
        }
        #notif.show { transform:translateX(0); }
        #notif.err  { border-left-color:#A63247; color:#A63247; }

        @media(max-width:700px){
            body{padding:18px;}
            .news-card.featured{flex-direction:column;border-left:none;border-top:2px solid #1DF2DD;}
            .news-card.featured .card-icon{width:100%;min-width:unset;height:150px;}
            .page-header{flex-direction:column;align-items:flex-start;}
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-logo">VALT<span>ASY</span></div>
    <div class="header-right">
        <p>AGENTE: <span><?= htmlspecialchars($nombre_usuario_actual) ?></span></p>
        <div id="premiumBadge" style="margin-top:4px;"></div>
        <a href="../sesion/logout.php" style="margin-top:6px;display:inline-block;">Cerrar sesión</a>
    </div>
</div>

<nav class="nav-tabs">
    <a href="cliente.php"    class="nav-tab">Dashboard</a>
    <a href="crear_liga.php" class="nav-tab">Nueva Liga</a>
    <a href="noticias.php"   class="nav-tab activo">Noticias</a>
</nav>

<div class="page-header">
    <div class="page-title-block">
        <h2>Inteligencia de Campo</h2>
        <div class="live-indicator"><div class="live-dot"></div>FEED EN VIVO — VALORANT &amp; VCT</div>
    </div>
    <div class="controls">
        <div class="filter-bar">
            <button class="filter-btn activo" data-cat="all">TODO</button>
            <button class="filter-btn" data-cat="vct">VCT</button>
            <button class="filter-btn" data-cat="patch">PARCHES</button>
            <button class="filter-btn" data-cat="esports">ESPORTS</button>
            <button class="filter-btn" data-cat="general">GENERAL</button>
        </div>
        <button class="btn-refresh" id="btnRef">
            <span id="ico">↻</span> ACTUALIZAR
        </button>
    </div>
</div>

<div class="news-grid" id="grid"></div>

<!-- Modal -->
<div class="overlay" id="overlay">
    <div class="modal-box">
        <button class="m-close" id="mClose">✕</button>
        <div class="m-meta" id="mMeta"></div>
        <h3 class="m-title" id="mTitle"></h3>
        <div class="m-body" id="mBody"></div>
        <div class="m-foot" id="mFoot"></div>
    </div>
</div>

<div id="notif"></div>

<script>
const USUARIO = <?= json_encode($nombre_usuario_actual) ?>;
const CATS    = {vct:'VCT', patch:'PARCHE', esports:'ESPORTS', general:'GENERAL'};
const TCLASS  = {vct:'tag-vct', patch:'tag-patch', esports:'tag-esports', general:'tag-general'};
const EMOJIS  = {vct:'🎯', patch:'⚙️', esports:'🏆', general:'📡'};

let todas = [], filtradas = [], catActiva = 'all', busy = false;

function notif(msg, err=false, ms=3200){
    const el=document.getElementById('notif');
    el.textContent=msg; el.className='show'+(err?' err':'');
    clearTimeout(el._t); el._t=setTimeout(()=>el.className='',ms);
}

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

function skeleton(n=6){
    return Array.from({length:n},()=>`
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

function cardHTML(art, idx){
    const feat = idx===0 && catActiva==='all';
    const cls  = ['news-card', feat?'featured':'', art.breaking?'breaking':''].filter(Boolean).join(' ');
    return `
    <article class="${cls}" onclick="abrirModal(${idx})">
        <div class="card-icon">${EMOJIS[art.categoria]||'📡'}</div>
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

function renderCards(){
    const g = document.getElementById('grid');
    if(!filtradas.length){
        g.innerHTML=`<div class="vacio"><div class="vacio-ico">📡</div><p>Sin señal en esta frecuencia</p></div>`;
        return;
    }
    g.className='news-grid animate';
    g.innerHTML=filtradas.map((a,i)=>cardHTML(a,i)).join('');
    setTimeout(()=>g.classList.remove('animate'),400);
}

function abrirModal(idx){
    const art=filtradas[idx]; if(!art) return;
    document.getElementById('mMeta').innerHTML=`<span class="news-tag ${TCLASS[art.categoria]}">${CATS[art.categoria]}</span><span style="font-size:0.76rem;color:#6b6b7a;text-transform:uppercase;letter-spacing:0.05em;">${esc(art.fecha)}</span>`;
    document.getElementById('mTitle').textContent=art.titulo;
    document.getElementById('mBody').textContent=art.contenido||art.resumen;
    document.getElementById('mFoot').textContent='Fuente: '+art.fuente;
    document.getElementById('overlay').classList.add('open');
}
document.getElementById('mClose').onclick=()=>document.getElementById('overlay').classList.remove('open');
document.getElementById('overlay').onclick=e=>{if(e.target===e.currentTarget)document.getElementById('overlay').classList.remove('open');};

document.querySelectorAll('.filter-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
        document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('activo'));
        btn.classList.add('activo');
        catActiva=btn.dataset.cat;
        filtradas=catActiva==='all'?[...todas]:todas.filter(a=>a.categoria===catActiva);
        renderCards();
    });
});

async function cargarNoticias(){
    if(busy) return; busy=true;
    const g=document.getElementById('grid');
    const btn=document.getElementById('btnRef');
    const ico=document.getElementById('ico');
    g.className='news-grid'; g.innerHTML=skeleton(6);
    btn.disabled=true; ico.className='spin';

    const hoy=new Date().toLocaleDateString('es-ES',{day:'2-digit',month:'long',year:'numeric'});
    const prompt=`Eres un periodista experto en VALORANT y VCT esports.
Genera exactamente 9 noticias ficticias pero muy realistas y detalladas sobre VALORANT/VCT, con fecha ${hoy}.
Mezcla estas categorías (mínimo 2 de cada):
- vct: torneos VCT EMEA/Americas/Pacific, Masters, Champions
- patch: parches, balance, agentes nuevos, mapas
- esports: fichajes, resultados, organizaciones, jugadores pro
- general: skins, eventos, lore, meta, guías

Responde SOLO con JSON válido, sin texto adicional:
{"noticias":[{"titulo":"string","resumen":"1-2 frases","contenido":"3-5 frases detalladas","fuente":"nombre medio","fecha":"${hoy}","categoria":"vct|patch|esports|general","breaking":true|false}]}
Solo 1-2 noticias pueden tener breaking:true.`;

    try{
        const res=await fetch('https://api.anthropic.com/v1/messages',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({
                model:'claude-sonnet-4-20250514',
                max_tokens:1000,
                messages:[{role:'user',content:prompt}]
            })
        });
        if(!res.ok) throw new Error('status '+res.status);
        const data=await res.json();
        const texto=(data.content||[]).map(b=>b.text||'').join('');
        const m=texto.match(/\{[\s\S]*\}/);
        if(!m) throw new Error('json not found');
        const parsed=JSON.parse(m[0]);
        if(!parsed.noticias?.length) throw new Error('empty');
        todas=parsed.noticias;
        filtradas=catActiva==='all'?[...todas]:todas.filter(a=>a.categoria===catActiva);
        renderCards();
        notif('FEED ACTUALIZADO — '+todas.length+' transmisiones recibidas');
    }catch(err){
        console.error(err);
        todas=fallback();
        filtradas=catActiva==='all'?[...todas]:todas.filter(a=>a.categoria===catActiva);
        renderCards();
        notif('Modo caché activado',true,5000);
    }finally{
        busy=false; btn.disabled=false; ico.className=''; ico.textContent='↻';
    }
}

function fallback(){
    const h=new Date().toLocaleDateString('es-ES',{day:'2-digit',month:'short',year:'numeric'});
    return[
        {titulo:'VCT EMEA 2025: Fnatic y Team Liquid lideran la fase regular',resumen:'Ambos equipos se mantienen invictos tras tres semanas de competición intensa.',contenido:'Fnatic y Team Liquid han demostrado ser los equipos a batir en VCT EMEA 2025. Con rachas ganadoras que impresionan a analistas, ambas organizaciones parecen destinadas a competir por el primer puesto. Los analistas destacan la cohesión táctica de Fnatic y el dominio del mid-game de Liquid. El duelo directo entre ambos marcará el devenir de la fase regular.',fuente:'VCT Official',fecha:h,categoria:'vct',breaking:true},
        {titulo:'Parche 10.09: Jett recibe buffs y Killjoy ajustes de balance',resumen:'Riot ajusta varias habilidades para equilibrar el meta competitivo actual.',contenido:'El parche 10.09 introduce cambios en Jett aumentando el daño de Bladestorm y reduciendo su cooldown. Killjoy ve reducido el radio de su Lockdown en 15%, una respuesta directa a su dominancia en mapas cerrados. También se corrigen varios bugs reportados por la comunidad que afectaban a la detección de colisiones en Bind.',fuente:'Valorant Official',fecha:h,categoria:'patch',breaking:false},
        {titulo:'TenZ firma con LOUD en una transferencia histórica del VCT',resumen:'El icónico jugador canadiense da el salto a la franquicia brasileña.',contenido:'Tyson "TenZ" Ngo se une oficialmente a LOUD tras semanas de rumores. La organización brasileña apuesta por su talento individual para elevar su nivel en VCT Americas. TenZ expresó su emoción por este desafío y su deseo de llevar a LOUD a Champions. La transferencia se valora como una de las más costosas de la historia del juego.',fuente:'Dot Esports',fecha:h,categoria:'esports',breaking:true},
        {titulo:'Filtrado nuevo agente iniciador con poderes sónicos',resumen:'Dataminers encuentran referencias a un agente coreano con habilidades de sonido.',contenido:'Los dataminers encontraron referencias a un nuevo agente iniciador en los archivos del último parche, aparentemente con poderes sónicos. Las habilidades filtradas sugieren capacidades para revelar enemigos a través de paredes usando frecuencias acústicas y aturdir con ondas de sonido expansivas. Riot no ha confirmado la filtración.',fuente:'ValorantLeaks',fecha:h,categoria:'general',breaking:false},
        {titulo:'Paper Rex domina VCT Pacific con ACS histórico de f0rsaken',resumen:'El duelista establece un récord personal de ACS 318 ante DRX.',contenido:'Paper Rex continúa su racha en VCT Pacific tras vencer a DRX 2-0. Jason "f0rsaken" Susanto fue el MVP con ACS 318 y 34% de headshots. La organización refuerza su posición como favorita para Masters. El estilo agresivo de PRX sigue siendo un espectáculo para los aficionados de todo el mundo.',fuente:'Liquipedia Valorant',fecha:h,categoria:'vct',breaking:false},
        {titulo:'Colección de skins "Nebula": precios y fecha de lanzamiento',resumen:'Riot desvela la nueva línea de cosméticos que llega en la próxima actualización.',contenido:'La colección Nebula incluye skins para Vandal, Phantom, Operator, Frenzy y cuchillo con efectos de galaxias en movimiento. El precio de la colección completa será de 8.700 VP. Los efectos de muerte han sido los más aplaudidos en el tráiler. Las variantes de color ofrecen cuatro paletas distintas para cada arma.',fuente:'Riot Games',fecha:h,categoria:'general',breaking:false},
        {titulo:'Evil Geniuses reorganiza su roster para el segundo split',resumen:'La organización confirma dos salidas y busca nuevos talentos en Challenger.',contenido:'Evil Geniuses anunció que dos jugadores principales no continuarán para el segundo split de VCT Americas. La directiva está en negociaciones avanzadas con prospectos de la escena Challenger norteamericana y latinoamericana. El objetivo es construir un equipo más versátil tácticamente y con mayor potencial de crecimiento a largo plazo.',fuente:'HLTV Valorant',fecha:h,categoria:'esports',breaking:false},
        {titulo:'Parche 10.09: nuevo mapa Drift llega al pool competitivo',resumen:'El nuevo mapa de temática industrial se incorpora oficialmente a la rotación profesional.',contenido:'Drift, el nuevo mapa ambientado en instalaciones industriales, entra oficialmente en el pool competitivo con el parche 10.09. El mapa presenta una estructura asimétrica con numerosas verticalidades que favorecen a los duelistas. Los equipos ya han comenzado a preparar nuevas composiciones específicas para sacar partido de sus características únicas.',fuente:'Valorant Official',fecha:h,categoria:'patch',breaking:false},
        {titulo:'VCT Masters Bangkok 2025: formato, fechas y clasificados',resumen:'Todo lo que necesitas saber sobre el siguiente gran evento internacional.',contenido:'Riot Games confirma todos los detalles del VCT Masters Bangkok 2025. El torneo contará con 12 equipos de las tres regiones más slots para ligas asociadas. El formato será de grupos con eliminación directa y el prize pool total asciende a 1.000.000 de dólares. Las fechas sitúan el inicio del evento a finales del próximo mes en la capital tailandesa.',fuente:'VCT Official',fecha:h,categoria:'vct',breaking:false}
    ];
}

async function checkPremium(){
    try{
        const res=await fetch(`api_premium.php?usuario=${encodeURIComponent(USUARIO)}`);
        const j=await res.json();
        const el=document.getElementById('premiumBadge');
        if(j.es_premium){
            el.innerHTML='<span style="color:#FFC800;font-size:0.8rem;font-weight:700;letter-spacing:0.06em;">⚡ PREMIUM</span>';
        }else{
            el.innerHTML='<a href="crear_liga.php" style="color:#6b6b7a;font-size:0.8rem;text-decoration:none;" onmouseover="this.style.color=\'#A63247\'" onmouseout="this.style.color=\'#6b6b7a\'">⚡ Activar Premium</a>';
        }
    }catch(_){}
}

document.getElementById('btnRef').onclick=()=>cargarNoticias();

document.addEventListener('DOMContentLoaded',()=>{
    cargarNoticias();
    checkPremium();
});
</script>
</body>
</html>