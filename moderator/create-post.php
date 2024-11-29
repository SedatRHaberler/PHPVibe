<?php
if(isset($_POST['play-name'])) {
    if (isset($_FILES['play-img']) && !empty($_FILES['play-img']['name'])) {
        // Allowed file extensions
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif']; // Supported extensions
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif']; // Supported MIME types
        $maxFileSize = 5000000; // Maximum file size in bytes (5MB)

        // Uploaded file name and properties
        $fileName = basename($_FILES['play-img']['name']); // Get only the base name
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)); // Get file extension
        $fileType = mime_content_type($_FILES['play-img']['tmp_name']); // Get MIME type
        $fileSize = $_FILES['play-img']['size']; // Get file size

        // Validate file extension
        if (in_array($fileExtension, $allowedExtensions)) {
            // Validate MIME type
            if (in_array($fileType, $allowedMimeTypes)) {
                // Validate file size
                if ($fileSize <= $maxFileSize) {
                    // Generate a unique and secure file name
                    $uniqueFileName = uniqid('img_', true) . '.' . $fileExtension;

                    // Target upload directory and file path
                    $uploadDir = ABSPATH . '/storage/uploads/';
                    $thumb = $uploadDir . $uniqueFileName;

                    // Check if the upload directory exists and create it if not
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true); // Create the directory if necessary
                    }

                    // Safely move the uploaded file
                    if (move_uploaded_file($_FILES['play-img']['tmp_name'], $thumb)) {
                        // Set appropriate permissions for the uploaded file
                        chmod($thumb, 0644);

                        // Save the relative path of the uploaded file
                        $picture = str_replace(ABSPATH . '/', '', $thumb);
                    } else {
                        // File could not be moved
                        echo '<div class="msg-error">Failed to upload the file. Please try again.</div>';
                        $picture = '';
                    }
                } else {
                    // File size exceeds the limit
                    echo '<div class="msg-error">File size is too large. Maximum 5MB allowed.</div>';
                    $picture = '';
                }
            } else {
                // Invalid MIME type
                echo '<div class="msg-error">Invalid file type. Only JPEG, PNG, and GIF files are supported.</div>';
                $picture = '';
            }
        } else {
            // Invalid file extension
            echo '<div class="msg-error">Invalid file type. Only jpg, jpeg, png, and gif extensions are allowed.</div>';
            $picture = '';
        }
    } else {
        $picture = ''; // No file uploaded
    }


    // Sanitize and validate input data
    $ch = intval($_POST['ch']);
    $playName = htmlspecialchars($db->escape($_POST['play-name']), ENT_QUOTES, 'UTF-8');
    $content = htmlspecialchars($db->escape(htmlentities($_POST['content'])), ENT_QUOTES, 'UTF-8');
    $tags = htmlspecialchars($db->escape($_POST['tags']), ENT_QUOTES, 'UTF-8');

    // Insert into database using sanitized values
    $db->query("INSERT INTO " . DB_PREFIX . "posts (`date`, `ch`, `pic`, `title`, `content`, `tags`)
        VALUES (NOW(), '$ch', '$picture', '$playName', '$content', '$tags')");

    // Output sanitized user data
    echo '<div class="msg-info">Post ' . htmlspecialchars($_POST['play-name'], ENT_QUOTES, 'UTF-8') . ' created</div>';
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