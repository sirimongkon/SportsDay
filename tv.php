<?php require __DIR__.'/lib/db.php';
// โหมดจอสนาม: สลับจอตารางคะแนน ⇄ คู่กำลังแข่ง ทุก 15 วิ (reload = ข้อมูลสดทุกรอบ)
$v = $_GET['v'] ?? 'board';
$live = rows("SELECT m.*, s.name AS sport_name, s.type AS sport_type FROM matches m JOIN sports s ON s.id=m.sport_id WHERE m.status='live' ORDER BY m.updated_at DESC");
if (!$live) $v = 'board'; // ไม่มีคู่แข่งค้าง โชว์ตารางอย่างเดียว
$next = $v === 'board' && $live ? 'tv.php?v=live' : 'tv.php?v=board';
$board = standings();
$medals = setting('board_mode','points')==='medals';
$anns = rows("SELECT * FROM announcements WHERE active=1 ORDER BY id DESC");
$teams = []; foreach (rows("SELECT * FROM teams") as $t) $teams[$t['id']] = $t;
?>
<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>📺 จอสนาม</title>
<link rel="icon" type="image/png" href="uploads/logo/วพบ สุรินทร์.png">
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Prompt',sans-serif}
.ann-track{display:inline-flex;white-space:nowrap;animation:ann-scroll 25s linear infinite}
@keyframes ann-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
@media (prefers-reduced-motion:reduce){.ann-track{animation:none}}</style></head>
<body class="bg-gray-900 text-white min-h-screen">
<?php if($anns): $annTxt = implode(' &nbsp;•&nbsp; ', array_map(fn($a)=>h($a['text']), $anns)); ?>
<div class="bg-amber-400 text-gray-900 px-6 py-3 text-2xl font-bold overflow-hidden"><div class="ann-track"><span class="pr-8">📢 <?= $annTxt ?> &nbsp;•&nbsp;</span><span class="pr-8" aria-hidden="true">📢 <?= $annTxt ?> &nbsp;•&nbsp;</span></div></div>
<?php endif; ?>
<div class="max-w-6xl mx-auto px-6 py-6">
<?php if($v==='live'): ?>
<h1 class="text-4xl font-extrabold mb-4">🔴 กำลังแข่งขัน <span class="text-lg font-normal text-gray-400">(<?= count($live) ?> คู่)</span></h1>
<div class="grid md:grid-cols-2 gap-4">
<?php foreach($live as $m):
$ta = $teams[$m['team_a']] ?? null; $tb = $teams[$m['team_b']] ?? null; ?>
<div class="bg-gray-800 rounded-2xl p-5">
<div class="text-2xl font-bold text-amber-300"><?= h($m['sport_name']) ?> <?= div_badge($m['division']) ?></div>
<?php if($m['sport_type']==='rank'): ?>
<div class="mt-2 space-y-1"><?php foreach(match_places($m['id']) as $r): ?><div class="text-2xl"><?= [1=>'🥇',2=>'🥈',3=>'🥉'][$r['place']] ?? $r['place'] ?> <?= h(tname(['short'=>$r['short'],'name'=>$r['name']])) ?></div><?php endforeach; ?></div>
<?php else: ?>
<div class="flex items-center justify-between text-center mt-2">
<div class="flex-1 text-2xl font-semibold"><?= h($ta ? tname($ta) : '?') ?></div>
<div class="text-7xl font-extrabold tabular-nums mx-4"><?= $m['score_a'] ?><span class="text-gray-600">:</span><?= $m['score_b'] ?></div>
<div class="flex-1 text-2xl font-semibold"><?= h($tb ? tname($tb) : '?') ?></div>
</div><?php endif; ?>
<?php if(trim($m['venue'] ?? '') !== ''): ?><div class="text-xl text-gray-400 mt-2">📍 <?= h($m['venue']) ?></div><?php endif; ?>
</div><?php endforeach; ?></div>
<?php else: ?>
<h1 class="text-4xl font-extrabold mb-4">📊 ตารางคะแนนรวม</h1>
<table class="w-full text-2xl">
<tbody>
<?php $i = 0; $scored = array_sum(array_column($board, 'pts')) > 0; foreach($board as $r): $i++; ?>
<tr class="border-b border-gray-700">
<td class="py-3 font-bold w-16"><?= $scored ? $i : '–' ?></td>
<td class="py-3 font-semibold"><?= h(tname($r['team'])) ?></td>
<td class="py-3 text-center">🥇 <?= $r['w'] ?></td><td class="py-3 text-center">🥈 <?= $r['d'] ?></td><td class="py-3 text-center">🥉 <?= $r['l'] ?></td>
<?php if(!$medals): ?><td class="py-3 text-center font-extrabold text-3xl text-amber-300"><?= $r['pts'] ?></td><?php endif; ?></tr>
<?php endforeach; ?></tbody></table>
<?php endif; ?>
</div>
<script>setTimeout(()=>location.href=<?= json_encode($next) ?>, 15000);</script>
</body></html>
