<?php
if (isset($_POST['play-name'])) {
    $picture = 'storage/uploads/noimage.png';  // Default image if no file is uploaded
    $formInputName = 'play-img';                // Name of the file input
    $savePath = ABSPATH . '/storage/uploads';   // Directory to save the image
    $allowedExtArray = array('.jpg', '.png', '.gif'); // Allowed file extensions
    $imageQuality = 100;

    // Generate a secure and unique filename
    $saveName = md5(uniqid(time(), true)) . '-' . user_id();  // Ensure a unique name using uniqid()

    // Sanitize the file name to prevent path traversal or unsafe file names
    $saveName = basename($saveName); // Remove directory traversal characters

    // Check if the uploaded file is an image and has a valid extension
    $uploadedFileType = strtolower(pathinfo($_FILES[$formInputName]['name'], PATHINFO_EXTENSION));
    if (!in_array('.' . $uploadedFileType, $allowedExtArray)) {
        echo "Invalid file type.";  // User-friendly error message
        exit;
    }

    // Sanitize the file path to ensure it's within the intended directory
    $targetPath = $savePath . '/' . $saveName;
    $realPath = realpath($savePath); // Get the absolute path of the save directory

    if ($realPath === false || strpos(realpath($targetPath), $realPath) !== 0) {
        echo "Invalid file path.";  // User-friendly error message
        exit;
    }

    // Initialize file uploader class
    $uploader = new FileUploader($formInputName, $savePath, $saveName, $allowedExtArray);

// Sanitize file name and check for path traversal
    $filename = basename($_FILES['play-img']['name']);  // Ensure it's a simple file name
    $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $filename); // Remove any unwanted characters

// Use a unique name for the uploaded file
    $saveName = md5(time() . user_id()) . '.' . strtolower(pathinfo($filename, PATHINFO_EXTENSION));  // Unique, sanitized file name

// Ensure the upload path is within the designated directory
    $realSavePath = realpath($savePath);
    if ($realSavePath === false || strpos(realpath($savePath . '/' . $saveName), $realSavePath) !== 0) {
        die('Invalid file path or path traversal detected!');
    }

// Proceed with file upload
    if ($uploader->getIsSuccessful()) {
        // Optionally resize image
        //$uploader->resizeImage(200, 200, 'crop');

        // Save the image to the target path
        $uploader->saveImage($uploader->getTargetPath(), $imageQuality);

        // Retrieve the target path and sanitize it for safe database storage
        $thumb = $uploader->getTargetPath();
        $picture = str_replace(ABSPATH . '/', '', $thumb);  // Remove the base path for storage
    } else {
        echo "File upload failed.";  // User-friendly error message
        exit;
    }


    // Proceed with the rest of your logic, such as storing the picture path in the database
    // Example: $db->query("UPDATE table SET picture='" . toDb($picture) . "' WHERE ...");



    if (isset($_POST['categ']) && intval($_POST['categ']) > 0) {
        $ch = $_POST['categ'];
    } else {
        $ch = null;
    }

    $db->query("INSERT INTO " . DB_PREFIX . "postcats (`cat_name`, `picture`, `cat_desc`) VALUES ('" . toDb($_POST['play-name']) . "', '" . toDb($picture) . "' , '" . toDb($_POST['play-desc']) . "')");
    echo '<div class="msg-info">Blog category "' . htmlspecialchars($_POST['play-name'], ENT_QUOTES, 'UTF-8') . '" created</div>';
}

?><div class="row">
<form id="validate" class="form-horizontal styled" action="<?php echo admin_url('create-pch');?>" enctype="multipart/form-data" method="post">
<fieldset>
<div class="form-group form-material">
<label class="control-label">Category's title</label>
<div class="controls">
<input type="text" name="play-name" class="validate[required] col-md-12" placeholder="<?php echo _lang("The title"); ?>" /> 						
</div>	
</div>	

<div class="form-group form-material">
<label class="control-label"><?php echo _lang("Description"); ?></label>
<div class="controls">
<textarea rows="5" cols="5" name="play-desc" class="auto col-md-12" style="overflow: hidden; word-wrap: break-word; resize: horizontal; height: 88px;"></textarea>					
</div>	
</div>
<label class="control-label"><?php echo _lang("Image"); ?></label>
<div class="form-group form-material">

<div class="controls">
<input type="text" class="form-control empty" readonly="" />
<input type="file" id="play-img" name="play-img" class="styled" />
<label class="floating-label">Browse...</label>
</div>	
</div>
<div class="form-group form-material">
<button class="btn btn-large btn-primary pull-right" type="submit"><?php echo _lang("Create"); ?></button>	
</div>	
</fieldset>						
</form>
</div>
