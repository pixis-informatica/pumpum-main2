<?php
/**
 * PIXIS SOCIAL MEDIATOR
 * Este archivo sirve metadatos puros a los bots de WhatsApp/Facebook/etc.
 */

$slug = isset($_GET['producto']) ? $_GET['producto'] : '';
$bannerId = isset($_GET['banner']) ? $_GET['banner'] : '';
$theProduct = null;
$theBanner = null;

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

// --- NUEVA LÓGICA DE DETECCIÓN DE PLATAFORMA ---
// WhatsApp y Telegram prefieren la imagen original (ancha)
// Facebook y Discord prefieren la imagen ajustada (1.91:1) para no recortar
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isWhatsApp = (strpos($userAgent, 'WhatsApp') !== false || strpos($userAgent, 'Telegram') !== false);

if ($bannerId) {
    $redirectUrl = $baseUrl . "/index.html?banner=" . urlencode($bannerId);
} else {
    $redirectUrl = $baseUrl . "/index.html" . ($slug ? "?producto=" . urlencode($slug) : "");
}

/**
 * Convierte un título a slug idéntico al del JS
 * JS: text.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,'')
 */
function makeSlug($text) {
    // Transliterar caracteres especiales (tildes, ñ, ™, etc.)
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = strtolower($text);
    // Reemplazar todo lo que no sea letra o número por guion
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    // Quitar guiones del inicio y fin
    $text = trim($text, '-');
    return $text;
}

/**
 * Convierte la ruta de imagen a una URL absoluta válida para la web
 * Maneja: barras invertidas, espacios, caracteres especiales
 */
function makeImageUrl($imgPath, $baseUrl) {
    // Si ya es una URL absoluta, devolverla tal cual
    if (strpos($imgPath, 'http') === 0) {
        return $imgPath;
    }

    // 1. Reemplazar barras invertidas de Windows por barras normales
    $imgPath = str_replace('\\', '/', $imgPath);

    // 2. Quitar espacios/caracteres al inicio
    $imgPath = ltrim(trim($imgPath), '/');

    // 3. Codificar cada segmento del path por separado (preservar las /)
    $segments = explode('/', $imgPath);
    $encodedSegments = array_map(function($seg) {
        return rawurlencode($seg);
    }, $segments);
    $encodedPath = implode('/', $encodedSegments);

    return $baseUrl . '/' . $encodedPath;
}

// 1. Cargar datos (Productos o Banner)
if ($bannerId) {
    // Buscamos metadata del banner en site.json
    $siteData = @file_get_contents(__DIR__ . '/data/site.json');
    if ($siteData) {
        $site = json_decode($siteData, true);
        $siteBanners = $site['banners'] ?? [];
        
        // Fallback quirúrgico para banners conocidos si no están en el JSON
        $fallbackBanners = [
            'kitryzen' => ['t' => 'Kits de Actualización Ryzen'],
            'pccombo' => ['t' => 'PC Gamers y Combos'],
            'monitor' => ['t' => 'Monitores Raptor'],
            'rtx5050' => ['t' => 'Tarjetas Gráficas RTX 5050'],
            'rtx5060' => ['t' => 'Tarjetas Gráficas RTX 5060'],
            'notebooks' => ['t' => 'Notebooks y Mini PCs'],
            'prolongadores' => ['t' => 'Prolongadores Kelyx'],
            'ssd-hiksemi' => ['t' => 'SSDs Hiksemi'],
            'refrigeracion-raptor' => ['t' => 'Refrigeración Raptor'],
            'gabinetes-raptor' => ['t' => 'Gabinetes Raptor'],
            'perifericos-raptor' => ['t' => 'Periféricos Raptor']
        ];

        if (isset($siteBanners[$bannerId])) {
            $theBanner = $siteBanners[$bannerId];
        } elseif (isset($fallbackBanners[$bannerId])) {
            $theBanner = $fallbackBanners[$bannerId];
        }

        if ($theBanner) {
            // Buscar la imagen en los carruseles (donde sí están guardadas)
            $allSlides = array_merge($site['carouselTop'] ?? [], $site['carouselBottom'] ?? []);
            foreach ($allSlides as $slide) {
                if (($slide['bannerId'] ?? '') === $bannerId) {
                    $theBanner['img'] = $slide['imgPc'] ?? $slide['imgMobile'] ?? '';
                    break;
                }
            }
        }
    }
} elseif ($slug) {
    // Buscamos el producto en products.json
    $prodsData = @file_get_contents(__DIR__ . '/data/products.json');
    if ($prodsData) {
        $products = json_decode($prodsData, true);
        if (is_array($products)) {
            foreach ($products as $p) {
                $currentSlug = makeSlug($p['title'] ?? 'producto');
                if ($currentSlug === $slug) {
                    $theProduct = $p;
                    break;
                }
            }
        }
    }
}

// 2. Definir valores finales de metadata
if ($theBanner) {
    $bannerTitle = $theBanner['t'] ?? 'Promoción';
    $title = $bannerTitle . " - Pixis Informatica | 🚀 Ofertas";
    $description = "Aprovechá las mejores ofertas en " . $bannerTitle . ". Envíos a todo el país y el mejor servicio técnico.";
    $directBannerImg = makeImageUrl($theBanner['img'] ?? 'img/logo_pixis.png', $baseUrl);
    
    // BANNER: Original en WhatsApp, Ajustado en Facebook
    $image = $isWhatsApp ? $directBannerImg : ($baseUrl . "/meta_image.php?url=" . urlencode($directBannerImg));

} elseif ($theProduct) {
    $productTitle = $theProduct['title'];
    $priceLocal = isset($theProduct['priceLocal']) ? $theProduct['priceLocal'] : ($theProduct['price'] ?? 0);
    // Formato con decimales: $143.500,00
    $fmtLocal = "$" . number_format($priceLocal, 2, ',', '.');
    
    // Formato solicitado: Nombre del Producto - Pixis Informatica | Precio especial: $143.500,00
    $title = $productTitle . " - Pixis Informatica | Precio especial: " . $fmtLocal;
    
    $rawDesc = isset($theProduct['desc']) ? trim($theProduct['desc']) : '';
    $rawDesc = preg_replace('/[\x{1F300}-\x{1FFFF}]/u', '', $rawDesc);
    $rawDesc = preg_replace('/\s+/', ' ', $rawDesc);
    $rawDesc = trim($rawDesc);
    $description = mb_strlen($rawDesc) > 150
        ? mb_substr($rawDesc, 0, 150) . '...'
        : ($rawDesc ?: 'Disponible en Pixis Informática');
    
    $rawImg = $theProduct['img'] ?? '';
    $firstImg = trim(explode(',', $rawImg)[0]);
    $directProductImage = makeImageUrl($firstImg, $baseUrl);

    // PRODUCTO: Original en WhatsApp, Ajustado en Facebook para evitar recortes
    $image = $isWhatsApp ? $directProductImage : ($baseUrl . "/meta_image.php?url=" . urlencode($directProductImage));

} else {
    $title = "Pixis Informática | Especialistas en Computación";
    $description = "Tienda de computación online en Santiago del Estero. Venta de accesorios gamer y hardware.";
    $defaultImg = $baseUrl . "/img/logo_pixis.png";
    $image = $isWhatsApp ? $defaultImg : ($baseUrl . "/meta_image.php?url=" . urlencode($defaultImg));
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
    
    <!-- Metadatos para Robots (Pro-SEO) -->
    <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="Pixis Informática">
    <meta property="og:locale" content="es_AR">
    <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($description); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($image); ?>">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars($title); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($redirectUrl); ?>">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($description); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($image); ?>">

    <!-- Redirección para humanos (por si acaso caen aquí) -->
    <script>
        window.location.replace("<?php echo $redirectUrl; ?>");
    </script>
</head>
<body>
    <p>Redirigiendo a Pixis Informática...</p>
</body>
</html>
