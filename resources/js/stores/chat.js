import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useChatStore = defineStore('chat', () => {
  // 열린 채팅방들 [{id, name, type:'club'|'dm'|'group'}]
  const openRooms = ref([])
  const activeRoomId = ref(null)
  const isOpen = ref(false)

  const activeRoom = computed(() => openRooms.value.find(r => r.id === activeRoomId.value))
  const hasRooms = computed(() => openRooms.value.length > 0)

  function openRoom(room) {
    // { id, name, type }
    if (!openRooms.value.find(r => r.id === room.id)) {
      openRooms.value.push(room)
    }
    activeRoomId.value = room.id
    isOpen.value = true
  }

  function closeRoom(roomId) {
    openRooms.value = openRooms.value.filter(r => r.id !== roomId)
    if (activeRoomId.value === roomId) {
      activeRoomId.value = openRooms.value[0]?.id || null
    }
    if (!openRooms.value.length) isOpen.value = false
  }

  function toggleOpen() {
    isOpen.value = !isOpen.value
  }

  function minimize() {
    isOpen.value = false
  }

  // 떠 있는 채팅 아이콘/팝업을 한 번에 닫는다 (열어 둔 방 목록을 모두 비움)
  function closeAll() {
    openRooms.value = []
    activeRoomId.value = null
    isOpen.value = false
  }

  return { openRooms, activeRoomId, activeRoom, hasRooms, isOpen, openRoom, closeRoom, closeAll, toggleOpen, minimize }
})
