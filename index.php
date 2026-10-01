<?php
// ------------------------- EXTRAKTION (aus index2.php) -------------------------
function getInstagramImageData($postUrl) {
    preg_match('~^https?://(?:www\.)?instagram\.com/(?:[^/?#]+/)*p/([A-Za-z0-9_-]+)(?:[/?#]|$)~i', $postUrl, $matches);
    if (!isset($matches[1])) return null;
    
    $code = $matches[1];
    $query = [];
    parse_str((string) (parse_url($postUrl, PHP_URL_QUERY) ?? ''), $query);
    $imageIndex = isset($query['img_index']) && ctype_digit((string) $query['img_index'])
        ? max(1, (int) $query['img_index'])
        : 1;
    $embedUrl = "https://www.instagram.com/p/{$code}/embed/captioned/?img_index={$imageIndex}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $embedUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) return null;

    $imgUrl = '';
    if (preg_match_all('~\\\\?"display_url\\\\?"\s*:\s*\\\\?"([^"]+)\\\\?"~', $html, $jsonMatches)) {
        $displayUrls = [];
        foreach ($jsonMatches[1] as $rawUrl) {
            $decodedUrl = json_decode('"' . $rawUrl . '"');
            $displayUrls[] = str_replace(chr(92), '', is_string($decodedUrl) ? $decodedUrl : $rawUrl);
        }
        $candidateIndex = $imageIndex - 1;
        $imgUrl = $displayUrls[$candidateIndex] ?? $displayUrls[0];
    } elseif (preg_match('/<meta property="og:image" content="([^"]+)"/i', $html, $ogMatch)) {
        $imgUrl = html_entity_decode($ogMatch[1]);
    } elseif (preg_match('/<img[^>]+class="[^"]*EmbeddedMediaImage[^"]*"[^>]+src="([^"]+)"/i', $html, $imgMatch)) {
        $imgUrl = html_entity_decode($imgMatch[1]);
    }

    if (strpos($imgUrl, 'instagram_logo') !== false || strpos($imgUrl, 'static') !== false) {
        $imgUrl = '';
    }

    return [
        'img' => $imgUrl
    ];
}

// ------------------------- FORMULARVERARBEITUNG -------------------------
$formError = '';
$submittedUrls = [];
$urls = [];
$submittedCarouselIndexes = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['urls'])) {
    $submittedUrls = is_array($_POST['urls']) ? $_POST['urls'] : explode("\n", $_POST['urls']);
    $urls = array_values(array_filter(array_map('trim', $submittedUrls)));
    $submittedCarouselIndexes = isset($_POST['carousel_index']) && is_array($_POST['carousel_index'])
        ? array_map('trim', $_POST['carousel_index'])
        : [];

    if (count($urls) < 4) {
        $formError = 'Please add at least four regular Instagram posts.';
    } elseif (count($urls) > 20) {
        $formError = 'A maximum of 20 Instagram posts can be added.';
    } elseif (count(array_filter($urls, static function ($url) {
        return !preg_match('~^https?://(?:www\.)?instagram\.com/(?:[^/?#]+/)*p/[A-Za-z0-9_-]+(?:[/?#]|$)~i', $url);
    })) > 0) {
        $formError = 'Only regular Instagram post links are accepted. Reels and videos are not counted.';
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['urls']) && $formError === '') {
    $carouselIndexes = array_slice(array_pad($submittedCarouselIndexes, count($urls), ''), 0, count($urls));
    $urls = array_map(static function ($url, $carouselIndex) {
        if (ctype_digit($carouselIndex) && (int) $carouselIndex > 0 && preg_match('~^(https?://(?:www\.)?instagram\.com/(?:[^/?#]+/)*p/[A-Za-z0-9_-]+)\/?(?:\?.*)?$~i', $url, $matches)) {
                return $matches[1] . '/?img_index=' . (int) $carouselIndex;
            }
            return $url;
    }, $urls, $carouselIndexes);

    $images = [];
    foreach ($urls as $url) {
        $data = getInstagramImageData($url);
        if ($data && !empty($data['img'])) {
            $images[] = [
                'src' => $data['img'],
                'post_number' => count($images) + 1
            ];
        }
    }

    $format       = $_POST['format'] ?? 'a4_landscape';
    $cover_title  = trim($_POST['cover_title'] ?? '');
    $cover_name   = trim($_POST['cover_name'] ?? '');
    $cover_date   = trim($_POST['cover_date'] ?? '');
    $font         = $_POST['font'] ?? 'serif';
    $show_numbers = isset($_POST['show_numbers']) && $_POST['show_numbers'] === 'on';
    $cover_bold   = isset($_POST['cover_bold']) && $_POST['cover_bold'] === 'on';
    $placement    = $_POST['placement'] ?? 'contained';
    if (!in_array($placement, ['contained', 'full_bleed', 'dynamic'], true)) {
        $placement = 'contained';
    }

    $logicalPages = $format === 'a4_landscape'
        ? array_merge(['cover'], $images, ['backcover'])
        : array_merge(['cover'], $images);
    $pagesNeeded = $format === 'digital_portfolio'
        ? (int) (ceil(count($logicalPages) / 2) * 2)
        : (int) (ceil(count($logicalPages) / 4) * 4);
    $emptyPages = $pagesNeeded - count($logicalPages);
    for ($i = 0; $i < $emptyPages; $i++) {
        $logicalPages[] = 'empty';
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="referrer" content="no-referrer">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Instagram Posts Portfolio</title>
        <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
    </head>
    <body class="preview <?php echo $font === 'sans' ? 'font-sans' : 'font-serif'; ?>">
        <div class="no-print">
            <button type="button" onclick="returnToEditor()">Edit portfolio</button>
            <?php if ($format === 'digital_portfolio'): ?>
                <button type="button" onclick="downloadPdf()">Download PDF</button>
            <?php else: ?>
                <button type="button" onclick="downloadPdf()">Print / PDF</button>
            <?php endif; ?>
            <?php if ($format === 'a4_landscape'): ?>
                <p class="print-help">A4 printing: choose double-sided printing and turn the paper on the short edge.</p>
            <?php endif; ?>
        </div>

        <div id="booklet">
            <?php
            // Für A4 werden die Seiten pro Druckbogen ausgeschossen:
            // Vorderseite: letzte Seite | erste Seite; Rückseite: zweite Seite | vorletzte Seite.
            $sheetOrders = [];
            if ($format === 'a4_landscape') {
                for ($sheet = 0; $sheet < $pagesNeeded / 4; $sheet++) {
                    $sheetOrders[] = [
                        [$pagesNeeded - 1 - (2 * $sheet), 2 * $sheet],
                        [1 + (2 * $sheet), $pagesNeeded - 2 - (2 * $sheet)]
                    ];
                }
            } elseif ($format === 'digital_portfolio') {
                for ($spread = 0; $spread < $pagesNeeded / 2; $spread++) {
                    $sheetOrders[] = [[$spread * 2, ($spread * 2) + 1]];
                }
            } else {
                foreach ($logicalPages as $index => $_page) {
                    $sheetOrders[] = [[$index]];
                }
            }

            foreach ($sheetOrders as $sheetOrder):
                foreach ($sheetOrder as $sideOrder):
                    if ($format === 'a4_landscape' || $format === 'digital_portfolio'): ?><div class="sheet"><?php endif;
                    foreach ($sideOrder as $pageIndex):
                        $pageType = $logicalPages[$pageIndex];
                        $pageClass = is_string($pageType) ? $pageType : '';
                        if ($pageType === 'cover'): ?>
                            <div class="page cover">
                                <h1 class="<?php echo $cover_bold ? 'cover-title-bold' : ''; ?>"><?php echo htmlspecialchars($cover_title ?: 'INSTAGRAM POSTS PORTFOLIO'); ?></h1>
                                <div class="cover-meta">
                                    <?php if (!empty($cover_name)): ?><div><?php echo htmlspecialchars($cover_name); ?></div><?php endif; ?>
                                    <?php if (!empty($cover_date)): ?><div><?php echo htmlspecialchars($cover_date); ?></div><?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($pageClass === 'empty'): ?>
                            <div class="page empty"></div>
                        <?php elseif ($pageType === 'backcover'): ?>
                            <div class="page backcover"></div>
                        <?php else: ?>
                            <?php
                            $imageClass = 'post-image post-image-' . $placement;
                            $imageStyle = '';
                            if ($placement === 'dynamic') {
                                $dynamicLayouts = [
                                    [20, 24, 8, 10],
                                    [34, 28, 55, 8],
                                    [28, 42, 12, 48],
                                    [46, 24, 42, 34],
                                    [24, 38, 68, 52],
                                    [38, 32, 28, 18]
                                ];
                                [$width, $height, $left, $top] = $dynamicLayouts[($pageIndex - 1) % count($dynamicLayouts)];
                                $imageStyle = "width:{$width}%;height:{$height}%;left:{$left}%;top:{$top}%;";
                            }
                            ?>
                            <div class="page post-page placement-<?php echo $placement; ?>">
                                <img class="<?php echo $imageClass; ?>" style="<?php echo $imageStyle; ?>" src="<?php echo htmlspecialchars($pageType['src']); ?>" referrerpolicy="no-referrer" alt="">
                                <?php if ($show_numbers): ?><span class="page-num"><?php echo $pageIndex; ?></span><?php endif; ?>
                            </div>
                        <?php endif;
                    endforeach;
                    if ($format === 'a4_landscape' || $format === 'digital_portfolio'): ?></div><?php endif;
                endforeach;
            endforeach;
            ?>
        </div>
        <script src="script.js?v=<?php echo filemtime(__DIR__ . '/script.js'); ?>"></script>
    </body>
    </html>
    <?php
    exit;
}
?>

<!-- ------------------------- EINGABE-FORMULAR (schlank, schwarz/weiß) ------------------------- -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instagram Posts Portfolio</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
</head>
<body class="editor-page">
    <a class="site-link" href="https://fbeing.online">fbeing.online</a>
    <div class="card">
        <div class="title-row">
            <h1>Instagram Posts Portfolio</h1>
            <button type="button" id="info-button" class="info-button" aria-expanded="false" aria-controls="info-panel">i</button>
        </div>
        <div id="info-panel" class="info-panel" hidden>
            <p>At least 4 posts are required for a correctly formatted brochure. More than 4 posts are welcome; white pages are added when needed so the total remains divisible by 4.</p>
            <p>Add at least 4 and up to 20 regular Instagram posts.</p>
            <p>Enter a carousel image index only when a post contains multiple images.</p>
            <p>Choose the format, typeface, placement, page numbers, and cover title options, then create the portfolio.</p>
        </div>
        <p class="editor-intro">Add Instagram posts to your portfolio and output as a printable brochure or landscape portfolio PDF.</p>

        <form method="POST">
            <div id="post-fields">
                <?php for ($postIndex = 0; $postIndex < 4; $postIndex++): ?>
                    <div class="post-item" data-post-item>
                        <div class="post-item-bar">
                            <span>Instagram post</span>
                        </div>
                        <input type="text" name="urls[]" id="post-url-<?php echo $postIndex + 1; ?>" value="<?php echo htmlspecialchars($submittedUrls[$postIndex] ?? ''); ?>" placeholder="https://www.instagram.com/p/CXXXXXXXXXX/" required>
                        <label class="carousel-option">
                            Carousel image index
                            <span class="carousel-stepper">
                                <button type="button" class="carousel-step" data-step="1" aria-label="Increase carousel image index">↑</button>
                                <input type="number" name="carousel_index[]" class="carousel-index" min="1" step="1" value="<?php echo htmlspecialchars($submittedCarouselIndexes[$postIndex] ?? ''); ?>">
                                <button type="button" class="carousel-step" data-step="-1" aria-label="Decrease carousel image index">↓</button>
                            </span>
                        </label>
                        <button type="button" class="remove-post" aria-label="Remove post" hidden>Remove</button>
                    </div>
                <?php endfor; ?>
            </div>
            <button type="button" id="add-post">Add post</button>

            <hr>

            <div class="row">
                <div>
                    <label for="format">Format</label>
                    <select name="format" id="format">
                        <option value="a4_landscape">A4 Brochure</option>
                        <option value="digital_portfolio">Digital Portfolio</option>
                    </select>
                </div>
                <div>
                    <label for="font">Typeface</label>
                    <select name="font" id="font">
                        <option value="serif">Serif</option>
                        <option value="sans">Sans serif</option>
                    </select>
                </div>
                <div>
                    <label for="placement">Post placement</label>
                    <select name="placement" id="placement">
                        <option value="contained">Contained 90%</option>
                        <option value="full_bleed">Full bleed</option>
                        <option value="dynamic">Dynamic placement</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="cover_title">Cover title</label>
                    <input type="text" name="cover_title" id="cover_title" placeholder="Instagram Posts Portfolio">
                </div>
                <div>
                    <label for="cover_name">Name</label>
                    <input type="text" name="cover_name" id="cover_name">
                </div>
                <div>
                    <label for="cover_date">Date</label>
                    <input type="text" name="cover_date" id="cover_date">
                </div>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" name="show_numbers" id="show_numbers">
                <label for="show_numbers">Page numbers</label>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" name="cover_bold" id="cover_bold">
                <label for="cover_bold">Bold cover title</label>
            </div>

            <button type="submit">Create portfolio</button>
            <?php if ($formError !== ''): ?><p class="form-error"><?php echo htmlspecialchars($formError); ?></p><?php endif; ?>
        </form>
    </div>
    <script src="script.js?v=<?php echo filemtime(__DIR__ . '/script.js'); ?>"></script>
</body>
</html>
