<?php
function checkAuth() {
    $headers = getallheaders(); // Gelen tüm başlıkları al
    // Token başlıkta yoksa yetkisiz
    if (!isset($headers['Authorization'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Missing token.']);
        exit;
    }

    // Token geçerli değilse yetkisiz
    if ($headers['Authorization'] !== 'Bearer ' . get_option('login')) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid token.']);
        exit;
    }
}

global $db; // Veritabanı bağlantısı
// Token kontrolü
checkAuth();

// Gelen POST verilerini kontrol et
if (!_post('type') || !_post('file')) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid request. Missing required fields: type or file.']);
    exit;
}

// Verileri al ve varsayılan değerler ata
$type = intval(_post('type'));
$file = _post('file');
$title = _post('title') ? _post('title') : 'Untitled';
$description = _post('description') ? _post('description') : '';
$tags = _post('tags') ? explode(',', _post('tags')) : [];
$categ = _post('categ') ? intval(_post('categ')) : 0;
$duration = _post('duration') ? intval(_post('duration')) : 0;
$nsfw = _post('nsfw') ? intval(_post('nsfw')) : 0;
$pub = _post('pub') ? intval(_post('pub')) : 1;
$stayprivate = _post('priv') ? intval(_post('priv')) : 0;
$featured = _post('featured') ? intval(_post('featured')) : 0;
$thumb = _post('thumb') ? _post('thumb'): 'storage/uploads/noimage.png'; // Varsayılan küçük resim

// Dosya yüklenmişse işle
if (isset($_FILES['play-img']) && !empty($_FILES['play-img']['name'])) {
    $formInputName = 'play-img';
    $savePath = ABSPATH . '/storage/' . get_option('mediafolder') . '/thumbs';
    $saveName = md5(time()) . '-' . user_id();
    $allowedExtArray = ['.jpg', '.png', '.gif'];

    $uploader = new FileUploader($formInputName, $savePath, $saveName, $allowedExtArray);

    if ($uploader->getIsSuccessful()) {
        $thumb = $uploader->getTargetPath();
        $thumb = str_replace(ABSPATH . '/', '', $thumb); // Küçük resim yolunu düzenle
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Failed to upload thumbnail.']);
        exit;
    }
} elseif (_post('remote-img')) {
    $thumb = _post('remote-img'); // Uzaktan küçük resim bağlantısı
}

// Videoyu veritabanına ekle
$query = "
        INSERT INTO " . DB_PREFIX . "videos 
        (`stayprivate`, `pub`, `source`, `remote`, `user_id`, `date`, `thumb`, `title`, `duration`, `liked`, `category`, `nsfw`, `views`, `featured`) 
        VALUES 
        ('{$stayprivate}', '{$pub}', '{$file}', 'yes', '" . user_id() . "', NOW(), '{$thumb}', '{$title}', '{$duration}', '0', '{$categ}', '{$nsfw}', '0', '{$featured}')
    ";
debug_to_console($db->query($query));
if ($db->query($query)) {
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
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Failed to add video to the database.']);
}



