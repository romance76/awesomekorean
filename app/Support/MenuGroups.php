<?php

namespace App\Support;

/**
 * 상단 메뉴 "묶음" (Kay 결정 2026-10-12, qa/menu_restructure_plan.md).
 * 메뉴 하나하나(이름·경로·켜기/끄기·관리자 전용·로그인 필요)는 지금처럼 site_settings.menu_config 에 두고,
 * 여기서는 그 메뉴들을 어떤 묶음에 어떤 순서로 보여 줄지만 정한다 (site_settings.menu_groups).
 * type: single(혼자 — 누르면 바로 그 메뉴) · group(묶음 — 페이지 제목 옆 하위 탭) · hub(허브 — 자기 첫 화면이 있는 묶음, 예: MONEY)
 * 켜고 끄기: site_settings.menu_groups_enabled ('1' 일 때만 사이트가 묶음 메뉴를 씀 — 끄면 바로 예전 메뉴)
 */
class MenuGroups
{
    public const TYPES = ['single', 'group', 'hub'];

    public static function defaults(): array
    {
        return [
            ['key' => 'home', 'label' => '홈', 'label_en' => 'Home', 'icon' => '🏠', 'type' => 'single', 'items' => ['home']],
            ['key' => 'community', 'label' => '커뮤니티', 'label_en' => 'Community', 'icon' => '💬', 'type' => 'group', 'items' => ['community', 'qa', 'chat', 'clubs']],
            ['key' => 'jobs', 'label' => '구인구직', 'label_en' => 'Jobs', 'icon' => '💼', 'type' => 'single', 'items' => ['jobs']],
            ['key' => 'realestate', 'label' => '부동산', 'label_en' => 'Real Estate', 'icon' => '🏢', 'type' => 'single', 'items' => ['realestate']],
            ['key' => 'buysell', 'label' => '사고팔기', 'label_en' => 'Buy & Sell', 'icon' => '🛒', 'type' => 'group', 'items' => ['market', 'groupbuy']],
            ['key' => 'bizads', 'label' => '업소·광고', 'label_en' => 'Business & Ads', 'icon' => '🏪', 'type' => 'group', 'items' => ['directory', 'flyers']],
            ['key' => 'life', 'label' => '생활정보', 'label_en' => 'Living', 'icon' => '📘', 'type' => 'group', 'items' => ['info', 'news', 'recipes', 'events']],
            ['key' => 'fun', 'label' => '즐길거리', 'label_en' => 'Fun', 'icon' => '🎵', 'type' => 'group', 'items' => ['shorts', 'music']],
            ['key' => 'shopping', 'label' => '내돈내산 리뷰', 'label_en' => 'Reviews', 'icon' => '🛍️', 'type' => 'single', 'items' => ['shopping']],
            // 2단계에서 첫 화면(/money)을 만든 뒤 숨김을 푼다
            ['key' => 'money', 'label' => 'MONEY', 'label_en' => 'MONEY', 'icon' => '💰', 'type' => 'hub', 'path' => '/money', 'hidden' => true, 'items' => []],
        ];
    }

    /**
     * 관리자 화면에서 보낸 묶음 목록을 검사·정리한다. 문제가 있으면 [null, '쉬운 안내 문구'].
     * @return array{0: ?array, 1: ?string}
     */
    public static function sanitize($groups): array
    {
        if (!is_array($groups) || count($groups) < 1 || count($groups) > 20) {
            return [null, '묶음은 1~20개까지 만들 수 있어요.'];
        }
        $out = [];
        $seenGroups = [];
        $seenItems = [];
        foreach (array_values($groups) as $i => $g) {
            $n = $i + 1;
            if (!is_array($g)) return [null, "{$n}번째 묶음 형식이 올바르지 않아요."];
            $key = $g['key'] ?? '';
            if (!is_string($key) || !preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $key)) return [null, "{$n}번째 묶음의 키가 올바르지 않아요."];
            if (isset($seenGroups[$key])) return [null, "묶음 키 '{$key}' 가 두 번 있어요."];
            $seenGroups[$key] = true;

            $label = is_string($g['label'] ?? null) ? trim($g['label']) : '';
            if ($label === '' || mb_strlen($label) > 30 || preg_match('/[<>]/', $label)) return [null, "{$n}번째 묶음 이름은 1~30자, < > 없이 적어 주세요."];
            $labelEn = is_string($g['label_en'] ?? null) ? trim($g['label_en']) : '';
            if (mb_strlen($labelEn) > 40 || preg_match('/[<>]/', $labelEn)) return [null, "{$n}번째 묶음의 영어 이름이 올바르지 않아요."];
            $icon = is_string($g['icon'] ?? null) ? trim($g['icon']) : '';
            if (mb_strlen($icon) > 8 || preg_match('/[<>]/', $icon)) return [null, "{$n}번째 묶음의 아이콘이 올바르지 않아요."];
            $type = $g['type'] ?? 'group';
            if (!in_array($type, self::TYPES, true)) return [null, "{$n}번째 묶음의 종류가 올바르지 않아요."];
            $path = $g['path'] ?? null;
            if ($path !== null && $path !== '' && (!is_string($path) || !SafeUrl::ok($path))) return [null, "{$n}번째 묶음의 주소가 올바르지 않아요."];

            $items = $g['items'] ?? [];
            if (!is_array($items) || count($items) > 20) return [null, "{$n}번째 묶음의 메뉴 목록이 올바르지 않아요."];
            $cleanItems = [];
            foreach ($items as $it) {
                if (!is_string($it) || !preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $it)) return [null, "'{$label}' 묶음에 올바르지 않은 메뉴가 있어요."];
                if (isset($seenItems[$it])) return [null, "메뉴 '{$it}' 가 두 묶음에 들어 있어요. 한 묶음에만 넣어 주세요."];
                $seenItems[$it] = true;
                $cleanItems[] = $it;
            }
            if ($type === 'single' && count($cleanItems) !== 1) return [null, "'{$label}' 는 혼자 메뉴라 메뉴가 정확히 1개여야 해요. (여러 개면 '묶음'으로 바꿔 주세요)"];
            if ($type === 'group' && count($cleanItems) < 1) return [null, "'{$label}' 묶음에 메뉴가 없어요."];
            if ($type === 'hub' && !$path) return [null, "'{$label}' 허브에는 첫 화면 주소가 필요해요."];

            $row = ['key' => $key, 'label' => $label, 'label_en' => $labelEn, 'icon' => $icon, 'type' => $type, 'items' => $cleanItems];
            if ($path) $row['path'] = $path;
            if (!empty($g['hidden'])) $row['hidden'] = true;
            $out[] = $row;
        }
        return [$out, null];
    }
}
