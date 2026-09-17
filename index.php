<?php require __DIR__.'/lib/db.php';
$me = current_user();
$filter = $_GET['sport'] ?? 'all';
$divFilter = $_GET['div'] ?? 'all';
$grpFilter = $_GET['grp'] ?? 'all';
$dayFilter = $_GET['day'] ?? 'all';
if ($dayFilter!=='all' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayFilter)) $dayFilter = 'all';
// manager เห็นเฉพาะกีฬาที่ตัวเองรับผิดชอบ
if ($me && $me['role']==='manager') $filter = (string)$me['sport_id'];
$sports = rows("SELECT * FROM sports ORDER BY id");
$teams = []; foreach (rows("SELECT * FROM teams") as $t) $teams[$t['id']]=$t;
$divs = []; // ประเภทของกีฬาที่เลือก (สำหรับ dropdown)
if ($filter!=='all' && ($fs = row("SELECT * FROM sports WHERE id=?", [$filter]))) $divs = sport_divs($fs);
$q = "SELECT m.*, s.name AS sport_name, s.type AS sport_type FROM matches m JOIN sports s ON s.id=m.sport_id";
$w = []; $p = [];
if ($filter!=='all') { $w[]="m.sport_id=?"; $p[]=$filter; }
if ($divFilter!=='all') { $w[]="m.division=?"; $p[]=$divFilter; }
if ($grpFilter!=='all') {
  $ids = array_keys(array_filter($teams, fn($t)=>$t['grp']===$grpFilter));
  if ($ids) { $in = implode(',', array_fill(0,count($ids),'?')); $w[]="(m.team_a IN ($in) OR m.team_b IN ($in))"; $p=array_merge($p,$ids,$ids); }
  else $w[]="1=0";
}
if ($dayFilter!=='all') { $w[]="substr(m.play_at,1,10)=?"; $p[]=$dayFilter; }
if ($w) $q .= " WHERE ".implode(' AND ', $w);
$q .= " ORDER BY m.updated_at DESC, m.id DESC";
$matches = rows($q, $p);
$board = standings();
$medals = setting('board_mode','points')==='medals'; // โหมดเหรียญ: ซ่อนแต้ม เรียงทอง→เงิน→ทองแดง
$days = array_column(rows("SELECT DISTINCT substr(play_at,1,10) d FROM matches WHERE play_at<>'' ORDER BY d"), 'd');
$print = isset($_GET['print']); // โหมดสรุปผลสำหรับพิมพ์: เฉพาะคู่จบแล้ว ทุกกีฬา ไม่กรอง
if ($print) $matches = rows("SELECT m.*, s.name AS sport_name, s.type AS sport_type FROM matches m JOIN sports s ON s.id=m.sport_id WHERE m.status='finished' ORDER BY s.name, m.division, m.id");
$anns = rows("SELECT * FROM announcements WHERE active=1 ORDER BY id DESC");
$grps = array_values(array_unique(array_filter(array_column($teams, 'grp')))); sort($grps);
$typelb = ['points'=>'•แต้ม','sets'=>'•เซ็ต','rank'=>'•อันดับ'];
function qs($o) { return http_build_query(array_merge($_GET, $o)); }
?>
<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>🏆การแข่งขันมหกรรมกีฬาสถาบันพระบรมราชชนก🏆</title>
<link rel="icon" type="image/png" href="uploads/logo/วพบ สุรินทร์.png">
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Prompt',sans-serif}
@keyframes lead-glow{0%,100%{box-shadow:0 0 0 0 rgba(251,191,36,.55)}50%{box-shadow:0 0 14px 3px rgba(251,191,36,.55)}}
.lead-row{animation:lead-glow 2.4s ease-in-out infinite}
@media (prefers-reduced-motion:reduce){.lead-row{animation:none}}
.ann-track{display:inline-flex;white-space:nowrap;animation:ann-scroll 25s linear infinite}
.ann-wrap:hover .ann-track{animation-play-state:paused}
@keyframes ann-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
@media (prefers-reduced-motion:reduce){.ann-track{animation:none}}
@media print{ .noprint{ display:none !important; } body{ background:#fff; } section{ box-shadow:none !important; } }</style></head>
<body class="bg-gradient-to-b from-indigo-50 via-white to-amber-50 min-h-screen">
<header class="bg-gradient-to-r from-rose-500 via-orange-500 to-amber-500 text-white">
<nav class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between noprint">
<a href="index.php" class="font-extrabold text-lg">🏆 รายงานผลการแข่งขันกีฬาสี</a>
<div class="relative">
<button onclick="document.getElementById('navmenu').classList.toggle('hidden')" class="p-2 rounded-lg hover:bg-white/20" aria-label="เมนู">☰</button>
<div id="navmenu" class="hidden absolute right-0 mt-2 w-52 bg-white text-gray-800 rounded-2xl shadow-lg overflow-hidden text-sm z-50">
<?php if($me): ?><div class="px-4 py-2 bg-gray-50 text-xs text-gray-500">👤 <?= h($me['name']) ?> (<?= $me['role']==='admin' ? 'admin' : 'ดูแล '.h($me['sport_name'] ?? '') ?>)</div><?php endif; ?>
<a href="score.php" class="block px-4 py-3 hover:bg-orange-50">🖊️ กรอกสกอร์</a>
<a href="admin.php" class="block px-4 py-3 hover:bg-orange-50 border-t">⚙️ หลังบ้าน admin</a>
<?php if($me): ?><a href="#" onclick="fetch('api.php',{method:'POST',body:new URLSearchParams({act:'logout'})}).then(()=>location.reload())" class="block px-4 py-3 hover:bg-red-50 border-t text-red-600">🚪 ออกจากระบบ</a><?php endif; ?>
</div></div></nav>
<div class="max-w-6xl mx-auto px-4 pb-8">
<h1 class="text-3xl md:text-5xl font-extrabold">🏆 การแข่งขันมหกรรมกีฬาสถาบันพระบรมราชชนก</h1>
<p class="opacity-90 mt-1">ภาคตะวันออกเฉียงเหนือ ครั้งที่ 4 เขตการศึกษาที่ 4 และ 5 • อัปเดตสดจากสนาม</p>
</div></header>
<main class="max-w-6xl mx-auto px-4 py-6 space-y-8">
<?php if($anns): $annTxt = implode(' &nbsp;•&nbsp; ', array_map(fn($a)=>h($a['text']), $anns)); ?>
<div id="annbar" class="ann-wrap bg-amber-100 border border-amber-300 rounded-2xl shadow px-4 py-2 flex items-center gap-2 overflow-hidden">
<span class="shrink-0">📢</span>
<div class="flex-1 overflow-hidden"><div class="ann-track text-sm font-semibold text-amber-900"><span class="pr-8"><?= $annTxt ?> &nbsp;•&nbsp;</span><span class="pr-8" aria-hidden="true"><?= $annTxt ?> &nbsp;•&nbsp;</span></div></div>
<button onclick="document.getElementById('annbar').remove()" class="shrink-0 text-amber-700 hover:text-amber-900 px-1" aria-label="ปิดประกาศ">✕</button>
</div>
<?php endif; ?>
<!-- ตารางรวม -->
<section class="bg-white rounded-2xl shadow p-4 md:p-6">
<h2 class="text-xl font-bold mb-3">📊 ตารางคะแนนรวม <span class="text-xs font-normal text-gray-400">(ชนะ/ที่ 1 = 🥇คอลัมน์แรก)</span>
<?php if($print): ?><button onclick="window.print()" class="noprint ml-2 text-sm bg-gray-900 text-white rounded-full px-4 py-1">🖨️ พิมพ์</button><span class="text-xs font-normal text-gray-400"> • สรุปเฉพาะคู่ที่จบแล้ว ณ <?= date('d/m/Y H:i') ?></span>
<?php else: ?><a href="?print=1" class="noprint ml-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1">🖨️ สรุปผล</a><?php endif; ?></h2>
<div class="overflow-x-auto"><table class="w-full text-sm">
<thead><tr class="text-left text-gray-500 border-b"><th class="py-2">อันดับ</th><th>สถาบัน</th><th class="text-center">สาย</th><th class="text-center">แข่ง</th><th class="text-center">🥇</th><th class="text-center">🥈</th><th class="text-center">🥉</th><?php if(!$medals): ?><th class="text-center">แต้ม</th><?php endif; ?></tr></thead>
<tbody>
<?php $i=0;
$scored = array_sum(array_column($board,'pts'))+array_sum(array_column($board,'w'))+array_sum(array_column($board,'d'))+array_sum(array_column($board,'l'))>0; foreach($board as $r): $i++;
if ($scored && $i===1) { $medal='🥇'; $rowStyle='background:linear-gradient(90deg,#fef3c7,#fde68a);border-left:6px solid #f59e0b'; }
elseif ($scored && $i===2) { $medal='🥈'; $rowStyle='background:linear-gradient(90deg,#f8fafc,#e2e8f0);border-left:6px solid #94a3b8'; }
elseif ($scored && $i===3) { $medal='🥉'; $rowStyle='background:linear-gradient(90deg,#fff7ed,#fed7aa);border-left:6px solid #c2703d'; }
else { $medal = $scored ? $i : '–'; $rowStyle=''; } ?>
<tr class="border-b last:border-0 <?= ($scored && $i===1)?'lead-row font-bold':'' ?>" <?= $rowStyle!==''?'style="'.$rowStyle.'"':'' ?>>
<td class="py-2 font-bold"><?= $medal ?></td>
<td title="<?= h($r['team']['name']) ?>"><span class="inline-block align-middle"><?= tlogo($r['team'],26) ?></span> <a href="team.php?id=<?= $r['team']['id'] ?>" class="hover:underline"><?= ($scored && $i===1)?'👑 ':'' ?><?= h(tname($r['team'])) ?></a><?= ($scored && $i===1)?' <span class="text-[11px] bg-amber-500 text-white px-2 py-0.5 rounded-full animate-pulse">ผู้นำ</span>':'' ?></td>
<td class="text-center text-gray-500"><?= h($r['team']['grp']) ?></td>
<td class="text-center"><?= $r['p'] ?></td><td class="text-center"><?= $r['w'] ?></td><td class="text-center"><?= $r['d'] ?></td><td class="text-center"><?= $r['l'] ?></td>
<?php if(!$medals): ?><td class="text-center font-extrabold text-lg text-orange-600"><?= $r['pts'] ?></td><?php endif; ?></tr>
<?php endforeach; ?></tbody></table></div></section>
<!-- ตัวกรอง -->
<?php if(!$print): ?>
<section class="space-y-2 noprint">
<?php if($me && $me['role']==='manager'): ?>
<p class="text-sm bg-emerald-100 text-emerald-800 rounded-xl px-4 py-2">👤 ดูเฉพาะรายการ <b><?= h($me['sport_name'] ?? '') ?></b> ที่คุณรับผิดชอบ</p>
<?php else: ?>
<div class="flex flex-wrap gap-2">
<a href="?<?= qs(['sport'=>'all','div'=>'all']) ?>" class="px-4 py-2 rounded-full text-sm font-semibold <?= $filter==='all'?'bg-gray-900 text-white':'bg-white shadow' ?>">ทั้งหมด</a>
<?php foreach($sports as $s): ?>
<a href="?<?= qs(['sport'=>$s['id'],'div'=>'all']) ?>" class="px-4 py-2 rounded-full text-sm font-semibold <?= (string)$filter===(string)$s['id']?'bg-gray-900 text-white':'bg-white shadow' ?>"><?= h($s['name']) ?> <span class="opacity-60"><?= $typelb[$s['type']]??'' ?></span></a>
<?php endforeach; ?></div>
<?php endif; ?>
<div class="flex flex-wrap gap-2 items-center">
<?php if($divs): ?>
<select onchange="location='?'+this.value" class="bg-white shadow rounded-full text-sm px-4 py-2">
<option value="<?= qs(['div'=>'all']) ?>">ทุกประเภท</option>
<?php foreach($divs as $d): ?><option value="<?= qs(['div'=>$d]) ?>" <?= $divFilter===$d?'selected':'' ?>><?= h($d) ?></option><?php endforeach; ?>
</select>
<?php endif; ?>
<?php foreach($grps as $g): ?>
<a href="?<?= qs(['grp'=>$g]) ?>" class="px-4 py-2 rounded-full text-sm font-semibold <?= $grpFilter===$g?'bg-indigo-600 text-white':'bg-white shadow' ?>">สาย <?= h($g) ?></a>
<?php endforeach; ?>
<?php if($grpFilter!=='all'): ?><a href="?<?= qs(['grp'=>'all']) ?>" class="text-sm text-gray-500 underline">ล้างสาย</a><?php endif; ?>
</div>
<?php if($days): ?>
<div class="flex flex-wrap gap-2 items-center">
<a href="?<?= qs(['day'=>'all']) ?>" class="px-4 py-2 rounded-full text-sm font-semibold <?= $dayFilter==='all'?'bg-indigo-600 text-white':'bg-white shadow' ?>">📅 ทุกวัน</a>
<?php foreach($days as $d): ?>
<a href="?<?= qs(['day'=>$d]) ?>" class="px-4 py-2 rounded-full text-sm font-semibold <?= $dayFilter===$d?'bg-indigo-600 text-white':'bg-white shadow' ?>"><?= th_day($d) ?></a>
<?php endforeach; ?>
<?php if($dayFilter!=='all'): ?><a href="?<?= qs(['day'=>'all']) ?>" class="text-sm text-gray-500 underline">ล้างวัน</a><?php endif; ?>
</div>
<?php endif; ?>
</section>
<?php endif; ?>
<!-- รายคู่ -->
<?php
$mgroups = ['live' => [], 'next' => [], 'done' => []];
foreach ($matches as $m) {
  if ($m['status'] === 'live') $mgroups['live'][] = $m;
  elseif ($m['status'] === 'finished') $mgroups['done'][] = $m;
  else $mgroups['next'][] = $m;
}
// คู่รอแข่งเรียงวัน/เวลาใกล้→ไกล (ไม่ใส่วันไว้ท้าย)
usort($mgroups['next'], fn($a,$b)=> [(($a['play_at']??'')==='')?1:0, $a['play_at']??'', $a['id']] <=> [(($b['play_at']??'')==='')?1:0, $b['play_at']??'', $b['id']]);
$mlb = ['live' => '🔴 กำลังแข่งขัน', 'next' => '⏳ ยังไม่ถึงเวลา', 'done' => '✅ แข่งขันจบแล้ว'];
$mtint = ['live' => 'bg-red-50 border-red-400', 'next' => 'bg-white border-transparent', 'done' => 'bg-gray-50 border-gray-200'];
foreach ($mgroups as $gk => $gm):
if (!$gm) continue;
if (!$print): ?><h2 class="text-lg font-bold"><?= $mlb[$gk] ?> <span class="text-sm font-normal text-gray-400">(<?= count($gm) ?> คู่)</span></h2><?php endif; ?>
<section class="grid md:grid-cols-2 gap-4">
<?php foreach($gm as $m):
$ta=$teams[$m['team_a']]??null; $tb=$teams[$m['team_b']]??null;
$badge = $m['status']==='finished' ? '<span class="bg-gray-900 text-white text-xs px-2 py-1 rounded-full">จบแล้ว 🔒</span>' : ($m['status']==='live' ? '<span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full animate-pulse">กำลังแข่ง 🔴</span>' : '<span class="bg-gray-200 text-xs px-2 py-1 rounded-full">ยังไม่แข่ง</span>');
$divBadge = div_badge($m['division']);
// ปุ่มกรอกโชว์เฉพาะคนมีสิทธิ์: admin ทุกรายการ / manager เฉพาะกีฬาตัวเอง / ทั่วไปไม่เห็น
$can = $me && ($me['role']==='admin' || (int)($me['sport_id'] ?? 0)===(int)$m['sport_id']);
$qr = $can ? base_url().'/score.php?t='.$m['qr_token'] : '';
?>
<div class="rounded-2xl shadow p-4 border-2 <?= $mtint[$gk] ?>">
<div class="flex items-center gap-2 mb-2 flex-wrap"><span class="font-bold text-indigo-600"><?= h($m['sport_name']) ?></span><?= $divBadge ?><?= $badge ?></div>
<?php $mapbtn = map_link($m); $wv = h(th_when($m)).(trim($m['venue']??'')!=='' ? ' 📍 '.h($m['venue']) : ''); if(trim($wv)!=='' || $mapbtn!==''): ?><p class="text-xs text-gray-500 mb-2 flex items-center gap-2 flex-wrap"><?php if(trim($wv)!==''): ?><span>📅 <?= $wv ?></span><?php endif; ?><?= $mapbtn ?></p><?php endif; ?>
<?php if($m['sport_type']==='rank'):
$pl = match_places($m['id']); $pmedal=[1=>'🥇',2=>'🥈',3=>'🥉']; ?>
<div class="space-y-1">
<?php foreach($pl as $r): ?><div class="flex items-center gap-2 text-sm"><span class="w-6 text-center shrink-0"><?= $pmedal[$r['place']]??$r['place'] ?></span><span class="w-6 shrink-0 flex justify-center"><?= tlogo(['logo'=>$r['logo'] ?? '','color'=>'#999'],24,false) ?></span><b class="truncate"><?= h(tname(['short'=>$r['short'],'name'=>$r['name']])) ?></b></div><?php endforeach;
if(!$pl) echo '<p class="text-sm text-gray-400">ยังไม่มีผลอันดับ</p>'; ?>
</div>
<?php else:
$setRows = $m['sport_type']==='sets' ? match_sets($m['id']) : []; ?>
<div class="flex items-center justify-between gap-2 text-center">
<div class="flex-1"><?= tlogo($ta ?? ['color'=>'#999'],40) ?><div class="font-semibold mt-1 text-sm" title="<?= h($ta['name']??'?') ?>"><?= h($ta?tname($ta):'?') ?></div></div>
<div class="text-4xl font-extrabold tabular-nums"><?= $m['score_a'] ?><span class="text-gray-300 mx-1">:</span><?= $m['score_b'] ?></div>
<div class="flex-1"><?= tlogo($tb ?? ['color'=>'#999'],40) ?><div class="font-semibold mt-1 text-sm" title="<?= h($tb['name']??'?') ?>"><?= h($tb?tname($tb):'?') ?></div></div>
</div>
<?php if($setRows): ?>
<div class="overflow-x-auto mt-2"><table class="w-full text-sm tabular-nums">
<thead><tr class="text-gray-400 text-xs"><th class="text-left font-normal py-1">ทีม \ เซ็ต</th><?php foreach($setRows as $s): ?><th class="font-normal px-2"><?= $s['set_no'] ?></th><?php endforeach; ?><th class="font-bold text-gray-600 px-2">เซ็ต</th></tr></thead>
<tbody>
<?php foreach([['t'=>$ta,'k'=>'a'],['t'=>$tb,'k'=>'b']] as $rr): ?>
<tr class="border-t">
<td class="py-1 pr-1 font-semibold truncate max-w-[110px]" title="<?= h($rr['t']['name']??'?') ?>"><?= h($rr['t']?tname($rr['t']):'?') ?></td>
<?php foreach($setRows as $s): $w=set_winner($s['a'],$s['b']); $win=($w==='A'&&$rr['k']==='a')||($w==='B'&&$rr['k']==='b'); ?>
<td class="text-center px-2 py-1 <?= $win?'font-extrabold bg-green-100 rounded':'text-gray-400' ?>"><?= $rr['k']==='a' ? $s['a'] : $s['b'] ?></td>
<?php endforeach; ?>
<td class="text-center px-2 font-extrabold"><?= $rr['k']==='a' ? $m['score_a'] : $m['score_b'] ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php endif; ?>
<?php if($can && !$print):
$title = $m['sport_type']==='rank' ? trim($m['sport_name'].' '.($m['division']!==''?$m['division']:'')) : $m['sport_name'].' '.($ta?tname($ta):'?').' vs '.($tb?tname($tb):'?'); ?>
<div class="mt-3 flex gap-2">
<a href="score.php?t=<?= h($m['qr_token']) ?>" class="flex-1 text-center bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold rounded-xl px-3 py-2">✏️ กรอกสกอร์คู่นี้</a>
<button onclick='showQR(<?= j($qr) ?>,<?= j($title) ?>)' class="bg-gray-100 hover:bg-gray-200 text-sm rounded-xl px-3 py-2">QR</button>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
</section>
<?php endforeach;
if(!$matches) echo '<p class="text-gray-500 bg-white rounded-2xl shadow p-6">ยังไม่มีการแข่งขันในหมวดนี้ — ให้ admin เพิ่มแมตช์ที่หลังบ้าน</p>'; ?>
</main>
<?php if($me): ?>
<div id="qrbox" class="hidden fixed inset-0 bg-black/50 items-center justify-center p-4"><div class="bg-white rounded-2xl p-6 text-center max-w-xs w-full">
<h3 id="qrtitle" class="font-bold mb-2"></h3>
<div id="qr" class="flex justify-center"></div><p class="text-xs text-gray-500 mt-2 break-all" id="qrurl"></p>
<button onclick="document.getElementById('qrbox').style.display='none'" class="mt-3 bg-gray-900 text-white rounded-full px-5 py-2 text-sm">ปิด</button></div></div>
<script src="asset/qrcode.min.js"></script>
<?php endif; ?>
<script>
<?php if($me): ?>
function showQR(url,title){ const b=document.getElementById('qrbox'); b.style.display='flex';
document.getElementById('qrtitle').textContent=title||''; document.getElementById('qrurl').textContent=url; const el=document.getElementById('qr'); el.innerHTML='';
if (typeof QRCode==='undefined'){ el.innerHTML='<p class="text-red-500 text-sm">โหลดตัวสร้าง QR ไม่ได้<br>ก็อปลิงก์ด้านล่างส่งให้กรรมการแทน</p>'; return; }
new QRCode(el,{text:url,width:220,height:220}); }
document.getElementById('qrbox').addEventListener('click',e=>{ if(e.target.id==='qrbox') e.target.style.display='none'; });
<?php endif; ?>
document.addEventListener('click',e=>{ const m=document.getElementById('navmenu'); if(!m.classList.contains('hidden') && !e.target.closest('nav')) m.classList.add('hidden'); });
<?php if(!$print): ?>setTimeout(()=>location.reload(), 60000); // รีเฟรชทุก 1 นาทีให้เห็นผลสด<?php endif; ?>
</script></body></html>
