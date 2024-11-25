<?php
if(isset($_POST['play-name'])) {
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
        exit('Invalid file type.');
    }

    // Initialize file uploader class
    $uploader = new FileUploader($formInputName, $savePath, $saveName, $allowedExtArray);

    if ($uploader->getIsSuccessful()) {
        // Optionally resize image
        //$uploader->resizeImage(200, 200, 'crop');

        // Save the image to the target path
        $uploader->saveImage($uploader->getTargetPath(), $imageQuality);

        // Retrieve the target path and sanitize it for safe database storage
        $thumb = $uploader->getTargetPath();
        $picture = str_replace(ABSPATH . '/', '', $thumb); // Remove the base path for storage
    }


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
