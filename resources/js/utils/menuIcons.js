// 메뉴 key → AppIcon 이름 / 파스텔 칩 색상 매핑 (Warm Modern 리뉴얼 공용)
// NavBar, BottomNav, Home 카테고리 타일에서 공유한다.
export const MENU_ICONS = {
    home: 'home',
    community: 'message-circle',
    qa: 'help-circle',
    jobs: 'briefcase',
    market: 'shopping-cart',
    directory: 'store',
    realestate: 'building',
    events: 'calendar',
    news: 'newspaper',
    recipes: 'utensils',
    clubs: 'users',
    games: 'gamepad',
    shorts: 'video',
    music: 'music',
    groupbuy: 'shopping-bag',
    chat: 'message-square',
    friends: 'heart-handshake',
    elder: 'heart',
    comms: 'phone',
    shopping: 'shopping-bag',
    points: 'coins',
    dashboard: 'user',
}

// 파스텔 배경 / 아이콘 색 (Tailwind 클래스)
export const MENU_CHIP_COLORS = {
    home: 'bg-amber-50 text-amber-600',
    community: 'bg-blue-50 text-blue-600',
    qa: 'bg-amber-50 text-amber-600',
    jobs: 'bg-emerald-50 text-emerald-600',
    market: 'bg-orange-50 text-orange-600',
    directory: 'bg-pink-50 text-pink-600',
    realestate: 'bg-violet-50 text-violet-600',
    events: 'bg-rose-50 text-rose-600',
    news: 'bg-sky-50 text-sky-600',
    recipes: 'bg-orange-50 text-orange-600',
    clubs: 'bg-teal-50 text-teal-600',
    games: 'bg-indigo-50 text-indigo-600',
    shorts: 'bg-fuchsia-50 text-fuchsia-600',
    music: 'bg-purple-50 text-purple-600',
    groupbuy: 'bg-lime-50 text-lime-600',
    chat: 'bg-cyan-50 text-cyan-600',
    friends: 'bg-pink-50 text-pink-600',
    elder: 'bg-red-50 text-red-500',
    comms: 'bg-emerald-50 text-emerald-600',
    shopping: 'bg-lime-50 text-lime-600',
    points: 'bg-amber-50 text-amber-600',
    dashboard: 'bg-gray-100 text-ink-light',
}

export function menuIcon(key) {
    return MENU_ICONS[key] || 'grid'
}

export function menuChipColor(key) {
    return MENU_CHIP_COLORS[key] || 'bg-amber-50 text-amber-600'
}
