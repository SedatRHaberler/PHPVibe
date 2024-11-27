<?php
error_reporting(E_ALL);

// Define the root path
if (!defined('ABSPATH')) {
    define('ABSPATH', str_replace('\\', '/', dirname(__FILE__)));
}

// Disable output compression for proper file handling
@ini_set('zlib.output_compression', 'Off');

if (isset($_GET["file"])) {
    // Decode and validate the file token
    $token = htmlspecialchars(base64_decode(base64_decode($_GET["file"])));
    list($filename, $pathx) = explode('@@', $token);

    if (strpos($pathx, 'storage') !== false) {
        $pathx = str_replace("storage/", "", $pathx);
    }

    $path = ABSPATH . '/storage/' . $pathx . '/';
    $ext = strrchr($filename, ".");

    // Allowed extensions
    $allowedExtensions = ['.mp4', '.mp3', '.jpg', '.jpeg', '.png', '.gif'];
    if (!in_array($ext, $allowedExtensions)) {
        exit("Something is wrong");
    }

    $file = $path . $filename;
} elseif (isset($_GET["sk"])) {
    // Handle PHPVibe 5 file calls
    $media = 'media'; // Change if the admin configuration is different

    // Validate the 'sk' parameter
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $_GET["sk"])) {
        die('{"jsonrpc" : "2.0", "error" : {"code": 400, "message": "Invalid input."}, "id" : "id"}');
    }

    // Ensure 'q' parameter is valid
    $q = isset($_GET["q"]) && intval($_GET["q"]) > 0 ? $_GET["q"] : "";

    // Define the file path
    $ext = '.mp4';
    $file = ABSPATH . '/storage/' . $media . '/' . $_GET["sk"] . $ext;

    // Sanitize filename to prevent XSS
    $filename = basename($file);
    $filename = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');

    // Check if file exists and is valid
    if (!is_file($file)) {
        die('{"jsonrpc" : "2.0", "error" : {"code": 404, "message": "File not found."}, "id" : "id"}');
    }

    $allowedMimeTypes = ['mp4', 'ogg', 'webm'];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedMimeTypes)) {
        die('{"jsonrpc" : "2.0", "error" : {"code": 400, "message": "Invalid video format."}, "id" : "id"}');
    }

    // Send appropriate headers
    header("Content-Type: video/$ext");
    header('Content-Disposition: inline; filename="' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . '"');
    header('Accept-Ranges: bytes');

    // Stream the file with byte-range support
    if (isset($_SERVER['HTTP_RANGE'])) {
        rangeDownload($file);
    } else {
        $filesize = filesize($file);
        header('Content-Length: ' . $filesize);

        $handle = fopen($file, "rb");
        if (!$handle) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 500, "message": "Error reading the file."}, "id" : "id"}');
        }

        while (!feof($handle)) {
            echo fread($handle, 8192);
            ob_flush();
            flush();
        }
        fclose($handle);
        exit;
    }
}

// Function to handle range-based downloads
function rangeDownload($file): void
{
    if (!is_file($file)) {
        header('HTTP/1.1 404 Not Found');
        die('{"jsonrpc" : "2.0", "error" : {"code": 404, "message": "File not found."}, "id" : "id"}');
    }

    // Get MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file);
    finfo_close($finfo);

    $allowedMimeTypes = ['video/mp4', 'video/webm', 'video/ogg'];
    if (!in_array($mimeType, $allowedMimeTypes)) {
        header('HTTP/1.1 403 Forbidden');
        die('{"jsonrpc" : "2.0", "error" : {"code": 403, "message": "Forbidden file type."}, "id" : "id"}');
    }

    $fp = @fopen($file, 'rb');
    if (!$fp) {
        header('HTTP/1.1 500 Internal Server Error');
        die('{"jsonrpc" : "2.0", "error" : {"code": 500, "message": "Unable to open file."}, "id" : "id"}');
    }

    $size = filesize($file);
    $length = $size;
    $start = 0;
    $end = $size - 1;

    header("Accept-Ranges: bytes");

    if (isset($_SERVER['HTTP_RANGE'])) {
        list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
        if (strpos($range, ',') !== false) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            fclose($fp);
            exit;
        }

        if ($range[0] === '-') {
            $start = $size - substr($range, 1);
        } else {
            $range = explode('-', $range);
            $start = (int)$range[0];
            $end = isset($range[1]) && is_numeric($range[1]) ? (int)$range[1] : $size - 1;
        }

        if ($start > $end || $start > $size - 1 || $end >= $size) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            fclose($fp);
            exit;
        }

        $length = $end - $start + 1;
        fseek($fp, $start);
        header('HTTP/1.1 206 Partial Content');
    }

    header("Content-Type: $mimeType");
    header("Content-Range: bytes $start-$end/$size");
    header("Content-Length: $length");
    header('Content-Disposition: attachment; filename="' . htmlspecialchars(basename($file), ENT_QUOTES, 'UTF-8') . '"');

    ob_clean();
    flush();

    $buffer = 8192;
    while (!feof($fp) && ($pos = ftell($fp)) <= $end) {
        if ($pos + $buffer > $end) {
            $buffer = $end - $pos + 1;
        }
        echo fread($fp, $buffer);
        flush();
    }

    fclose($fp);
}
