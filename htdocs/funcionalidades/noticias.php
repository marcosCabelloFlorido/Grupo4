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
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/noticias.css">
</head>
<body>

<div class="header">
    <div class="header-logo">VALT<span>ASY</span></div>
    <div class="header-right">
        <p>AGENTE: <span><?= htmlspecialchars($nombre_usuario_actual) ?></span></p>
        <div id="premiumBadge" style="margin-top:4px;"></div>
        <a href="../sesion/cerrar.php" style="margin-top:6px; display:inline-block;">[ DESCONECTAR ]</a>
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

<!-- Modal de artículo -->
<div class="overlay" id="overlay">
    <div class="modal-box">
        <button class="m-close" id="mClose">✕</button>
        <div class="m-meta"  id="mMeta"></div>
        <h3 class="m-title"  id="mTitle"></h3>
        <div class="m-body"  id="mBody"></div>
        <div class="m-foot"  id="mFoot"></div>
    </div>
</div>

<div id="notif"></div>

<script>
    const USUARIO = <?= json_encode($nombre_usuario_actual) ?>;
</script>
<script src="js/noticias.js"></script>
</body>
</html>
