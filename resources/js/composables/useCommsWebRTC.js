/**
 * useCommsWebRTC — PeerJS 기반 음성 통화
 *
 * PeerJS가 SDP/ICE 처리를 전부 내부적으로 해결.
 * 우리는 peer.call() / call.answer() 만 호출하면 됨.
 */
import { ref, computed, onUnmounted } from 'vue'
import axios from 'axios'
import Peer from 'peerjs'
import { useAuthStore } from '@/stores/auth'
import { startRingtone, stopRingtone } from '@/services/RingtoneService'

export function useCommsWebRTC() {
  // ── 상태 (기존 인터페이스 유지) ────────────────────────────────
  const callStatus         = ref('idle')
  const callDuration       = ref(0)
  const isMuted            = ref(false)
  const isSpeaker          = ref(true)
  const currentCallId      = ref(null)
  const currentRoomId      = ref(null)
  const remoteUser         = ref(null)
  const incomingCall       = ref(null)
  const remoteAudioBlocked = ref(false)

  let peer = null              // PeerJS Peer 인스턴스
  let currentMediaConn = null  // PeerJS MediaConnection
  let localStream = null
  let remoteAudioEl = null     // 원격 오디오 재생용 Audio 객체
  let durationTimer = null
  let missedTimer = null

  // ── PeerJS 초기화 ─────────────────────────────────────────────
  let myPeerId = null

  function initPeer(myUserId) {
    if (peer) return

    // 탭마다 고유 ID (같은 사용자 여러 탭 충돌 방지)
    const suffix = Math.random().toString(36).substring(2, 6)
    myPeerId = 'sk-' + myUserId + '-' + suffix
    console.log('[PeerJS] Initializing as', myPeerId)

    peer = new Peer(myPeerId, {
      host: window.location.hostname,
      port: 443,
      path: '/peerjs/',
      secure: true,
      config: {
        iceServers: [
          { urls: 'stun:stun.l.google.com:19302' },
          {
            urls: 'turn:68.183.60.70:3478',
            username: 'awesomekorean',
            credential: 'Skrtc2026!',
          },
          {
            urls: 'turn:68.183.60.70:3478?transport=tcp',
            username: 'awesomekorean',
            credential: 'Skrtc2026!',
          },
        ],
      },
      debug: 2,
    })

    peer.on('open', (id) => {
      console.log('[PeerJS] ✅ Connected as', id)
      // 서버에 내 peer ID 등록 (상대방이 나를 찾을 수 있게)
      axios.post('/api/comms/presence/peer-id', { peer_id: id }).catch(() => {})
    })

    peer.on('error', (err) => {
      console.error('[PeerJS] ❌ Error:', err.type, err.message)
    })

    // ★ 수신 전화 — PeerJS의 peer.on('call')
    peer.on('call', (mediaConnection) => {
      console.log('[PeerJS] 📞 Incoming media call from', mediaConnection.peer)
      // metadata에서 통화 정보 추출
      const meta = mediaConnection.metadata || {}
      currentMediaConn = mediaConnection

      // 이미 ringing 상태면 (Echo로 call.initiated 먼저 옴) → 대기
      // Echo로 안 왔으면 여기서 ringing 시작
      if (callStatus.value !== 'ringing') {
        incomingCall.value = {
          call_id: meta.call_id || null,
          room_id: meta.room_id || null,
          caller_id: meta.caller_id || null,
          caller_name: meta.caller_name || mediaConnection.peer,
          caller_avatar: meta.caller_avatar || null,
        }
        currentRoomId.value = meta.room_id || null
        callStatus.value = 'ringing'
        startRingtone()
        missedTimer = setTimeout(() => {
          if (callStatus.value === 'ringing') {
            stopRingtone()
            callStatus.value = 'idle'
            incomingCall.value = null
            currentMediaConn = null
          }
        }, 30000)
      }
    })

    peer.on('disconnected', () => {
      console.warn('[PeerJS] Disconnected, reconnecting...')
      if (peer && !peer.destroyed) peer.reconnect()
    })
  }

  // ── 원격 오디오 재생 ──────────────────────────────────────────
  function playRemoteStream(stream) {
    console.log('[PeerJS] 🔊 Playing remote stream, tracks:', stream.getAudioTracks().length)

    // 새 Audio 객체로 재생 (DOM 의존 없음)
    if (remoteAudioEl) { try { remoteAudioEl.pause() } catch {} }
    remoteAudioEl = new Audio()
    remoteAudioEl.srcObject = stream
    remoteAudioEl.autoplay = true
    remoteAudioEl.setAttribute('playsinline', '')
    remoteAudioEl.play().then(() => {
      remoteAudioBlocked.value = false
      console.log('[PeerJS] ✅ Remote audio playing!')
    }).catch(e => {
      console.warn('[PeerJS] ⚠️ Audio blocked:', e.name)
      remoteAudioBlocked.value = true
      // DOM 엘리먼트로 fallback
      const domAudio = document.getElementById('sk-remote-audio')
      if (domAudio) {
        domAudio.srcObject = stream
        domAudio.play().catch(() => {})
      }
    })
  }

  // ── MediaConnection 이벤트 설정 ───────────────────────────────
  function setupMediaConnection(mc) {
    mc.on('stream', (remoteStream) => {
      console.log('[PeerJS] ✅ Got remote stream!')
      playRemoteStream(remoteStream)
      if (callStatus.value !== 'connected') {
        callStatus.value = 'connected'
        startDurationTimer()
      }
    })

    mc.on('close', () => {
      console.log('[PeerJS] Media connection closed')
      handleCallEnded()
    })

    mc.on('error', (err) => {
      console.error('[PeerJS] Media error:', err)
      handleCallEnded()
    })
  }

  // ── 마이크 획득 ────────────────────────────────────────────────
  async function getLocalStream() {
    if (localStream) return localStream
    try {
      localStream = await navigator.mediaDevices.getUserMedia({
        audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
        video: false,
      })
      console.log('[PeerJS] ✅ Mic:', localStream.getAudioTracks()[0]?.label)
      return localStream
    } catch (err) {
      console.error('[PeerJS] ❌ Mic failed:', err.name)
      if (err.name === 'NotAllowedError') alert('마이크 권한을 허용해주세요.')
      return null
    }
  }

  // ── 타이머 ────────────────────────────────────────────────────
  function startDurationTimer() {
    stopDurationTimer()
    callDuration.value = 0
    durationTimer = setInterval(() => callDuration.value++, 1000)
  }
  function stopDurationTimer() {
    if (durationTimer) { clearInterval(durationTimer); durationTimer = null }
  }

  // ── 통화 종료 ─────────────────────────────────────────────────
  function handleCallEnded() {
    stopDurationTimer()
    stopRingtone()
    if (missedTimer) { clearTimeout(missedTimer); missedTimer = null }
    remoteAudioBlocked.value = false
    if (remoteAudioEl) { try { remoteAudioEl.pause(); remoteAudioEl.srcObject = null } catch {} }
    if (currentMediaConn) { try { currentMediaConn.close() } catch {} }
    localStream?.getTracks().forEach(t => t.stop())
    localStream = null
    currentMediaConn = null
    remoteAudioEl = null
    callStatus.value = 'ended'
    const endedId = currentCallId.value
    setTimeout(() => {
      if (currentCallId.value === endedId || callStatus.value === 'ended') {
        callStatus.value = 'idle'
        currentCallId.value = null
        currentRoomId.value = null
        remoteUser.value = null
        incomingCall.value = null
      }
    }, 3000)
  }

  // ── 소리 차단 해제 ────────────────────────────────────────────
  function unblockRemoteAudio() {
    if (remoteAudioEl) {
      remoteAudioEl.play().then(() => { remoteAudioBlocked.value = false }).catch(() => {})
    }
  }

  // ── WebSocket 수신 (Echo — 기존 유지, 벨소리 + FCM 보조) ──────
  function listenForSignals(myUserId) {
    // PeerJS 초기화
    initPeer(myUserId)

    if (!window.Echo) return

    window.Echo.private(`user.${myUserId}`)
      .listen('.call.initiated', (event) => {
        console.log('[PeerJS] Echo: incoming call event', event)
        // PeerJS peer.on('call')보다 먼저 올 수 있음
        if (callStatus.value === 'idle') {
          incomingCall.value = event
          currentRoomId.value = event.room_id
          callStatus.value = 'ringing'
          startRingtone()
          missedTimer = setTimeout(() => {
            if (callStatus.value === 'ringing') {
              stopRingtone()
              if (incomingCall.value?.call_id) {
                axios.post(`/api/comms/calls/${incomingCall.value.call_id}/end`).catch(() => {})
              }
              incomingCall.value = null
              currentRoomId.value = null
              callStatus.value = 'idle'
            }
          }, 30000)
        }
      })
      .listen('.webrtc.signal', (event) => {
        // call-ended 시그널만 처리 (나머지는 PeerJS가 처리)
        if (event.type === 'call-ended' && event.room_id === currentRoomId.value) {
          handleCallEnded()
        }
        if (event.type === 'call-answered-elsewhere') {
          if (!currentCallId.value && callStatus.value === 'ringing') {
            stopRingtone()
            if (missedTimer) { clearTimeout(missedTimer); missedTimer = null }
            incomingCall.value = null
            currentRoomId.value = null
            currentMediaConn = null
            callStatus.value = 'idle'
          }
        }
      })
  }

  // ── 발신 ───────────────────────────────────────────────────────
  async function startCall(targetUser) {
    if (callStatus.value !== 'idle' || !peer) return
    remoteUser.value = targetUser
    callStatus.value = 'calling'

    const stream = await getLocalStream()
    if (!stream) { callStatus.value = 'idle'; return }

    try {
      // 서버에 통화 기록 + FCM 푸시
      const { data } = await axios.post('/api/comms/calls/initiate', { callee_id: targetUser.id })
      currentCallId.value = data.call_id
      currentRoomId.value = data.room_id

      // 상대방의 PeerJS ID를 서버에서 조회
      const { data: peerData } = await axios.get(`/api/comms/presence/peer-id/${targetUser.id}`)
      const targetPeerId = peerData.peer_id
      if (!targetPeerId) {
        console.error('[PeerJS] Target peer ID not found — user offline?')
        // PeerJS 없어도 Echo로 call.initiated는 전달됨 → 상대가 접속하면 연결 시도
      }
      console.log('[PeerJS] Calling', targetPeerId)

      const auth = useAuthStore()
      currentMediaConn = peer.call(targetPeerId, stream, {
        metadata: {
          call_id: data.call_id,
          room_id: data.room_id,
          caller_id: auth.user?.id,
          caller_name: auth.user?.nickname || auth.user?.name || '알 수 없음',
          caller_avatar: auth.user?.avatar || null,
        },
      })

      setupMediaConnection(currentMediaConn)

      // 폴링 (서버에서 종료 확인)
      startCallMonitor()
    } catch (err) {
      console.error('[PeerJS] startCall failed:', err)
      handleCallEnded()
    }
  }

  // ── 수신 수락 ──────────────────────────────────────────────────
  async function answerCall() {
    if (!incomingCall.value) return

    const { call_id, room_id, caller_id, caller_name, caller_avatar } = { ...incomingCall.value }

    currentCallId.value = call_id
    currentRoomId.value = room_id
    remoteUser.value = { id: caller_id, name: caller_name, avatar: caller_avatar }
    callStatus.value = 'connecting'
    incomingCall.value = null
    stopRingtone()
    if (missedTimer) { clearTimeout(missedTimer); missedTimer = null }

    const stream = await getLocalStream()
    if (!stream) { callStatus.value = 'idle'; return }

    try {
      // 서버에 수락 알림
      if (call_id) await axios.post(`/api/comms/calls/${call_id}/answer`).catch(() => {})

      // ★ PeerJS answer — SDP/ICE 자동!
      if (currentMediaConn) {
        console.log('[PeerJS] Answering media connection')
        currentMediaConn.answer(stream)
        setupMediaConnection(currentMediaConn)
      } else {
        console.error('[PeerJS] No media connection to answer!')
      }

      startCallMonitor()
    } catch (err) {
      console.error('[PeerJS] answerCall error:', err)
    }
  }

  // ── 수신 거부 ──────────────────────────────────────────────────
  async function declineCall() {
    if (!incomingCall.value) return
    stopRingtone()
    if (missedTimer) { clearTimeout(missedTimer); missedTimer = null }
    if (currentMediaConn) { try { currentMediaConn.close() } catch {} }
    if (incomingCall.value.call_id) {
      await axios.post(`/api/comms/calls/${incomingCall.value.call_id}/end`).catch(() => {})
    }
    incomingCall.value = null
    currentRoomId.value = null
    currentMediaConn = null
    callStatus.value = 'idle'
  }

  // ── 통화 종료 ──────────────────────────────────────────────────
  async function endCall(notifyServer = true) {
    console.log('[PeerJS] endCall called, notifyServer:', notifyServer, 'callId:', currentCallId.value, 'remoteUser:', remoteUser.value?.id)
    if (notifyServer && currentCallId.value) {
      // 상대에게 시그널
      if (remoteUser.value?.id && currentRoomId.value) {
        axios.post('/api/comms/calls/signal', {
          target_user_id: remoteUser.value.id,
          room_id: currentRoomId.value,
          type: 'call-ended',
          payload: {},
        }).catch(e => console.warn('[PeerJS] signal end failed:', e))
      }
      try {
        await axios.post(`/api/comms/calls/${currentCallId.value}/end`)
        console.log('[PeerJS] ✅ End API sent OK for call', currentCallId.value)
      } catch (e) {
        console.error('[PeerJS] ❌ End API failed:', e.response?.status, e.response?.data)
      }
    } else {
      console.log('[PeerJS] endCall: skipping server notify (notifyServer:', notifyServer, 'callId:', currentCallId.value, ')')
    }
    handleCallEnded()
  }

  // ── 폴링 ──────────────────────────────────────────────────────
  function startCallMonitor() {
    const monitor = setInterval(async () => {
      if (!currentCallId.value || callStatus.value === 'idle' || callStatus.value === 'ended') {
        clearInterval(monitor); return
      }
      try {
        const { data } = await axios.get(`/api/comms/calls/${currentCallId.value}/status`)
        if (data.status === 'ended') { clearInterval(monitor); handleCallEnded() }
      } catch {}
    }, 3000)
  }

  // ── 컨트롤 ────────────────────────────────────────────────────
  function toggleMute() {
    localStream?.getAudioTracks().forEach(t => (t.enabled = !t.enabled))
    isMuted.value = !isMuted.value
  }

  async function toggleSpeaker() {
    isSpeaker.value = !isSpeaker.value
    // 모바일에서 setSinkId 미지원 → UI만 토글
  }

  const durationFormatted = computed(() => {
    const m = Math.floor(callDuration.value / 60).toString().padStart(2, '0')
    const s = (callDuration.value % 60).toString().padStart(2, '0')
    return `${m}:${s}`
  })

  onUnmounted(() => {
    try { endCall(false) } catch {}
    if (peer) { try { peer.destroy() } catch {} ; peer = null }
  })

  return {
    callStatus, callDuration, durationFormatted, isMuted, isSpeaker,
    currentCallId, currentRoomId, remoteUser, incomingCall,
    remoteAudioBlocked, unblockRemoteAudio,
    listenForSignals, startCall, answerCall, declineCall, endCall,
    toggleMute, toggleSpeaker,
  }
}
