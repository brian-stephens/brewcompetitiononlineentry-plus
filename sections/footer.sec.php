<?php 
/**
 * Module:      footer.sec.php 
 * Description: This module houses the footer displayed on all pages. 
 * 
 */

/*
// Redirect if directly accessed
if ((!isset($_SESSION['prefs'.$prefix_session])) || ((isset($_SESSION['prefs'.$prefix_session])) && (!isset($base_url)))) {
    $redirect = "../../index.php";
    $redirect_go_to = sprintf("Location: %s", $redirect);
    header($redirect_go_to);
    exit();
}
*/

$footer = "";

if ((!empty($current_version_display_append)) && (strpos($current_version_display, $current_version_display_append) !== false)) {
	$new_version_display = str_replace($current_version_display_append, "", $current_version_display);
	$current_version_display = $new_version_display."<small>".$current_version_display_append."</small>";
}

if(!empty($_SESSION['contestName'])) $footer .= "<span class=\"hidden-sm hidden-md\">".$_SESSION['contestName']." &ndash; </span>";

$footer .= "based on <a href=\"http://www.brewingcompetitions.com\" target=\"_blank\">BCOE&amp;M</a> ".$current_version_display." &ndash; customized";

$footer .= " <span class=\"far fa-copyright fa-xs\"></span>2009-".date('Y');

if (((TESTING) || (DEBUG)) && (isset($starttime))) {
	$mtime = microtime(); 
	$mtime = explode(" ",$mtime); 
	$mtime = $mtime[1] + $mtime[0]; 
	$endtime = $mtime; 
	$totaltime = ($endtime - $starttime); 
	$footer .= " &ndash; Page Created: ".number_format($totaltime, 3)."s"; 
}

echo $footer;
?>