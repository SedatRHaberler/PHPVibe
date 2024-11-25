<?php
if(isset($_GET['delete-video'])) {
unpublish_video(intval($_GET['delete-video']));
echo '<div class="msg-info">Video #'.intval($_GET['delete-video']).' was unpublished.</div>';
} 
if(isset($_GET['feature-video'])) {
$id = intval($_GET['feature-video']);
if($id){
$db->query("UPDATE ".DB_PREFIX."videos set featured = '1' where id='".intval($id)."'");
echo '<div class="msg-info">Video #'.$id.' was featured.</div>';
}
} 
if(isset($_POST['checkRow'])) {
foreach ($_POST['checkRow'] as $del) {
unpublish_video(intval($del));
}
    echo '<div class="msg-info">Videos #'.implode(',', array_map('htmlspecialchars', $_POST['checkRow'])).' unpublished.</div>';

}
$key = (isset($_GET['key'])) ? $_GET['key'] : $_POST['key'];
if(!$key || empty($key) ) {
echo "Please use the search form to find a video.";
} else {
$options = DB_PREFIX."videos.id,".DB_PREFIX."videos.title,".DB_PREFIX."videos.featured,".DB_PREFIX."videos.user_id,".DB_PREFIX."videos.thumb,".DB_PREFIX."videos.views,".DB_PREFIX."videos.liked,".DB_PREFIX."videos.duration,".DB_PREFIX."videos.nsfw";
       $vq = "select #what#, ".DB_PREFIX."users.name as owner FROM ".DB_PREFIX."videos LEFT JOIN ".DB_PREFIX."users ON ".DB_PREFIX."videos.user_id = ".DB_PREFIX."users.id 
	WHERE ( ".DB_PREFIX."videos.title like '%".$key."%' or ".DB_PREFIX."videos.description like '%".$key."%' or ".DB_PREFIX."videos.tags like '%".$key."%' ) and pub >  0
	   ORDER BY CASE WHEN ".DB_PREFIX."videos.title like '" .$key. "%' THEN 0
	           WHEN ".DB_PREFIX."videos.title like '%" .$key. "%' THEN 1
	           WHEN ".DB_PREFIX."videos.tags like '" .$key. "%' THEN 2
               WHEN ".DB_PREFIX."videos.tags like '%" .$key. "%' THEN 3		   
               WHEN ".DB_PREFIX."videos.description like '%" .$key. "%' THEN 4
			   WHEN ".DB_PREFIX."videos.tags like '%" .$key. "%' THEN 5
               ELSE 6
          END, title ";
$count = $db->get_row(str_replace("#what#", "count(*) as nr", $vq));
$videos = $db->get_results(str_replace("#what#", $options, $vq.this_limit()));

if($videos) {

$ps = admin_url('search-videos').'&key='.$key.'&p=';
$a = new pagination;	
$a->set_current(this_page());
$a->set_first_page(true);
$a->set_pages_items(7);
$a->set_per_page(bpp());
$a->set_values($count->nr);
?>
<div class="row">

    <div class="block full mar20_top mar10_bottom">
        <ul class="nav nav-tabs nav-tabs-line">
            <li class="disabled" role="presentation">
                <a href="javascript:void(0)">#<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?></a>
            </li>
            <li class="active">
                <a href="<?php echo htmlspecialchars($ps, ENT_QUOTES, 'UTF-8'); ?>"> Videos & Music</a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars(str_replace('sk=search-videos', 'sk=search-images', canonical()), ENT_QUOTES, 'UTF-8'); ?>">Images</a>
            </li>
        </ul>
    </div>

    <form class="form-horizontal styled" action="<?php echo htmlspecialchars($ps, ENT_QUOTES, 'UTF-8'); ?>&p=<?php echo htmlspecialchars(this_page(), ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data" method="post">

<div class="cleafix full"></div>
<fieldset>
<div class="table-overflow top10">
                        <table class="table table-bordered table-checks">
                          <thead>
                              <tr>
<th> <div class="checkbox-custom checkbox-danger"> <input type="checkbox" name="checkRows" class="check-all" /> <label for="checkRows"></label> </div>  </th>
                                  <th width="130px"><?php echo _lang("Thumb"); ?></th>
                                  <th width="35%"><?php echo _lang("Video"); ?></th>
                                  <th><?php echo _lang("Duration"); ?></th>
                                  <th><?php echo _lang("Likes"); ?></th>
                                  <th><?php echo _lang("Views"); ?></th>
								  <th><button class="btn btn-large btn-danger" type="submit"><?php echo _lang("Unpublish selected"); ?></button></th>
                              </tr>
                          </thead>
                          <tbody>
						  <?php foreach ($videos as $video) { ?>
                              <tr>
                                  <td><input type="checkbox" name="checkRow[]" value="<?php echo htmlspecialchars($video->id, ENT_QUOTES, 'UTF-8'); ?>" class="styled" /></td>
                                  <td><img src="<?php echo htmlspecialchars(thumb_fix($video->thumb), ENT_QUOTES, 'UTF-8'); ?>" style="width:130px; height:90px;"></td>
                                  <td><?php echo htmlspecialchars(_html($video->title), ENT_QUOTES, 'UTF-8'); ?></td>
                                  <td><?php echo htmlspecialchars(video_time($video->duration), ENT_QUOTES, 'UTF-8'); ?></td>
                                  <td><?php echo htmlspecialchars(_html($video->liked), ENT_QUOTES, 'UTF-8'); ?></td>
                                  <td><?php echo htmlspecialchars(_html($video->views), ENT_QUOTES, 'UTF-8'); ?></td>
                                  <td>
                                      <div class="btn-group">
                                          <a class="btn btn-sm btn-outline btn-danger" href="<?php echo htmlspecialchars($ps, ENT_QUOTES, 'UTF-8'); ?><?php echo htmlspecialchars(this_page(), ENT_QUOTES, 'UTF-8'); ?>&delete-video=<?php echo htmlspecialchars($video->id, ENT_QUOTES, 'UTF-8'); ?>"><i class="icon-trash" style="margin-right:5px;"></i></a>
                                          <a class="btn btn-sm btn-outline btn-info" href="<?php echo htmlspecialchars(admin_url('edit-video'), ENT_QUOTES, 'UTF-8'); ?>&vid=<?php echo htmlspecialchars($video->id, ENT_QUOTES, 'UTF-8'); ?>"><i class="icon-edit" style="margin-right:5px;"></i><?php echo _lang("Edit"); ?></a>
                                          <?php if($video->featured < 1) { ?>
                                              <a class="btn btn-sm btn-outline btn-default" href="<?php echo htmlspecialchars($ps, ENT_QUOTES, 'UTF-8'); ?><?php echo htmlspecialchars(this_page(), ENT_QUOTES, 'UTF-8'); ?>&feature-video=<?php echo htmlspecialchars($video->id, ENT_QUOTES, 'UTF-8'); ?>" title="Feature"><i class="icon-star" style="margin-right:5px;"></i></a>
                                          <?php } else { ?>
                                              <a class="btn btn-sm btn-outline btn-info" href="<?php echo htmlspecialchars(admin_url('videos'), ENT_QUOTES, 'UTF-8'); ?>&p=<?php echo htmlspecialchars(this_page(), ENT_QUOTES, 'UTF-8'); ?>&feature-video=<?php echo htmlspecialchars($video->id, ENT_QUOTES, 'UTF-8'); ?>" title="Unfeature"><i class="icon-star-half" style="margin-right:5px;"></i></a>
                                          <?php } ?>
                                          <a class="btn btn-sm btn-outline btn-primary" target="_blank" href="<?php echo htmlspecialchars(video_url($video->id, $video->title), ENT_QUOTES, 'UTF-8'); ?>"><i class="icon-check" style="margin-right:5px;"></i><?php echo _lang("View"); ?></a>
                                      </div>
                                  </td>
                              </tr>
                          <?php } ?>
						</tbody>  
</table>
</div>						
</fieldset>					
</form>
    <?php
    // Sanitize the input before using it.
    $ps = htmlspecialchars($ps, ENT_QUOTES, 'UTF-8');

    // Call the method with the sanitized $ps.
    $a->show_pages($ps);}}
    ?>

</div>