<?php
/**
 * api_noticias.php
 * Obtiene noticias de Valorant y VCT desde NewsAPI.org.
 *
 * GET → devuelve artículos en JSON
 *
 * CONFIGURACIÓN NECESARIA:
 *   1. Regístrate en https://newsapi.org (plan gratuito disponible)
 *   2. Copia tu API key en la constante NEWS_API_KEY de abajo
 *   3. El caché de 15 minutos evita sobrepasar el límite de peticiones
 */

session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// ── CONFIGURACIÓN ────────────────────────────────────────────────────────
define('NEWS_API_KEY', 'TU_CLAVE_NEWSAPI_AQUI'); // 👈 Reemplaza con tu key de newsapi.org
define('CACHE_FILE',  __DIR__ . '/../cache/noticias_cache.json');
define('CACHE_TTL',   900); // 15 minutos en segundos
define('MAX_ARTICLES', 30);

// ── Verificar sesión (seguridad básica) ──────────────────────────────────
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Sesión caducada."]);
    exit();
}

// ── Función: leer caché ──────────────────────────────────────────────────
function leerCache(): ?array {
    if (!file_exists(CACHE_FILE)) return null;
    $contenido = file_get_contents(CACHE_FILE);
    if (!$contenido) return null;
    $datos = json_decode($contenido, true);
    if (!$datos || !isset($datos['timestamp'], $datos['articles'])) return null;
    if ((time() - $datos['timestamp']) > CACHE_TTL) return null; // caducado
    return $datos['articles'];
}

// ── Función: escribir caché ──────────────────────────────────────────────
function escribirCache(array $articulos): void {
    $dir = dirname(CACHE_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    file_put_contents(CACHE_FILE, json_encode([
        'timestamp' => time(),
        'articles'  => $articulos
    ]));
}

// ── Intentar servir desde caché ──────────────────────────────────────────
$cachedArticles = leerCache();
if ($cachedArticles !== null) {
    echo json_encode(["status" => "success", "source" => "cache", "articles" => $cachedArticles]);
    exit();
}

// ── Si no hay API key configurada, devolver error controlado ─────────────
if (NEWS_API_KEY === 'TU_CLAVE_NEWSAPI_AQUI' || empty(NEWS_API_KEY)) {
    http_response_code(503);
    echo json_encode([
        "status"  => "error",
        "message" => "API key de noticias no configurada. Edita api_noticias.php y añade tu clave de newsapi.org"
    ]);
    exit();
}

// ── Petición a NewsAPI ───────────────────────────────────────────────────
// Buscamos artículos sobre Valorant y VCT en varios idiomas
$queries = [
    'Valorant VCT 2025',
    'Valorant esports',
    'VCT EMEA Champions'
];

$todosLosArticulos = [];

foreach ($queries as $q) {
    $url = 'https://newsapi.org/v2/everything?' . http_build_query([
        'q'        => $q,
        'language' => 'en',          // inglés para más cobertura, la UI muestra sin filtro
        'sortBy'   => 'publishedAt',
        'pageSize' => 15,
        'apiKey'   => NEWS_API_KEY
    ]);

    $ctx = stream_context_create([
        'http' => [
            'timeout'       => 8,
            'ignore_errors' => true
        ]
    ]);

    $respuesta = @file_get_contents($url, false, $ctx);
    if (!$respuesta) continue;

    $datos = json_decode($respuesta, true);
    if (empty($datos['articles'])) continue;

    foreach ($datos['articles'] as $art) {
        // Filtrar artículos eliminados o sin contenido útil
        if (($art['title'] ?? '') === '[Removed]') continue;
        if (empty($art['url'])) continue;

        $todosLosArticulos[] = $art;
    }
}

// ── Deduplicar por URL ───────────────────────────────────────────────────
$vistos     = [];
$unicos     = [];
foreach ($todosLosArticulos as $art) {
    $url = $art['url'] ?? '';
    if ($url && !isset($vistos[$url])) {
        $vistos[$url] = true;
        $unicos[]     = $art;
    }
}

// ── Ordenar por fecha descendente y limitar ──────────────────────────────
usort($unicos, function ($a, $b) {
    return strcmp($b['publishedAt'] ?? '', $a['publishedAt'] ?? '');
});
$articulos = array_slice($unicos, 0, MAX_ARTICLES);

if (empty($articulos)) {
    http_response_code(503);
    echo json_encode(["status" => "error", "message" => "No se pudieron obtener artículos de NewsAPI."]);
    exit();
}

// ── Guardar en caché y responder ─────────────────────────────────────────
escribirCache($articulos);

echo json_encode([
    "status"   => "success",
    "source"   => "live",
    "total"    => count($articulos),
    "articles" => $articulos
]);
?>