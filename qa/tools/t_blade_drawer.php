<?php
// /info 햄버거 메뉴(info/_header.blade.php 의 "모바일 메뉴" 구간과 메뉴 스크립트 구간)를 작은 Blade 변환기로 컴파일해 실제로 돌려 본다.
// 사용: node php.js run t_blade_drawer.php ../../app/Support/MenuIcons.php ../../resources/views/info/_header.blade.php
namespace {
    function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
    function collect($a) { return new Coll($a); }
    class Coll implements \JsonSerializable {
        public $a;
        function __construct($a) { $this->a = $a; }
        function map($f) { return new Coll(array_map($f, $this->a)); }
        function pluck($k) { return new Coll(array_map(fn ($x) => $x[$k], $this->a)); }
        function values() { return new Coll(array_values($this->a)); }
        function contains($f) { foreach ($this->a as $x) if ($f($x)) return true; return false; }
        function jsonSerialize(): mixed { return $this->a; }
    }
    function json_encode_c($v) { return json_encode($v, JSON_UNESCAPED_UNICODE, 512); }

    function balanced($s, $i) { // $s[$i] == '(' → 닫는 괄호 위치
        $d = 0; $q = null; $n = strlen($s);
        for ($k = $i; $k < $n; $k++) {
            $c = $s[$k];
            if ($q) { if ($c === '\\') { $k++; continue; } if ($c === $q) $q = null; continue; }
            if ($c === "'" || $c === '"') { $q = $c; continue; }
            if ($c === '(') $d++;
            if ($c === ')') { $d--; if ($d === 0) return $k; }
        }
        throw new Exception('괄호가 안 닫힘: ' . substr($s, $i, 60));
    }

    function compile_blade($s) {
        $s = preg_replace('/\{\{--.*?--\}\}/s', '', $s);
        $s = preg_replace_callback('/@php\b(.*?)@endphp/s', fn ($m) => '<?php ' . $m[1] . ' ?>', $s);
        $s = preg_replace_callback('/\{!!\s*(.*?)\s*!!\}/s', fn ($m) => '<?php echo ' . $m[1] . '; ?>', $s);
        $s = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/s', fn ($m) => '<?php echo e(' . $m[1] . '); ?>', $s);
        $out = ''; $i = 0; $n = strlen($s);
        while ($i < $n) {
            if ($s[$i] === '@' && preg_match('/\G@(if|elseif|foreach|json)\s*\(/', $s, $m, 0, $i)) {
                $open = $i + strlen($m[0]) - 1;
                $close = balanced($s, $open);
                $expr = substr($s, $open + 1, $close - $open - 1);
                $d = $m[1];
                if ($d === 'if') $out .= "<?php if ($expr): ?>";
                elseif ($d === 'elseif') $out .= "<?php elseif ($expr): ?>";
                elseif ($d === 'foreach') $out .= "<?php foreach ($expr): ?>";
                else $out .= "<?php echo json_encode_c($expr); ?>";
                $i = $close + 1; continue;
            }
            if ($s[$i] === '@' && preg_match('/\G@(else|endif|endforeach)\b/', $s, $m, 0, $i)) {
                $out .= $m[1] === 'else' ? '<?php else: ?>' : ($m[1] === 'endif' ? '<?php endif; ?>' : '<?php endforeach; ?>');
                $i += strlen($m[0]); continue;
            }
            $out .= $s[$i++];
        }
        return $out;
    }

    function render($code, $vars) {
        extract($vars);
        ob_start();
        eval('?>' . $code);
        return ob_get_clean();
    }

    function check($ok, $msg) { echo ($ok ? 'ok   ' : 'FAIL ') . $msg . "\n"; if (!$ok) $GLOBALS['fails'] = ($GLOBALS['fails'] ?? 0) + 1; }
}

namespace App\Support {
    require '/tmp/MenuIcons.php';
}

namespace {
    $src = file_get_contents('/tmp/_header.blade.php');
    $a = strpos($src, '{{-- 모바일 메뉴 (슬라이드)');
    $b = strpos($src, '{{-- Google Translate 위젯');
    $drawer = substr($src, $a, $b - $a);
    $js0 = strpos($src, '// ☰ 메뉴의 묶음 펼치기');
    $js1 = strpos($src, '// EN 번역 토글');
    $js = substr($src, $js0, $js1 - $js0);
    check($a !== false && $b !== false && $js0 !== false && $js1 !== false, '구간 표시를 찾음');

    $menus = [
        ['key' => 'home', 'label' => '홈', 'path' => '/', 'icon' => '🏠'],
        ['key' => 'community', 'label' => '커뮤니티', 'path' => '/community', 'icon' => '💬'],
        ['key' => 'info', 'label' => '정보', 'path' => '/info', 'icon' => '📘'],
    ];
    $groups = [
        ['key' => 'home', 'label' => '홈', 'icon' => '', 'type' => 'single', 'items' => [$menus[0]], 'color' => '', 'image' => '', 'login_required' => false, 'path' => '/'],
        ['key' => 'hub', 'label' => 'N잡러', 'icon' => '', 'type' => 'hub', 'items' => [], 'color' => '#066', 'image' => '/img/n.png', 'login_required' => false, 'path' => '/money'],
        ['key' => 'community', 'label' => '커뮤니티', 'icon' => '', 'type' => 'group', 'items' => [$menus[1]], 'color' => '', 'image' => '', 'login_required' => false, 'path' => '/community'],
        ['key' => 'life', 'label' => '생활정보', 'icon' => '', 'type' => 'group', 'items' => [$menus[2]], 'color' => '', 'image' => '', 'login_required' => false, 'path' => '/info'],
    ];

    foreach ([['묶음', $groups], ['낱개', null]] as [$name, $navGroups]) {
        $html = render(compile_blade($drawer), ['navGroups' => $navGroups, 'menus' => $menus]);
        check(strpos($html, '전체 메뉴') !== false, "$name: 머리글");
        check(substr_count($html, 'info-menu-panel') >= 1 && strpos($html, '<?php') === false && strpos($html, '@if') === false && strpos($html, '{{') === false, "$name: 컴파일 찌꺼기 없음");
        check(strpos($html, 'icon-chip') !== false && strpos($html, '<svg') !== false, "$name: 아이콘 칩과 SVG");
        if ($navGroups) {
            check(substr_count($html, 'data-open-toggle') === 3, '묶음 화살표는 group 종류 2개 + 마이 1개');
            check(strpos($html, 'data-fav-group="life"') !== false && strpos($html, 'data-fav-group="dashboard"') !== false, '별 버튼(묶음·마이)');
            check(strpos($html, 'data-sub="life"') !== false && strpos($html, 'text-amber-700 font-bold') !== false, '정보 줄 강조');
            check(strpos($html, '/img/n.png') !== false, '허브 이미지');
            check(strpos($html, '마이페이지') !== false && strpos($html, '/friends') !== false, '마이 하위');
        } else {
            check(strpos($html, '>홈<') !== false || strpos($html, '홈') !== false, '낱개 메뉴 글자');
        }
    }

    $jsOut = render(compile_blade($js), ['navGroups' => $groups]);
    check(strpos($jsOut, 'var GROUPS = [{"key":"home","items":["home"]}') !== false, 'GROUPS 목록이 JSON 으로 들어감: ' . substr($jsOut, strpos($jsOut, 'var GROUPS'), 160));
    check(strpos($jsOut, '@') === false, '스크립트에 @ 찌꺼기 없음');

    echo ($GLOBALS['fails'] ?? 0) ? "\nFAILED\n" : "\nALL OK\n";
}
