<?php
// ------------------------- EXTRAKTION (aus index2.php) -------------------------
function getInstagramImageData($postUrl) {
    preg_match('/(?:p|reel|tv)\/([A-Za-z0-9_-]+)/', $postUrl, $matches);
    if (!isset($matches[1])) return null;
    
    $code = $matches[1];
    $embedUrl = "https://www.instagram.com/p/{$code}/embed/captioned/";

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
    if (preg_match('/"display_url":"([^"]+)"/', $html, $jsonMatch)) {
        $imgUrl = stripcslashes($jsonMatch[1]);
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
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['urls']) && trim($_POST['urls']) !== '') {
    $urls = explode("\n", trim($_POST['urls']));
    $urls = array_map('trim', $urls);
    $urls = array_filter($urls);

    $images = [];
    foreach ($urls as $url) {
        $data = getInstagramImageData($url);
        if ($data && !empty($data['img'])) {
            $images[] = $data['img'];
        }
    }

    // Optionen
    $format       = $_POST['format'] ?? 'a4_landscape';
    $full_bleed   = isset($_POST['full_bleed']) && $_POST['full_bleed'] === 'on';
    $cover_title  = trim($_POST['cover_title'] ?? '');
    $cover_sub    = trim($_POST['cover_subtitle'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');
    $show_account = isset($_POST['show_account']) && $_POST['show_account'] === 'on';
    $back_text    = trim($_POST['back_text'] ?? '');

    // Logische Seiten des Hefts: Cover, Bilder, Rückseite und Leerseiten.
    $logicalPages = array_merge(['cover'], $images, ['backcover']);
    $pagesNeeded = (int) (ceil(count($logicalPages) / 4) * 4);
    $emptyPages = $pagesNeeded - count($logicalPages);
    for ($i = 0; $i < $emptyPages; $i++) {
        $logicalPages[] = 'empty';
    }

    // ------------------------- BOOKLET AUSGABE (leicht & schnell) -------------------------
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="referrer" content="no-referrer">
        <title>Instagram Booklet</title>
        <style>
            @page {
                <?php if ($format === 'a4_landscape'): ?>
                size: A4 landscape;
                <?php else: ?>
                size: A5 portrait;
                <?php endif; ?>
                margin: 0;
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { 
                background: #000; 
                font-family: 'Helvetica', Arial, sans-serif;
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 999;
            }
            .no-print button {
                background: #fff;
                color: #000;
                border: 2px solid #fff;
                padding: 10px 24px;
                font-weight: 700;
                font-size: 1rem;
                cursor: pointer;
                letter-spacing: 0.5px;
            }
            .no-print button:hover {
                background: #222;
                color: #fff;
            }

            #booklet {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 20px;
                gap: 20px;
            }

            /* ---------- PAGES ---------- */
            .page {
                background: #fff;
                overflow: hidden;
                display: flex;
                flex-shrink: 0;
                page-break-after: always;
                break-after: page;
                position: relative;
            }

            <?php if ($format === 'a4_landscape'): ?>
            .sheet {
                width: 297mm;
                height: 210mm;
                display: flex;
                flex-shrink: 0;
            }
            .sheet .page {
                width: 148.5mm;
                height: 100%;
            }
            .page.cover, .page.backcover {
                background: #000;
                color: #fff;
                text-align: center;
                padding: 30mm;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }
            .page img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }
            <?php else: ?>
            .page {
                width: 148mm;
                height: 210mm;
                background: #000;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }
            .page img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }
            .page.cover, .page.backcover {
                background: #000;
                color: #fff;
                text-align: center;
                padding: 20mm;
                justify-content: center;
            }
            <?php endif; ?>

            .page.cover h1 {
                font-size: 3rem;
                font-weight: 300;
                letter-spacing: 6px;
                margin-bottom: 0.2em;
            }
            .page.cover .account {
                font-size: 1.4rem;
                opacity: 0.7;
                letter-spacing: 3px;
                margin-bottom: 0.3em;
            }
            .page.cover .sub {
                font-size: 1.2rem;
                opacity: 0.5;
                letter-spacing: 2px;
            }
            .page.backcover p {
                font-size: 1.2rem;
                opacity: 0.7;
                line-height: 1.6;
                max-width: 80%;
                margin: 0 auto;
            }
            .page.empty {
                background: #fff;
            }

            .page-num {
                position: absolute;
                bottom: 15px;
                font-size: 0.7rem;
                color: rgba(255,255,255,0.3);
                letter-spacing: 1px;
            }
            .num-left { left: 20px; }
            .num-right { right: 20px; }
            .num-center { right: 20px; }

            @media print {
                body { background: none; }
                .no-print { display: none !important; }
                #booklet { padding: 0; gap: 0; }
                .page { margin: 0; box-shadow: none; }
                .sheet { page-break-after: always; break-after: page; }
                .sheet .page { page-break-after: auto; break-after: auto; }
                img { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            }
        </style>
    </head>
    <body>
        <div class="no-print">
            <button type="button" onclick="window.print()">🖨️ Drucken / PDF</button>
            <?php if ($format === 'a4_landscape'): ?>
                <div style="color:#fff;background:#111;padding:8px 12px;margin-top:8px;font-size:.75rem;text-align:right;">A4 · beidseitig · kurze Kante wenden</div>
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
            } else {
                foreach ($logicalPages as $index => $_page) {
                    $sheetOrders[] = [[$index]];
                }
            }

            foreach ($sheetOrders as $sheetOrder):
                foreach ($sheetOrder as $sideOrder):
                    if ($format === 'a4_landscape'): ?><div class="sheet"><?php endif;
                    foreach ($sideOrder as $pageIndex):
                        $pageType = $logicalPages[$pageIndex];
                        $pageClass = is_string($pageType) ? $pageType : '';
                        if ($pageType === 'cover'): ?>
                            <div class="page cover">
                                <?php if ($show_account && !empty($account_name)): ?>
                                    <div class="account">@<?php echo htmlspecialchars($account_name); ?></div>
                                <?php endif; ?>
                                <h1><?php echo htmlspecialchars($cover_title ?: 'INSTAGRAM'); ?></h1>
                                <?php if (!empty($cover_sub)): ?><div class="sub"><?php echo htmlspecialchars($cover_sub); ?></div><?php endif; ?>
                            </div>
                        <?php elseif ($pageType === 'backcover'): ?>
                            <div class="page backcover">
                                <?php if (!empty($back_text)): ?><p><?php echo nl2br(htmlspecialchars($back_text)); ?></p><?php else: ?><p style="opacity:0.2;">●</p><?php endif; ?>
                            </div>
                        <?php elseif ($pageClass === 'empty'): ?>
                            <div class="page empty"></div>
                        <?php else: ?>
                            <div class="page">
                                <img src="<?php echo htmlspecialchars($pageType); ?>" referrerpolicy="no-referrer" alt="">
                                <span class="page-num num-center"><?php echo $pageIndex; ?></span>
                            </div>
                        <?php endif;
                    endforeach;
                    if ($format === 'a4_landscape'): ?></div><?php endif;
                endforeach;
            endforeach;
            ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>

<!-- ------------------------- EINGABE-FORMULAR (schlank, schwarz/weiß) ------------------------- -->
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instagram Booklet</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #000;
            color: #fff;
            font-family: 'Helvetica', Arial, sans-serif;
            padding: 40px 20px;
            line-height: 1.5;
        }
        .card {
            max-width: 720px;
            margin: 0 auto;
            padding: 30px 20px;
            border: 1px solid #333;
        }
        h1 {
            font-size: 1.8rem;
            font-weight: 300;
            letter-spacing: 2px;
            margin-bottom: 0.2em;
        }
        p.desc {
            color: #aaa;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }
        label {
            display: block;
            margin-top: 20px;
            font-weight: 500;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }
        label span {
            font-weight: 400;
            color: #888;
            font-size: 0.8rem;
        }
        textarea, input[type="text"], select {
            width: 100%;
            padding: 10px 12px;
            background: #111;
            border: 1px solid #444;
            color: #fff;
            font-family: inherit;
            font-size: 0.95rem;
            box-sizing: border-box;
            margin-top: 4px;
            border-radius: 0;
        }
        textarea:focus, input[type="text"]:focus, select:focus {
            outline: none;
            border-color: #fff;
        }
        textarea {
            height: 140px;
            resize: vertical;
        }
        .row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 4px;
        }
        .row > div {
            flex: 1 1 200px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 8px;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #fff;
            cursor: pointer;
        }
        .checkbox-group label {
            margin: 0;
            font-weight: 400;
            cursor: pointer;
        }
        hr {
            margin: 30px 0 20px 0;
            border: none;
            border-top: 1px solid #333;
        }
        button {
            background: #fff;
            color: #000;
            border: 2px solid #fff;
            padding: 12px 20px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            margin-top: 30px;
            letter-spacing: 1px;
            border-radius: 0;
            transition: background 0.2s, color 0.2s;
        }
        button:hover {
            background: #222;
            color: #fff;
        }
        .hint {
            color: #666;
            font-size: 0.8rem;
            margin-top: 12px;
            text-align: center;
        }
        @media (max-width: 600px) {
            .card { padding: 20px 15px; }
            .row { flex-direction: column; gap: 0; }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>📘 Booklet</h1>
        <p class="desc">Instagram‑Bilder im Booklet‑Layout – wähle Format und Gestaltung.</p>

        <form method="POST">
            <label for="urls">Post‑URLs <span>(eine pro Zeile)</span></label>
            <textarea name="urls" id="urls" placeholder="https://www.instagram.com/p/CXXXXXXXXXX/"></textarea>

            <hr>

            <div class="row">
                <div>
                    <label for="format">Format</label>
                    <select name="format" id="format">
                        <option value="a4_landscape">A4 Landscape (2 A5 pro Seite)</option>
                        <option value="a5_portrait">A5 Portrait (1 Bild pro Seite)</option>
                    </select>
                </div>
                <div>
                    <label for="full_bleed">Bildrand</label>
                    <div class="checkbox-group" style="margin-top:10px;">
                        <input type="checkbox" name="full_bleed" id="full_bleed" checked>
                        <label for="full_bleed">Full‑Bleed (randlos)</label>
                    </div>
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="cover_title">Cover‑Titel</label>
                    <input type="text" name="cover_title" id="cover_title" placeholder="z. B. Meine Bilder">
                </div>
                <div>
                    <label for="cover_subtitle">Untertitel</label>
                    <input type="text" name="cover_subtitle" id="cover_subtitle" placeholder="z. B. 2024">
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="account_name">Account‑Name</label>
                    <input type="text" name="account_name" id="account_name" placeholder="dein_name">
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <div class="checkbox-group">
                        <input type="checkbox" name="show_account" id="show_account">
                        <label for="show_account">Auf Cover zeigen</label>
                    </div>
                </div>
            </div>

            <label for="back_text">Text auf der Rückseite</label>
            <textarea name="back_text" id="back_text" rows="3" placeholder="Dein individueller Text …" style="height:70px;"></textarea>

            <button type="submit">📄 Booklet generieren</button>
            <div class="hint">Das Booklet wird im gleichen Tab angezeigt – Seitenzahl wird auf ein Vielfaches von 4 gebracht.</div>
        </form>
    </div>
</body>
</html>
