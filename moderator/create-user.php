<?php
if (_post('name') && _post('password') && _post('email')) {
$avatar = 'uploads/def-avatar.jpg';  // Default avatar

if ($_FILES['avatar']) {
// Sanitize file input
$formInputName = 'avatar';  // This is the name given to the form's file input
$savePath = ABSPATH . '/storage/uploads';  // Folder to save the image
$filename = basename($_FILES['avatar']['name']);  // Sanitize the filename (removes path traversal characters)

// Set allowed file types
$allowedExtArray = array('.jpg', '.png', '.gif');
$imageQuality = 100;

// Validate file extension
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array('.' . $ext, $allowedExtArray)) {
die('Invalid file type!');
}

// Validate MIME type (check if it's an actual image)
$fileType = mime_content_type($_FILES['avatar']['tmp_name']);
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($fileType, $allowedMimes)) {
die('Invalid file type!');
}

// Generate a unique file name to avoid conflicts and ensure security
$saveName = md5(time() . user_id()) . '.' . $ext;

// Ensure the target file path is valid and within the allowed directory
$realPath = realpath($savePath);
$targetPath = $savePath . '/' . $saveName;

// Check if the file is inside the allowed directory
if ($realPath === false || strpos(realpath($targetPath), $realPath) !== 0) {
die('Invalid file path!');
}

// Proceed with the file upload
$uploader = new FileUploader($formInputName, $savePath, $saveName, $allowedExtArray);
if ($uploader->getIsSuccessful()) {
// Optionally resize the image (if needed)
//$uploader->resizeImage(200, 200, 'crop');
$uploader->saveImage($uploader->getTargetPath(), $imageQuality);

// Get the final path after upload
$thumb = $uploader->getTargetPath();
$avatar = str_replace(ABSPATH . '/', '', $thumb);
}
}

// Prepare the user data for saving
$keys_values = array(
"avatar" => $avatar,
"local" => _post('city'),
"country" => _post('country'),
"name" => _post('name'),
"email" => _post('email'),
"password" => sha1(_post('password')),
"type" => "core"
);

// Add user to the database
$id = user::AddUser($keys_values);

// Display a success message
echo '<div class="msg-info">User ' . _post('name') . ' created with id: #' . $id . '</div>';
}
?>
<div class="row">
<form id="validate" class="form-horizontal styled" action="<?php echo admin_url('create-user');?>" enctype="multipart/form-data" method="post">
<fieldset><!-- Form -->
<?php echo '			
<div class="form-group form-material">
<label class="control-label">Name<span class="text-error">*</span></label>
<div class="controls">
<input type="text" name="name" class="validate[required] col-md-12" placeholder="Visible name"> 						
</div>
</div>						
<div class="form-group form-material">
<label class="control-label">'._lang("Email").'<span class="text-error">*</span></label>
<div class="controls">
<input type="text" name="email" class="validate[required] col-md-12" placeholder="'._lang("Email address").' "> 
</div>
</div>	
<div class="form-group form-material">
<label class="control-label">'._lang("Choose Password").' <span class="text-error">*</span></label>
<div class="controls">	
<input type="text" name="password" class="validate[required] col-md-12" value="'.uniqid().'"> 
</div>
</div>
<div class="form-group form-material">	
	<label class="control-label">'._lang("City").'</label>
	<div class="controls">	
<input type="text" name="city" class="col-md-12" placeholder="'._lang("City").'">

</div>
</div>
<div class="form-group form-material">							
<label class="control-label">'._lang("Country").'</label>
<div class="controls">							
<input type="text" name="country" class=" col-md-12" placeholder="'._lang("Country").'"> 

</div>
</div>
						 <label class="control-label">'._lang("Avatar").'</label>

<div class="form-group form-material form-material-file">	
	             <div class="controls">	  
<input type="text" class="form-control empty" readonly="" />				 
				 <input type="file" name="avatar" class="styled">
				 <label class="floating-label">Browse...</label>
	</div>
</div>                          
						<div class="row">
							
								<button class="btn btn-large btn-primary pull-right" type="submit">Create user</button>
							
						</div>';
?>		
					
</fieldset>						
</form>
</div>
