<?php require __DIR__.'/lib/db.php';
$t = row("SELECT * FROM teams WHERE id=?", [$_GET['id'] ?? 0]);
if (!$t) die('ไม่พบสถาบัน');
$stat = null;
foreach (standings() as $r) if ($r['team']['id'] == $t['id']) $stat = $r;
$ms = rows("SELECT m.*, s.name AS sport_name, s.type AS sport_type FROM matches m JOIN sports s ON s.id=m.sport_id WHERE (m.team_a=? OR m.team_b=?) AND m.status IN ('live','finished') ORDER BY m.updated_at DESC", [$t['id'], $t['id']]);
$pls = rows("SELECT p.place, m.id AS mid, s.name AS sport_name, m.division FROM placements p JOIN matches m ON m.id=p.match_id JOIN sports s ON s.id=m.sport_id WHERE p.team_id=? AND m.status='finished' ORDER BY p.place", [$t['id']]);
$teams = []; foreach (rows("SELECT * FROM teams") as $x) $teams[$x['id']] = $x;
$pmedal = [1=>'🥇', 2=>'🥈', 3=>'🥉'];
?>
<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h(tname($t)) ?> • สถิติ</title>
<link rel="icon" type="image/png" href="uploads/logo/วพบ สุรินทร์.png">
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;800&display=swap" rel="stylesheet"><style>body{font-family:'Prompt',sans-serif}</style></head>
<body class="bg-gradient-to-b from-indigo-50 via-white to-amber-50 min-h-screen">
<div class="max-w-3xl mx-auto px-4 py-6">
<a href="index.php" class="text-sm text-gray-500">← กลับหน้าแรก</a>
<div class="bg-white rounded-2xl shadow p-5 mt-2 flex items-center gap-4">
<?= tlogo($t, 64) ?>
<div><h1 class="text-2xl font-extrabold" title="<?= h($t['name']) ?>"><?= h(tname($t)) ?></h1>
<p class="text-sm text-gray-500"><?= h($t['name']) ?><?= $t['grp']!=='' ? ' • สาย '.h($t['grp']) : '' ?></p>
<?php if($stat): ?><p class="mt-1 font-bold">แข่ง <?= $stat['p'] ?> • 🥇 <?= $stat['w'] ?> • 🥈 <?= $stat['d'] ?> • 🥉 <?= $stat['l'] ?><?php if(setting('board_mode','points')!=='medals'): ?> • <span class="text-orange-600 text-xl"><?= $stat['pts'] ?> แต้ม</span><?php endif; ?></p><?php endif; ?></div>
</div>
<?php if($pls): ?>
<div class="bg-white rounded-2xl shadow p-5 mt-4"><h2 class="font-bold mb-2">🏅 ผลอันดับ</h2>
<?php foreach($pls as $p): ?><div class="text-sm py-1 border-b last:border-0"><?= $pmedal[$p['place']] ?? $p['place'] ?> <b><?= h($p['sport_name']) ?></b> <?= div_badge($p['division']) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<div class="bg-white rounded-2xl shadow p-5 mt-4"><h2 class="font-bold mb-2">🏟️ ผลการแข่งขัน</h2>
<?php
$grp = ['live' => [], 'win' => [], 'draw' => [], 'loss' => []];
foreach ($ms as $m) {
  $mine_a = (int)$m['team_a'] === (int)$t['id'];
  $mine = $mine_a ? $m['score_a'] : $m['score_b'];
  $theirs = $mine_a ? $m['score_b'] : $m['score_a'];
  if ($m['status'] !== 'finished') $grp['live'][] = $m;
  elseif ($mine > $theirs) $grp['win'][] = $m;
  elseif ($mine < $theirs) $grp['loss'][] = $m;
  else $grp['draw'][] = $m;
}
$glb = ['live' => '🔴 กำลังแข่งขัน', 'win' => '✅ ชนะ', 'draw' => '➖ เสมอ', 'loss' => '❌ แพ้'];
foreach ($glb as $k => $lb):
if (!$grp[$k]) continue; ?>
<h3 class="mt-3 font-bold text-sm"><?= $lb ?> <span class="font-normal text-gray-400">(<?= count($grp[$k]) ?>)</span></h3>
<?php foreach ($grp[$k] as $m):
$ta = $teams[$m['team_a']] ?? null; $tb = $teams[$m['team_b']] ?? null; ?>
<div class="text-sm py-2 border-b last:border-0 flex flex-wrap items-center gap-2">
<b class="text-indigo-600"><?= h($m['sport_name']) ?></b><?= div_badge($m['division']) ?>
<span><?= h($ta ? tname($ta) : '?') ?> <?= $m['score_a'] ?> : <?= $m['score_b'] ?> <?= h($tb ? tname($tb) : '?') ?></span>
<?php if(trim($m['play_at'] ?? '') !== '' || trim($m['venue'] ?? '') !== ''): ?><span class="text-xs text-gray-400">📅 <?= h(th_when($m)) ?><?= trim($m['venue'] ?? '') !== '' ? ' 📍 '.h($m['venue']) : '' ?></span><?php endif; ?>
</div>
<?php endforeach; ?>
<?php endforeach;
if(!$ms && !$pls) echo '<p class="text-sm text-gray-400">ยังไม่มีผลการแข่งขัน</p>'; ?></div>
</div></body></html>
