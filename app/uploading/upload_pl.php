<?php
/**
 * upload.php (Customised for PHPVibe.com)
 *
 * Copyright 2013, Moxiecode Systems AB
 * Released under GPL License.
 *
 * License: http://www.plupload.com/license
 * Contributing: http://www.plupload.com/contributing
 */


// Make sure file is not cached (as it happens for example on iOS devices)
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/* 
// Support CORS
header("Access-Control-Allow-Origin: *");
// other CORS headers if any...
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
	exit; // finish preflight CORS requests here
}
*/

// 5 minutes execution time
@set_time_limit(5 * 60);

// Uncomment this one to fake upload time
// usleep(5000);
require_once("../../load.php");
if(!is_user()) {
die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "'._lang("Login first!").'"}, "id" : "id"}');
}
//Save to db
function vinsert($file) {
    global $db, $token;

    // Just one insert
    if (!isset($_SESSION['upl-' . $token])) {
        $ext = substr($file, strrpos($file, '.') + 1);
        $ext = strtolower($ext); // Normalize the file extension

        // Use prepared statement for the first insert
        $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "videos_tmp (`uid`, `name`, `path`, `ext`) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", user_id(), $token, $file, $ext);
        $stmt->execute();

        // Get source for the video file (ensure it's properly sanitized inside the function)
        $source = get_file($file, $token);

        // Use prepared statement for the second insert
        $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "videos (`date`, `pub`, `token`, `user_id`, `source`) VALUES (now(), '0', ?, ?, ?)");
        $stmt->bind_param("sis", $token, user_id(), $source);
        $stmt->execute();

        // Add activity
        $doit = $db->get_row("SELECT id FROM " . DB_PREFIX . "videos WHERE token = ? ORDER BY id DESC LIMIT 1", [$token]);
        if ($doit) {
            add_activity('4', $doit->id);
        }
    }

    // Prevent multiple inserts when chunking
    $_SESSION['upl-' . $token] = 1;
}

// Settings
$targetDir = ABSPATH.'/storage/'.get_option('mediafolder')."/";
$token = toDb($_REQUEST['token']);
//$targetDir = 'uploads';
$cleanupTargetDir = true; // Remove old files
$maxFileAge = 5 * 3600; // Temp file age in seconds


// Create target dir
if (!file_exists($targetDir)) {
	@mkdir($targetDir);
}

// Get and sanitize the file name
if (isset($_REQUEST["name"])) {
    $fileName = basename($_REQUEST["name"]); // Remove any path information
} elseif (!empty($_FILES)) {
    $fileName = basename($_FILES["file"]["name"]); // Remove any path information
} else {
    $fileName = uniqid("file_");
}

// Check for insecure file types
if (is_insecure_file(strtolower($fileName))) {
    die('{"jsonrpc" : "2.0", "error" : {"code": 107, "message": "Insecure file detected!"}, "id" : "id"}');
}

// Define file paths securely
$targetDir = realpath($targetDir);
if ($targetDir === false) {
    die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Invalid target directory."}, "id" : "id"}');
}

$filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
$ext = pathinfo($fileName, PATHINFO_EXTENSION);
$targetPath = $targetDir . DIRECTORY_SEPARATOR . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '.' . $ext;

// Ensure paths stay within the allowed directory
if (strpos(realpath($filePath), $targetDir) !== 0 || strpos(realpath($targetPath), $targetDir) !== 0) {
    die('{"jsonrpc" : "2.0", "error" : {"code": 108, "message": "Invalid file path."}, "id" : "id"}');
}

// Remove old temp files securely
if ($cleanupTargetDir && is_dir($targetDir)) {
    foreach (glob($targetDir . DIRECTORY_SEPARATOR . '*.part') as $tmpfilePath) {
        if ($tmpfilePath !== "{$filePath}.part" && filemtime($tmpfilePath) < time() - $maxFileAge) {
            @unlink($tmpfilePath);
        }
    }
}

// Open temp file
if (!$out = @fopen("{$filePath}.part", $chunks ? "ab" : "wb")) {
    die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
}

extracted($out);

// Check if file upload is complete
if (!$chunks || $chunk == $chunks - 1) {
    if (!rename("{$filePath}.part", $filePath)) {
        die('{"jsonrpc" : "2.0", "error" : {"code": 109, "message": "Failed to finalize file upload."}, "id" : "id"}');
    }
}

// Safely rename and insert
if (!rename($filePath, $targetPath)) {
    die('{"jsonrpc" : "2.0", "error" : {"code": 110, "message": "Failed to move uploaded file."}, "id" : "id"}');
}
vinsert($token . '.' . $ext);

// Return success response
die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');
