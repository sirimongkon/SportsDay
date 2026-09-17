<?php
// ponytail: ไฟล์เดียวจบสำหรับ DB+auth ไม่แยก model/service ถ้าโตค่อยแยก
session_start();
define('DB_FILE', __DIR__ . '/../data/app.sqlite');

function db() {
  static $pdo = null;
  if ($pdo) return $pdo;
  $needInit = !file_exists(DB_FILE);
  if (!in_array('sqlite', PDO::getAvailableDrivers(), true))
    die('เซิร์ฟเวอร์ยังไม่เปิด pdo_sqlite — แจ้งผู้ดูแลโฮสต์เปิด extension pdo_sqlite แล้วรีเฟรช');
  $pdo = new PDO('sqlite:' . DB_FILE);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  if ($needInit) init_db($pdo);
  migrate($pdo); // อัปเกรด DB เก่าแบบไม่ทิ้งข้อมูล
  return $pdo;
}
// เติมคอลัมน์/ตารางที่ขาดให้ DB เก่า (idempotent)
function migrate($pdo) {
  $cols = fn($t) => array_column($pdo->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC), 'name');
  if (!in_array('divisions', $cols('sports'))) $pdo->exec("ALTER TABLE sports ADD COLUMN divisions TEXT DEFAULT '[]'");
  if (!in_array('third_point', $cols('sports'))) $pdo->exec("ALTER TABLE sports ADD COLUMN third_point INTEGER DEFAULT 1");
  if (!in_array('short', $cols('teams'))) $pdo->exec("ALTER TABLE teams ADD COLUMN short TEXT DEFAULT ''");
  if (!in_array('grp', $cols('teams'))) $pdo->exec("ALTER TABLE teams ADD COLUMN grp TEXT DEFAULT ''");
  if (!in_array('logo', $cols('teams'))) $pdo->exec("ALTER TABLE teams ADD COLUMN logo TEXT DEFAULT ''");
  if (!in_array('division', $cols('matches'))) $pdo->exec("ALTER TABLE matches ADD COLUMN division TEXT DEFAULT ''");
  if (!in_array('play_at', $cols('matches'))) $pdo->exec("ALTER TABLE matches ADD COLUMN play_at TEXT DEFAULT ''");
  if (!in_array('venue', $cols('matches'))) $pdo->exec("ALTER TABLE matches ADD COLUMN venue TEXT DEFAULT ''");
  if (!in_array('map_url', $cols('matches'))) $pdo->exec("ALTER TABLE matches ADD COLUMN map_url TEXT DEFAULT ''");
  $pdo->exec("CREATE TABLE IF NOT EXISTS placements(id INTEGER PRIMARY KEY AUTOINCREMENT, match_id INTEGER, team_id INTEGER, place INTEGER)");
  $pdo->exec("CREATE TABLE IF NOT EXISTS audit(id INTEGER PRIMARY KEY AUTOINCREMENT, match_id INTEGER, user_id INTEGER, username TEXT DEFAULT '', action TEXT DEFAULT '', detail TEXT DEFAULT '', created_at INTEGER DEFAULT 0)");
  $pdo->exec("CREATE TABLE IF NOT EXISTS settings(k TEXT PRIMARY KEY, v TEXT DEFAULT '')");
  $pdo->exec("CREATE TABLE IF NOT EXISTS announcements(id INTEGER PRIMARY KEY AUTOINCREMENT, text TEXT DEFAULT '', active INTEGER DEFAULT 1, updated_at INTEGER DEFAULT 0)");
}
function init_db($pdo) {
  $pdo->exec("CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, role TEXT DEFAULT 'manager', username TEXT UNIQUE, pass_hash TEXT, sport_id INTEGER NULL, failed INTEGER DEFAULT 0, locked_until INTEGER DEFAULT 0);
  CREATE TABLE IF NOT EXISTS sports(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, type TEXT DEFAULT 'points', best_of INTEGER DEFAULT 3, win_point INTEGER DEFAULT 3, draw_point INTEGER DEFAULT 1, divisions TEXT DEFAULT '[]', third_point INTEGER DEFAULT 1);
  CREATE TABLE IF NOT EXISTS teams(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, color TEXT DEFAULT '#6366f1', short TEXT DEFAULT '', grp TEXT DEFAULT '', logo TEXT DEFAULT '');
  CREATE TABLE IF NOT EXISTS matches(id INTEGER PRIMARY KEY AUTOINCREMENT, sport_id INTEGER, team_a INTEGER, team_b INTEGER, score_a INTEGER DEFAULT 0, score_b INTEGER DEFAULT 0, status TEXT DEFAULT 'scheduled', locked INTEGER DEFAULT 0, qr_token TEXT UNIQUE, updated_at INTEGER DEFAULT 0, division TEXT DEFAULT '');
  CREATE TABLE IF NOT EXISTS sets(id INTEGER PRIMARY KEY AUTOINCREMENT, match_id INTEGER, set_no INTEGER, a INTEGER DEFAULT 0, b INTEGER DEFAULT 0);
  CREATE TABLE IF NOT EXISTS placements(id INTEGER PRIMARY KEY AUTOINCREMENT, match_id INTEGER, team_id INTEGER, place INTEGER);");
  // seed 12 ชนิดกีฬา (แก้/ลบได้จากหน้า admin)
  $seed = [
    ['ฟุตซอล','points',0,3,1,'["ชาย","หญิง"]'],
    ['บาสเกตบอล','points',0,2,0,'["ชาย","หญิง"]'],
    ['วอลเลย์บอล','sets',5,3,0,'["ชาย","หญิง"]'],
    ['เปตอง','points',0,3,1,'["ทีมชาย 3 คน","ทีมหญิง 3 คน","คู่ผสม"]'],
    ['เทเบิลเทนนิส','sets',3,3,0,'["เดี่ยว","คู่","คู่ผสม"]'],
    ['แบดมินตัน','sets',3,3,0,'["เดี่ยว","คู่","คู่ผสม"]'],
    ['กรีฑา','rank',0,3,2,'["100 m ชาย","100 m หญิง","200 m ชาย","200 m หญิง","400 m ชาย","400 m หญิง","4x100 m ชาย","4x100 m หญิง","4x100 m LGBTQ","8x50 m บุคลากร","1500 m ชาย","1500 m หญิง"]'],
    ['โต้วาที','rank',0,3,2,'["ทั่วไป"]'],
    ['เต้นแอโรบิค','rank',0,3,2,'["ทั่วไป"]'],
    ['เต้นบาสโลบ','rank',0,3,2,'["ทั่วไป"]'],
    ['ทำอาหารเพื่อสุขภาพ','rank',0,3,2,'["ทั่วไป"]'],
    ['หัวเราะ','rank',0,3,2,'["ทั่วไป"]'],
  ];
  $st = $pdo->prepare("INSERT OR IGNORE INTO sports(name,type,best_of,win_point,draw_point,divisions,third_point) VALUES(?,?,?,?,?,?,1)");
  foreach ($seed as $s) $st->execute([$s[0],$s[1],$s[2],$s[3],$s[4],$s[5]]);
  // seed 8 สถาบัน (ชื่อย่อ + สาย)
  $teams = [
    ['วิทยาลัยการสาธารณสุขสิริธร จังหวัดขอนแก่น','วสส.ขอนแก่น','4','#EF4444'],
    ['วิทยาลัยพยาบาลบรมราชชนนี ขอนแก่น','พยบ.ขอนแก่น','4','#3B82F6'],
    ['วิทยาลัยพยาบาลศรีมหาสารคาม','พยบ.มหาสารคาม','4','#22C55E'],
    ['วิทยาลัยพยาบาลบรมราชชนนี อุดรธานี','พยบ.อุดรธานี','4','#F59E0B'],
    ['วิทยาลัยพยาบาลบรมราชชนนี นครราชสีมา','พยบ.นครราชสีมา','5','#8B5CF6'],
    ['วิทยาลัยพยาบาลบรมราชชนนี สุรินทร์','พยบ.สุรินทร์','5','#EC4899'],
    ['วิทยาลัยพยาบาลบรมราชชนนี สรรพสิทธิประสงค์','พยบ.สรรพสิทธิประสงค์','5','#14B8A6'],
    ['วิทยาลัยการสาธารณสุขสิริธร จังหวัดอุบลราชธานี','วสส.อุบลฯ','5','#F97316'],
  ];
  $st = $pdo->prepare("INSERT OR IGNORE INTO teams(name,short,grp,color) VALUES(?,?,?,?)");
  foreach ($teams as $t) $st->execute($t);
  if (!row("SELECT * FROM users WHERE username='admin'")) {
    $pdo->prepare("INSERT INTO users(name,role,username,pass_hash) VALUES(?,?,?,?)")
      ->execute(['ผู้ดูแลระบบ','admin','admin', password_hash('Admin1234', PASSWORD_DEFAULT)]);
  }
}
function row($sql, $p=[]) { $s=db()->prepare($sql); $s->execute($p); return $s->fetch(PDO::FETCH_ASSOC); }
function rows($sql, $p=[]) { $s=db()->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
function q($sql, $p=[]) { return db()->prepare($sql)->execute($p); }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function j($v) { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); } // ปลอดภัยใน onclick="..."/'...'
function base_url() {
  $host = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
  return (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $host . dirname($_SERVER['SCRIPT_NAME']);
}
// login กลาง (นับครั้งผิด + ล็อก 5 นาที) — ใช้ทุกฟอร์ม login กัน brute-force
function try_login($u, $p) {
  $r = row("SELECT * FROM users WHERE username=?", [trim($u)]);
  if (!$r) return [null, 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
  if ($r['locked_until'] > time()) return [null, 'ล็อกชั่วคราว ลองใหม่ใน '.ceil(($r['locked_until']-time())/60).' นาที'];
  if (!password_verify((string)$p, $r['pass_hash'])) {
    $f = $r['failed']+1;
    q("UPDATE users SET failed=?, locked_until=? WHERE id=?", [$f, $f>=5?time()+300:0, $r['id']]);
    return [null, 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง (ผิด '.$f.'/5)'];
  }
  q("UPDATE users SET failed=0, locked_until=0 WHERE id=?", [$r['id']]);
  $_SESSION['uid'] = $r['id'];
  return [$r, ''];
}
function new_token() { return bin2hex(random_bytes(16)); } // 32 ตัวอักษร เดาไม่ได้
function current_user() { return $_SESSION['uid'] ?? null ? row("SELECT u.*, s.name AS sport_name FROM users u LEFT JOIN sports s ON s.id=u.sport_id WHERE u.id=?", [$_SESSION['uid']]) : null; }
function is_admin() { $u = current_user(); return $u && $u['role'] === 'admin'; }
function can_edit_match($m) {
  $u = current_user();
  if ($u && $u['role'] === 'admin') return true; // admin แก้ได้เสมอ (รวมปลดล็อก)
  if ($u && $u['role'] === 'manager' && (int)$u['sport_id'] === (int)$m['sport_id'] && !(int)$m['locked']) return true;
  return false;
}
function tname($t) { return ($t['short'] ?? '') !== '' ? $t['short'] : $t['name']; } // ชื่อย่อก่อน ถ้าไม่มีใช้ชื่อเต็ม
// ป้ายประเภท: ชาย=ฟ้า หญิง=ชมพู LGBTQ=รุ้ง ผสม=ม่วง อื่น=เทา (inline style ล้วน)
function div_badge($division) {
  $d = trim((string)$division);
  if ($d === '') return '';
  if (stripos($d, 'lgbtq') !== false)
    $st = 'background:linear-gradient(90deg,#ef4444,#f59e0b,#22c55e,#3b82f6,#8b5cf6);color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.4)';
  elseif (mb_strpos($d, 'หญิง') !== false) $st = 'background:#ffe4e6;color:#be123c';
  elseif (mb_strpos($d, 'ชาย') !== false) $st = 'background:#dbeafe;color:#1d4ed8';
  elseif (mb_strpos($d, 'ผสม') !== false) $st = 'background:#ede9fe;color:#6d28d9';
  else $st = 'background:#f1f5f9;color:#475569';
  return '<span style="'.$st.'" class="text-xs px-2 py-1 rounded-full font-semibold">'.h($d).'</span>';
}
// โลโก้สถาบัน: มีไฟล์โชว์รูป ไม่มีใช้วงกลมสีเดิม
function tlogo($t, $px=40, $center=true) {
  $l = $t['logo'] ?? '';
  $mc = $center ? 'mx-auto ' : '';
  if ($l !== '' && is_file(__DIR__.'/../'.$l))
    return '<img src="'.h($l).'" alt="" style="width:'.$px.'px;height:'.$px.'px" class="'.$mc.'rounded-full object-cover bg-white ring-1 ring-black/10">';
  return '<div class="'.$mc.'rounded-full" style="width:'.$px.'px;height:'.$px.'px;background:'.h($t['color'] ?? '#999').'"></div>';
}
// คะแนนรวม: สายปะทะใช้ ชนะ/เสมอ/แพ้, สายอันดับใช้ ที่1/2/3 → ทอง/เงิน/ทองแดงนับในช่อง w/d/l
function standings() {
  $teams = rows("SELECT * FROM teams ORDER BY name");
  $out = [];
  foreach ($teams as $t) $out[$t['id']] = ['team'=>$t,'p'=>0,'w'=>0,'d'=>0,'l'=>0,'pts'=>0,'for'=>0,'against'=>0];
  foreach (rows("SELECT m.*, s.win_point, s.draw_point FROM matches m JOIN sports s ON s.id=m.sport_id WHERE m.status='finished' AND s.type!='rank'") as $m) {
    if (!isset($out[$m['team_a']]) || !isset($out[$m['team_b']])) continue;
    $A=&$out[$m['team_a']]; $B=&$out[$m['team_b']];
    $A['p']++; $B['p']++; $A['for']+=$m['score_a']; $A['against']+=$m['score_b']; $B['for']+=$m['score_b']; $B['against']+=$m['score_a'];
    if ($m['score_a']>$m['score_b']) { $A['w']++; $B['l']++; $A['pts']+=(int)$m['win_point']; }
    elseif ($m['score_b']>$m['score_a']) { $B['w']++; $A['l']++; $B['pts']+=(int)$m['win_point']; }
    else { $A['d']++; $B['d']++; $A['pts']+=(int)$m['draw_point']; $B['pts']+=(int)$m['draw_point']; }
  }
  foreach (rows("SELECT p.team_id, p.place, s.win_point, s.draw_point, s.third_point FROM placements p JOIN matches m ON m.id=p.match_id JOIN sports s ON s.id=m.sport_id WHERE m.status='finished'") as $r) {
    if (!isset($out[$r['team_id']])) continue;
    $T=&$out[$r['team_id']]; $T['p']++;
    if ($r['place']==1) { $T['w']++; $T['pts']+=(int)$r['win_point']; }
    elseif ($r['place']==2) { $T['d']++; $T['pts']+=(int)$r['draw_point']; }
    else { $T['l']++; $T['pts']+=(int)$r['third_point']; }
  }
  usort($out, fn($x,$y)=> [$y['pts'],$y['w'],$x['against']-$x['for']] <=> [$x['pts'],$x['w'],$y['against']-$y['for']]);
  if (setting('board_mode', 'points') === 'medals')
    usort($out, fn($x,$y)=> [$y['w'],$y['d'],$y['l'],$x['team']['name']] <=> [$x['w'],$x['d'],$x['l'],$y['team']['name']]); // โหมดเหรียญ: เรียงทอง→เงิน→ทองแดง
  return array_values($out);
}
function match_sets($mid) { return rows("SELECT * FROM sets WHERE match_id=? ORDER BY set_no", [$mid]); }
function match_places($mid) { return rows("SELECT p.*, t.name, t.short, t.logo FROM placements p JOIN teams t ON t.id=p.team_id WHERE p.match_id=? ORDER BY p.place", [$mid]); }
function set_winner($a,$b) { return $a>$b?'A':($b>$a?'B':'D'); }
function sport_divs($s) { $d = json_decode($s['divisions'] ?? '[]', true); return is_array($d) ? $d : []; }
// ตั้งค่าระบบแบบ key-value (เช่น board_mode: points|medals)
function setting($k, $def='') { $r = row("SELECT v FROM settings WHERE k=?", [$k]); return $r ? $r['v'] : $def; }
function set_setting($k, $v) { q("INSERT OR REPLACE INTO settings(k,v) VALUES(?,?)", [$k, $v]); }
// ประวัติการแก้สกอร์: ใครทำอะไรกับแมตช์ไหน (QR token ใช้ username 'QR')
function alog($mid, $action, $detail='') {
  $u = current_user();
  q("INSERT INTO audit(match_id,user_id,username,action,detail,created_at) VALUES(?,?,?,?,?,?)",
    [$mid, $u['id'] ?? null, $u ? $u['username'] : 'QR', $action, $detail, time()]);
}
// วันที่ พ.ศ.: 'YYYY-MM-DDTHH:MM' → '16/09/2569 09:00' (ว่างคืน '')
function th_when($m) {
  $s = trim($m['play_at'] ?? '');
  if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/', $s, $x)) return '';
  return $x[3].'/'.$x[2].'/'.($x[1]+543).' '.$x[4].':'.$x[5];
}
function th_day($ymd) { return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $x) ? $x[3].'/'.$x[2].'/'.substr($x[1]+543, 2) : $ymd; } // ปุ่มกรอง: 16/09/69
// ปุ่มนำทาง: ใช้ลิงก์ที่ปัก > ค้นหาจากชื่อสนาม > ไม่มีทั้งคู่คืน '' (กัน javascript: ทั้งขาเข้า API และตรงนี้)
function map_link($m) {
  $u = trim($m['map_url'] ?? '');
  if ($u === '' && trim($m['venue'] ?? '') !== '')
    $u = 'https://www.google.com/maps/search/?api=1&query=' . urlencode(trim($m['venue']));
  if (strpos($u, 'http://') !== 0 && strpos($u, 'https://') !== 0) return '';
  return '<a href="' . h($u) . '" target="_blank" rel="noopener" class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-full px-3 py-1 font-semibold">📍 นำทาง</a>';
}
