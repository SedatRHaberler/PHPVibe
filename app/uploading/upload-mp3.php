<?php //error_reporting(0);
require_once("../../load.php");
/**
 * @param array $headers
 * @param array $allowedExts
 * @param int $maxFileSize
 * @param string $target_path
 * @param string $new_name
 * @return void
 */
function filename(array $headers, array $allowedExts, int $maxFileSize, string $target_path, string $new_name): void
{
	$fileName = urldecode($headers['X-File-Name']);
	if (is_insecure_file($fileName)) {
		echo '{"success":false, "details": "Insecure file detected."}';
		die();
	}
	$fileSize = $headers['X-File-Size'];
	$ext = substr($fileName, strrpos($fileName, '.') + 1);
	if (in_array($ext, $allowedExts) or empty($allowedExts)) {
		if ($fileSize < $maxFileSize or empty($maxFileSize)) {
			$input = fopen("php://input", 'r');
			$output = fopen($target_path . $new_name . '.' . $ext, 'a');
			if ($output != false) {
				while (!feof($input)) {
					$buffer = fread($input, 4096);
					fwrite($output, $buffer);
				}
				fclose($output);
				$truefile = $target_path . $new_name . '.' . $ext;
				$insertit = $new_name . '.' . $ext;

				vinsert($insertit);
				echo '{"success":true, "file": "' . $insertit . '"}';

			} else echo('{"success":false, "details": "Can\'t create a file handler."}');
			fclose($input);
		} else {
			echo('{"success":false, "details": "Maximum file size: ' . ByteSize($maxFileSize) . '."}');
		};
	} else {
		echo('{"success":false, "details": "File type ' . $ext . ' not allowed."}');
	}
}

if(is_user()) {
$target_path = ABSPATH.'/storage/'.get_option('tmp-folder','rawmedia')."/";
$final_path = ABSPATH.'/storage/'.get_option('mediafolder', 'media')."/";
$ip = ABSPATH.'/storage/'.get_option('mediafolder', 'media').'/thumbs/';	;

$allowedExts = array();
$maxFileSize = 0;
$token = toDb($_GET['token']);
$new_name = $token;


function ByteSize($bytes) {
	return byte($bytes);
}

function getHeaders() {
	return headers();
}  

function vinsert($file) {
global $db, $token;
$ext = substr($file, strrpos($file, '.') + 1);
$db->query("INSERT INTO ".DB_PREFIX."videos_tmp (`uid`, `name`, `path`, `ext`) VALUES ('".user_id()."', '".$token."', '".$file."', '".$ext."')");
//Insert
global $target_path,  $final_path, $ip;
//rename($target_path.$file, $final_path.$file);
rename(ABSPATH.'/storage/'.get_option('tmp-folder','rawmedia')."/".$file, $final_path.$file);
$source = get_file($file,$token);
$db->query("INSERT INTO ".DB_PREFIX."videos (`media`,`pub`,`token`, `user_id`, `source`, `thumb`,`date`) VALUES ('2','0','".$token."', '".user_id()."', '".$source."','".get_option('mediafolder')."/thumbs/xmp3.jpg', now())");

//Extract Duration

$cmd = get_option('ffmpeg-cmd','ffmpeg')." -i ".$final_path.$file;
exec ( "$cmd 2>&1", $output );
$text = implode ( "\r", $output );
if (preg_match ( '!Duration: ([0-9:.]*)[, ]!', $text, $matches )) {
			list ( $v_hours, $v_minutes, $v_seconds ) = explode ( ":", $matches [1] );
			// duration in time format
			$d = $v_hours * 3600 + $v_minutes * 60 + $v_seconds;			
		}
if(isset($d)) {		
list ( $duration, $trash ) = explode ( ".", $d );
}		
if(isset($duration)) {
$db->query("UPDATE  ".DB_PREFIX."videos SET duration='".$duration."' WHERE token = '".$token."'");
}



$doit = $db->get_row("SELECT id from ".DB_PREFIX."videos where token = '".$token."' order by id DESC limit 0,1");
if($doit) { add_activity('4', $doit->id); }
}

$headers = getHeaders();

if (isset($headers['X-Requested-With']) && ($headers['X-Requested-With']=='XMLHttpRequest')) {
	filename($headers, $allowedExts, $maxFileSize, $target_path, $new_name);
} else {
	if ($_FILES['file']['name'] != '') {
		// Sanitize the file name to remove potential path traversal characters
		$fileName = basename(urldecode($_FILES['file']['name'])); // Use basename() to get only the file name

		if (is_insecure_file($fileName)) {
			echo '{"success":false, "details": "Insecure file detected."}';
			die();
		}

		$fileSize = $_FILES['file']['size'];
		$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)); // Get the extension
		$allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf']; // Example allowed extensions

		if (in_array($ext, $allowedExts) || empty($allowedExts)) {
			if ($fileSize < $maxFileSize || empty($maxFileSize)) {
				// Sanitize the new name to prevent directory traversal
				$new_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $new_name); // Allow only safe characters for new file name

				// Define the target path (ensure it is within a safe directory)
				$target_path = ABSPATH . '/uploads/';
				$target_path .= $new_name . '.' . $ext;

				// Check if the final destination path is within the allowed directory
				if (realpath($target_path) !== false && strpos(realpath($target_path), realpath($target_path)) === 0) {
					if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
						echo '{"success":true, "file": "OK"}';
						vinsert($new_name . '.' . $ext); // Store the file in the database
					} else {
						echo '{"success":false, "details": "move_uploaded_file failed"}';
					}
				} else {
					echo '{"success":false, "details": "Invalid file path. Upload failed."}';
				}
			} else {
				echo '{"success":false, "details": "Maximum file size: ' . ByteSize($maxFileSize) . '."}';
			}
		} else {
			// Sanitize the file extension to prevent XSS or other issues
			$sanitizedExt = htmlspecialchars($ext, ENT_QUOTES, 'UTF-8');
			echo '{"success":false, "details": "File type ' . $sanitizedExt . ' not allowed."}';
		}
	} else {
		echo '{"success":false, "details": "No file received."}';
	}

	}
}

