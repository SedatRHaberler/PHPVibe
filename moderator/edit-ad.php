<?php global $db;
if(isset($_GET['id']) || isset($_POST['id'])) {
$id= (isset($_GET['id'])) ? $_GET['id'] : $_POST['id'];
    if (isset($_POST['name'])) {
        $spot = (isset($_POST['ad_spot']) && !nullval($_POST['ad_spot'])) ? $_POST['ad_spot'] : $_POST['spot'];

        // Sanitize the ad content to prevent XSS
        $content = addslashes($_POST['content']);
        // Escape the title to prevent XSS
        $title = htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8');  // Safe escaping of user input

        // Execute the database query
        $db->query("UPDATE ".DB_PREFIX."ads SET
        `ad_spot` = '".$spot."',
        `ad_content` = '".$content."',
        `ad_title` = '".$db->escape($title)."'
        WHERE ad_id = $id");

        // Output the sanitized name
        echo '<div class="msg-info">Ad ' . $title . ' updated</div>';
    }

$ad = $db->get_row("select * from ".DB_PREFIX."ads WHERE ".DB_PREFIX."ads.ad_id =$id");

?>
<div class="row">
    <form id="validate" class="form-horizontal styled" action="<?php echo admin_url('edit-ad'); ?>&id=<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data" method="post">
<fieldset>
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
<div class="form-group form-material">
<label class="control-label"><i class="icon-copy"></i>Ad title</label>
<div class="controls">
    <input type="text" name="name" class="validate[required] col-md-12" value="<?php echo htmlspecialchars($ad->ad_title, ENT_QUOTES, 'UTF-8'); ?>"/>
<span class="help-block" id="limit-text">Only visible to you.</span>						
					
</div>	
</div>	
<div class="form-group form-material">
<label class="control-label"><i class="icon-th"></i>Ad spot</label>
<div class="controls">
    <input type="text" name="spot" class="col-md-12" value="<?php echo htmlspecialchars($ad->ad_spot, ENT_QUOTES, 'UTF-8'); ?>"/>
<br /> - OR SELECT EXISTING - <br />
<?php
$predef = "sidebar-start,before-videoplayer,after-videoplayer,sidebar-end,search-top,search-bottom,top-of-comments,related-videos-top,video-list-bottom,video-list-top,blog-sidebar-top,blog-sidebar-bottom,home-start,home-end,users-top,users-bottom,playlists-top,playlists-bottom,after-video-carousel,after-video-loop";
$categories = $db->get_results("SELECT distinct ad_spot FROM  ".DB_PREFIX."ads order by ad_spot asc limit 0,10000");
echo ' 
	<select data-placeholder="'._lang("Choose a spot:").'" name="ad_spot" id="clear-results" class="select" tabindex="2">
	<option value="" selected>-- None --</option>';
	$avails = explode(',',$predef);
	sort($avails);

	foreach ( $avails as $pf) {
	if(!nullval($pf)) {
echo'<option value="'.$pf.'">'.ucwords(str_replace('-',' ',$pf)).'</option>';
}
}
	if($categories) {
foreach ($categories as $cat) {	
if(!nullval($cat->ad_spot) && !in_array($cat->ad_spot,explode(',',$predef))) {
echo'<option value="'.$cat->ad_spot.'">'.ucwords(str_replace('-',' ',$cat->ad_spot)).'</option>';
}
	}
}


echo '</select>
	 ';
?>
<span class="help-block" id="limit-text">Important! You can position several ads in same spot, they will show up randomly</span>						
</div>	
</div>	

<div class="form-group form-material">
<label class="control-label"><strong>The Advertisement</strong> <br><em></em>Html/Js code</label>
<div class="controls">
    <label>
<textarea rows="5" cols="5" name="content" class="col-md-12" style="word-wrap: break-word; resize: horizontal; height: 88px;">
    <?php echo htmlspecialchars(stripslashes($ad->ad_content), ENT_QUOTES, 'UTF-8'); ?>
</textarea>
    </label>

    <span class="help-block" id="limit-text">Place here the actual html or js code that renders your ad (for example Google Adsense code, or other provider code. Or just use plain html to create your ad's output).</span>
</div>	
</div>

<div class="form-group form-material">
<button class="btn btn-large btn-primary pull-right" type="submit">Modify Ad</button>	
</div>	
</fieldset>						
</form>
</div>
<?php } else {
echo 'Something went wrong, id is missing!';	
} ?>
