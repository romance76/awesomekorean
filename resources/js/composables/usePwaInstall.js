import { ref, onMounted, onUnmounted } from 'vue'

/**
 * usePwaInstall()
 * Handles PWA install prompt (beforeinstallprompt) and iOS guidance.
 */
export function usePwaInstall() {
  const canInstall    = ref(false)
  const isInstalled   = ref(false)
  const isIos         = ref(false)
  const installResult = ref(null)
  let deferredPrompt  = null

  function checkInstalled() {
    isInstalled.value =
      window.matchMedia('(display-mode: standalone)').matches ||
      window.navigator.standalone === true
  }

  function checkIos() {
    const ua = navigator.userAgent
    isIos.value = /iphone|ipad|ipod/i.test(ua) && !/crios/i.test(ua)
  }

  function onBeforeInstallPrompt(e) {
    e.preventDefault()
    deferredPrompt = e
    canInstall.value = true
  }

  onMounted(() => {
    checkInstalled()
    checkIos()
    if (!isInstalled.value) {
      window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt)
      // iOS doesn't fire beforeinstallprompt but we can still show guidance
      if (isIos.value) canInstall.value = true
    }
  })

  onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt)
  })

  async function triggerInstall() {
    if (isIos.value || !deferredPrompt) return
    deferredPrompt.prompt()
    const { outcome } = await deferredPrompt.userChoice
    installResult.value = outcome
    deferredPrompt = null
    canInstall.value = false
    if (outcome === 'accepted') isInstalled.value = true
  }

  return { canInstall, isInstalled, isIos, installResult, triggerInstall }
}
