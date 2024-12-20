<?php
if (isset($_GET['delete-cron'])) {
    // Sanitize the 'delete-cron' parameter to prevent XSS
    $cron = htmlspecialchars($_GET['delete-cron'], ENT_QUOTES, 'UTF-8');

    // Validate that the cron parameter is safe and corresponds to a valid cron job
    // For example, check if the cron parameter is a valid cron job ID (e.g., integer, alphanumeric, etc.)
    // You should implement your own validation logic based on your application's requirements
    if (is_valid_cron($cron)) {
        delete_cron($cron);
        echo '<div class="msg-info">Cron #' . $cron . ' deleted.</div>';
    } else {
        echo '<div class="msg-error">Invalid cron job ID.</div>';
    }
}

function getListChName($id) {
global $cachedb;	
$result = $cachedb->get_row("SELECT cat_name as name FROM ".DB_PREFIX."channels WHERE cat_id ='" . intval($id) . "'");
if($result) {
return 	$result->name;
}
return '';
}
if (isset($_POST['checkRow'])) {
    // Sanitize each value in 'checkRow' to prevent XSS
    $safe_checkRow = array_map(function($del) {
        return htmlspecialchars($del, ENT_QUOTES, 'UTF-8');
    }, $_POST['checkRow']);

    // Example validation: Ensure 'checkRow' contains only valid cron IDs (e.g., integers)
    $valid_crons = array_filter($safe_checkRow, function($del) {
        return is_numeric($del); // Only allow numeric cron IDs
    });

    // Perform deletion of crons
    foreach ($valid_crons as $del) {
        delete_cron($del);
    }

    // Output sanitized and validated values to prevent XSS
    if (count($valid_crons) > 0) {
        // Sanitize the cron IDs before outputting them
        $sanitized_crons = array_map('intval', $valid_crons); // Ensure they are integers
        $cron_list = implode(',', $sanitized_crons);

        // Escape the cron list to prevent XSS
        echo '<div class="msg-info">Crons #' . htmlspecialchars($cron_list, ENT_QUOTES, 'UTF-8') . ' deleted.</div>';
    } else {
        echo '<div class="msg-error">No valid cron IDs provided.</div>';
    }

}

if(isset($_GET["docreate"])) {
add_cron($_GET);

} else {
$count = $db->get_row("Select count(*) as nr from ".DB_PREFIX."crons");
$crons = $db->get_results("select * from ".DB_PREFIX."crons order by cron_id DESC ".this_limit()."");
$ps = admin_url('crons').'&p=';

$a = new pagination;	
$a->set_current(this_page());
$a->set_first_page(true);
$a->set_pages_items(7);
$a->set_per_page(bpp());
$a->set_values($count->nr);
$a->show_pages($ps);
?>
<form class="form-horizontal styled" action="<?php echo admin_url('crons');?>&p=<?php echo this_page();?>" enctype="multipart/form-data" method="post">
<h3>Automated video imports</h3>
<?php if($crons) { ?>
<div class="cleafix full"></div>
<div class="panel top10 multicheck">
<fieldset>
<div class="panel-heading">
<h3 class="panel-title">
<i class="material-icons">
autorenew
</i>
Crons
</h3>
<ul class="panel-actions">
    <li class="chbox">
	<div class="checkbox-custom checkbox-primary nopad"> <input type="checkbox" name="checkRows" class="check-all" /> <label for="checkRows"></label> </div>
	</li>
	<li>
<button class="btn btn-large btn-danger" type="submit"><?php echo _lang("Delete selected"); ?></button>
</li>
</ul>
</div>

<div class="panel-body" style="border-top: 1px solid #e4eaec; padding-top:15px;">
 <div class="multilist">
<ul class="list-group">
            
						  <?php foreach ($crons as $cron) {
							$cval = maybe_unserialize($cron->cron_value);
							//print_r($cval);
						  ?>
                             <li class="list-group-item">
	                         <div class="row">
                                <div class="inline-block img-hold">
                               <span class="pull-left mg-t-xs mg-r-md top20">
								 <input type="checkbox" name="checkRow[]" value="<?php echo $cron->cron_id; ?>" class="styled" /></td>
                                </span>
								<div class="inline-block right20 img-txt">
                             <h4> <?php echo stripslashes($cron->cron_name);?></h4>
							 <div class="img-det-text">
							 <i class="material-icons">timelapse</i> <?php if($cron->cron_lastrun > 1) { echo $cron->cron_lastrun; } else { echo "Never";} ?>
							 <i class="material-icons">timer</i> <?php echo stripslashes($cron->cron_period); ?>
							 <i class="material-icons">cached</i>  <?php echo stripslashes($cron->cron_pages); ?> max pages
							 <i class="material-icons">list</i> <?php echo $cval['bpp'];?> items / page
							 <i class="material-icons">sort_by_alpha</i> <?php echo $cval['order'];?>							 
							 <i class="material-icons">group_add</i> <?php echo getUserName($cval['owner']);?> 
							 <i class="material-icons">list_alt</i> <?php echo getListChName($cval['categ']);?> 

							 </div>
							  </div>
								  </div>
								                                 
								 
								<div class="btn-group btn-group-vertical pull-right">
								<a class="btn btn-sm btn-outline btn-default confirm" href="<?php echo admin_url('crons');?>&p=<?php echo this_page();?>&delete-cron=<?php echo $cron->cron_id;?>">
		                        <i class="material-icons mright10"> delete </i>  delete
								</a>
								<a class="btn btn-sm btn-raised btn-primary" href="<?php echo admin_url('edit-cron');?>&id=<?php echo $cron->cron_id;?>">
								<i class="material-icons mright10"> edit </i> modify
								</a>
								  
								  </div>
								  </div>
                              </li>
							  <?php } ?>
						</ul>
</div>
</div>
</div>					
</fieldset>					
</form>

<?php  $a->show_pages($ps); } else { 
echo '<div class="msg-note">No crons yet</div>';
} 
}
?>
