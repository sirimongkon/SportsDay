<?php require __DIR__.'/lib/db.php';
// ทางเข้า 2 แบบ: (1) QR token สแกนแล้วกรอกเลย ไม่ต้อง login (2) login manager แล้วเลือกแมตช์กีฬาตัวเอง
$byToken = null;
if (!empty($_GET['t'])) $byToken = row("SELECT m.*, s.name AS sport_name, s.type AS sport_type, s.best_of FROM matches m JOIN sports s ON s.id=m.sport_id WHERE m.qr_token=?", [$_GET['t']]);
$me = current_user();
$loginErr = '';
if (!$byToken && !$me && ($_POST['login']??'')) {
  [$r, $loginErr] = try_login($_POST['username'] ?? '', $_POST['password'] ?? '');
  if ($r) { $me = current_user(); header("Location: score.php".($_GET['match']?"?match=".urlencode($_GET['match']):'')); exit; }
}
$match = $byToken;
if (!$match && $me && !empty($_GET['match'])) $match = row("SELECT m.*, s.name AS sport_name, s.type AS sport_type, s.best_of FROM matches m JOIN sports s ON s.id=m.sport_id WHERE m.id=?", [$_GET['match']]);
if (!$match && $me) {
  if ($me['role']==='manager') {
    $list = rows("SELECT m.*, s.name AS sport_name, s.type AS sport_type FROM matches m JOIN sports s ON s.id=m.sport_id WHERE m.sport_id=? ORDER BY m.play_at, m.id", [$me['sport_id']]);
  } else {
    $list = rows("SELECT m.*, s.name AS sport_name, s.type AS sport_type FROM matches m JOIN sports s ON s.id=m.sport_id ORDER BY m.play_at, m.id");
  }
}
$teams=[]; foreach(rows("SELECT * FROM teams") as $t) $teams[$t['id']]=$t;
$editable = false; $viaToken = (bool)$byToken;
if ($match) {
  if ($viaToken) $editable = !(int)$match['locked']; // QR: กรอกได้ถ้ายังไม่ล็อก
  else $editable = $me && can_edit_match($match);
  $sets = $match['sport_type']==='sets' ? match_sets($match['id']) : [];
  $places = $match['sport_type']==='rank' ? match_places($match['id']) : [];
  $ta=$teams[$match['team_a']]??['name'=>'?','short'=>'?']; $tb=$teams[$match['team_b']]??['name'=>'?','short'=>'?'];
  $pv = [1=>0,2=>0,3=>0]; foreach($places as $p) $pv[(int)$p['place']] = $p['team_id'];
}
?>
<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>กรอกสกอร์</title><link rel="icon" type="image/png" href="uploads/logo/วพบ สุรินทร์.png"><script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;800&display=swap" rel="stylesheet"><style>body{font-family:'Prompt',sans-serif}input[type=number]{-moz-appearance:textfield}</style></head>
<body class="bg-gradient-to-b from-emerald-50 to-white min-h-screen">
<div class="max-w-xl mx-auto px-4 py-6">
<?php if($match && !$viaToken): ?><a href="score.php" class="text-sm text-gray-500">← กลับรายการ</a>
<?php else: ?><a href="index.php" class="text-sm text-gray-500">← กลับหน้าแรก</a><?php endif; ?>
<h1 class="text-2xl font-extrabold mt-1">🖊️ กรอกสกอร์<?php if($match){ echo ' • '.h($match['sport_name']); if($match['division']!=='') echo ' • '.h($match['division']); } ?></h1>
<?php if($byToken): ?><p class="text-sm text-emerald-700 bg-emerald-100 rounded-xl px-3 py-2 mt-2">✅ เข้าผ่าน QR ประจำคู่ กรอกได้เลยไม่ต้องใส่รหัส</p><?php endif; ?>

<?php if(!$match): ?>
  <?php if(!$me): ?>
  <form method="post" class="bg-white rounded-2xl shadow p-5 mt-4 space-y-3">
  <h2 class="font-bold">🔑 เข้าสู่ระบบผู้จัดการกีฬา</h2>
  <?php if($loginErr): ?><p class="text-red-600 text-sm"><?= h($loginErr) ?></p><?php endif; ?>
  <input name="username" placeholder="ชื่อผู้ใช้" class="w-full border rounded-xl px-3 py-2">
  <input name="password" type="password" placeholder="รหัสผ่าน (อักษร+ตัวเลข 6+ ตัว)" class="w-full border rounded-xl px-3 py-2">
  <button name="login" value="1" class="w-full bg-gray-900 text-white rounded-xl py-2 font-bold">เข้าสู่ระบบ</button></form>
  <?php else: ?>
  <div class="bg-white rounded-2xl shadow p-5 mt-4">
  <p class="font-bold">สวัสดี <?= h($me['name']) ?> (<?= $me['role']==='admin' ? 'admin' : h($me['sport_name']??'') ?>) <a class="text-red-500 text-sm" href="#" onclick="fetch('api.php',{method:'POST',body:new URLSearchParams({act:'logout'})}).then(()=>location.reload())">ออก</a></p>
  <h2 class="mt-3 font-bold"><?= $me['role']==='admin' ? 'เลือกรายการทั้งหมด:' : 'เลือกคู่ของกีฬาคุณ:' ?></h2>
  <?php
  $groups = [];
  foreach (($list ?? []) as $m) {
    $dd = substr(trim($m['play_at'] ?? ''), 0, 10);
    $groups[preg_match('/^\d{4}-\d{2}-\d{2}$/', $dd) ? $dd : ''][] = $m;
  }
  $daykeys = array_values(array_filter(array_keys($groups), fn($k) => $k !== '')); sort($daykeys);
  if (isset($groups[''])) $daykeys[] = '';
  foreach ($daykeys as $dk): ?>
  <h3 class="mt-3 font-bold text-sm">📅 <?= $dk === '' ? 'ไม่ระบุวัน' : th_day($dk) ?> <span class="font-normal text-gray-400">(<?= count($groups[$dk]) ?> คู่)</span></h3>
  <?php foreach ($groups[$dk] as $m):
  if ($m['status'] === 'finished') { $row = 'bg-green-50 border-green-300'; $pill = '<span class="text-xs bg-green-600 text-white rounded-full px-2 py-0.5">จบแล้ว 🔒</span>'; }
  elseif ($m['status'] === 'live') { $row = 'bg-amber-50 border-amber-300'; $pill = '<span class="text-xs bg-amber-500 text-white rounded-full px-2 py-0.5 animate-pulse">กรอกแล้ว (ยังไม่จบ)</span>'; }
  else { $row = 'bg-white border-gray-200'; $pill = '<span class="text-xs bg-gray-200 text-gray-600 rounded-full px-2 py-0.5">ยังไม่กรอก</span>'; } ?>
  <a class="block border-2 rounded-xl px-3 py-2 mt-2 <?= $row ?>" href="score.php?match=<?= $m['id'] ?>">
  <?php if($me['role']==='admin'): ?><span class="text-xs bg-indigo-100 text-indigo-700 rounded-full px-2 py-0.5"><?= h($m['sport_name']) ?></span> <?php endif; ?>
  <?php if(($m['sport_type']??'')==='rank'): ?>
  🏅 <?= h($m['division']!==''?$m['division']:$m['sport_name']) ?>
  <?php else: ?>
  <?= h(tname($teams[$m['team_a']]??['name'=>'?','short'=>'?'])) ?> <?= $m['score_a'] ?> : <?= $m['score_b'] ?> <?= h(tname($teams[$m['team_b']]??['name'=>'?','short'=>'?'])) ?>
  <?php endif; ?>
  <?= $pill ?></a><?php endforeach; ?>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
<?php else: ?>
<div class="bg-white rounded-2xl shadow p-5 mt-4">
<?php if($match['sport_type']==='rank'): ?>
<div class="text-center font-bold text-lg">🏅 <?= h($match['sport_name']) ?></div>
<div class="text-center mt-1"><?= div_badge($match['division']) ?></div>
<?php else: ?>
<div class="text-center font-bold">
<div class="flex items-center justify-center gap-4">
<div><?= tlogo($ta,56) ?><div class="text-sm mt-1" title="<?= h($ta['name']) ?>"><?= h(tname($ta)) ?></div></div>
<span class="text-gray-300 font-black text-2xl">vs</span>
<div><?= tlogo($tb,56) ?><div class="text-sm mt-1" title="<?= h($tb['name']) ?>"><?= h(tname($tb)) ?></div></div>
</div></div>
<?php endif; ?>
<?php if((int)$match['locked']): ?><p class="text-center text-sm bg-gray-900 text-white rounded-xl px-3 py-2 mt-3">🔒 จบการแข่งขันแล้ว แก้ไขไม่ได้ — ติดต่อ admin ปลดล็อก</p><?php endif; ?>
<?php if($match['sport_type']==='rank'): ?>
<p class="text-sm text-gray-500 mt-3">เลือกสถาบันที่ได้ที่ 1 / 2 / 3:</p>
<div class="space-y-2 mt-2">
<?php $medals=[1=>'🥇 ที่ 1',2=>'🥈 ที่ 2',3=>'🥉 ที่ 3']; foreach($medals as $pl=>$lb): ?>
<label class="flex items-center gap-2 border rounded-xl px-3 py-2"><span class="font-bold text-sm w-16"><?= $lb ?></span>
<select id="pl<?= $pl ?>" <?= $editable?'':'disabled' ?> class="flex-1 border rounded-lg px-2 py-2">
<option value="0">— ยังไม่ระบุ —</option>
<?php foreach($teams as $t): ?><option value="<?= $t['id'] ?>" <?= (int)$pv[$pl]===(int)$t['id']?'selected':'' ?>><?= h(tname($t)) ?></option><?php endforeach; ?>
</select></label>
<?php endforeach; ?></div>
<?php elseif($match['sport_type']==='points'): ?>
<div class="flex items-center justify-center gap-4 mt-4">
<div class="text-center"><div class="text-sm text-gray-500" title="<?= h($ta['name']) ?>"><?= h(tname($ta)) ?></div>
<button <?= $editable?'':'disabled' ?> onclick="ch('a',1)" class="bg-green-500 text-white w-10 h-10 rounded-full text-xl font-bold">+</button>
<input id="sa" type="number" value="<?= $match['score_a'] ?>" <?= $editable?'':'disabled' ?> class="w-24 text-5xl font-extrabold text-center border rounded-2xl py-2">
<button <?= $editable?'':'disabled' ?> onclick="ch('a',-1)" class="bg-gray-200 w-10 h-10 rounded-full text-xl font-bold">−</button></div>
<div class="text-3xl font-black text-gray-300">:</div>
<div class="text-center"><div class="text-sm text-gray-500" title="<?= h($tb['name']) ?>"><?= h(tname($tb)) ?></div>
<button <?= $editable?'':'disabled' ?> onclick="ch('b',1)" class="bg-green-500 text-white w-10 h-10 rounded-full text-xl font-bold">+</button>
<input id="sb" type="number" value="<?= $match['score_b'] ?>" <?= $editable?'':'disabled' ?> class="w-24 text-5xl font-extrabold text-center border rounded-2xl py-2">
<button <?= $editable?'':'disabled' ?> onclick="ch('b',-1)" class="bg-gray-200 w-10 h-10 rounded-full text-xl font-bold">−</button></div>
</div>
<?php else: ?>
<p class="text-sm text-gray-500 mt-3">กรอกทีละเซ็ตว่าใครได้แต้มเท่าไหร่ (ชนะ <?= (int)$match['best_of'] ?> ใน <?= (int)$match['best_of'] ?> เซ็ตก่อนชนะ):</p>
<div id="sets" class="space-y-2 mt-2"></div>
<?php if($editable): ?><button onclick="addSet()" class="mt-2 w-full border-2 border-dashed rounded-xl py-2 text-sm">＋ เพิ่มเซ็ต</button><?php endif; ?>
<?php endif; ?>
<p id="msg" class="text-sm text-center mt-3"></p>
<?php if($editable): ?>
<div class="grid grid-cols-2 gap-2 mt-3">
<button onclick="save()" class="bg-orange-500 text-white rounded-xl py-3 font-bold">💾 บันทึก</button>
<button onclick="finish()" class="bg-gray-900 text-white rounded-xl py-3 font-bold">🏁 จบการแข่งขัน</button>
</div><p class="text-xs text-gray-400 mt-1">กดจบ = ล็อกทันที ปลดได้เฉพาะ admin</p>
<?php endif; ?>
</div>
<?php $hist = rows("SELECT * FROM audit WHERE match_id=? ORDER BY id DESC LIMIT 5", [$match['id']]);
$actlb = ['save'=>'บันทึก','finish'=>'จบการแข่งขัน','unlock'=>'ปลดล็อก'];
if($hist): ?>
<div class="bg-white rounded-2xl shadow p-4 mt-3 text-xs text-gray-500">
<b>📝 ประวัติการบันทึก</b>
<?php foreach($hist as $hh): ?><div>⏰ <?= date('d/m H:i', $hh['created_at']) ?> • <?= h($hh['username']) ?> • <?= h($actlb[$hh['action']] ?? $hh['action']) ?><?= $hh['detail']!=='' ? ' ('.h($hh['detail']).')' : '' ?></div><?php endforeach; ?>
</div>
<?php endif; ?>
<script>
const MID=<?= (int)$match['id'] ?>, TOKEN=<?= json_encode($_GET['t']??'') ?>, TYPE=<?= json_encode($match['sport_type']) ?>;
let sets=<?= json_encode($sets) ?>;
function ch(w,d){ const el=document.getElementById(w==='a'?'sa':'sb'); el.value=Math.max(0,(+el.value||0)+d); }
function addSet(a=0,b=0){ sets.push({a,b}); render(); }
function render(){ const box=document.getElementById('sets'); if(!box) return; box.innerHTML='';
 sets.forEach((s,i)=>{ const w=s.a>s.b?'A (ซ้ายได้เซ็ต)':(s.b>s.a?'B (ขวาได้เซ็ต)':'ยังเสมอ');
 box.innerHTML+=`<div class="flex items-center gap-2 border rounded-xl px-2 py-2"><span class="font-bold text-sm w-14">เซ็ต ${i+1}</span>
 <input type="number" min="0" value="${s.a}" oninput="sets[${i}].a=+this.value||0;renderW()" class="w-full border rounded-lg px-2 py-1 text-center text-xl font-bold">
 <span>:</span>
 <input type="number" min="0" value="${s.b}" oninput="sets[${i}].b=+this.value||0;renderW()" class="w-full border rounded-lg px-2 py-1 text-center text-xl font-bold">
 <span id="w${i}" class="text-xs w-28">${w}</span>
 <button onclick="sets.splice(${i},1);render()" class="text-red-500">✕</button></div>`; }); }
function renderW(){ sets.forEach((s,i)=>{ const el=document.getElementById('w'+i); if(el) el.textContent=s.a>s.b?'A (ซ้ายได้เซ็ต)':(s.b>s.a?'B (ขวาได้เซ็ต)':'ยังเสมอ'); }); }
async function save(){ const msg=document.getElementById('msg'); msg.textContent='กำลังบันทึก…';
 const fd=new URLSearchParams({act:'save',match_id:MID,token:TOKEN});
 if(TYPE==='points'){ fd.set('score_a',document.getElementById('sa').value); fd.set('score_b',document.getElementById('sb').value); }
 else if(TYPE==='rank'){ const pl=[]; [1,2,3].forEach(p=>{ const v=+document.getElementById('pl'+p).value; if(v) pl.push({team:v,place:p}); }); fd.set('placements',JSON.stringify(pl)); }
 else fd.set('sets',JSON.stringify(sets));
 const r=await (await fetch('api.php',{method:'POST',body:fd})).json();
 msg.textContent=r.ok?'✅ บันทึกแล้ว':'❌ '+r.msg; msg.className='text-sm text-center mt-3 '+(r.ok?'text-green-600':'text-red-600'); }
async function finish(){ if(!confirm('จบการแข่งขันคู่นี้? (จะล็อกทันที)'))return;
 const r=await (await fetch('api.php',{method:'POST',body:new URLSearchParams({act:'finish',match_id:MID,token:TOKEN})})).json();
 if(r.ok) location.reload(); else alert(r.msg); }
render();
</script>
<?php endif; ?>
</div></body></html>
