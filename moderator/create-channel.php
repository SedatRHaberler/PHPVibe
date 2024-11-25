<?php
if(isset($_POST['play-name'])) {
$picture ='';
// Set image upload path
    if (isset($_FILES['play-img']) && !empty($_FILES['play-img']['name'])) {
        // Sanitize the file name to prevent path traversal
        $fileName = basename(urldecode($_FILES['play-img']['name'])); // Get only the file name (no path)
        $fileName = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileName); // Remove unsafe characters

        // Get the file extension and validate it
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif']; // Allowed extensions
        if (!in_array($extension, $allowedExtensions)) {
            echo '<div class="msg-warning">Invalid file type for image upload.</div>';
            return;
        }

        // Generate a unique name for the file to avoid overwriting
        $newFileName = 'play-img-' . uniqid() . '.' . $extension;

        // Define the target directory for uploads
        $targetDir = ABSPATH . '/storage/uploads/';

        // Construct the full path for the uploaded file
        $thumb = $targetDir . $newFileName;

        // Verify that the file path is within the target directory
        if (realpath($thumb) !== false && strpos(realpath($thumb), realpath($targetDir)) === 0) {
            // Attempt to move the uploaded file
            if (move_uploaded_file($_FILES['play-img']['tmp_name'], $thumb)) {
                // Update the picture option with the relative file path
                $picture = str_replace(ABSPATH . '/', '', $thumb);
                echo '<div class="msg-info">Image uploaded successfully.</div>';
            } else {
                echo '<div class="msg-warning">Image upload failed.</div>';
            }
        } else {
            echo '<div class="msg-warning">Invalid file path. Upload failed.</div>';
        }
    }


$ch = 0;
if($_POST['subz'] > 1) {
$ch = $_POST['categ'.$_POST['type']];if(is_array($ch)) { $ch = $ch[0];}
}

$db->query("INSERT INTO ".DB_PREFIX."channels (`sub`,`type`,`child_of`, `cat_name`, `picture`, `cat_desc`) VALUES ('".$_POST['sub']."','".$_POST['type']."','".$ch."','".toDb($_POST['play-name'])."', '".toDb($picture)."' , '".toDb($_POST['play-desc'])."')");$db->clean_cache();
// Sanitize the user input to prevent XSS
    $play_name = htmlspecialchars($_POST['play-name'], ENT_QUOTES, 'UTF-8');

// Safely output the sanitized value
    echo '<div class="msg-info">Channel ' . $play_name . ' created</div>';
}

?>
<div class="row">
	
	<h3><a class="btn btn-large btn-success mright20" href="<?php echo admin_url('channels'); ?>"> Back</a> Create a category </h3>
	
<script>
   
 $(document).ready(function(){
  $('#chz,#a2,#a3').hide();
      $('.trigger').on('ifChecked', function(event){
        $('#a1,#a2,#a3').hide();
        $('#a' + $(this).data('rel')).show();
    });
	 $('.shs').on('ifChecked', function(event){
        if ($(this).data('rel') === 1) {
		$('#chz').hide();
		} else {
		$('#chz').show();
		}
    });

});
</script>

<div class="panel">
	<div class="panel-body"> 
	
<form id="validate" class="form-horizontal styled" action="<?php echo admin_url('create-channel');?>" enctype="multipart/form-data" method="post">
<fieldset>
<div class="form-group form-material">
<label class="control-label"><i class="icon-bookmark"></i><?php echo _lang("Title"); ?></label>
<div class="controls">
<input type="text" name="play-name" class="validate[required] col-md-12" placeholder="Your category's title" /> 						
</div>	
</div>
<div class="form-group form-material">
	<label class="control-label"><i class="icon-user"></i>Is this a sub-category?</label>
	<div class="controls">
	<label class="radio inline"><input type="radio" name="subz" data-rel="1" class="styled shs" value="1" checked>No</label>
	<label class="radio inline"><input type="radio" name="subz" data-rel="2" class="styled shs" value="2">Yes</label>
	</div>
	</div>		
<div class="form-group form-material">
	<label class="control-label"><i class="icon-user"></i>Accepted media</label>
	<div class="controls">
	<label class="radio inline"><input type="radio" name="type" data-rel="1" class="styled trigger" value="1" checked>Video</label>
	<label class="radio inline"><input type="radio" name="type" data-rel="2" class="styled trigger" value="2">Music</label>
	<label class="radio inline"><input type="radio" name="type" data-rel="3" class="styled trigger" value="3">Images</label>
	</div>
	</div>	
	
<div id="chz" class="control-group row">
	<label class="control-label">Parent channel:</label>
	<div class="controls">
	<div id="a1" class="sel">
	<?php echo cats_select("categ1","select","");?>
	<span class="help-block" id="limit-text">FOR VIDEOS</span>
	  </div> 
<div id="a2" class="sel">
	<?php echo cats_select('categ2',"select","","2");?>
	<span class="help-block" id="limit-text">FOR MUSIC</span>
	  </div>  
<div id="a3" class="sel">
	<?php echo cats_select('categ3',"select","","3");?>
	<span class="help-block" id="limit-text">FOR IMAGES</span>
	  </div>  	
  
	  </div>
	  </div>
	

<div class="form-group form-material">
	<label class="control-label"><i class="icon-cloud-upload"></i>Sharing to this channel:</label>
	<div class="controls">
	<label class="radio inline"><input type="radio" name="sub" class="styled" value="1" checked>Public (Every registred user)</label>
	<label class="radio inline"><input type="radio" name="sub" class="styled" value="0">Private (Mods & Admins)</label>

	</div>
	</div>	
<div class="form-group form-material">
<label class="control-label"><?php echo _lang("Description"); ?></label>
<div class="controls">
<textarea rows="5" cols="5" name="play-desc" class="auto col-md-12" style="overflow: hidden; word-wrap: break-word; resize: horizontal; height: 88px;"></textarea>					
</div>	
</div>
<label class="control-label"><?php echo _lang("Channel image"); ?></label>
<div class="form-group form-material form-material-file">

<div class="controls">
<input type="text" class="form-control empty" readonly=""/>
<input type="file" id="play-img" name="play-img" class="styled" />
<label class="floating-label">Browse...</label>

</div>	
</div>
<div class="form-group form-material">
<button class="btn btn-large btn-primary pull-right" type="submit"><?php echo _lang("Create channel"); ?></button>	
</div>	
</fieldset>						
</form>
</div>

</div>
</div>
