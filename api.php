<?php
require __DIR__.'/lib/db.php';
header('Content-Type: application/json; charset=utf-8');
$act = $_POST['act'] ?? '';
function fail($m){ echo json_encode(['ok'=>false,'msg'=>$m]); exit; }
function ok($d=[]){ echo json_encode(['ok'=>true]+$d); exit; }

// --- login (กันเดาสุ่ม: ทายผิด 5 ครั้งล็อก 5 นาที) ---
if ($act==='login') {
  [$r, $err] = try_login($_POST['username'] ?? '', $_POST['password'] ?? '');
  if (!$r) fail($err);
  ok(['role'=>$r['role']]);
}
if ($act==='logout') { session_destroy(); ok(); }

$me = current_user();
if (!$me && !in_array($act,['save','finish'])) fail('กรุณาเข้าสู่ระบบ');

// --- บันทึกสกอร์ (manager เฉพาะกีฬาตัวเอง + แมตช์ไม่ล็อก / admin ทุกกรณี) ---
if ($act==='save') {
  $m = row("SELECT * FROM matches WHERE id=?", [$_POST['match_id']??0]);
  if (!$m) fail('ไม่พบแมตช์');
  $viaToken = hash_equals((string)$m['qr_token'], (string)($_POST['token']??'___'));
  $allow = $viaToken || can_edit_match($m);
  if (!$allow) fail('ไม่มีสิทธิ์');
  if ((int)$m['locked'] && !$viaToken && ($me['role']??'')!=='admin') fail('แมตช์ล็อกแล้ว ติดต่อ admin');
  if ((int)$m['locked'] && $viaToken) fail('แมตช์จบแล้ว (ล็อก) ติดต่อ admin ปลดล็อก');
  $sport = row("SELECT * FROM sports WHERE id=?", [$m['sport_id']]);
  $detail = '';
  if ($sport['type']==='rank') {
    // รับ placements เป็น JSON: [{team, place}] ที่ 1/2/3
    $pl = json_decode($_POST['placements']??'[]', true) ?: [];
    $seen = [];
    foreach ($pl as $r) {
      $tid = (int)($r['team']??0); $place = (int)($r['place']??0);
      if (!$tid || $place<1 || $place>3) fail('ข้อมูลอันดับไม่ถูกต้อง');
      if (isset($seen[$tid]) || in_array($place, array_column($seen, 'p'))) fail('สถาบันหรืออันดับซ้ำกัน');
      if (!row("SELECT id FROM teams WHERE id=?", [$tid])) fail('ไม่พบสถาบัน');
      $seen[$tid] = ['p'=>$place];
    }
    q("DELETE FROM placements WHERE match_id=?", [$m['id']]);
    foreach ($seen as $tid => $v) q("INSERT INTO placements(match_id,team_id,place) VALUES(?,?,?)", [$m['id'],$tid,$v['p']]);
    q("UPDATE matches SET status='live', updated_at=? WHERE id=?", [time(), $m['id']]);
    $detail = 'บันทึกอันดับ';
  } elseif ($sport['type']==='sets') {
    // รับ sets เป็น JSON: [{a,b}] บอกว่าเซ็ตนั้นใครได้แต้ม
    $sets = json_decode($_POST['sets']??'[]', true) ?: [];
    q("DELETE FROM sets WHERE match_id=?", [$m['id']]);
    $wa=0; $wb=0; $n=0;
    foreach ($sets as $s) {
      $n++; $a=max(0,(int)($s['a']??0)); $b=max(0,(int)($s['b']??0));
      q("INSERT INTO sets(match_id,set_no,a,b) VALUES(?,?,?,?)", [$m['id'],$n,$a,$b]);
      if ($a>$b) $wa++; elseif ($b>$a) $wb++;
    }
    q("UPDATE matches SET score_a=?, score_b=?, status='live', updated_at=? WHERE id=?", [$wa,$wb,time(),$m['id']]);
    $detail = "เซ็ต $wa:$wb";
  } else {
    q("UPDATE matches SET score_a=?, score_b=?, status='live', updated_at=? WHERE id=?",
      [max(0,(int)$_POST['score_a']), max(0,(int)$_POST['score_b']), time(), $m['id']]);
    $detail = max(0,(int)$_POST['score_a']).':'.max(0,(int)$_POST['score_b']);
  }
  alog($m['id'], 'save', $detail.($viaToken?' (QR)':''));
  ok();
}
if ($act==='finish') {
  $m = row("SELECT * FROM matches WHERE id=?", [$_POST['match_id']??0]);
  if (!$m) fail('ไม่พบแมตช์');
  $viaToken = hash_equals((string)$m['qr_token'], (string)($_POST['token']??'___'));
  if (!$viaToken && !can_edit_match($m)) fail('ไม่มีสิทธิ์');
  if ((int)$m['locked'] && ($me['role']??'')!=='admin') fail('แมตช์จบแล้ว (ล็อก) ติดต่อ admin ปลดล็อก');
  // ponytail: กดจบ = ล็อกทันที ปลดได้เฉพาะ admin
  q("UPDATE matches SET status='finished', locked=1, updated_at=? WHERE id=?", [time(), $m['id']]);
  alog($m['id'], 'finish', $m['score_a'].':'.$m['score_b'].($viaToken?' (QR)':''));
  ok();
}
if ($act==='unlock') {
  if (!is_admin()) fail('เฉพาะ admin');
  q("UPDATE matches SET locked=0, updated_at=? WHERE id=?", [time(), $_POST['match_id']??0]);
  alog($_POST['match_id'] ?? 0, 'unlock', '');
  ok();
}

// --- admin only: จัดการทุกอย่าง ---
if (!is_admin() && in_array($act,['sport_add','sport_set','sport_del','sport_set_div','team_add','team_set','team_logo','team_del','match_add','match_set','match_del','mgr_add','mgr_set','mgr_del','mgr_reset','admin_pass','token_regen','ann_add','ann_set','ann_toggle','ann_del','board_mode']))
  fail('เฉพาะ admin');
// โหมดตารางคะแนน: points (แต้ม) | medals (เหรียญอย่างเดียว)
if ($act==='board_mode') {
  $m = $_POST['mode'] ?? '';
  if (!in_array($m, ['points','medals'])) fail('โหมดไม่ถูกต้อง');
  set_setting('board_mode', $m); ok();
}
// --- ประกาศประชาสัมพันธ์ ---
if ($act==='ann_add') {
  $t = trim($_POST['text'] ?? ''); if ($t==='') fail('ใส่ข้อความประกาศด้วย');
  q("INSERT INTO announcements(text,active,updated_at) VALUES(?,1,?)", [$t, time()]); ok();
}
if ($act==='ann_set') {
  $t = trim($_POST['text'] ?? ''); if ($t==='') fail('ใส่ข้อความประกาศด้วย');
  q("UPDATE announcements SET text=?, updated_at=? WHERE id=?", [$t, time(), $_POST['id'] ?? 0]); ok();
}
if ($act==='ann_toggle') {
  $r = row("SELECT * FROM announcements WHERE id=?", [$_POST['id'] ?? 0]); if (!$r) fail('ไม่พบประกาศ');
  q("UPDATE announcements SET active=?, updated_at=? WHERE id=?", [(int)$r['active']?0:1, time(), $r['id']]); ok();
}
if ($act==='ann_del') { q("DELETE FROM announcements WHERE id=?", [$_POST['id']]); ok(); }
if ($act==='sport_add') {
  $type = $_POST['type'] ?? 'points'; if (!in_array($type,['points','sets','rank'])) $type='points';
  $name = trim($_POST['name'] ?? ''); if ($name==='') fail('ใส่ชื่อกีฬาด้วย');
  $divs = array_values(array_filter(array_map('trim', explode(',', $_POST['divisions'] ?? ''))));
  try { q("INSERT INTO sports(name,type,best_of,win_point,draw_point,divisions) VALUES(?,?,?,?,?,?)",
    [$name, $type, (int)($_POST['best_of']?:3), (int)($_POST['win_point']??3), (int)($_POST['draw_point']??1), json_encode($divs, JSON_UNESCAPED_UNICODE)]); }
  catch(Exception $e){ fail('ชื่อกีฬานี้มีแล้ว'); }
  ok();
}
if ($act==='sport_set') {
  $old = row("SELECT * FROM sports WHERE id=?", [$_POST['id']]);
  if (!$old) fail('ไม่พบกีฬา');
  $type = $_POST['type'] ?? $old['type']; if (!in_array($type,['points','sets','rank'])) $type=$old['type'];
  if ($type !== $old['type']) {
    $c = row("SELECT COUNT(*) c FROM matches WHERE sport_id=?", [$_POST['id']])['c'];
    if ($c > 0) fail('เปลี่ยนประเภทไม่ได้ มีแมตช์อยู่ '.$c.' คู่ — ลบแมตช์ก่อน');
  }
  $divs = array_values(array_filter(array_map('trim', explode(',', $_POST['divisions'] ?? ''))));
  q("UPDATE sports SET name=?, type=?, best_of=?, win_point=?, draw_point=?, third_point=?, divisions=? WHERE id=?",
    [trim($_POST['name'])?:$old['name'], $type, (int)($_POST['best_of']??$old['best_of']), (int)($_POST['win_point']??$old['win_point']), (int)($_POST['draw_point']??$old['draw_point']), (int)($_POST['third_point']??$old['third_point']), json_encode($divs, JSON_UNESCAPED_UNICODE), $_POST['id']]); ok();
}
if ($act==='sport_del') {
  $c = row("SELECT COUNT(*) c FROM matches WHERE sport_id=?", [$_POST['id']])['c'];
  if ($c > 0) fail('ลบไม่ได้ มีแมตช์ของกีฬานี้อยู่ '.$c.' คู่ — ลบแมตช์ก่อน');
  q("DELETE FROM sports WHERE id=?", [$_POST['id']]); ok();
}
if ($act==='sport_set_div') {
  $divs = array_values(array_filter(array_map('trim', explode(',', $_POST['divisions'] ?? ''))));
  q("UPDATE sports SET divisions=? WHERE id=?", [json_encode($divs, JSON_UNESCAPED_UNICODE), $_POST['id']]); ok();
}
if ($act==='team_add') {
  $name = trim($_POST['name'] ?? ''); if ($name==='') fail('ใส่ชื่อสถาบันด้วย');
  try { q("INSERT INTO teams(name,short,grp,color) VALUES(?,?,?,?)", [$name, trim($_POST['short']??''), trim($_POST['grp']??''), $_POST['color']?:'#6366f1']); }
  catch(Exception $e){ fail('ชื่อสถาบันนี้มีแล้ว'); }
  ok(['id'=>(int)db()->lastInsertId()]);
}
if ($act==='team_set') { q("UPDATE teams SET name=?, short=?, grp=?, color=? WHERE id=?", [trim($_POST['name']), trim($_POST['short']??''), trim($_POST['grp']??''), $_POST['color']?:'#6366f1', $_POST['id']]); ok(['id'=>(int)$_POST['id']]); }
// โลโก้สถาบัน: ตรวจว่าเป็นรูปจริง + รับเฉพาะ JPG/PNG/GIF/WebP ไม่เกิน 2MB
if ($act==='team_logo') {
  $id = (int)($_POST['id'] ?? 0);
  $t = row("SELECT * FROM teams WHERE id=?", [$id]); if (!$t) fail('ไม่พบสถาบัน');
  $dir = __DIR__.'/uploads'; if (!is_dir($dir)) mkdir($dir, 0755, true);
  if (($_POST['remove'] ?? '') === '1') {
    foreach (glob($dir.'/team_'.$id.'.*') ?: [] as $old) unlink($old);
    q("UPDATE teams SET logo='' WHERE id=?", [$id]); ok();
  }
  if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) fail('อัปโหลดไม่สำเร็จ');
  $f = $_FILES['logo'];
  if ($f['size'] > 2*1024*1024) fail('ไฟล์ใหญ่เกิน 2MB');
  $info = @getimagesize($f['tmp_name']); if (!$info) fail('ไฟล์ไม่ใช่รูปภาพ');
  $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$info['mime']] ?? null;
  if (!$ext) fail('รับเฉพาะ JPG/PNG/GIF/WebP');
  foreach (glob($dir.'/team_'.$id.'.*') ?: [] as $old) unlink($old);
  $name = 'uploads/team_'.$id.'.'.$ext;
  if (!move_uploaded_file($f['tmp_name'], __DIR__.'/'.$name)) fail('บันทึกไฟล์ไม่ได้ — ตรวจสิทธิ์เขียนโฟลเดอร์ uploads/');
  q("UPDATE teams SET logo=? WHERE id=?", [$name, $id]); ok(['logo'=>$name]);
}
if ($act==='team_del') {
  $c = row("SELECT COUNT(*) c FROM matches WHERE team_a=? OR team_b=?", [$_POST['id'], $_POST['id']])['c'];
  $c += row("SELECT COUNT(*) c FROM placements WHERE team_id=?", [$_POST['id']])['c'];
  if ($c > 0) fail('ลบไม่ได้ มีแมตช์/ผลอันดับอ้างสถาบันนี้อยู่ — ลบแมตช์ก่อน');
  q("DELETE FROM teams WHERE id=?", [$_POST['id']]); ok();
}
if ($act==='match_add') {
  $sp = row("SELECT * FROM sports WHERE id=?", [$_POST['sport_id'] ?? 0]);
  if (!$sp) fail('ไม่พบกีฬา');
  $ta = $_POST['team_a'] ?: null; $tb = $_POST['team_b'] ?: null;
  if ($sp['type'] !== 'rank') {
    if (!$ta || !$tb) fail('เลือกทั้ง 2 สถาบัน');
    if ($ta == $tb) fail('เลือกสถาบันซ้ำกัน');
    if (!row("SELECT id FROM teams WHERE id=?", [$ta]) || !row("SELECT id FROM teams WHERE id=?", [$tb])) fail('ไม่พบสถาบัน');
  }
  $map = trim($_POST['map_url'] ?? '');
  if ($map !== '' && strpos($map, 'http://') !== 0 && strpos($map, 'https://') !== 0) fail('ลิงก์แผนที่ต้องขึ้นต้นด้วย http(s)://');
  q("INSERT INTO matches(sport_id,team_a,team_b,status,qr_token,updated_at,division,play_at,venue,map_url) VALUES(?,?,?,'scheduled',?,?,?,?,?,?)",
    [$sp['id'], $ta, $tb, new_token(), time(), trim($_POST['division']??''), trim($_POST['play_at']??''), trim($_POST['venue']??''), $map]); ok();
}
if ($act==='match_del') { q("DELETE FROM matches WHERE id=?", [$_POST['id']]); q("DELETE FROM sets WHERE match_id=?", [$_POST['id']]); q("DELETE FROM placements WHERE match_id=?", [$_POST['id']]); ok(); }
// แก้แมตช์: ได้แค่ ประเภท/วัน/สนาม/ลิงก์แผนที่ (ไม่แตะคู่ทีม+กีฬา กันแต้มรวมเพี้ยน)
// ข้อยกเว้น: แมตช์เปล่ารอบชิง (ทีมว่าง) ให้ admin ใส่คู่ทีมได้ครั้งเดียวตอนรู้ผู้ชนะ
if ($act==='match_set') {
  $cur = row("SELECT * FROM matches WHERE id=?", [$_POST['id'] ?? 0]);
  if (!$cur) fail('ไม่พบแมตช์');
  $map = trim($_POST['map_url'] ?? '');
  if ($map !== '' && strpos($map, 'http://') !== 0 && strpos($map, 'https://') !== 0) fail('ลิงก์แผนที่ต้องขึ้นต้นด้วย http(s)://');
  $teamSql = ''; $teamP = [];
  if (empty($cur['team_a']) || empty($cur['team_b'])) {
    $ta = (int)($_POST['team_a'] ?? 0); $tb = (int)($_POST['team_b'] ?? 0);
    if ($ta && $tb) {
      if ($ta == $tb) fail('เลือกสถาบันซ้ำกัน');
      if (!row("SELECT id FROM teams WHERE id=?", [$ta]) || !row("SELECT id FROM teams WHERE id=?", [$tb])) fail('ไม่พบสถาบัน');
      $teamSql = ', team_a=?, team_b=?'; $teamP = [$ta, $tb];
    }
  }
  q("UPDATE matches SET division=?, play_at=?, venue=?, map_url=?, updated_at=?$teamSql WHERE id=?",
    array_merge([trim($_POST['division'] ?? ''), trim($_POST['play_at'] ?? ''), trim($_POST['venue'] ?? ''), $map, time()], $teamP, [$_POST['id']])); ok();
}
if ($act==='mgr_add') {
  $u=trim($_POST['username']??''); $p=$_POST['password']??'';
  if (trim($_POST['name'] ?? '')==='' || $u==='') fail('ใส่ชื่อและ username ด้วย');
  if (!row("SELECT id FROM sports WHERE id=?", [$_POST['sport_id'] ?? 0])) fail('ไม่พบกีฬา');
  if (strlen($p)<6 || !preg_match('/[A-Za-z]/',$p) || !preg_match('/[0-9]/',$p)) fail('รหัสต้องยาว 6+ ตัว มีทั้งอักษรและตัวเลข');
  try { q("INSERT INTO users(name,role,username,pass_hash,sport_id) VALUES(?,?,?, ?,?)",
    [trim($_POST['name']), 'manager', $u, password_hash($p, PASSWORD_DEFAULT), $_POST['sport_id']]); }
  catch(Exception $e){ fail('username นี้มีแล้ว'); }
  ok();
}
if ($act==='mgr_del') { q("DELETE FROM users WHERE id=? AND role='manager'", [$_POST['id']]); ok(); }
if ($act==='mgr_set') {
  $r = row("SELECT * FROM users WHERE id=? AND role='manager'", [$_POST['id'] ?? 0]);
  if (!$r) fail('ไม่พบผู้จัดการ');
  $name = trim($_POST['name'] ?? ''); if ($name==='') fail('ใส่ชื่อด้วย');
  $u = trim($_POST['username'] ?? ''); if ($u==='') fail('ใส่ username ด้วย');
  if ($u !== $r['username'] && row("SELECT id FROM users WHERE username=?", [$u])) fail('username นี้มีแล้ว');
  if (!row("SELECT id FROM sports WHERE id=?", [$_POST['sport_id'] ?? 0])) fail('ไม่พบกีฬา');
  $p = $_POST['password'] ?? '';
  if ($p !== '') {
    if (strlen($p)<6 || !preg_match('/[A-Za-z]/',$p) || !preg_match('/[0-9]/',$p)) fail('รหัสต้องยาว 6+ ตัว มีทั้งอักษรและตัวเลข');
    q("UPDATE users SET name=?, username=?, pass_hash=?, sport_id=?, failed=0, locked_until=0 WHERE id=?",
      [$name, $u, password_hash($p, PASSWORD_DEFAULT), $_POST['sport_id'], $r['id']]);
  } else {
    q("UPDATE users SET name=?, username=?, sport_id=? WHERE id=?", [$name, $u, $_POST['sport_id'], $r['id']]);
  }
  ok();
}
if ($act==='mgr_reset') {
  $p=$_POST['password']??'';
  if (strlen($p)<6 || !preg_match('/[A-Za-z]/',$p) || !preg_match('/[0-9]/',$p)) fail('รหัสต้องยาว 6+ ตัว มีทั้งอักษรและตัวเลข');
  q("UPDATE users SET pass_hash=?, failed=0, locked_until=0 WHERE id=? AND role='manager'", [password_hash($p, PASSWORD_DEFAULT), $_POST['id']]); ok();
}
if ($act==='token_regen') { $t=new_token(); q("UPDATE matches SET qr_token=? WHERE id=?", [$t, $_POST['id']]); ok(['token'=>$t]); }
if ($act==='admin_pass') { // เปลี่ยนรหัสตัวเอง (admin เท่านั้น + ต้องใส่รหัสเดิมถูก)
  $me = current_user();
  $r = $me ? row("SELECT * FROM users WHERE id=?", [$me['id']]) : null;
  if (!$r || !password_verify((string)($_POST['old'] ?? ''), $r['pass_hash'])) fail('รหัสเดิมไม่ถูกต้อง');
  $p = $_POST['password'] ?? '';
  if (strlen($p)<6 || !preg_match('/[A-Za-z]/',$p) || !preg_match('/[0-9]/',$p)) fail('รหัสต้องยาว 6+ ตัว มีทั้งอักษรและตัวเลข');
  q("UPDATE users SET pass_hash=? WHERE id=?", [password_hash($p, PASSWORD_DEFAULT), $me['id']]); ok();
}
fail('unknown action');
