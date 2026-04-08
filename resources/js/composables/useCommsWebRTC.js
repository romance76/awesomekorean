import { ref, computed, onUnmounted } from 'vue'
import axios from 'axios'
import { startRingtone, stopRingtone } from '@/services/RingtoneService'

const ICE_SERVERS = {
  iceServers: [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
    { urls: 'stun:stun2.l.google.com:19302' },
    { urls: 'stun:stun3.l.google.com:19302' },
    // 무료 TURN 서버 (NAT 뒤에서도 연결 가능)
    {
      urls: 'turn:openrelay.metered.ca:80',
      username: 'openrelayproject',
      credential: 'openrelayproject',
    },
    {
      urls: 'turn:openrelay.metered.ca:443',
      username: 'openrelayproject',
      credential: 'openrelayproject',
    },
    {
      urls: 'turn:openrelay.metered.ca:443?transport=tcp',
      username: 'openrelayproject',
      credential: 'openrelayproject',
    },
  ],
  iceCandidatePoolSize: 10,
}

export function useCommsWebRTC() {
  const callStatus    = ref('idle')   // idle | ringing | calling | connected | ended
  const callDuration  = ref(0)
  const isMuted       = ref(false)
  const isSpeaker     = ref(true)
  const currentCallId = ref(null)
  const currentRoomId = ref(null)
  const remoteUser    = ref(null)
  const incomingCall  = ref(null)

  let pc = null
  let localStream = null
  let durationTimer = null
  let missedTimer = null
  let pendingOffer = null  // 수신 중 도착한 offer 버퍼
  let pendingIceCandidates = []  // PC 생성 전 도착한 ICE 후보 버퍼

  // ── PeerConnection ──────────────────────────────────────────────
  function createPeerConnection(roomId, targetUserId) {
    if (pc) { try { pc.close() } catch {} }
    pc = new RTCPeerConnection(ICE_SERVERS)

    pc.onicecandidate = ({ candidate }) => {
      if (candidate) {
        axios.post('/api/comms/calls/signal', {
          target_user_id: targetUserId,
          room_id: roomId,
          type: 'ice-candidate',
          payload: { candidate: candidate.toJSON() },
        }).catch(e => console.warn('[WebRTC] ICE signal failed:', e))
      }
    }

    pc.ontrack = (event) => {
      console.log('[WebRTC] Remote track received')
      const audio = document.getElementById('sk-remote-audio')
      if (audio) {
        audio.srcObject = event.streams[0]
        audio.play().catch(() => {})
      }
    }

    pc.onconnectionstatechange = () => {
      console.log('[WebRTC] Connection state:', pc?.connectionState)
      if (pc && ['failed', 'disconnected', 'closed'].includes(pc.connectionState)) {
        handleCallEnded()
      }
      if (pc?.connectionState === 'connected' && callStatus.value !== 'connected') {
        callStatus.value = 'connected'
        startDurationTimer()
      }
    }

    // 버퍼된 ICE 후보 처리
    if (pendingIceCandidates.length > 0) {
      console.log(`[WebRTC] Processing ${pendingIceCandidates.length} buffered ICE candidates`)
      pendingIceCandidates.forEach(c => {
        pc.addIceCandidate(new RTCIceCandidate(c)).catch(() => {})
      })
      pendingIceCandidates = []
    }

    return pc
  }

  async function getLocalStream() {
    if (localStream) return localStream
    try {
      localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false })
      return localStream
    } catch (err) {
      console.error('[WebRTC] 마이크 접근 실패:', err.name, err.message)
      if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        throw new Error('마이크를 찾을 수 없습니다. 마이크가 연결되어 있는지 확인해주세요.')
      } else if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        throw new Error('마이크 권한이 거부되었습니다. 브라우저 설정에서 마이크를 허용해주세요.')
      }
      throw new Error('마이크를 사용할 수 없습니다: ' + err.message)
    }
  }

  function startDurationTimer() {
    stopDurationTimer()
    callDuration.value = 0
    durationTimer = setInterval(() => callDuration.value++, 1000)
  }

  function stopDurationTimer() {
    if (durationTimer) { clearInterval(durationTimer); durationTimer = null }
  }

  function handleCallEnded() {
    stopDurationTimer()
    stopRingtone()
    if (missedTimer) { clearTimeout(missedTimer); missedTimer = null }
    localStream?.getTracks().forEach(t => t.stop())
    if (pc) { try { pc.close() } catch {} }
    pc = null
    localStream = null
    pendingOffer = null
    pendingIceCandidates = []
    callStatus.value = 'ended'
    setTimeout(() => {
      callStatus.value = 'idle'
      currentCallId.value = null
      currentRoomId.value = null
      remoteUser.value = null
      incomingCall.value = null
    }, 2000)
  }

  // ── WebSocket 시그널 수신 ─────────────────────────────────────
  function listenForSignals(myUserId) {
    if (!window.Echo) {
      console.warn('[WebRTC] window.Echo not available')
      return
    }

    window.Echo.private(`user.${myUserId}`)
      .listen('.webrtc.signal', async (event) => {
        const { type, payload, room_id } = event
        console.log('[WebRTC] Signal received:', type, 'room:', room_id)

        // offer 도착 — 수신 중이면 버퍼링
        if (type === 'offer') {
          if (callStatus.value === 'ringing' || currentRoomId.value === room_id) {
            pendingOffer = { sdp: payload.sdp, room_id }
            console.log('[WebRTC] Offer buffered (waiting for user to answer)')
          }
          return
        }

        // answer 도착 — 발신자가 받음
        if (type === 'answer') {
          if (pc && currentRoomId.value === room_id) {
            console.log('[WebRTC] Setting remote answer')
            await pc.setRemoteDescription(new RTCSessionDescription(payload.sdp))
          }
          return
        }

        // ICE 후보
        if (type === 'ice-candidate') {
          if (pc && pc.remoteDescription) {
            await pc.addIceCandidate(new RTCIceCandidate(payload.candidate)).catch(() => {})
          } else {
            // PC가 아직 없거나 remoteDescription이 없으면 버퍼
            pendingIceCandidates.push(payload.candidate)
            console.log('[WebRTC] ICE candidate buffered')
          }
          return
        }

        if (type === 'call-answered') {
          if (callStatus.value === 'calling') {
            callStatus.value = 'connected'
            startDurationTimer()
          }
          return
        }

        if (type === 'call-ended') {
          endCall(false)
        }
      })
      .listen('.call.initiated', (event) => {
        console.log('[WebRTC] Incoming call:', event)
        incomingCall.value = event
        currentRoomId.value = event.room_id  // ← 중요! offer 매칭을 위해 미리 설정
        callStatus.value = 'ringing'
        startRingtone()

        // 30초 부재중 처리
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
      })
  }

  // ── 발신 ───────────────────────────────────────────────────────
  async function startCall(targetUser) {
    if (callStatus.value !== 'idle') return
    remoteUser.value = targetUser
    callStatus.value = 'calling'

    try {
      const { data } = await axios.post('/api/comms/calls/initiate', { callee_id: targetUser.id })
      currentCallId.value = data.call_id
      currentRoomId.value = data.room_id

      const stream = await getLocalStream()
      createPeerConnection(data.room_id, targetUser.id)
      stream.getTracks().forEach(t => pc.addTrack(t, stream))

      const offer = await pc.createOffer()
      await pc.setLocalDescription(offer)

      await axios.post('/api/comms/calls/signal', {
        target_user_id: targetUser.id,
        room_id: data.room_id,
        type: 'offer',
        payload: { sdp: offer },
      })
      console.log('[WebRTC] Offer sent to', targetUser.name)
    } catch (err) {
      console.error('[WebRTC] startCall failed:', err)
      handleCallEnded()
      throw err
    }
  }

  // ── 수신 수락 ──────────────────────────────────────────────────
  async function answerCall() {
    if (!incomingCall.value) return
    stopRingtone()
    if (missedTimer) { clearTimeout(missedTimer); missedTimer = null }

    const { call_id, room_id, caller_id, caller_name, caller_avatar } = incomingCall.value
    currentCallId.value = call_id
    currentRoomId.value = room_id
    remoteUser.value = { id: caller_id, name: caller_name, avatar: caller_avatar }

    try {
      // 서버에 수락 알림
      await axios.post(`/api/comms/calls/${call_id}/answer`)

      // 마이크 스트림
      const stream = await getLocalStream()
      createPeerConnection(room_id, caller_id)
      stream.getTracks().forEach(t => pc.addTrack(t, stream))

      // 버퍼된 offer 처리
      if (pendingOffer && pendingOffer.room_id === room_id) {
        console.log('[WebRTC] Processing buffered offer')
        await pc.setRemoteDescription(new RTCSessionDescription(pendingOffer.sdp))
        const answer = await pc.createAnswer()
        await pc.setLocalDescription(answer)

        await axios.post('/api/comms/calls/signal', {
          target_user_id: caller_id,
          room_id,
          type: 'answer',
          payload: { sdp: answer },
        })
        pendingOffer = null
        console.log('[WebRTC] Answer sent back to caller')
      } else {
        console.warn('[WebRTC] No buffered offer — waiting for offer to arrive')
      }

      callStatus.value = 'connected'
      incomingCall.value = null
      startDurationTimer()
    } catch (err) {
      console.error('[WebRTC] answerCall failed:', err)
      handleCallEnded()
    }
  }

  // ── 수신 거부 ──────────────────────────────────────────────────
  async function declineCall() {
    if (!incomingCall.value) return
    stopRingtone()
    if (missedTimer) { clearTimeout(missedTimer); missedTimer = null }
    await axios.post(`/api/comms/calls/${incomingCall.value.call_id}/end`).catch(() => {})
    incomingCall.value = null
    currentRoomId.value = null
    pendingOffer = null
    pendingIceCandidates = []
    callStatus.value = 'idle'
  }

  // ── 통화 종료 ──────────────────────────────────────────────────
  async function endCall(notifyServer = true) {
    if (notifyServer && currentCallId.value) {
      await axios.post(`/api/comms/calls/${currentCallId.value}/end`).catch(() => {})
    }
    handleCallEnded()
  }

  // ── 음소거 토글 ────────────────────────────────────────────────
  function toggleMute() {
    localStream?.getAudioTracks().forEach(t => (t.enabled = !t.enabled))
    isMuted.value = !isMuted.value
  }

  // ── 스피커폰 토글 ─────────────────────────────────────────────
  async function toggleSpeaker() {
    isSpeaker.value = !isSpeaker.value
    const audio = document.getElementById('sk-remote-audio')
    if (audio && typeof audio.setSinkId === 'function') {
      try {
        const devices = await navigator.mediaDevices.enumerateDevices()
        const speakers = devices.filter(d => d.kind === 'audiooutput')
        if (speakers.length > 1) {
          // 스피커폰이면 기본(스피커), 아니면 이어피스(첫번째 장치)
          const targetId = isSpeaker.value ? 'default' : speakers[0].deviceId
          await audio.setSinkId(targetId)
        }
      } catch (e) {
        console.warn('[WebRTC] setSinkId failed:', e)
      }
    }
  }

  const durationFormatted = computed(() => {
    const m = Math.floor(callDuration.value / 60).toString().padStart(2, '0')
    const s = (callDuration.value % 60).toString().padStart(2, '0')
    return `${m}:${s}`
  })

  onUnmounted(() => { endCall(false) })

  return {
    callStatus, callDuration, durationFormatted, isMuted, isSpeaker,
    currentCallId, currentRoomId, remoteUser, incomingCall,
    listenForSignals, startCall, answerCall, declineCall, endCall,
    toggleMute, toggleSpeaker,
  }
}
