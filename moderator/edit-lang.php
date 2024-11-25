<?php
// Sanitize the lang-code or fallback to escaped GET parameter
$the_lang = (isset($_POST["lang-code"])) ? sanitize_language_code($_POST["lang-code"]) : sanitize_language_code(escape($_GET['id']));

// Fetch terms from the database
$en_terms = $db->get_results("SELECT DISTINCT term from " . DB_PREFIX . "langs limit 0,100000", ARRAY_A);

if ($en_terms) {
    $translated = lang_terms($the_lang);

    if (isset($_POST["lang-code"])) {
        $lang = $the_lang;
        $ar = array();
        $ar["language-name"] = htmlspecialchars($_POST["language-name"], ENT_QUOTES, 'UTF-8');  // Sanitize language name

        foreach ($_POST["term"] as $key => $value) {
            $ar[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');  // Sanitize term values
        }

        delete_language($lang);
        add_language($lang, $ar);

        // Output safely
        echo '<div class="msg-info">Language ' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . ' was updated.</div>';

        // Clean the cache
        $db->clean_cache();
        $translated = lang_terms($the_lang);
    }

    // Check if the languages directory is writable
    if (!is_writable(ABSPATH . '/storage/langs')) {
        echo '<div class="msg-warning">Languages folder (/lib/langs) is not writable. Langs can\'t be edited. </div>';
    }

    // Secure the language file path to prevent path traversal
    $lang_file = ABSPATH . '/storage/langs/' . basename($the_lang) . '.json';  // Prevent path traversal by using basename

    // Check if the language file exists
    if (!file_exists($lang_file)) {
        echo '<div class="msg-warning">Language ' . htmlspecialchars($lang_file, ENT_QUOTES, 'UTF-8') . ' doesn\'t exist yet.</div>';
    }

?>

    <div class="cleafix row">
        <form id="validate" class="form-horizontal styled" action="<?php echo admin_url('edit-lang'); ?>&id=<?php echo htmlspecialchars($the_lang, ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data" method="post">
            <div class="form-group form-material">

<label class="control-label"><i class="icon-globe"></i>Language code</label>
<div class="controls">
    <label>
        <input type="text" name="lang-code" class=" col-md-1" value="<?php echo htmlspecialchars($the_lang, ENT_QUOTES, 'UTF-8'); ?>" />
    </label>

</div>	
</div>
<div class="form-group form-material">
<label class="control-label"><i class="icon-font"></i>Language name</label>
<div class="controls">
<input type="text" name="language-name" class=" col-md-5" value="<?php echo $translated["language-name"]; ?>" /> 
</div>	
</div>
<div class="table-overflow top10">
                        <table class="table table-bordered table-checks">
                          <thead>
                              <tr>
                                 
                                  <th>Term</th>
                                  <th >Translation</th>
								  
                               </tr>
                          </thead>
                          <tbody>
						  <?php foreach ($en_terms as $t) {
                             if($t["term"] !== "language-name") {
						  ?>
                              <tr>
                                   <td><?php echo stripslashes($t["term"]); ?></td>
                                  <td>
								  <?php if(isset($translated[$t["term"]])) { ?>
								  <input type="text" name="term[<?php echo stripslashes($t["term"]); ?>]" class="col-md-12" value="<?php echo $translated[$t["term"]]; ?>" /> 	
								  <?php } else { ?>
								   <input type="text" name="term[<?php echo stripslashes($t["term"]); ?>]" class="col-md-12" value="<?php echo $t["term"]; ?>" /> 	
								  <?php } ?>
								  </td>
                                                                
                              </tr>
							  <?php }} ?>
						</tbody>  
</table>
</div>
<div class="form-group form-material">
<button class="btn btn-large btn-primary pull-right" type="submit">Update language</button>	
</div>	
</form>						
</div>						
<?php  } else {
echo '<div class="msg-warning">Missing the lang id.</div>';
}

?>
