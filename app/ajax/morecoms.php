<?php include_once('../../load.php');
$ob = toDb($_REQUEST['ob']);
if(not_empty($ob)) {
echo comments(htmlspecialchars($ob), 10000,0);
}
?>