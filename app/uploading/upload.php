<?php require_once("../load.php");
if(is_user()) {
$target_path = ABSPATH.'/storage/'.get_option('mediafolder')."/";
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
$source = get_file($file,$token);
$db->query("INSERT INTO ".DB_PREFIX."videos (`date`,`pub`,`token`, `user_id`, `source`) VALUES (now(), '0','".$token."', '".user_id()."', '".$source."')");
}

$headers = getHeaders();

if ($headers['X-Requested-With']=='XMLHttpRequest') { 
   $fileName = $headers['X-File-Name'];
  if(is_insecure_file($fileName)){
   echo '{"success":false, "details": "Insecure file detected."}';
   die();
   }
   $fileSize = $headers['X-File-Size'];
	$ext = substr($fileName, strrpos($fileName, '.') + 1);
	if (in_array($ext,$allowedExts) or empty($allowedExts)) {
		if ($fileSize<$maxFileSize or empty($maxFileSize)) {
		$input = fopen("php://input",'r');
		$output = fopen($target_path.$new_name.'.'.$ext,'a');
		if ($output!=false) {
			while (!feof($input)) {
				$buffer=fread($input, 4096);
				fwrite($output, $buffer);
			}
			fclose($output);
			$truefile = $target_path.$new_name.'.'.$ext;
			$insertit = $new_name.'.'.$ext;

			vinsert($insertit);
			echo '{"success":true, "file": "'.$insertit.'"}';
			
		} else echo('{"success":false, "details": "Can\'t create a file handler."}');
		fclose($input);
	} else { echo('{"success":false, "details": "Maximum file size: '.ByteSize($maxFileSize).'."}'); };
	} else {
		echo('{"success":false, "details": "File type '.$ext.' not allowed."}');
		}
} else {
    if ($_FILES['file']['name'] != '') {
        $fileName = $_FILES['file']['name'];
        if (is_insecure_file($fileName)) {
            echo '{"success":false, "details": "Insecure file detected."}';
            die();
        }

        $fileSize = $_FILES['file']['size'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));  // Dosya uzantısını küçük harfe dönüştür
        if (in_array($ext, $allowedExts) || empty($allowedExts)) {
            if ($fileSize < $maxFileSize || empty($maxFileSize)) {
                // Yalnızca geçerli dosya adları kullan
                $newFileName = preg_replace("/[^a-zA-Z0-9_-]/", "", $new_name); // Geçersiz karakterleri temizleyin
                $target_path = $target_path . $newFileName . '.' . $ext;

                // Dosya yolunda traversal saldırılarını engellemek için güvenlik önlemleri
                if (realpath($target_path) !== false && strpos(realpath($target_path), $target_path) === 0) {
                    if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
                        echo '{"success":true, "file": "OK"}';
                        vinsert($newFileName . '.' . $ext);
                    } else {
                        echo '{"success":false, "details": "move_uploaded_file failed"}';
                    }
                } else {
                    echo '{"success":false, "details": "Invalid file path."}';
                }
            } else {
                echo '{"success":false, "details": "Maximum file size: ' . ByteSize($maxFileSize) . '.';
        }
    } else {
        // Uzantı kontrolü sırasında sanitize işlemi
        $sanitizedExt = htmlspecialchars($ext, ENT_QUOTES, 'UTF-8');
        echo '{"success":false, "details": "File type ' . $sanitizedExt . ' not allowed."}';
    }
} else {
    echo '{"success":false, "details": "No file received."}';
}


	}
}

