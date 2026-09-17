<?php
// คืนค่า DB จากไฟล์แบ็กอัพ (admin เท่านั้น) — ตรวจไฟล์ก่อนทับ + สำรองก้อนปัจจุบันอัตโนมัติ
require __DIR__.'/lib/db.php';
function back($ok, $msg) {
  echo '<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>คืนค่าข้อมูล</title><script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script></head><body class="bg-gray-50 min-h-screen"><div class="max-w-md mx-auto px-4 py-10 text-center">'
    .'<p class="'.($ok?'text-green-600':'text-red-600').' font-bold">'.h($msg).'</p><a href="admin.php" class="inline-block mt-4 bg-gray-900 text-white rounded-full px-5 py-2 text-sm">← กลับหลังบ้าน</a></div></body></html>';
  exit;
}
if (!is_admin()) back(false, 'เฉพาะ admin');
if (empty($_FILES['db']) || $_FILES['db']['error'] !== UPLOAD_ERR_OK) back(false, 'อัปโหลดไม่สำเร็จ');
$f = $_FILES['db'];
if ($f['size'] > 20*1024*1024) back(false, 'ไฟล์ใหญ่เกิน 20MB');
if ($f['size'] < 100) back(false, 'ไฟล์เล็กผิดปกติ ไม่น่าใช่ DB');
// ตรวจว่าเป็น SQLite + มีตารางหลักครบ (เปิดไฟล์ tmp อ่านอย่างเดียว)
$head = file_get_contents($f['tmp_name'], false, null, 0, 16);
if ($head !== "SQLite format 3\0") back(false, 'ไฟล์นี้ไม่ใช่ SQLite — ยกเลิกเพื่อความปลอดภัย');
try {
  $t = new PDO('sqlite:'.$f['tmp_name']);
  $tables = $t->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
  $t = null;
} catch (Exception $e) { back(false, 'อ่านไฟล์ DB ไม่ได้ — ยกเลิก'); }
foreach (['sports','teams','matches','users'] as $need)
  if (!in_array($need, $tables)) back(false, 'ไฟล์นี้ไม่มีตาราง '.$need.' — ไม่ใช่ DB ของระบบนี้');
// สำรองก้อนปัจจุบันก่อนทับเสมอ
$bak = __DIR__.'/data/app-'.date('Ymd-Hi').'.sqlite.bak';
if (!copy(DB_FILE, $bak)) back(false, 'สำรองก้อนปัจจุบันไม่ได้ — ยกเลิกเพื่อความปลอดภัย');
if (!copy($f['tmp_name'], DB_FILE)) back(false, 'คืนค่าไม่สำเร็จ (ก้อนเดิมยังอยู่ครบ)');
back(true, 'คืนค่าข้อมูลสำเร็จ ✅ สำรองก้อนเดิมไว้ที่ '.basename($bak));
