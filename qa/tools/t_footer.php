<?php
namespace Illuminate\Database\Migrations { class Migration {} }
namespace App\Models { class SiteSetting {} }
namespace Illuminate\Support\Facades { class Cache {} class Schema {} }
namespace {
$m = require '/tmp/2026_10_14_240000_remove_games_link_from_saved_footer.php';
$ok = 0; $bad = 0;
function check($name, $cond, $extra = '') { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name $extra\n"; } }

$cfg = ['version' => 2, 'tagline' => 'x', 'columns' => [
  ['title' => '서비스', 'links' => [['label' => '커뮤니티', 'url' => '/community'], ['label' => '구인구직', 'url' => '/jobs']]],
  ['title' => '콘텐츠', 'links' => [['label' => '뉴스', 'url' => '/news'], ['label' => '레시피', 'url' => '/recipes'], ['label' => '게임', 'url' => '/games'], ['label' => '음악', 'url' => '/music']]],
  ['title' => '안내', 'links' => [['label' => '소개', 'url' => '/about']]],
], 'copyright' => '©', 'sns' => ['facebook' => '', 'instagram' => 'x'], 'additional_text' => ''];

$out = $m->strip($cfg, ['/games']);
$labels = array_map(fn ($c) => array_column($c['links'], 'label'), $out['columns']);
check('the games link is removed', $labels[1] === ['뉴스', '레시피', '음악'], json_encode($labels, JSON_UNESCAPED_UNICODE));
check('other columns untouched', $out['columns'][0] === $cfg['columns'][0] && $out['columns'][2] === $cfg['columns'][2]);
check('everything outside columns untouched', $out['version'] === 2 && $out['tagline'] === 'x' && $out['copyright'] === '©' && $out['sns'] === $cfg['sns'] && $out['additional_text'] === '');
check('link order preserved, keys reindexed', array_keys($out['columns'][1]['links']) === [0, 1, 2]);
check('idempotent: second run changes nothing', $m->strip($out, ['/games']) === $out);

$v = $cfg; $v['columns'][1]['links'][] = ['label' => '포커', 'url' => '/games/poker/'];
check('sub-paths and trailing slashes are removed too', array_column($m->strip($v, ['/games'])['columns'][1]['links'], 'label') === ['뉴스', '레시피', '음악']);
$n = $cfg; $n['columns'][1]['links'][] = ['label' => '게임즈', 'url' => '/gamesx'];
check('similar-looking path is NOT removed', in_array('게임즈', array_column($m->strip($n, ['/games'])['columns'][1]['links'], 'label'), true));
check('no games link -> config identical', $m->strip($out, ['/games']) === $out);
check('config without columns is returned as is', $m->strip(['version' => 2], ['/games']) === ['version' => 2]);
check('malformed links do not crash', is_array($m->strip(['columns' => [['links' => 'oops'], 'x', ['links' => [null, 5, ['label' => 'a']]]]], ['/games'])));
echo "\n$ok passed, $bad failed\n";
}
