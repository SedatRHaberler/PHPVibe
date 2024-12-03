<?php
error_reporting(0);

// Root
if (!defined('ABSPATH')) {
	$abs = str_replace(array('\\', '/app/minify'), array('/', ''), dirname(__FILE__));
	define('ABSPATH', $abs);
}

$full_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$uri_parts = explode('css.php', $full_link, 2);
$site_link = $uri_parts[0];
$site_link = str_replace('app/minify/', '', $site_link);

$themefold = isset($_GET['t']) ? $_GET['t'] : "main";
$themefold = preg_replace('/(\.+\/)/', '', $themefold);

$txt = '';

if (isset($_GET['sign'])) {
	$styles = '';
	if (isset($_GET['f'])) {
		$styles = preg_replace('/(\.+\/)/', '', $_GET['f']);
	}
	$sf = preg_replace('/\W+/', '-', $styles);
	$cachedfile = ABSPATH . '/storage/minify/' . date('w-m-y') . '-' . $sf . '.css';

	// Dinamik dosya değişim kontrolü
	$cache_valid = file_exists($cachedfile);
	if ($cache_valid) {
		// Kontrol: Cache'deki dosya, kullanılan CSS'lerden daha eski mi?
		$newstyles = explode('_', $styles);
		foreach ($newstyles as $css) {
			$cssfile = ABSPATH . '/themes/' . $themefold . '/styles/' . trim($css) . '.css';
			if (file_exists($cssfile) && filemtime($cssfile) > filemtime($cachedfile)) {
				$cache_valid = false;
				break;
			}
		}
	}

	if ($cache_valid) {
		// Cache dosyasını sun
		header('Cache-Control: public');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
		header("Content-type: text/css");
		header('Pragma: public');
		readfile($cachedfile);
	} else {
		$defaults = array('phpvibe', 'bootstrap.min', 'playerads', 'materialicons', 'roboto');
		$newstyles = !empty($styles) ? explode('_', $styles) : array();
		$allstyles = array_unique(array_merge($newstyles, $defaults));
		$txt = '';

		foreach ($allstyles as $css) {
			$cssfile = ABSPATH . '/themes/' . $themefold . '/styles/' . trim($css) . '.css';
			if (file_exists($cssfile)) {
				$txt .= file_get_contents($cssfile);
			}
		}

		// Fix font urls
		$txt = str_replace('fonts/', $site_link . 'themes/' . $themefold . '/styles/fonts/', $txt);
		$txt = str_replace(': ', ':', $txt);
		$txt = preg_replace("/\s{2,}/", " ", $txt);
		$txt = str_replace("\n", "", $txt);
		$txt = str_replace(', ', ",", $txt);
		$txt = preg_replace('/(\/\*[\w\'\s\r\n\*\+\,\"\-\.]*\*\/)/', '', $txt);
		$txt = str_replace('and(', 'and (', $txt);

		if (!empty(trim($txt))) {
			$f = fopen($cachedfile, 'w');
			fwrite($f, $txt);
			fclose($f);
		}

		header('Cache-Control: public');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
		header("Content-type: text/css");
		echo($txt);
	}
}
?>
