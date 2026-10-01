<?php
// Funktion zum Extrahieren der echten Bild-URL aus dem Embed-HTML
function getInstagramImageData($postUrl) {
    preg_match('/(?:p|reel|tv)\/([A-Za-z0-9_-]+)/', $postUrl, $matches);
    if (!isset($matches[1])) return null;
    
    $code = $matches[1];
    $embedUrl = "https://www.instagram.com/p/{$code}/embed/captioned/";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $embedUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // Simuliere einen echten Browser Desktop User-Agent
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) return null;

    $imgUrl = '';
    $caption = '';

    // 1. Suche nach der hochauflösenden URL im JSON-Quelltext des Embeds
    if (preg_match('/"display_url":"([^"]+)"/', $html, $jsonMatch)) {
        $imgUrl = stripcslashes($jsonMatch[1]);
    } 
    // Fallback 2: Suche nach og:image Meta Tag
    elseif (preg_match('/<meta property="og:image" content="([^"]+)"/i', $html, $ogMatch)) {
        $imgUrl = html_entity_decode($ogMatch[1]);
    }
    // Fallback 3: EmbeddedMediaImage Klasse
    elseif (preg_match('/<img[^>]+class="[^"]*EmbeddedMediaImage[^"]*"[^>]+src="([^"]+)"/i', $html, $imgMatch)) {
        $imgUrl = html_entity_decode($imgMatch[1]);
    }

    // Filtere das Standard-Logo / Platzhalter raus
    if (strpos($imgUrl, 'instagram_logo') !== false || strpos($imgUrl, 'static') !== false) {
        $imgUrl = '';
    }

    // Extrahiere die Caption
    if (preg_match('/<div[^>]+class="[^"]*Caption[^"]*"[^>]*>(.*?)<\/div>/is', $html, $capMatch)) {
        $caption = strip_tags($capMatch[1]);
    }

    return [
        'img' => $imgUrl,
        'caption' => trim($caption)
    ];
}

// Formularverarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['urls'])) {
    $rawUrls = explode("\n", $_POST['urls']);
    $posts = [];

    foreach ($rawUrls as $url) {
        $url = trim($url);
        if (empty($url)) continue;

        $data = getInstagramImageData($url);
        if ($data && !empty($data['img'])) {
            $posts[] = $data;
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
      <meta charset="UTF-8">
      <meta name="referrer" content="no-referrer">
      <title>Instagram A5 Booklet Export</title>
      <style>
        @page { size: A5 portrait; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #181818; color: #fff; }
        
        .no-print { position: fixed; top: 15px; right: 15px; z-index: 999; }
        .no-print button { background: #0073aa; color: #fff; border: none; padding: 12px 20px; font-weight: bold; border-radius: 4px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }

        #booklet { display: flex; flex-direction: column; align-items: center; gap: 20px; padding: 20px; }

        .page { width: 148mm; height: 210mm; background: #fff; color: #111; padding: 12mm; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        .page.cover { background: #000; color: #fff; justify-content: center; align-items: center; text-align: center; }
        
        .media-box { width: 100%; height: 130mm; background: #f0f0f0; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        .media-box img { width: 100%; height: 100%; object-fit: cover; display: block; }
        
        .caption { font-size: 0.82rem; line-height: 1.4; height: 35mm; overflow: hidden; border-top: 1px solid #eee; padding-top: 4mm; color: #333; }
        .meta { font-size: 0.7rem; color: #888; display: flex; justify-content: space-between; border-top: 1px dashed #ccc; padding-top: 2mm; text-transform: uppercase; }

        @media print {
          body { background: none; }
          .no-print { display: none !important; }
          #booklet { display: block; padding: 0; }
          .page { margin: 0; box-shadow: none; page-break-after: always; break-after: page; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
      </style>
    </head>
    <body>
      <div class="no-print">
        <button onclick="window.print()">🖨️ Booklet Drucken / Als PDF</button>
      </div>

      <div id="booklet">
        <div class="page cover">
          <h1 style="letter-spacing: 2px;">INSTAGRAM</h1>
          <p style="color: #aaa; text-transform: uppercase;">A5 BOOKLET • <?php echo count($posts); ?> POSTS</p>
        </div>

        <?php if (empty($posts)): ?>
          <div class="page">
            <p style="color: red; text-align: center; margin-top: 50mm;">Es konnte kein Bild geladen werden. Instagram hat die Anfrage geblockt.</p>
          </div>
        <?php else: ?>
          <?php foreach ($posts as $index => $post): ?>
            <div class="page">
              <div class="media-box">
                <!-- referrerpolicy="no-referrer" verhindert Bild-Sperren von Instagram -->
                <img src="<?php echo htmlspecialchars($post['img']); ?>" referrerpolicy="no-referrer" crossorigin="anonymous" alt="Post Image">
              </div>
              <div class="caption">
                <?php echo !empty($post['caption']) ? htmlspecialchars($post['caption']) : '<i>Keine Bildunterschrift</i>'; ?>
              </div>
              <div class="meta">
                <span>Instagram Export</span>
                <span>Seite <?php echo $index + 1; ?> von <?php echo count($posts); ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Instagram PHP Booklet Generator</title>
  <style>
    body { font-family: -apple-system, sans-serif; background: #f4f4f7; padding: 40px 20px; margin: 0; }
    .card { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    h1 { font-size: 1.4rem; margin-top: 0; }
    textarea { width: 100%; height: 160px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-family: monospace; box-sizing: border-box; margin-bottom: 15px; }
    button { background: #000; color: #fff; border: none; padding: 12px 20px; font-weight: bold; border-radius: 4px; cursor: pointer; width: 100%; }
    button:hover { background: #333; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Instagram PHP Booklet Generator v2</h1>
    <p style="font-size:0.85rem; color:#666;">Füge öffentliche Instagram Post-URLs ein (eine pro Zeile). Das Skript liest jetzt die echten High-Res Bild-URLs aus.</p>
    <form method="POST">
      <textarea name="urls" placeholder="https://www.instagram.com/p/CXXXXXXXXXX/"></textarea>
      <button type="submit">Booklet Generieren</button>
    </form>
  </div>
</body>
</html>
 das war der einzig funktionierende code bis jetzt versuch rauszufinden warum und apply new design without influencing possible functional setup with this code