<?php
const AUTH_TOKEN = '74598cd427d0057145974e2a11b5c42c9218d3950199523afe08aec86781990b'; // Sabit bir token tanımlayın

function checkAuth() {
    $headers = getallheaders(); // Gelen tüm başlıkları al

    // Token başlıkta yoksa yetkisiz
    if (!isset($headers['Authorization'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Missing token.']);
        exit;
    }

    // Token geçerli değilse yetkisiz
    if ($headers['Authorization'] !== 'Bearer ' . AUTH_TOKEN) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid token.']);
        exit;
    }
}
function apiAddVideo() {
    global $db; // Veritabanı bağlantısı
    file_put_contents('log.txt', 'apiAddVideo çalıştı: ' . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);
    echo json_encode(['success' => true, 'message' => 'Fonksiyon tetiklendi.']);
    // Token kontrolü
    checkAuth();
    // POST verilerini kontrol et
    if (!_post('type') || !_post('file')) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Invalid request. Missing required fields.']);
        exit;
    }

    // Videonun varsayılan bilgileri
    $type = intval(_post('type'));
    $file = _post('file');
    $title = _post('title') ? _post('title') : 'Untitled';
    $description = _post('description') ? _post('description') : '';
    $tags = _post('tags') ? explode(',', _post('tags')) : [];
    $categ = _post('categ') ? intval(_post('categ')) : 0;
    $duration = _post('duration') ? intval(_post('duration')) : 0;
    $nsfw = _post('nsfw') ? intval(_post('nsfw')) : 0;
    $thumb = 'storage/uploads/noimage.png'; // Varsayılan küçük resim
    $pub = _post('pub') ? intval(_post('pub')) : 1;
    $stayprivate = _post('priv') ? intval(_post('priv')) : 0;
    $featured = _post('featured') ? intval(_post('featured')) : 0;

    // Eğer dosya yüklenmişse
    if (isset($_FILES['play-img']) && !empty($_FILES['play-img']['name'])) {
        $formInputName = 'play-img';
        $savePath = ABSPATH . '/storage/' . get_option('mediafolder') . '/thumbs';
        $saveName = md5(time()) . '-' . user_id();
        $allowedExtArray = ['.jpg', '.png', '.gif'];
        $uploader = new FileUploader($formInputName, $savePath, $saveName, $allowedExtArray);

        if ($uploader->getIsSuccessful()) {
            $thumb = $uploader->getTargetPath();
            $thumb = str_replace(ABSPATH . '/', '', $thumb); // Küçük resim yolunu düzenle
        }
    } elseif (_post('remote-img')) {
        $thumb = _post('remote-img'); // Uzaktan küçük resim bağlantısı
    }

    // Veritabanına video ekleme
    $query = "
        INSERT INTO " . DB_PREFIX . "videos 
        (`stayprivate`, `pub`, `source`, `remote`, `user_id`, `date`, `thumb`, `title`, `duration`, `liked`, `category`, `nsfw`, `views`, `featured`) 
        VALUES 
        ('{$stayprivate}', '{$pub}', '{$file}', 'yes', '" . user_id() . "', NOW(), '{$thumb}', '{$title}', '{$duration}', '0', '{$categ}', '{$nsfw}', '0', '{$featured}')
    ";
    $db->query($query);

    // Son eklenen video ID'sini al
    $videoId = $db->insert_id;

    // Etiketleri kaydet
    foreach ($tags as $tag) {
        save_tag(trim($tag), $videoId);
    }

    // Açıklamayı kaydet
    save_description($videoId, $description);

    // Başarı yanıtı döndür
    http_response_code(201); // Created
    echo json_encode([
        'success' => true,
        'message' => 'Video added successfully.',
        'video_id' => $videoId,
        'title' => $title,
        'thumbnail' => site_url() . '/' . $thumb,
    ]);
    exit;
}
