<?php error_reporting(E_ALL);
// physical path of your root
if( !defined( 'ABSPATH' ) )
	define( 'ABSPATH', str_replace( '\\', '/',  dirname( __FILE__ ) )  );
@ini_set('zlib.output_compression', 'Off');
if(isset($_GET["file"])) {
/*Build file compatibility for Vibe 3 files*/
$token = htmlspecialchars(base64_decode(base64_decode($_GET["file"])));
list($filename,$pathx) = explode('@@', $token);
if (strpos($pathx, 'storage') !== false) {
$pathx = str_replace("storage/","",$pathx);
}
$path = ABSPATH.'/storage/'.$pathx.'/';
$ext=strrchr($filename, ".");
$ignore = array('.mp4','.mp3',".jpg", ".jpeg", ".png", ".gif");
if(!in_array($ext,$ignore)) {
exit("Something is wrong");
}
$file = $path . $filename;
/* End compatibility */
} else if(isset($_GET["sk"])) {
/* PHPVibe 5 file calls */
$media = 'media'; /* Edit this if changed from admin */	
if (strpos('.',$_GET["sk"])!==false) {
exit("Something is wrong");
}
if(!isset($_GET["q"]) || (intval($_GET["q"]) < 1)) { $q = "";} else {$q = $_GET["q"]; }
$ext = '.mp4';
if(strlen($q) < 3) { 
$file = ABSPATH.'/storage/'.$media.'/'.$_GET["sk"].$ext;
} else {
$file = ABSPATH.'/storage/'.$media.'/'.$_GET["sk"].'-'.$q.$ext;	
}
/* End PHPVibe 5 File finder */
}

if (isset($file) && is_file($file)) {
    // Sanitize file extension and validate video formats
    $allowedMimeTypes = ['mp4', 'ogg', 'webm']; // Allowed file formats
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $ext = strtolower($ext); // Normalize extension to lowercase

    if (!in_array($ext, $allowedMimeTypes)) {
        die('{"jsonrpc" : "2.0", "error" : {"code": 400, "message": "Invalid video format."}, "id" : "id"}');
    }

    header("Content-Type: video/$ext");

    // Handle byte-range requests (for streaming)
    if (isset($_SERVER['HTTP_RANGE'])) {
        rangeDownload($file);
    } else {
        // Send headers for regular download
        $filesize = filesize($file);
        $filename = basename($file); // Extract safe filename
        $filename = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); // Sanitize filename

        header('Content-Length: ' . $filesize);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Accept-Ranges: bytes');

        // Open and read the file in chunks
        $handle = fopen($file, "rb");
        if ($handle === false) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 500, "message": "Error reading the file."}, "id" : "id"}');
        }

        while (!feof($handle)) {
            echo fread($handle, 8192); // Directly output the file chunk
            ob_flush(); // Ensure the output buffer is flushed
            flush();    // Send to the client
        }

        fclose($handle);
        exit;
    }
} else {
    die('{"jsonrpc" : "2.0", "error" : {"code": 404, "message": "File not found."}, "id" : "id"}');
}


function rangeDownload($file) {
    // Ensure the file exists and is a regular file
    if (!is_file($file)) {
        header('HTTP/1.1 404 Not Found');
        die('{"jsonrpc" : "2.0", "error" : {"code": 404, "message": "File not found."}, "id" : "id"}');
    }

    // Open the file safely
    $fp = @fopen($file, 'rb');
    if (!$fp) {
        header('HTTP/1.1 500 Internal Server Error');
        die('{"jsonrpc" : "2.0", "error" : {"code": 500, "message": "Unable to open file."}, "id" : "id"}');
    }

    $size = filesize($file); // File size
    $length = $size;         // Content length
    $start = 0;              // Start byte
    $end = $size - 1;        // End byte

    header("Accept-Ranges: bytes");

    if (isset($_SERVER['HTTP_RANGE'])) {
        $c_start = $start;
        $c_end = $end;

        // Extract the range string
        list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);

        // Ensure no multibyte ranges
        if (strpos($range, ',') !== false) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            fclose($fp);
            exit;
        }

        // Handle ranges
        if ($range[0] === '-') {
            // Last n bytes requested
            $c_start = $size - substr($range, 1);
        } else {
            $range = explode('-', $range);
            $c_start = (int)$range[0];
            $c_end = isset($range[1]) && is_numeric($range[1]) ? (int)$range[1] : $size - 1;
        }

        // Validate the range
        if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            fclose($fp);
            exit;
        }

        $start = $c_start;
        $end = $c_end;
        $length = $end - $start + 1;

        fseek($fp, $start);
        header('HTTP/1.1 206 Partial Content');
    }

    // Send headers
    header("Content-Range: bytes $start-$end/$size");
    header("Content-Length: $length");

    // Send the file content in chunks
    $buffer = 8192; // 8KB buffer size
    while (!feof($fp) && ($pos = ftell($fp)) <= $end) {
        if ($pos + $buffer > $end) {
            $buffer = $end - $pos + 1;
        }
        set_time_limit(0); // Prevent timeout for large files

        echo fread($fp, $buffer); // Output raw data (no sanitization required for binary data)
        flush(); // Send the data to the client
    }

    fclose($fp);
}
