import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import ko from '../i18n/ko'
import en from '../i18n/en'

const MSGS = { ko, en }

export const useLangStore = defineStore('lang', () => {
  const locale = ref(localStorage.getItem('sk_lang') || 'ko')
  const t = computed(() => MSGS[locale.value] || MSGS.ko)

  function setLang(lang) {
    if (!MSGS[lang]) return
    locale.value = lang
    localStorage.setItem('sk_lang', lang)
  }

  function toggle() { setLang(locale.value === 'ko' ? 'en' : 'ko') }

  function $t(key) {
    const keys = key.split('.')
    let val = t.value
    for (const k of keys) val = val?.[k]
    return val ?? key
  }

  return { locale, t, setLang, toggle, $t }
})
