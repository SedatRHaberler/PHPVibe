<?php
global $chunk, $chunks;
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
@set_time_limit(15 * 60);

// Uncomment this one to fake upload time
// usleep(5000);
require_once("../../load.php");
if(!is_user()) {
die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "'._lang("Login first!").'"}, "id" : "id"}');
}
/*
$abc = '<code>Serialized Post </code> '.maybe_serialize($_POST);
$abc .= '<code><br>Serialized GET </code> '.maybe_serialize($_GET);
$abc .= '<code><br>Serialized Request </code> '.maybe_serialize($_REQUEST).'<br>';
vibe_log($abc);
*/
//Save to db
//function
function vinsert($file) {
global $db, $token;
//Just one insert
if(!isset($_SESSION['upl-'.$token])){
$ext = substr($file, strrpos($file, '.') + 1);
$db->query("INSERT INTO ".DB_PREFIX."videos_tmp (`uid`, `name`, `path`, `ext`) VALUES ('".user_id()."', '".$token."', '".$file."', '".$ext."')");
$ext = strtolower($ext);
// Prepare conversion
$db->query("INSERT INTO ".DB_PREFIX."videos (`date`,`pub`,`token`, `user_id`, `tmp_source`, `thumb`) VALUES (now(), '0','".$token."', '".user_id()."', '".$file."','storage/uploads/processing.png')");
//Add action
$doit = $db->get_row("SELECT id from ".DB_PREFIX."videos where token = '".$token."' order by id DESC limit 0,1");
if($doit) { add_activity('4', $doit->id); }
}
//Prevent multiple
//inserts when chucking
$_SESSION['upl-'.$token] = 1;
}
// Settings
$targetDir = ABSPATH.'/storage/'.get_option('tmp-folder','rawmedia')."/";
$token = '';
if(isset($_REQUEST['token']) && not_empty($_REQUEST['token'])) {
$token = toDb($_REQUEST['token']);
} else {
if(isset($_REQUEST['pvo']) && not_empty($_REQUEST['pvo'])) {
$token = toDb($_REQUEST['pvo']);
}	
}

if (is_empty($token)) {
    $token = isset($_REQUEST['token']) ? htmlspecialchars($_REQUEST['token'], ENT_QUOTES, 'UTF-8') : '';
    $pvo = isset($_REQUEST['pvo']) ? htmlspecialchars($_REQUEST['pvo'], ENT_QUOTES, 'UTF-8') : '';

    die('{"jsonrpc" : "2.0", "error" : {"code": 107, "message": "' . _lang("Oups! Something went wrong. <br> Token was empty. [") . $token . " / " . $pvo . "] " . _lang("Please refresh the page and try again") . '"}, "id" : "id"}');
}

//$targetDir = 'uploads';
$cleanupTargetDir = true; // Remove old files
$maxFileAge = 5 * 3600; // Temp file age in seconds


// Create target dir
if (!file_exists($targetDir)) {
	@mkdir($targetDir);
}

// Get a safe file name
$fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : (isset($_REQUEST["fnm"]) ? $_REQUEST["fnm"] : ($_FILES["file"]["name"] ?? $token));

// Sanitize and validate file name
$fileName = basename($fileName); // Strip any path information
if (!$fileName || strpos($fileName, '..') !== false) {
    die('{"jsonrpc" : "2.0", "error" : {"code": 107, "message": "Invalid file name."}, "id" : "id"}');
}

// Check for insecure file types
if (is_insecure_file(strtolower($fileName))) {
    die('{"jsonrpc" : "2.0", "error" : {"code": 107, "message": "Insecure file type."}, "id" : "id"}');
}

// Determine file paths
$ext = pathinfo($fileName, PATHINFO_EXTENSION);
$targetPath = $targetDir . DIRECTORY_SEPARATOR . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '.' . $ext;
$filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

// Clean up old temp files
if ($cleanupTargetDir && is_dir($targetDir)) {
    foreach (glob($targetDir . DIRECTORY_SEPARATOR . '*.part') as $tmpfilePath) {
        if ($tmpfilePath !== "{$filePath}.part" && filemtime($tmpfilePath) < time() - $maxFileAge) {
            @unlink($tmpfilePath);
        }
    }
}

// Open temp file safely
$out = @fopen("{$filePath}.part", $chunks ? "ab" : "wb");
if (!$out) {
    die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
}


/**
 * @param $out
 * @return void
 */
function extracted($out): void
{
    if (!empty($_FILES)) {
        if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
        }

        // Read binary input stream and append it to temp file
        if (!$in = @fopen($_FILES["file"]["tmp_name"], "rb")) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
        }
    } else {
        if (!$in = @fopen("php://input", "rb")) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
        }
    }

    while ($buff = fread($in, 4096)) {
        fwrite($out, $buff);
    }

    @fclose($out);
    @fclose($in);
}

extracted($out);

// Check if file upload is complete
if (!$chunks || $chunk == $chunks - 1) {
    $realFilePath = realpath("{$filePath}.part");
    $realTargetPath = realpath($targetDir) . DIRECTORY_SEPARATOR . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '.' . $ext;

    // Validate file paths to prevent Path Traversal
    if ($realFilePath === false || $realTargetPath === false || strpos($realFilePath, realpath($targetDir)) !== 0 || strpos($realTargetPath, realpath($targetDir)) !== 0) {
        die('{"jsonrpc" : "2.0", "error" : {"code": 108, "message": "Invalid file path."}, "id" : "id"}');
    }

    // Rename safely
    if (!rename($realFilePath, $realTargetPath)) {
        die('{"jsonrpc" : "2.0", "error" : {"code": 109, "message": "Failed to finalize file upload."}, "id" : "id"}');
    }
}

// Insert into database
vinsert($token . '.' . $ext);

// Return Success JSON-RPC response
die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');
