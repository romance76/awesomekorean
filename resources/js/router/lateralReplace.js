// 같은 메뉴 묶음 안에서 탭끼리 옮겨 다닐 때(정보 → 뉴스 → 레시피 → 이벤트 …)는 이동 기록을 새로 쌓지 않고 지금 기록을 바꿔 끼운다.
// 이렇게 하지 않으면 뒤로가기가 "탭을 하나씩 거슬러" 가다가 그 묶음의 첫 화면(정보)에서 멈춰 보여서,
// 사용자는 "뒤로가기를 눌렀더니 메뉴 처음으로 돌아간다"고 느낀다. 바꿔 끼우면 뒤로가기는 그 묶음에 들어오기 전 화면으로 간다.
//
// 규칙: 지금 화면과 가려는 화면이 둘 다 "같은 묶음의 메뉴 첫 화면"일 때만 바꿔 끼움.
//   - 목록 → 글 상세(/info/글), 묶음 밖 이동, 글쓰기 등은 그대로 쌓는다(뒤로가기로 목록에 돌아와야 하므로).
//   - 사이트 상단 메뉴 묶음(site.menuGroups, type=group)과 관리자 메뉴 그룹(adminMenu 의 pcGroup)을 같은 방식으로 본다.
// router.push 를 감싸서 RouterLink·InfoFrame(서버 페이지 틀)·직접 push 호출을 한 번에 다룬다. (뒤로/앞으로 이동에는 영향 없음)
import { useSiteStore } from '../stores/site'
import { ADMIN_ITEMS } from '../admin/adminMenu'

const norm = (p) => (String(p || '/').split(/[?#]/)[0].replace(/\/+$/, '') || '/')

function publicGroupOf(path, site) {
  const groups = site.menuGroups
  if (!groups) return null
  const cfg = {}
  for (const m of (site.menuConfig || [])) cfg[m.key] = m
  for (const g of groups) {
    if (g.hidden || g.type !== 'group') continue
    if ((g.items || []).some(k => cfg[k] && norm(cfg[k].path) === path)) return 'p:' + g.key
  }
  return null
}

function adminGroupOf(path) {
  const it = ADMIN_ITEMS.find(i => i.to && norm(i.to) === path)
  return it ? 'a:' + it.pcGroup : null
}

export function groupKeyOf(path, site) {
  const p = norm(path)
  return p === '/admin' || p.startsWith('/admin/') ? adminGroupOf(p) : publicGroupOf(p, site)
}

export function installLateralReplace(router) {
  const push = router.push.bind(router)
  router.push = (to) => {
    try {
      const cur = router.currentRoute.value
      const target = router.resolve(to)
      if (norm(target.path) !== norm(cur.path)) {
        const site = useSiteStore()
        const a = groupKeyOf(cur.path, site)
        if (a && a === groupKeyOf(target.path, site)) return router.replace(to)
      }
    } catch { /* 판단하지 못하면 평소처럼 쌓는다 */ }
    return push(to)
  }
}
