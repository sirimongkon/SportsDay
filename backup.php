<?php
require __DIR__.'/lib/db.php';
if (!is_admin()) die('เฉพาะ admin');
$f = DB_FILE;
if (!is_file($f)) die('ไม่พบไฟล์ข้อมูล');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="sportsday-'.date('Ymd-Hi').'.sqlite"');
header('Content-Length: '.filesize($f));
readfile($f);
