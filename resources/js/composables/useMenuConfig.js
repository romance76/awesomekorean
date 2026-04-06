import { ref } from 'vue'
import axios from 'axios'

const menuConfig = ref(null)
let loaded = false

export function useMenuConfig() {
  async function loadConfig() {
    if (loaded) return
    try {
      const { data } = await axios.get('/api/settings/public')
      if (data.data?.menu_config) {
        const parsed = typeof data.data.menu_config === 'string' ? JSON.parse(data.data.menu_config) : data.data.menu_config
        if (Array.isArray(parsed)) menuConfig.value = parsed
      }
      loaded = true
    } catch {}
  }

  function getDefaultView(key) {
    if (!menuConfig.value) return 'list'
    const menu = menuConfig.value.find(m => m.key === key)
    return menu?.defaultView || 'list'
  }

  return { menuConfig, loadConfig, getDefaultView }
}
