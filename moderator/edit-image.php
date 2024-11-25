<?php if(isset($_POST['edited-image']) && !is_null(intval($_POST['edited-image']))) {
    if (isset($_FILES['play-img']) && !empty($_FILES['play-img']['name'])) {
        $formInputName   = 'play-img';
        $savePath        = ABSPATH . '/storage/' . get_option('mediafolder') . '/thumbs';  // Destination directory

        // Generate a unique, safe file name
        $saveName        = md5(uniqid(time(), true)) . '-' . user_id();  // Ensure a unique name using uniqid()

        // Sanitize and validate file extensions
        $allowedExtArray = array('.jpg', '.png', '.gif');
        $fileExt         = strtolower(pathinfo($_FILES[$formInputName]['name'], PATHINFO_EXTENSION));

        if (!in_array('.' . $fileExt, $allowedExtArray)) {
            exit('Invalid file type');
        }

        // Sanitize the file name to prevent directory traversal
        $saveName = basename($saveName) . '.' . $fileExt;  // Ensure safe file name

        // Ensure the save path is inside the intended directory
        $realPath = realpath($savePath);
        $targetPath = $realPath . '/' . $saveName;

        // Check if the real path is valid and within the allowed directory
        if ($realPath === false || strpos(realpath($targetPath), $realPath) !== 0) {
            exit('File upload path error');
        }

        // Check file size (optional but recommended for security)
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        if ($_FILES[$formInputName]['size'] > $maxFileSize) {
            exit('File is too large');
        }

        // Validate MIME type (optional but adds another layer of security)
        $fileType = mime_content_type($_FILES[$formInputName]['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($fileType, $allowedMimes)) {
            exit('Invalid file type');
        }

        // Move the uploaded file to the desired directory
        if (move_uploaded_file($_FILES[$formInputName]['tmp_name'], $targetPath)) {
            // File upload was successful

            // Optionally, you can resize the image here if needed
            // Resize logic here (optional)

            // Get the relative path of the uploaded file
            $thumb = str_replace(ABSPATH . '/', '', $targetPath);  // Relative path for the thumb
            $source = str_replace(ABSPATH . '/', 'localimage/', $targetPath);  // Another sanitized URL path

            // Ensure the paths are safe and update the database
            $db->query("UPDATE " . DB_PREFIX . "images SET source='" . toDb($source) . "', thumb='" . get_option('mediafolder') . '/' . toDb($thumb) . "' WHERE id = '" . intval($_POST['edited-image']) . "'");
        } else {
            exit('File upload failed');
        }
    }

    $db->query("UPDATE  ".DB_PREFIX."images SET ispremium='".toDb(_post('ispremium'))."',liked='".toDb(_post('likes'))."',views='".toDb(_post('views'))."',stayprivate='".toDb(_post('priv'))."',title='".toDb(_post('title'))."', description='".toDb(_post('description') )."', category='".toDb(intval(_post('categ')))."', tags='".toDb(_post('tags') )."', nsfw='".intval(_post('nsfw') )."' WHERE id = '".intval($_POST['edited-image'])."'");
echo '<div class="msg-info">image: '._post('title').' updated.</div>';
$db->clean_cache();
} 
$image = $db->get_row("SELECT * from ".DB_PREFIX."images where id = '".intval(_get("vid"))."' ");
if($image) {
?>

<div class="row">
<h3>Update <a href="<?php echo image_url($image->id,$image->title); ?>" target="_blank"><?php echo $image->title; ?> <i class="icon-link"></i></a></h3>
<form id="validate" class="form-horizontal styled" action="<?php echo admin_url('edit-image');?>&vid=<?php echo $image->id; ?>" enctype="multipart/form-data" method="post">
<fieldset>
<input type="hidden" name="edited-image" id="edited-image" value = "<?php echo $image->id; ?>"/>
<div class="form-group form-material">
<label class="control-label"><i class="icon-bookmark"></i><?php echo _lang("Title"); ?></label>
<div class="controls">
<input type="text" name="title" class="validate[required] col-md-12" value="<?php echo $image->title; ?>" /> 						
</div>	
</div>	
	
<div class="form-group form-material">
<label class="control-label"><?php echo _lang("Description"); ?></label>
<div class="controls">
<textarea rows="5" cols="5" name="description" class="auto validate[required] col-md-12" style="overflow: hidden; word-wrap: break-word; resize: horizontal; height: 88px;"><?php echo $image->description; ?></textarea>					
</div>	
</div>
<div class="form-group form-material">
<label class="control-label">Image</label>
<div class="row mleft20">
<img src="<?php echo thumb_fix($image->thumb); ?>" style="max-width:350px; max-height:380px; margin-bottom:5px;"/>
</div>
<div class="controls">
<div class="row">
<div class="col-md-6">
<div class="form-group form-material form-material-file">
<div class="controls">
<input type="text" class="form-control empty" readonly="" />
<input type="file" id="play-img" name="play-img" class="styled" />
<label class="floating-label">Browse...</label>
<span class="help-block" id="limit-text"><?php echo _lang("Select only if you wish to change the image");?></span>
</div>
</div>
</div>
</div>
</div>
	
</div>
	<div class="control-group blc row">
	<label class="control-label"><?php echo _lang("Category:"); ?></label>
	<div class="controls">
	<?php echo cats_select('categ','select','validate[required]', 3); ?>
	<?php  if(isset($hint)) { ?>
	  <span class="help-block"> <?php echo $hint; ?></span>
	<?php } ?>  
	<script>
	      $(document).ready(function(){
	$('.select').find('option[value="<?php echo $image->category;?>"]').attr("selected",true);	
});
	</script>
	  </div>             
	  </div>
	  <div class="form-group form-material">
	<label class="control-label"><?php echo _lang("Tags:"); ?></label>
	<div class="controls">
	<input type="text" id="tags" name="tags" class="tags col-md-12" value="<?php echo $image->tags; ?>">
	</div>
	</div>
	<div class="form-group form-material">
	<label class="control-label">Premium ?</label>
	<div class="controls">
	<label class="radio inline"><input type="radio" name="ispremium" class="styled" value="1" <?php if($image->ispremium > 0 ) { echo "checked"; } ?>>Premium </label>
	<label class="radio inline"><input type="radio" name="ispremium" class="styled" value="0" <?php if($image->ispremium < 1 ) { echo "checked"; } ?>>Normal</label>
	</div>
	</div>
	<div class="form-group form-material">
	<label class="control-label"><?php echo _lang("NSFW:"); ?></label>
	<div class="controls">
	<label class="radio inline"><input type="radio" name="nsfw" class="styled" value="1" <?php if($image->nsfw > 0 ) { echo "checked"; } ?>> <?php echo _lang("Not safe"); ?> </label>
	<label class="radio inline"><input type="radio" name="nsfw" class="styled" value="0" <?php if($image->nsfw < 1 ) { echo "checked"; } ?>><?php echo _lang("Safe"); ?></label>
	</div>
	</div>
	<div class="control-group blc row">
	<label class="control-label"><?php echo _lang("Visibility"); ?> </label>
	<div class="controls">
	<label class="radio inline"><input type="radio" name="priv" class="styled" value="1" <?php if($image->stayprivate > 0 ) { echo "checked"; } ?>> <?php echo _lang("Followers only");?> </label>
	<label class="radio inline"><input type="radio" name="priv" class="styled" value="0" <?php if($image->stayprivate < 1 ) { echo "checked"; } ?>><?php echo _lang("Everybody");?> </label>
	</div>
	</div>
	<div class="form-group form-material">
	<label class="control-label">Views</label>
	<div class="controls">
	<input type="text" id="views" name="views" class=" col-md-12" value="<?php echo $image->views; ?>">
	</div>
	</div>
	<div class="form-group form-material">
	<label class="control-label">Likes</label>
	<div class="controls">
	<input type="text" id="liked" name="likes" class=" col-md-12" value="<?php echo $image->liked; ?>">
	</div>
	</div>
	
<div class="form-group form-material">
<button class="btn btn-large btn-primary pull-right" type="submit"><?php echo _lang("Update image"); ?></button>	
</div>	
</fieldset>						
</form>
<?php
} else {
echo '<div class="msg-warning">Missing image</div>';
} ?>
</div>
