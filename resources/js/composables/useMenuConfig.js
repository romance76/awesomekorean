import { useSiteStore } from '../stores/site'

export function useMenuConfig() {
  const siteStore = useSiteStore()

  async function loadConfig() {
    await siteStore.load() // 스토어에서 한 번만 로드
  }

  function getDefaultView(key) {
    const mc = siteStore.menuConfig
    if (!mc) return 'list'
    const menu = mc.find(m => m.key === key)
    return menu?.defaultView || 'list'
  }

  return { menuConfig: siteStore.menuConfig, loadConfig, getDefaultView }
}
