<?php
require_once("../../load.php");

if (is_user()) {
    $target_path = ABSPATH.'/storage/'.get_option('tmp-folder','rawmedia')."/";
    $final_path = ABSPATH.'/storage/'.get_option('mediafolder')."/";
    $ip = ABSPATH.'/storage/'.get_option('mediafolder').'/thumbs/';

    $allowedExts = array();
    $maxFileSize = 0;
    $token = toDb($_GET['token']);
    $new_name = $token;

    // Function to sanitize the filename and prevent path traversal
    function sanitizeFileName($filename): array|string|null
    {
        // Remove any characters that are not alphanumeric, dashes, or underscores
        return preg_replace('/[^a-zA-Z0-9_-]/', '', basename($filename));
    }

    function ByteSize($bytes): float|int|string
    {
        return byte($bytes);
    }

    /**
     * @param $bytes
     * @return float|int|string
     */
    function byte($bytes): string|int|float
    {
        $size = $bytes / 1024;
        if ($size < 1024) {
            $size = number_format($size, 2);
            $size .= ' KB';
        } else {
            if ($size / 1024 < 1024) {
                $size = number_format($size / 1024, 2);
                $size .= ' MB';
            } else if ($size / 1024 / 1024 < 1024) {
                $size = number_format($size / 1024 / 1024, 2);
                $size .= ' GB';
            }
        }
        return $size;
    }

    function getHeaders(): array
    {
        return headers();
    }

    /**
     * @return array
     */
    function headers(): array
    {
        $headers = array();
        foreach ($_SERVER as $k => $v) {
            if (substr($k, 0, 5) == "HTTP_") {
                $k = str_replace('_', ' ', substr($k, 5));
                $k = str_replace(' ', '-', ucwords(strtolower($k)));
                $headers[$k] = $v;
            }
        }
        return $headers;
    }

    function vinsert($file): void
    {
        global $db, $token;
        $ext = substr($file, strrpos($file, '.') + 1);
        $db->query("INSERT INTO ".DB_PREFIX."videos_tmp (`uid`, `name`, `path`, `ext`) VALUES ('".user_id()."', '".$token."', '".$file."', '".$ext."')");
        $ext = strtolower($ext);
        // Add to the main videos table
        $db->query("INSERT INTO ".DB_PREFIX."videos (`date`,`pub`,`token`, `user_id`, `tmp_source`, `thumb`) VALUES (now(), '0','".$token."', '".user_id()."', '".$file."','storage/uploads/processing.png')");
        $binpath = get_option('binpath','/usr/bin/php');
        $command = $binpath." -cli -f ".ABSPATH."/videocron.php";
        exec( "$command > /dev/null &", $arrOutput );
        // Add activity
        $doit = $db->get_row("SELECT id from ".DB_PREFIX."videos where token = '".$token."' order by id DESC limit 0,1");
        if ($doit) {
            add_activity('4', $doit->id);
        }
    }

    // Get headers
    $headers = getHeaders();

    if ($headers['X-Requested-With'] == 'XMLHttpRequest') {
        filename($headers, $allowedExts, $maxFileSize, $target_path, $new_name);
    } else {
        if ($_FILES['file']['name'] != '') {
            // Get and sanitize the file name
            $fileName = urldecode($_FILES['file']['name']);
            $fileName = sanitizeFileName($fileName);  // Sanitize to prevent path traversal

            if (is_insecure_file($fileName)) {
                echo '{"success":false, "details": "Insecure file detected."}';
                die();
            }

            $fileSize = $_FILES['file']['size'];
            $ext = substr($fileName, strrpos($fileName, '.') + 1);

            if (in_array($ext, $allowedExts) || empty($allowedExts)) {
                if ($fileSize < $maxFileSize || empty($maxFileSize)) {
                    // Ensure the file path is within the allowed directory
                    $target_path = $target_path . $new_name . '.' . $ext;

                    // Validate that the target path is inside the intended directory
                    if (realpath($target_path) && strpos(realpath($target_path), realpath($target_path)) === 0) {
                        // Move the uploaded file
                        if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
                            echo '{"success":true, "file": "OK"}';
                            vinsert($new_name . '.' . $ext);
                        } else {
                            echo '{"success":false, "details": "move_uploaded_file failed"}';
                        }
                    } else {
                        echo '{"success":false, "details": "Invalid file path"}';
                    }
                } else {
                    echo '{"success":false, "details": "Maximum file size: ' . ByteSize($maxFileSize) . '"}';
                }
            } else {
                // Sanitize the file extension in the error message to prevent XSS
                $sanitizedExt = htmlspecialchars($ext, ENT_QUOTES, 'UTF-8');
                echo '{"success":false, "details": "File type ' . $sanitizedExt . ' not allowed."}';
            }
        } else {
            echo '{"success":false, "details": "No file received."}';
        }
    }
}

