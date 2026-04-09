import { ref } from 'vue'

const visible = ref(false)
const title = ref('')
const message = ref('')
const type = ref('alert') // alert | confirm | prompt
const inputValue = ref('')
const inputPlaceholder = ref('')
let resolveFn = null

export function useModal() {
  function showAlert(msg, ttl = '') {
    return new Promise(resolve => {
      title.value = ttl
      message.value = msg
      type.value = 'alert'
      visible.value = true
      resolveFn = resolve
    })
  }

  function showConfirm(msg, ttl = '') {
    return new Promise(resolve => {
      title.value = ttl
      message.value = msg
      type.value = 'confirm'
      visible.value = true
      resolveFn = resolve
    })
  }

  function showPrompt(msg, ttl = '', placeholder = '') {
    return new Promise(resolve => {
      title.value = ttl
      message.value = msg
      type.value = 'prompt'
      inputValue.value = ''
      inputPlaceholder.value = placeholder
      visible.value = true
      resolveFn = resolve
    })
  }

  function onOk() {
    visible.value = false
    if (type.value === 'prompt') resolveFn?.(inputValue.value)
    else resolveFn?.(true)
  }

  function onCancel() {
    visible.value = false
    if (type.value === 'prompt') resolveFn?.(null)
    else resolveFn?.(false)
  }

  return { visible, title, message, type, inputValue, inputPlaceholder, showAlert, showConfirm, showPrompt, onOk, onCancel }
}
