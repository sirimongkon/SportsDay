<?php require __DIR__.'/lib/db.php';
$me = current_user(); $err='';
if (!$me && ($_POST['login']??'')) {
  [$r, $err] = try_login($_POST['username'] ?? '', $_POST['password'] ?? '');
  if ($r && $r['role']!=='admin') { $err='บัญชีนี้ไม่ใช่ admin'; $r=null; session_destroy(); }
  if ($r) { header("Location: admin.php"); exit; }
}
if ($me && $me['role']!=='admin') { $err='บัญชีนี้ไม่ใช่ admin'; $me=null; session_destroy(); }
if ($me) {
  $sports=rows("SELECT * FROM sports ORDER BY id"); $teams=rows("SELECT * FROM teams ORDER BY id");
  $mgrs=rows("SELECT u.*, s.name AS sport_name FROM users u LEFT JOIN sports s ON s.id=u.sport_id WHERE u.role='manager' ORDER BY u.id");
  $matches=rows("SELECT m.*, s.name AS sport_name, s.type AS sport_type, ta.name AS na, ta.short AS sa, tb.name AS nb, tb.short AS sb FROM matches m JOIN sports s ON s.id=m.sport_id LEFT JOIN teams ta ON ta.id=m.team_a LEFT JOIN teams tb ON tb.id=m.team_b ORDER BY m.id DESC");
  $mcount=[]; foreach(rows("SELECT sport_id, COUNT(*) c FROM matches GROUP BY sport_id") as $c) $mcount[$c['sport_id']]=$c['c'];
  $anns=rows("SELECT * FROM announcements ORDER BY id DESC");
  $divmap=[]; foreach($sports as $s) $divmap[$s['id']]=sport_divs($s);
  $base=base_url();
}
?>
<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>หลังบ้าน admin • คชรัตน์เกมส์</title><link rel="icon" type="image/png" href="uploads/logo/วพบ สุรินทร์.png"><script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;800&display=swap" rel="stylesheet"><style>body{font-family:'Prompt',sans-serif}</style></head>
<body class="bg-gray-50 min-h-screen"><div class="max-w-6xl mx-auto px-4 py-6">
<div class="flex gap-2 text-sm">
<a href="index.php" class="text-gray-500 hover:underline px-1 py-1">← หน้าแรก</a>
<a href="score.php" class="bg-orange-500 hover:bg-orange-600 text-white font-bold rounded-full px-4 py-1">🖊️ กรอกสกอร์</a>
</div>
<h1 class="text-2xl font-extrabold">⚙️ หลังบ้าน admin <span class="text-sm font-normal text-gray-500">จัดการได้ทุกอย่าง</span></h1>
<?php if(!$me): ?>
<form method="post" class="bg-white rounded-2xl shadow p-5 mt-4 max-w-sm space-y-3">
<?php if($err): ?><p class="text-red-600 text-sm"><?= h($err) ?></p><?php endif; ?>
<p class="text-sm text-gray-500"></p>
<input name="username" placeholder="admin" class="w-full border rounded-xl px-3 py-2">
<input name="password" type="password" placeholder="รหัสผ่าน" class="w-full border rounded-xl px-3 py-2">
<button name="login" value="1" class="w-full bg-gray-900 text-white rounded-xl py-2 font-bold">เข้า admin</button></form>
<?php else: ?>
<p class="text-sm mt-1">สวัสดี <?= h($me['name']) ?> • <a href="#" class="text-red-500" onclick="fetch('api.php',{method:'POST',body:new URLSearchParams({act:'logout'})}).then(()=>location.href='index.php')">ออกจากระบบ</a> • <a href="backup.php" class="text-blue-600">📥 สำรองข้อมูล DB</a></p>
<form onsubmit="return chPass(event)" class="mt-2 flex flex-wrap items-center gap-2 text-sm bg-white rounded-xl shadow px-3 py-2">
<span class="font-bold">🔑 เปลี่ยนรหัส admin:</span>
<input id="pw_old" type="password" placeholder="รหัสเดิม" autocomplete="current-password" class="border rounded-lg px-2 py-1 text-sm">
<input id="pw_new" type="password" placeholder="รหัสใหม่ 6+ ตัว อักษร+ตัวเลข" autocomplete="new-password" class="border rounded-lg px-2 py-1 text-sm">
<button class="bg-gray-900 text-white rounded-lg px-3 py-1 text-sm">เปลี่ยนรหัส</button></form>
<form action="restore.php" method="post" enctype="multipart/form-data" onsubmit="return confirm('คืนค่าจะทับข้อมูลปัจจุบันทั้งหมด (ระบบสำรองก้อนเดิมให้อัตโนมัติ) — ทำต่อ?')" class="mt-2 flex flex-wrap items-center gap-2 text-sm bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
<span>♻️ คืนค่าจากไฟล์สำรอง:</span><input name="db" type="file" accept=".sqlite,.db" required class="text-xs">
<button class="bg-amber-500 text-white rounded-lg px-3 py-1 text-xs font-bold">คืนค่า</button></form>
<?php $bmode = setting('board_mode', 'points'); ?>
<div class="mt-2 flex flex-wrap items-center gap-3 text-sm bg-white rounded-xl shadow px-3 py-2">
<span class="font-bold">📊 ตารางคะแนนโชว์แบบ:</span>
<label class="flex items-center gap-1"><input type="radio" name="bmode" value="points" <?= $bmode!=='medals'?'checked':'' ?> onchange="setBoard(this.value)"> แต้มรวม (แบบปัจจุบัน)</label>
<label class="flex items-center gap-1"><input type="radio" name="bmode" value="medals" <?= $bmode==='medals'?'checked':'' ?> onchange="setBoard(this.value)"> เหรียญอย่างเดียว (ไม่โชว์แต้ม)</label></div>
<div class="grid md:grid-cols-2 gap-4 mt-4">
<!-- ประกาศ -->
<div class="bg-white rounded-2xl shadow p-4 md:col-span-2">
<h2 class="font-bold">📢 ประกาศประชาสัมพันธ์ (วิ่งเหนือตารางคะแนน)</h2>
<form onsubmit="return addAnn(event)" class="flex gap-2 mt-2 text-sm">
<input type="hidden" id="a_id" value="">
<input id="a_text" placeholder="พิมพ์ประกาศ เช่น พิธีเปิด 9:00 ที่สนามกลาง" class="flex-1 border rounded-lg px-2 py-1">
<button id="a_btn" class="bg-gray-900 text-white rounded-lg px-3 py-1">+ เพิ่ม</button>
<button id="a_cancel" type="button" onclick="cancelAnn()" class="hidden bg-gray-200 rounded-lg px-3 py-1">ยกเลิก</button></form>
<div class="mt-2 space-y-2 text-sm"><?php foreach($anns as $a): ?>
<div class="border rounded-xl px-3 py-2 flex items-center gap-2 <?= $a['active']?'':'opacity-50' ?>">
<span class="text-xs px-2 py-0.5 rounded-full <?= $a['active']?'bg-green-100 text-green-700':'bg-gray-200 text-gray-500' ?>"><?= $a['active']?'แสดง':'ซ่อน' ?></span>
<span class="flex-1"><?= h($a['text']) ?></span>
<button onclick="toggleAnn(<?= $a['id'] ?>)" class="text-xs bg-gray-100 rounded-lg px-2 py-1"><?= $a['active']?'ซ่อน':'แสดง' ?></button>
<button onclick='editAnn(<?= (int)$a['id'] ?>,<?= j($a['text']) ?>)' class="text-xs text-blue-600">แก้</button>
<button onclick="del('ann_del',<?= $a['id'] ?>)" class="text-xs text-red-500">ลบ</button>
</div><?php endforeach;
if(!$anns) echo '<p class="text-xs text-gray-400">ยังไม่มีประกาศ — เพิ่มแล้วจะวิ่งโชว์หน้าแรกทันที</p>'; ?></div></div>
<!-- แมตช์ + QR -->
<div class="bg-white rounded-2xl shadow p-4 md:col-span-2">
<h2 class="font-bold">🏟️ แมตช์/รายการ + QR (สแกนแล้วกรอกเลย ไม่ต้องใส่รหัส)</h2>
<form onsubmit="return addMatch(event)" class="flex flex-wrap gap-2 mt-2 text-sm items-center">
<input type="hidden" id="m_id" value="">
<select id="m_sport" onchange="syncDiv()" class="border rounded-lg px-2 py-1"><?php foreach($sports as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?></select>
<select id="m_div" class="border rounded-lg px-2 py-1"></select>
<select id="m_a" onchange="syncTeams()" class="border rounded-lg px-2 py-1"><option value="">—</option><?php foreach($teams as $t): ?><option value="<?= $t['id'] ?>"><?= h(tname($t)) ?></option><?php endforeach; ?></select><span>vs</span>
<select id="m_b" onchange="syncTeams()" class="border rounded-lg px-2 py-1"><option value="">—</option><?php foreach($teams as $t): ?><option value="<?= $t['id'] ?>"><?= h(tname($t)) ?></option><?php endforeach; ?></select>
<input id="m_when" type="datetime-local" title="วัน/เวลาแข่ง" class="border rounded-lg px-2 py-1">
<input id="m_venue" placeholder="สนาม" class="border rounded-lg px-2 py-1 w-24">
<input id="m_map" placeholder="ลิงก์ Google Maps (ว่างได้)" class="border rounded-lg px-2 py-1 w-44">
<button id="m_btn" class="bg-orange-500 text-white rounded-lg px-3 py-1 font-bold">+ เพิ่ม</button>
<button id="m_cancel" type="button" onclick="cancelMatch()" class="hidden bg-gray-200 rounded-lg px-3 py-1">ยกเลิก</button></form>
<p class="text-xs text-gray-400 mt-1">สายอันดับ (กรีฑา/เต้น/ทำอาหาร…) ไม่ต้องเลือกคู่ — เลือกประเภทแล้วกดเพิ่มได้เลย • ลิงก์แผนที่: ค้นสถานที่ใน Google Maps → Share → Copy link มาวาง</p>
<div class="mt-3 space-y-2 text-sm">
<?php foreach($matches as $m): $url=$base.'/score.php?t='.$m['qr_token'];
$title = $m['sport_type']==='rank' ? $m['sport_name'].' '.($m['division']!==''?$m['division']:'') : $m['sport_name'].' '.($m['sa']?:$m['na']).' vs '.($m['sb']?:$m['nb']); ?>
<div class="border rounded-xl px-3 py-2 flex flex-wrap items-center gap-2">
<b><?= h($m['sport_name']) ?></b>
<?= div_badge($m['division']) ?>
<?php if($m['sport_type']!=='rank'): ?><span><?= h(($m['sa']?:$m['na']) ?? '?') ?> <?= $m['score_a'] ?>:<?= $m['score_b'] ?> <?= h(($m['sb']?:$m['nb']) ?? '?') ?></span><?php endif; ?>
<?php if(trim($m['play_at']??'')!=='' || trim($m['venue']??'')!==''): ?><span class="text-xs text-gray-500">📅 <?= h(th_when($m)) ?><?= trim($m['venue']??'')!=='' ? ' 📍 '.h($m['venue']) : '' ?></span><?php endif; ?>
<span class="text-xs px-2 py-0.5 rounded-full <?= $m['locked']?'bg-gray-900 text-white':'bg-green-100 text-green-700' ?>"><?= $m['status'] ?><?= $m['locked']?' 🔒':'' ?></span>
<?php if($m['locked']): ?><button onclick="unlock(<?= $m['id'] ?>)" class="bg-amber-500 text-white text-xs rounded-lg px-2 py-1">🔓 ปลดล็อก</button><?php endif; ?>
<button onclick='showQR(<?= j($url) ?>,<?= j($title) ?>)' class="bg-gray-100 text-xs rounded-lg px-2 py-1">🖨️ QR</button>
<button onclick="regen(<?= $m['id'] ?>)" class="text-xs text-gray-500">สุ่ม QR ใหม่</button>
<a href="score.php?match=<?= $m['id'] ?>" class="text-xs text-blue-600">เปิดกรอก</a>
<button onclick='editMatch(<?= $m['id'] ?>,<?= $m['sport_id'] ?>,<?= j($m['division'] ?? '') ?>,<?= j($m['play_at'] ?? '') ?>,<?= j($m['venue'] ?? '') ?>,<?= j($m['map_url'] ?? '') ?>,<?= (int)($m['team_a'] ?? 0) ?>,<?= (int)($m['team_b'] ?? 0) ?>)' class="text-xs text-blue-600">แก้</button>
<button onclick="del('match_del',<?= $m['id'] ?>)" class="text-xs text-red-500">ลบ</button>
</div><?php endforeach; ?></div></div>
<!-- manager -->
<div class="bg-white rounded-2xl shadow p-4">
<h2 class="font-bold">👤 ผู้จัดการกีฬา (1 คน : 1 กีฬา)</h2>
<form onsubmit="return addMgr(event)" class="space-y-2 mt-2 text-sm">
<input type="hidden" id="g_id" value="">
<input id="g_name" placeholder="ชื่อ เช่น ครูแดง" class="w-full border rounded-lg px-2 py-1">
<input id="g_user" placeholder="username เช่น mgr-football" class="w-full border rounded-lg px-2 py-1">
<input id="g_pass" placeholder="รหัส 6+ ตัว มีอักษร+ตัวเลข (ตอนแก้: ว่างไว้ = ไม่เปลี่ยน)" class="w-full border rounded-lg px-2 py-1">
<select id="g_sport" class="w-full border rounded-lg px-2 py-1"><?php foreach($sports as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?></select>
<div class="flex gap-2"><button id="g_btn" class="flex-1 bg-gray-900 text-white rounded-lg py-2 font-bold">+ เพิ่ม manager</button>
<button id="g_cancel" type="button" onclick="cancelMgr()" class="hidden bg-gray-200 rounded-lg px-3 py-2">ยกเลิก</button></div></form>
<div class="mt-3 space-y-2 text-sm"><?php foreach($mgrs as $g): ?>
<div class="border rounded-xl px-3 py-2">🧑 <?= h($g['name']) ?> (<b><?= h($g['username']) ?></b>) → <?= h($g['sport_name']??'-') ?>
<div class="flex gap-2 mt-1"><button onclick='editMgr(<?= (int)$g['id'] ?>,<?= j($g['name']) ?>,<?= j($g['username']) ?>,<?= (int)$g['sport_id'] ?>)' class="text-xs bg-gray-100 rounded-lg px-2 py-1">แก้ชื่อ/user/รหัส</button>
<button onclick="resetPin(<?= $g['id'] ?>)" class="text-xs bg-gray-100 rounded-lg px-2 py-1">ตั้งรหัสใหม่</button>
<button onclick="del('mgr_del',<?= $g['id'] ?>)" class="text-xs text-red-500">ลบ</button></div></div><?php endforeach; ?></div></div>
<!-- กีฬา/สถาบัน -->
<div class="space-y-4">
<div class="bg-white rounded-2xl shadow p-4 text-sm">
<h2 class="font-bold">🏅 กีฬา (12 ชนิด)</h2>
<form onsubmit="return addSport(event)" class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2 items-end text-sm">
<input type="hidden" id="s_id" value="">
<label class="col-span-2">ชื่อกีฬา<input id="s_name" placeholder="เช่น ฟุตซอล" class="w-full border rounded-lg px-2 py-1 mt-0.5"></label>
<label>ประเภทรูปแบบการแข่ง<select id="s_type" class="w-full border rounded-lg px-2 py-1 mt-0.5"><option value="points">แต้มรวม (ฟุตบอล/บาส/เปตอง)</option><option value="sets">นับเซ็ต (วอลเลย์/แบด/ปิงปอง)</option><option value="rank">อันดับ 1-2-3 (กรีฑา/เต้น/ทำอาหาร)</option></select></label>
<label title="สายนับเซ็ต: ชนะกี่เซ็ตก่อนถึงชนะ เช่น best of 5">Best of (เซ็ต)<input id="s_best" type="number" value="3" class="w-full border rounded-lg px-2 py-1 mt-0.5"></label>
<label title="สายแต้มรวม = แต้มเมื่อชนะ / สายอันดับ = แต้มที่ 1">แต้มชนะ / ที่ 1<input id="s_win" type="number" value="3" class="w-full border rounded-lg px-2 py-1 mt-0.5"></label>
<label title="สายแต้มรวม = แต้มเมื่อเสมอ / สายอันดับ = แต้มที่ 2">แต้มเสมอ / ที่ 2<input id="s_draw" type="number" value="1" class="w-full border rounded-lg px-2 py-1 mt-0.5"></label>
<label title="ใช้เฉพาะสายอันดับ: แต้มที่ 3">แต้มที่ 3<input id="s_third" type="number" value="1" class="w-full border rounded-lg px-2 py-1 mt-0.5"></label>
<label class="col-span-2" title="เช่น ชาย,หญิง หรือ เดี่ยว,คู่,คู่ผสม — จะเป็นตัวเลือกตอนสร้างแมตช์">ประเภท/รุ่น (คั่นด้วย ,)<input id="s_divs" placeholder="เช่น ชาย,หญิง" class="w-full border rounded-lg px-2 py-1 mt-0.5"></label>
<div class="flex gap-2"><button id="s_btn" class="bg-gray-900 text-white rounded-lg px-3 py-1">+ เพิ่ม</button>
<button id="s_cancel" type="button" onclick="cancelSport()" class="hidden bg-gray-200 rounded-lg px-3 py-1">ยกเลิก</button></div></form>
<ul class="mt-2 space-y-2"><?php foreach($sports as $s): $c=$mcount[$s['id']]??0;
$desc = $s['type']==='sets' ? "เซ็ต best of {$s['best_of']}" : ($s['type']==='rank' ? "อันดับ 🥇{$s['win_point']}/🥈{$s['draw_point']}/🥉{$s['third_point']}" : "แต้มรวม ชนะ {$s['win_point']}/เสมอ {$s['draw_point']}"); ?>
<li class="border rounded-xl px-2 py-1">• <b><?= h($s['name']) ?></b> (<?= $desc ?>)
<div class="text-xs text-gray-500">ประเภท: <?= h(implode(', ', sport_divs($s))?:'—') ?></div>
<div class="flex gap-2 mt-1 flex-wrap">
<button onclick='editSport(<?= (int)$s['id'] ?>,<?= j($s['name']) ?>,<?= j($s['type']) ?>,<?= (int)$s['best_of'] ?>,<?= (int)$s['win_point'] ?>,<?= (int)$s['draw_point'] ?>,<?= (int)($s['third_point'] ?? 1) ?>,<?= j(implode(',', sport_divs($s))) ?>)' class="text-xs text-blue-600">แก้ชื่อ/แต้ม</button>
<?php if($c>0): ?><span class="text-xs text-gray-400" title="มีแมตช์อยู่ <?= $c ?> คู่ ลบแมตช์ก่อน">ลบไม่ได้ (มี <?= $c ?> แมตช์) 🔒</span>
<?php else: ?><button onclick="del('sport_del',<?= $s['id'] ?>)" class="text-xs text-red-500">ลบกีฬา</button><?php endif; ?>
</div></li><?php endforeach; ?></ul></div>
<div class="bg-white rounded-2xl shadow p-4 text-sm">
<h2 class="font-bold">🏫 สถาบันที่เข้าร่วม (8 แห่ง)</h2>
<form onsubmit="return addTeam(event)" class="grid grid-cols-2 gap-2 mt-2">
<input type="hidden" id="t_id" value="">
<input id="t_name" placeholder="ชื่อเต็ม" class="border rounded-lg px-2 py-1 col-span-2">
<input id="t_short" placeholder="ชื่อย่อ" class="border rounded-lg px-2 py-1">
<input id="t_grp" placeholder="สาย เช่น 4" class="border rounded-lg px-2 py-1">
<input id="t_color" type="color" value="#6366f1" class="w-10 h-9">
<label class="col-span-2 text-xs text-gray-500">โลโก้สถาบัน (JPG/PNG/GIF/WebP ไม่เกิน 2MB) <input id="t_logo" type="file" accept="image/*" class="block mt-1"></label>
<div class="col-span-2"><img id="t_prev" class="hidden w-14 h-14 rounded-full object-cover bg-white ring-1 ring-black/10" alt=""></div>
<div class="flex gap-2"><button id="t_btn" class="bg-gray-900 text-white rounded-lg px-3 py-1">+ เพิ่ม</button>
<button id="t_cancel" type="button" onclick="cancelTeam()" class="hidden bg-gray-200 rounded-lg px-3 py-1">ยกเลิก</button></div></form>
<ul class="mt-2"><?php foreach($teams as $t): ?><li title="<?= h($t['name']) ?>" class="flex items-center gap-2 py-0.5"><span class="w-7 shrink-0 flex justify-center"><?= tlogo($t,28,false) ?></span> <span><?= h(tname($t)) ?> <span class="text-gray-400">สาย <?= h($t['grp']) ?></span></span> <button onclick='editTeam(<?= (int)$t['id'] ?>,<?= j($t['name']) ?>,<?= j($t['short']??'') ?>,<?= j($t['grp']??'') ?>,<?= j($t['color']??'#6366f1') ?>,<?= j($t['logo']??'') ?>)' class="text-xs text-blue-600">แก้</button><?php if(($t['logo']??'')!==''): ?> <button onclick="logoDel(<?= (int)$t['id'] ?>)" class="text-xs text-gray-500">ลบโลโก้</button><?php endif; ?> <button onclick="del('team_del',<?= $t['id'] ?>)" class="text-red-500 text-xs">ลบ</button></li><?php endforeach; ?></ul></div>
</div></div>
<div id="qrbox" class="hidden fixed inset-0 bg-black/50 items-center justify-center p-4"><div class="bg-white rounded-2xl p-6 text-center">
<h3 id="qrtitle" class="font-bold mb-2"></h3><div id="qr" class="flex justify-center"></div><p class="text-xs mt-2"><a id="qrurl" class="text-blue-600 break-all" target="_blank"></a></p>
<p class="text-xs text-gray-500">พิมพ์ป้ายนี้ไปติดข้างสนามได้เลย</p>
<button onclick="window.print()" class="mt-2 bg-gray-900 text-white rounded-full px-5 py-2 text-sm">🖨️ พิมพ์</button>
<button onclick="document.getElementById('qrbox').style.display='none'" class="mt-2 ml-2 bg-gray-200 rounded-full px-5 py-2 text-sm">ปิด</button></div></div>
<script src="asset/qrcode.min.js"></script>
<script>
const DIVMAP=<?= json_encode($divmap, JSON_UNESCAPED_UNICODE) ?>;
function syncDiv(){ const sel=document.getElementById('m_div'); const arr=DIVMAP[document.getElementById('m_sport').value]||[];
 sel.textContent=''; const o0=document.createElement('option'); o0.value=''; o0.textContent='— ประเภท —'; sel.appendChild(o0);
 arr.forEach(d=>{ const o=document.createElement('option'); o.textContent=d; sel.appendChild(o); }); }
// กันเลือกสถาบันซ้ำกัน 2 ฝั่ง: ฝั่งที่เลือกแล้ว อีกฝั่งเลือกไม่ได้
function syncTeams(){ [['m_a','m_b'],['m_b','m_a']].forEach(([s,o])=>{
 const sv=document.getElementById(s).value, other=document.getElementById(o);
 [...other.options].forEach(op=>{ if(op.value!=='') op.disabled = op.value===sv; });
 if(other.value!=='' && other.value===sv) other.value=''; }); }
async function post(d){ try { const r=await (await fetch('api.php',{method:'POST',body:d})).json(); if(!r.ok) alert(r.msg); else location.reload(); }
 catch(e){ alert('ติดต่อ server ไม่สำเร็จ ('+e+') — ลองรีเฟรชหน้าแล้วทำใหม่'); } }
function addMatch(e){ e.preventDefault();
 const id=document.getElementById('m_id').value;
 const fd=new URLSearchParams({act:id?'match_set':'match_add',id,sport_id:m_sport.value,division:m_div.value,team_a:m_a.value,team_b:m_b.value,play_at:m_when.value,venue:m_venue.value,map_url:m_map.value});
 return post(fd); }
function editMatch(id,sport,div,when,venue,map,ta,tb){ m_id.value=id; m_sport.value=sport; syncDiv(); m_div.value=div;
 m_when.value=when; m_venue.value=venue; m_map.value=map;
 const shell=!ta||!tb; // แมตช์เปล่ารอบชิง: ให้เลือกทีมได้, แมตช์ปกติล็อกคู่ทีมเหมือนเดิม
 m_a.value=ta||''; m_b.value=tb||''; syncTeams();
 m_sport.disabled=!shell; m_a.disabled=m_b.disabled=!shell;
 m_btn.textContent='💾 บันทึก'; m_cancel.classList.remove('hidden'); m_when.focus(); }
function cancelMatch(){ m_id.value=''; m_a.value=m_b.value=''; m_when.value=m_venue.value=m_map.value=''; syncDiv(); syncTeams();
 m_sport.disabled=m_a.disabled=m_b.disabled=false; m_btn.textContent='+ เพิ่ม'; m_cancel.classList.add('hidden'); }
function addSport(e){ e.preventDefault();
 const id=document.getElementById('s_id').value;
 const fd=new URLSearchParams({act:id?'sport_set':'sport_add',id,name:s_name.value,type:s_type.value,best_of:s_best.value,win_point:s_win.value,draw_point:s_draw.value,third_point:s_third.value,divisions:s_divs.value});
 return post(fd); }
function editSport(id,name,type,best,win,draw,third,divs){ s_id.value=id; s_name.value=name; s_type.value=type; s_best.value=best; s_win.value=win; s_draw.value=draw; s_third.value=third; s_divs.value=divs;
 s_btn.textContent='💾 บันทึก'; s_cancel.classList.remove('hidden'); s_name.focus(); }
function cancelSport(){ s_id.value=''; s_name.value=s_divs.value=''; s_type.value='points'; s_best.value=s_win.value=3; s_draw.value=s_third.value=1; s_btn.textContent='+ เพิ่ม'; s_cancel.classList.add('hidden'); }
function setDiv(id){ const v=prompt('ประเภทคั่นด้วยเครื่องหมาย , (เช่น ชาย,หญิง):'); if(v!==null) post(new URLSearchParams({act:'sport_set_div',id,divisions:v})); }
async function addTeam(e){ e.preventDefault();
 const id=document.getElementById('t_id').value;
 let r=await (await fetch('api.php',{method:'POST',body:new URLSearchParams({act:id?'team_set':'team_add',id,name:t_name.value,short:t_short.value,grp:t_grp.value,color:t_color.value})})).json();
 if(!r.ok){ alert(r.msg); return false; }
 const tid=r.id||id;
 if(t_logo.files.length){
  const fd=new FormData(); fd.append('act','team_logo'); fd.append('id',tid); fd.append('logo',t_logo.files[0]);
  r=await (await fetch('api.php',{method:'POST',body:fd})).json();
  if(!r.ok){ alert(r.msg); return false; }
 }
 location.reload(); return false; }
function editTeam(id,name,short,grp,color,logo){ t_id.value=id; t_name.value=name; t_short.value=short; t_grp.value=grp; t_color.value=/^#[0-9a-fA-F]{6}$/.test(color)?color:'#6366f1';
 const pv=document.getElementById('t_prev'); if(logo){ pv.src=logo; pv.classList.remove('hidden'); } else pv.classList.add('hidden');
 t_btn.textContent='💾 บันทึก'; t_cancel.classList.remove('hidden'); t_name.focus(); }
function cancelTeam(){ t_id.value=''; t_name.value=t_short.value=t_grp.value=''; t_color.value='#6366f1'; t_logo.value=''; t_prev.classList.add('hidden'); t_btn.textContent='+ เพิ่ม'; t_cancel.classList.add('hidden'); }
function logoDel(id){ if(confirm('ลบโลโก้?')) post(new URLSearchParams({act:'team_logo',id,remove:'1'})); }
function addMgr(e){ e.preventDefault();
 const id=document.getElementById('g_id').value;
 if(id) return post(new URLSearchParams({act:'mgr_set',id,name:g_name.value,username:g_user.value,password:g_pass.value,sport_id:g_sport.value}));
 return post(new URLSearchParams({act:'mgr_add',name:g_name.value,username:g_user.value,password:g_pass.value,sport_id:g_sport.value})); }
function editMgr(id,name,username,sport){ g_id.value=id; g_name.value=name; g_user.value=username; g_sport.value=sport; g_pass.value='';
 g_btn.textContent='💾 บันทึก'; g_cancel.classList.remove('hidden'); g_name.focus(); }
function cancelMgr(){ g_id.value=''; g_name.value=g_user.value=g_pass.value=''; g_btn.textContent='+ เพิ่ม manager'; g_cancel.classList.add('hidden'); }
function addAnn(e){ e.preventDefault();
 const id=document.getElementById('a_id').value;
 return post(new URLSearchParams({act:id?'ann_set':'ann_add',id,text:a_text.value})); }
function editAnn(id,text){ a_id.value=id; a_text.value=text; a_btn.textContent='💾 บันทึก'; a_cancel.classList.remove('hidden'); a_text.focus(); }
function cancelAnn(){ a_id.value=''; a_text.value=''; a_btn.textContent='+ เพิ่ม'; a_cancel.classList.add('hidden'); }
function toggleAnn(id){ post(new URLSearchParams({act:'ann_toggle',id})); }
function setBoard(mode){ post(new URLSearchParams({act:'board_mode',mode})); }
function del(act,id){ if(confirm('ลบ?')) post(new URLSearchParams({act,id})); }
function unlock(id){ post(new URLSearchParams({act:'unlock',match_id:id})); }
function regen(id){ post(new URLSearchParams({act:'token_regen',id})); }
function resetPin(id){ const p=prompt('รหัสใหม่ (6+ ตัว มีอักษร+ตัวเลข):'); if(p) post(new URLSearchParams({act:'mgr_reset',id,password:p})); }
function chPass(e){ e.preventDefault();
 if(pw_new.value.length<6){ alert('รหัสใหม่ต้องยาว 6+ ตัว'); return false; }
 if(!confirm('เปลี่ยนรหัส admin?')) return false;
 return post(new URLSearchParams({act:'admin_pass',old:pw_old.value,password:pw_new.value})); }
function showQR(url,title){ const b=document.getElementById('qrbox'); b.style.display='flex';
 document.getElementById('qrtitle').textContent=title; document.getElementById('qrurl').textContent=url; document.getElementById('qrurl').href=url;
 const el=document.getElementById('qr'); el.innerHTML='';
 if (typeof QRCode==='undefined'){ el.innerHTML='<p class="text-red-500 text-sm">โหลดตัวสร้าง QR ไม่ได้<br>ก็อปลิงก์ด้านล่างส่งให้กรรมการแทน</p>'; return; }
 new QRCode(el,{text:url,width:240,height:240}); }
syncDiv(); syncTeams();
</script>
<?php endif; ?></div></body></html>
