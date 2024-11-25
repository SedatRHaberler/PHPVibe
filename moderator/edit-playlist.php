<?php
global $db;
if (isset($_POST['play-name'])) {
    if ($_FILES['play-img']) {
        $formInputName = 'play-img';  // This is the name given to the form's file input
        $savePath = ABSPATH . '/storage/uploads';  // The folder to save the image
        $filename = basename($_FILES['play-img']['name']);  // Sanitize the file name
        $saveName = md5(time()) . '-' . user_id() . '.' . strtolower(pathinfo($filename, PATHINFO_EXTENSION));  // Generate a unique file name with extension
        $allowedExtArray = array('.jpg', '.png', '.gif');  // Set allowed file types
        $imageQuality = 100;

        // Validate file extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array('.' . $ext, $allowedExtArray)) {
            die('Invalid file type!');
        }

        // Ensure the file is not a directory traversal attempt
        $targetPath = $savePath . '/' . $saveName;
        $realPath = realpath($savePath);
        if ($realPath === false || strpos(realpath($targetPath), $realPath) !== 0) {
            die('Invalid file path!');
        }

        $uploader = new FileUploader($formInputName, $savePath, $saveName, $allowedExtArray);
        if ($uploader->getIsSuccessful()) {
            $uploader->saveImage($uploader->getTargetPath(), $imageQuality);
            $thumb = $uploader->getTargetPath();
            $picture = str_replace(ABSPATH . '/', '', $thumb);  // Store the relative file path in the database

            // Update database with the new picture path
            $db->query("UPDATE " . DB_PREFIX . "playlists SET picture='" . toDb($picture) . "' WHERE id= '" . intval($_GET['id']) . "'");
        }
    }

    // Update other playlist details in the database
    $db->query("UPDATE " . DB_PREFIX . "playlists SET title ='" . toDb($_POST['play-name']) . "', description = '" . toDb($_POST['play-desc']) . "' WHERE id = '" . intval($_GET['id']) . "'");

    echo '<div class="msg-info">Playlist ' . htmlspecialchars($_POST['play-name'], ENT_QUOTES, 'UTF-8') . ' updated</div>';
}

$ch = $db->get_row("SELECT * FROM ".DB_PREFIX."playlists where id ='".intval($_GET['id'])."'");
if($ch) {
?>

<div class="row">
<form id="validate" class="form-horizontal styled" action="<?php echo admin_url('edit-playlist');?>&id=<?php echo intval($_GET['id']); ?>" enctype="multipart/form-data" method="post">
<fieldset>
<div class="form-group form-material">
<label class="control-label"><i class="icon-bookmark"></i><?php echo _lang("Title"); ?></label>
<div class="controls">
<input type="text" name="play-name" class="validate[required] col-md-12" value="<?php echo $ch->title; ?>" /> 						
</div>	
</div>	
	
<div class="form-group form-material">
<label class="control-label"><?php echo _lang("Description"); ?></label>
<div class="controls">
<textarea rows="5" cols="5" name="play-desc" class="auto validate[required] col-md-12" style="overflow: hidden; word-wrap: break-word; resize: horizontal; height: 88px;"><?php echo $ch->description; ?></textarea>					
</div>	
</div>
<p class="control-label text-left"><?php echo _lang("Playlist image"); ?></p>
<div class="form-group form-material form-material-file">
<input type="text" class="form-control empty" readonly="" />

<input type="file" id="play-img" name="play-img" class="styled" />
<label class="floating-label">Browse...</label>
</div>

<div class="controls">
<span class="help-block" id="limit-text"><?php echo _lang("Select only if you wish to change the image");?></span>
</div>	
<div class="form-group form-material">
<button class="btn btn-large btn-primary pull-right" type="submit"><?php echo _lang("Update playlist"); ?></button>	
</div>	
</fieldset>						
</form>
</div>
<?php } else {
    echo '<div class="msg-warning">Playlist ' . htmlspecialchars($_GET['id'], ENT_QUOTES, 'UTF-8') . ' not found</div>';
} ?>
