<?php
namespace Illuminate\Database\Migrations { class Migration {} }
namespace App\Models { class SiteSetting {} }
namespace Illuminate\Support\Facades { class Cache {} class Schema {} }
namespace {
$m = require '/tmp/2026_10_14_260000_remove_game_clause_from_terms.php';
$ok = 0; $bad = 0;
function check($name, $cond, $extra = '') { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name $extra\n"; } }

$src = file_get_contents('/tmp/2026_10_03_010000_expand_terms_and_privacy_page_content.php');
preg_match_all("/<<<'HTML'\n(.*?)\nHTML;/s", $src, $mm);
check('found both original documents', count($mm[1]) === 2, (string) count($mm[1]));
[$terms, $privacy] = $mm[1];

check('original terms really mention 게임', substr_count($terms, '게임') >= 6, (string) substr_count($terms, '게임'));
$t = $m->scrub($terms); $p = $m->scrub($privacy);
check('terms: no 게임 left', substr_count($t, '게임') === 0, (string) substr_count($t, '게임'));
check('terms: no 도박 left', substr_count($t, '도박') === 0);
check('privacy: no 게임 left', substr_count($p, '게임') === 0);
check('terms: article 24 title renamed', str_contains($t, '<h3>제24조 (포인트 및 가상재화에 관한 특칙)</h3>'));
check('terms: virtual currency is-not-cash notice kept', str_contains($t, '가상재화는 실제 화폐가 아니며, 현금 또는 경제적 가치로 환전·양도·출금될 수 없습니다.'));
check('terms: article 24 still points to company policy clause', str_contains($t, '회원 탈퇴 또는 서비스 종료 시 소멸될 수 있으며'));
check('terms: article numbers 23 and 25 untouched', str_contains($t, '제23조 (구인구직') && str_contains($t, '제25조 (유료서비스'));
check('terms: service list keeps other services', str_contains($t, '실시간 채팅, 숏츠, 이벤트, 친구, 음악듣기 등'));
check('terms: only the intended text changed (length shrank a little)', strlen($t) < strlen($terms) && strlen($terms) - strlen($t) < 400, (string) (strlen($terms) - strlen($t)));
check('privacy: other purposes kept', str_contains($p, '채팅, 친구 등 서비스 제공'));
check('idempotent', $m->scrub($t) === $t && $m->scrub($p) === $p);
check('already-edited text untouched', $m->scrub('<p>자유롭게 고친 문장</p>') === '<p>자유롭게 고친 문장</p>');
echo "\n$ok passed, $bad failed\n";
}
