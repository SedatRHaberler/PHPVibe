<?php
if(isset($_GET['delete-image'])) {
unpublish_image(intval($_GET['delete-image']));
} 
if(isset($_GET['feature-image'])) {
$id = intval($_GET['feature-image']);
if($id){
$db->query("UPDATE ".DB_PREFIX."images set featured = '1' where id='".intval($id)."'");
echo '<div class="msg-info">Image #'.$id.' was featured.</div>';
}
}
if (isset($_POST['checkRow'])) {
    // Sanitize and validate input
    $sanitized_ids = array_map('intval', $_POST['checkRow']); // Convert all values to integers

    // Perform the unpublish action
    foreach ($sanitized_ids as $del) {
        unpublish_image($del);
    }

    // Safely display the unpublished image IDs
    $safe_ids = array_map(function($id) {
        return htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    }, $sanitized_ids);

    echo '<div class="msg-info">Images #' . implode(', ', $safe_ids) . ' unpublished.</div>';
}
$key = (isset($_GET['key'])) ? $_GET['key'] : $_POST['key'];
if(!$key || empty($key) ) {
echo "Please use the search form to find a image.";
} else {
$options = DB_PREFIX."images.id,".DB_PREFIX."images.title,".DB_PREFIX."images.featured,".DB_PREFIX."images.user_id,".DB_PREFIX."images.thumb,".DB_PREFIX."images.views,".DB_PREFIX."images.liked";
       $vq = "select #what#, ".DB_PREFIX."users.name as owner FROM ".DB_PREFIX."images LEFT JOIN ".DB_PREFIX."users ON ".DB_PREFIX."images.user_id = ".DB_PREFIX."users.id 
	WHERE ( ".DB_PREFIX."images.title like '%".$key."%' or ".DB_PREFIX."images.description like '%".$key."%' or ".DB_PREFIX."images.tags like '%".$key."%' )
	   ORDER BY CASE WHEN ".DB_PREFIX."images.title like '" .$key. "%' THEN 0
	           WHEN ".DB_PREFIX."images.title like '%" .$key. "%' THEN 1
	           WHEN ".DB_PREFIX."images.tags like '" .$key. "%' THEN 2
               WHEN ".DB_PREFIX."images.tags like '%" .$key. "%' THEN 3		   
               WHEN ".DB_PREFIX."images.description like '%" .$key. "%' THEN 4
			   WHEN ".DB_PREFIX."images.tags like '%" .$key. "%' THEN 5
               ELSE 6
          END, title ";
$count = $db->get_row(str_replace("#what#", "count(*) as nr", $vq));
$images = $db->get_results(str_replace("#what#", $options, $vq.this_limit()));
if($images) {

$ps = admin_url('search-images').'&key='.$key.'&p=';
$a = new pagination;	
$a->set_current(this_page());
$a->set_first_page(true);
$a->set_pages_items(7);
$a->set_per_page(bpp());
$a->set_values($count->nr);
?>
<div class="block full mar20_top mar10_bottom">
    <ul class="nav nav-tabs nav-tabs-line">
        <li class="disabled" role="presentation">
            <a href="javascript:void(0)">#<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?></a>
        </li>
        <li>
            <a href="<?php echo htmlspecialchars(str_replace('sk=search-images', 'sk=search-videos', canonical()), ENT_QUOTES, 'UTF-8'); ?>">Videos & Music</a>
        </li>
        <li class="active">
            <a href="<?php echo htmlspecialchars(canonical(), ENT_QUOTES, 'UTF-8'); ?>">Images</a>
        </li>
    </ul>
</div>


<div class="row">
    <form class="form-horizontal styled" action="<?php echo htmlspecialchars($ps, ENT_QUOTES, 'UTF-8'); ?><?php echo htmlspecialchars(this_page(), ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data" method="post">

<div class="cleafix full"></div>
<fieldset>
<div class="table-overflow top10">
                        <table class="table table-bordered table-checks">
                          <thead>
                              <tr>
<th> <div class="checkbox-custom checkbox-danger"> <input type="checkbox" name="checkRows" class="check-all" /> <label for="checkRows"></label> </div>  </th>
                                  <th width="130px"><?php echo _lang("Thumb"); ?></th>
                                  <th width="35%"><?php echo _lang("Image"); ?></th>
                                  <th><?php echo _lang("Likes"); ?></th>
                                  <th><?php echo _lang("Views"); ?></th>
								  <th><button class="btn btn-large btn-danger" type="submit"><?php echo _lang("Unpublish selected"); ?></button></th>
                              </tr>
                          </thead>
                          <tbody>
						  <?php foreach ($images as $image) { ?>
                              <tr>
                                  <td>
                                      <input type="checkbox" name="checkRow[]" value="<?php echo htmlspecialchars($image->id, ENT_QUOTES, 'UTF-8'); ?>" class="styled" />
                                  </td>
                                  <td>
                                      <img src="<?php echo htmlspecialchars(thumb_fix($image->thumb), ENT_QUOTES, 'UTF-8'); ?>" style="width:130px; height:90px;">
                                  </td>
                                  <td><?php echo htmlspecialchars(_html($image->title), ENT_QUOTES, 'UTF-8'); ?></td>
                                  <td><?php echo htmlspecialchars(_html($image->liked), ENT_QUOTES, 'UTF-8'); ?></td>
                                  <td><?php echo htmlspecialchars(_html($image->views), ENT_QUOTES, 'UTF-8'); ?></td>
                                  <td>
                                      <div class="btn-group">
                                          <a class="btn btn-sm btn-outline btn-danger" href="<?php echo htmlspecialchars(admin_url('images') . '&p=' . this_page() . '&delete-image=' . $image->id, ENT_QUOTES, 'UTF-8'); ?>">
                                              <i class="icon-trash" style="margin-right:5px;"></i>
                                          </a>
                                          <a class="btn btn-sm btn-outline btn-info" href="<?php echo htmlspecialchars(admin_url('edit-image') . '&vid=' . $image->id, ENT_QUOTES, 'UTF-8'); ?>">
                                              <i class="icon-edit" style="margin-right:5px;"></i><?php echo htmlspecialchars(_lang("Edit"), ENT_QUOTES, 'UTF-8'); ?>
                                          </a>
                                          <?php if ($image->featured < 1) { ?>
                                              <a class="btn btn-sm btn-outline btn-default" href="<?php echo htmlspecialchars(admin_url('images') . '&p=' . this_page() . '&feature-image=' . $image->id, ENT_QUOTES, 'UTF-8'); ?>" title="Feature">
                                                  <i class="icon-star" style="margin-right:5px;"></i>
                                              </a>
                                          <?php } else { ?>
                                              <a class="btn btn-sm btn-outline btn-info" href="<?php echo htmlspecialchars(admin_url('images') . '&p=' . this_page() . '&feature-image=' . $image->id, ENT_QUOTES, 'UTF-8'); ?>" title="Unfeature">
                                                  <i class="icon-star-half" style="margin-right:5px;"></i>
                                              </a>
                                          <?php } ?>
                                          <a class="btn btn-sm btn-outline btn-primary" target="_blank" href="<?php echo htmlspecialchars(image_url($image->id, $image->title), ENT_QUOTES, 'UTF-8'); ?>">
                                              <i class="icon-check" style="margin-right:5px;"></i><?php echo htmlspecialchars(_lang("View"), ENT_QUOTES, 'UTF-8'); ?>
                                          </a>
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
$ps = isset($_GET['ps']) ? htmlspecialchars($_GET['ps'], ENT_QUOTES, 'UTF-8') : '';
$a->show_pages($ps); }

} ?>
</div>