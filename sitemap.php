<?php
// Veritabanı bağlantısını içe aktar
include('load.php'); // PHPVibe'nin ana yapılandırma dosyası

header("Content-Type: application/xml; charset=utf-8");

// XML başlangıcı
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

// Ana sayfa URL'si
echo '<url>';
echo '<loc>' . site_url() . '</loc>';
echo '<lastmod>' . date('Y-m-d') . '</lastmod>';
echo '<changefreq>daily</changefreq>';
echo '<priority>1.0</priority>';
echo '</url>';

// Videoları sitemap'e ekle
$videos = $db->get_results("SELECT id, title, date FROM vibe_videos WHERE pub > 0");
if ($videos) {
    foreach ($videos as $video) {
        echo '<url>';
        echo '<loc>' . video_url($video->id, $video->title) . '</loc>';
        echo '<lastmod>' . date('Y-m-d', strtotime($video->date)) . '</lastmod>';
        echo '<changefreq>weekly</changefreq>';
        echo '<priority>0.8</priority>';
        echo '</url>';
    }
}

// Kategorileri sitemap'e ekle
$categories = $db->get_results("SELECT tagid, tag FROM vibe_tags");
if ($categories) {
    foreach ($categories as $category) {
        echo '<url>';
        echo '<loc>' . category_url($category->id, $category->name) . '</loc>';
        echo '<changefreq>weekly</changefreq>';
        echo '<priority>0.6</priority>';
        echo '</url>';
    }
}

echo '</urlset>';

