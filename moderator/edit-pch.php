<?php
if (isset($_POST['play-name'])) {
    if ($_FILES['play-img']) {
        $formInputName = 'play-img';  // This is the name given to the form's file input
        $savePath = ABSPATH . '/storage/uploads';  // The folder to save the image

        // Sanitize the file name to prevent directory traversal
        $filename = basename($_FILES['play-img']['name']);  // Get only the base file name
        $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $filename);  // Remove unwanted characters

        // Generate a unique file name and append the file extension
        $saveName = md5(time() . user_id()) . '-' . rand(1000, 9999) . '.' . strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Set allowed file types
        $allowedExtArray = array('.jpg', '.png', '.gif');
        $imageQuality = 100;

        // Validate file extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array('.' . $ext, $allowedExtArray)) {
            die('Invalid file type!');
        }

        // Ensure the file is saved in the intended directory and prevent directory traversal
        $targetPath = $savePath . '/' . $saveName;
        $realPath = realpath($savePath);
        if ($realPath === false || strpos(realpath($targetPath), $realPath) !== 0) {
            die('Invalid file path!');
        }

        // Handle file upload
        $uploader = new FileUploader($formInputName, $savePath, $saveName, $allowedExtArray);
        if ($uploader->getIsSuccessful()) {
            // Optionally resize the image
            //$uploader->resizeImage(200, 200, 'crop');
            $uploader->saveImage($uploader->getTargetPath(), $imageQuality);

            $thumb = $uploader->getTargetPath();
            $picture = str_replace(ABSPATH . '/', '', $thumb);  // Store relative path in the database

            // Update the database with the new picture path
            $db->query("UPDATE " . DB_PREFIX . "postcats SET picture='" . toDb($picture) . "' WHERE cat_id='" . intval($_GET['id']) . "'");
        }
    }


    // Update category details in the database
    $db->query("UPDATE " . DB_PREFIX . "postcats SET child_of='" . intval($_POST['categ']) . "', cat_name='" . toDb($_POST['play-name']) . "', cat_desc='" . toDb($_POST['play-desc']) . "' WHERE cat_id='" . intval($_GET['id']) . "'");

    echo '<div class="msg-info">Category ' . htmlspecialchars($_POST['play-name'], ENT_QUOTES, 'UTF-8') . ' updated</div>';
}
$ch = $db->get_row("SELECT * FROM ".DB_PREFIX."postcats where cat_id ='".intval($_GET['id'])."'");
if($ch) {
?>

<div class="row">
<form id="validate" class="form-horizontal styled" action="<?php echo admin_url('edit-pch');?>&id=<?php echo intval($_GET['id']); ?>" enctype="multipart/form-data" method="post">
<fieldset>
<div class="form-group form-material">
<label class="control-label"><i class="icon-bookmark"></i><?php echo _lang("Title"); ?></label>
<div class="controls">
<input type="text" name="play-name" class="validate[required] col-md-12" value="<?php echo $ch->cat_name; ?>" /> 						
</div>	
</div>	
<?php
echo '<div class="form-group form-material">
	<label class="control-label">'._lang("Child of:").'</label>
	<div class="controls">
	<select data-placeholder="'._lang("Choose a parentcategory:").'" name="categ" id="clear-results" class="select" tabindex="2">
	';

echo '<option value="">-- None --</option>';
$categories = $db->get_results("SELECT cat_id as id, cat_name as name FROM  ".DB_PREFIX."postcats order by cat_name asc limit 0,10000");
if($categories) {
foreach ($categories as $cat) {	
echo'<option value="'.intval($cat->id).'">'.stripslashes($cat->name).'</option>';
$titles[$cat->id] =  $cat->name;
	}
if($ch->child_of) { echo'<option value="'.$ch->child_of.'" selected>'.$titles[$ch->child_of].'</option>';	}	
}
	


echo '</select>
	  </div>             
	  </div>';
?>		
<div class="form-group form-material">
<label class="control-label"><?php echo _lang("Description"); ?></label>
<div class="controls">
<textarea rows="5" cols="5" name="play-desc" class="auto col-md-12" style="overflow: hidden; word-wrap: break-word; resize: horizontal; height: 88px;"><?php echo $ch->cat_desc; ?></textarea>					
</div>	
</div>
<div class="form-group form-material">
<label class="control-label"><?php echo _lang("Image"); ?></label>
<div class="controls">
<input type="file" id="play-img" name="play-img" class="styled" />
<span class="help-block" id="limit-text"><?php echo _lang("Select only if you wish to change the image");?></span>
</div>	
</div>
<div class="form-group form-material">
<button class="btn btn-large btn-primary pull-right" type="submit"><?php echo _lang("Update"); ?></button>	
</div>	
</fieldset>						
</form>
</div>
<?php } else {
    echo '<div class="msg-warning">Category ' . htmlspecialchars($_GET['id'], ENT_QUOTES, 'UTF-8') . ' not found</div>';
} ?>
