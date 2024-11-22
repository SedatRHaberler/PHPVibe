<?php
if (isset($_POST['play-name'])) {
    // Sanitize the 'play-name' to prevent XSS
    $play_name = htmlspecialchars($_POST['play-name'], ENT_QUOTES, 'UTF-8');

    // Process file upload (with validation)
    if (isset($_FILES['play-img']) && !empty($_FILES['play-img']['name'])) {
        $endextens = explode('.', $_FILES['play-img']['name']);
        $extension = strtolower(end($endextens));

        // Validate the file extension (allow only specific image types)
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($extension, $allowed_extensions)) {
            $thumb = ABSPATH . '/storage/uploads/' . nice_url($_FILES['play-img']['name']) . uniqid() . '.' . $extension;
            if (move_uploaded_file($_FILES['play-img']['tmp_name'], $thumb)) {
                $picture = str_replace(ABSPATH . '/', '', $thumb);
            } else {
                $picture = '';
            }
        } else {
            // If the file is not allowed, set picture to an empty string
            $picture = '';
        }
    } else {
        $picture = '';
    }

    // Use prepared statements to prevent SQL injection
    $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "posts (`date`, `ch`, `pic`, `title`, `content`, `tags`)
                          VALUES (now(), :ch, :pic, :title, :content, :tags)");

    // Bind parameters safely
    $stmt->bindParam(':ch', intval($_POST['ch']), PDO::PARAM_INT);
    $stmt->bindParam(':pic', $picture, PDO::PARAM_STR);
    $stmt->bindParam(':title', $play_name, PDO::PARAM_STR);
    $stmt->bindParam(':content', htmlentities($_POST['content'], ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
    $stmt->bindParam(':tags', $_POST['tags'], PDO::PARAM_STR);

    // Execute the query
    $stmt->execute();

    // Output sanitized message
    echo '<div class="msg-info">Post ' . $play_name . ' created</div>';
}

?>
<div class="row">
<form id="validate" class="form-horizontal styled" action="<?php echo admin_url('create-post');?>" enctype="multipart/form-data" method="post">
<fieldset>
<div class="form-group form-material">
<label class="control-label"><i class="icon-text-height"></i>Title</label>
<div class="controls">
<input type="text" name="play-name" class="validate[required] col-md-12"/> 						
</div>	
</div>	

<div class="form-group form-material">
<label class="control-label">Article content</label>
<div class="controls">
<textarea rows="5" cols="5" name="content" class="ckeditor col-md-12" style="word-wrap: break-word; resize: horizontal; height: 88px;"></textarea>					
</div>	
</div>
<label class="control-label">Image?</label>
<div class="form-group form-material form-material-file">

<div class="controls">
<input type="text" class="form-control empty" readonly="" />
<input type="file" id="play-img" name="play-img" class="styled" />
<label class="floating-label">Browse...</label>


</div>	
</div>
<?php
echo '<div class="form-group form-material">
	<label class="control-label">'._lang("Category:").'</label>
	<div class="controls">
	<select data-placeholder="'._lang("Choose a category:").'" name="ch" id="clear-results" class="select" tabindex="2">
	';
$categories = $db->get_results("SELECT cat_id as id, cat_name as name FROM  ".DB_PREFIX."postcats order by cat_name asc limit 0,10000");
if($categories) {
foreach ($categories as $cat) {	
echo'<option value="'.intval($cat->id).'">'.stripslashes($cat->name).'</option>';
	}
}	else {
echo'<option value="">'._lang("No categories").'</option>';
}
echo '</select>
	  </div>             
	  </div>';
?>
<div class="form-group form-material">
	<label class="control-label">Tags</label>
	<div class="controls">
	<input type="text" id="tags" name="tags" class="tags col-md-12" value="">
	<span class="help-block" id="limit-text">Press enter after each tag</span>
	</div>
	</div>
<div class="form-group form-material">
<button class="btn btn-large btn-primary pull-right" type="submit">Create</button>	
</div>	
</fieldset>						
</form>
</div>
