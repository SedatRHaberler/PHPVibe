<?php if(isset($_GET['ac']) && $_GET['ac'] ="remove-logo"){
update_option('site-logo', '');
 $db->clean_cache();
}
if(isset($_POST['update_options_now'])){
foreach($_POST as $key=>$value)
{
if($key !== "site-logo") {
  update_option($key, $value);
}
}
  echo '<div class="msg-info">Configuration options have been updated.</div>';

// Set logo
    if (isset($_FILES['site-logo']) && !empty($_FILES['site-logo']['name'])) {
        // Sanitize the file name to prevent path traversal
        $fileName = basename(urldecode($_FILES['site-logo']['name'])); // Get only the base file name (no directory path)
        $fileName = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileName); // Remove any unsafe characters

        // Get the file extension and validate it
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif']; // Allowed extensions
        if (!in_array($extension, $allowedExtensions)) {
            echo '<div class="msg-warning">Invalid file type for logo upload.</div>';
            return;
        }

        // Generate a unique name for the file to avoid overwriting
        $newFileName = 'site-logo-' . uniqid() . '.' . $extension;

        // Define the target directory for uploads
        $targetDir = ABSPATH . '/uploads/';

        // Construct the full path for the uploaded file
        $thumb = $targetDir . $newFileName;

        // Verify that the file path is within the target directory
        if (realpath($thumb) !== false && strpos(realpath($thumb), realpath($targetDir)) === 0) {
            // Attempt to move the uploaded file
            if (move_uploaded_file($_FILES['site-logo']['tmp_name'], $thumb)) {
                // Update the logo option with the relative file path
                $sthumb = str_replace(ABSPATH . '/', '', $thumb);
                update_option('site-logo', $sthumb);
                echo '<div class="msg-info">Logo uploaded successfully.</div>';
            } else {
                echo '<div class="msg-warning">Logo upload failed.</div>';
            }
        } else {
            echo '<div class="msg-warning">Invalid file path. Upload failed.</div>';
        }
    }

  $db->clean_cache();
}
$all_options = get_all_options();
?>

<div class="row">
<h3>EzSql Caching</h3>
For now this settings reside in vibe_config.
<pre>
/** MySQL cache timeout */
/** For how many hours should queries be cached? **/
define( 'DB_CACHE', '1' );
</pre>
<h3>FullCache</h3>
For now this settings reside in app/classes/fullcache.php
<pre>
define('FULLCACHE_DEFAULT_TTL', 10800);
</pre>
Note: fullcache duration is in seconds.

Further fullcache manipulation can be done in load.php
<pre>
/* Cache it for visitors */
$cacheable = array("video","videos","search","profile","api");
if(!isset($_SESSION['user_id']) && in_array(com(), $cacheable)) {
require_once( CNC.'/fullcache.php' );
FullCache::Encode($_SERVER['REQUEST_URI']);
FullCache::Live();
}
</pre>
<div class="msg-info">For now moving this settings to the admin panel has proven impossible without affecting server load.</div>
</div>
