<?php
include_once '../class/db.class.php';
require_once '../class/caches.class.php';

$db = new Database();
$caches = new Caches($database);

// get count of active caches
$cnt = $caches->getActiveCount();

// INSERT current cnt into [activecaches] table
$errmsg = '';
$db->query("INSERT INTO activecaches (cachecount) VALUES (:cnt)");
$db->bind(':cnt', $cnt);
$db->execute();

if (!empty($errmsg)) {
	echo $errmsg;
} 
else {
	echo 'There are '. $cnt .' active caches as of ' . date("Y-m-d", strtotime("now")) . '. Sweet!';
}
?>