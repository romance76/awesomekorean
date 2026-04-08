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
      if (pc?.connectionState === 'connected') {
        console.log('[WebRTC] ✅ P2P Connected — audio should work!')
        if (callStatus.value !== 'connected') {
          callStatus.value = 'connected'
          startDurationTimer()
        }
      }
      // failed만 로그 — 바로 끊지 않음 (TURN으로 재시도 가능)
      if (pc?.connectionState === 'failed') {
        console.error('[WebRTC] ❌ Connection failed — but keeping call alive')
        // 10초 후에도 failed면 종료
        setTimeout(() => {
          if (pc?.connectionState === 'failed') {
            console.error('[WebRTC] ❌ Still failed after 10s — ending call')
            handleCallEnded()
          }
        }, 10000)
      }
    }

    // ICE 후보는 setRemoteDescription 후에 처리해야 함 → 여기서는 하지 않음
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
    console.log('[WebRTC] Call ended — showing end screen for 3s')
    setTimeout(() => {
      callStatus.value = 'idle'
      currentCallId.value = null
      currentRoomId.value = null
      remoteUser.value = null
      incomingCall.value = null
    }, 3000)
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
            // answer 설정 후 버퍼된 ICE 후보 처리
            if (pendingIceCandidates.length > 0) {
              console.log(`[WebRTC] Processing ${pendingIceCandidates.length} buffered ICE (caller)`)
              for (const c of pendingIceCandidates) {
                await pc.addIceCandidate(new RTCIceCandidate(c)).catch(() => {})
              }
              pendingIceCandidates = []
            }
            callStatus.value = 'connected'
            startDurationTimer()
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

        // 다른 기기에서 수락됨 → 벨 울리는 중이면 중지
        if (type === 'call-answered-elsewhere') {
          // 내가 수락한 통화면 무시 (currentCallId가 설정됨)
          if (currentCallId.value) {
            console.log('[WebRTC] call-answered-elsewhere ignored (I answered this call)')
          } else if (callStatus.value === 'ringing') {
            console.log('[WebRTC] Call answered on another device — dismissing')
            stopRingtone()
            if (missedTimer) { clearTimeout(missedTimer); missedTimer = null }
            incomingCall.value = null
            currentRoomId.value = null
            pendingOffer = null
            pendingIceCandidates = []
            callStatus.value = 'idle'
          }
          return
        }

        if (type === 'call-ended') {
          // 현재 진행 중인 통화의 room만 종료
          if (room_id === currentRoomId.value) {
            console.log('[WebRTC] Call ended by remote')
            endCall(false)
          } else {
            console.log('[WebRTC] call-ended for different room, ignoring')
          }
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
      console.log('[WebRTC] Call initiated:', data.call_id, data.room_id)

      // 마이크 (실패해도 계속 진행)
      let stream = null
      try {
        stream = await getLocalStream()
        console.log('[WebRTC] Microphone OK')
      } catch (micErr) {
        console.warn('[WebRTC] Microphone failed:', micErr.message, '— proceeding without mic')
      }

      console.log('[WebRTC] Creating PeerConnection...')
      createPeerConnection(data.room_id, targetUser.id)
      if (stream) {
        stream.getTracks().forEach(t => pc.addTrack(t, stream))
        console.log('[WebRTC] Tracks added to PC')
      }

      console.log('[WebRTC] Creating offer...')
      const offer = await pc.createOffer({ offerToReceiveAudio: true })
      await pc.setLocalDescription(offer)
      console.log('[WebRTC] Offer created, SDP length:', offer.sdp?.length, 'sending to signal API...')

      await axios.post('/api/comms/calls/signal', {
        target_user_id: targetUser.id,
        room_id: data.room_id,
        type: 'offer',
        payload: { sdp: offer },
      })
      console.log('[WebRTC] ✅ Offer sent to', targetUser.name)
    } catch (err) {
      console.error('[WebRTC] startCall failed:', err)
      handleCallEnded()
      throw err
    }
  }

  // ── 수신 수락 ──────────────────────────────────────────────────
  async function answerCall() {
    console.log('[WebRTC] answerCall called, incomingCall:', JSON.stringify(incomingCall.value), 'status:', callStatus.value)
    if (!incomingCall.value) {
      console.warn('[WebRTC] answerCall: no incomingCall!')
      return
    }
    stopRingtone()
    if (missedTimer) { clearTimeout(missedTimer); missedTimer = null }

    const { call_id, room_id, caller_id, caller_name, caller_avatar } = incomingCall.value
    currentCallId.value = call_id
    currentRoomId.value = room_id
    remoteUser.value = { id: caller_id, name: caller_name, avatar: caller_avatar }
    callStatus.value = 'connected'
    incomingCall.value = null
    startDurationTimer()  // 수락 즉시 타이머 시작

    try {
      // 1. 서버에 수락 알림
      console.log('[WebRTC] Step 1: Answering call', call_id, 'room', room_id)
      try {
        const resp = await axios.post(`/api/comms/calls/${call_id}/answer`)
        console.log('[WebRTC] Step 1 OK: Server responded', resp.status, resp.data)
      } catch (apiErr) {
        console.error('[WebRTC] Step 1 FAILED: API error', apiErr.response?.status, apiErr.response?.data)
        // API 실패해도 계속 진행 (로컬 통화는 시도)
      }

      // 2. 마이크 스트림 (실패해도 계속 진행)
      let stream = null
      try {
        stream = await getLocalStream()
        console.log('[WebRTC] Step 2 OK: Microphone acquired')
      } catch (micErr) {
        console.warn('[WebRTC] Step 2 WARN: No microphone —', micErr.message, '(수신 전용)')
      }

      // 3. PeerConnection 생성
      createPeerConnection(room_id, caller_id)
      if (stream) {
        stream.getTracks().forEach(t => pc.addTrack(t, stream))
        console.log('[WebRTC] Step 3 OK: Local tracks added')
      } else {
        console.log('[WebRTC] Step 3: No local tracks (listen only)')
      }

      // 4. 버퍼된 offer 처리
      if (pendingOffer && pendingOffer.room_id === room_id) {
        console.log('[WebRTC] Step 4: Processing buffered offer')
        await pc.setRemoteDescription(new RTCSessionDescription(pendingOffer.sdp))
        console.log('[WebRTC] Step 4a OK: Remote description set')

        // 4b. setRemoteDescription 후에 버퍼된 ICE 후보 처리 (순서 중요!)
        if (pendingIceCandidates.length > 0) {
          console.log(`[WebRTC] Step 4a-1: Processing ${pendingIceCandidates.length} buffered ICE candidates`)
          for (const c of pendingIceCandidates) {
            await pc.addIceCandidate(new RTCIceCandidate(c)).catch(e => console.warn('[WebRTC] ICE add failed:', e))
          }
          pendingIceCandidates = []
        }

        const answer = await pc.createAnswer()
        await pc.setLocalDescription(answer)
        console.log('[WebRTC] Step 4b OK: Local answer created')

        await axios.post('/api/comms/calls/signal', {
          target_user_id: caller_id,
          room_id,
          type: 'answer',
          payload: { sdp: answer },
        })
        pendingOffer = null
        console.log('[WebRTC] Step 4c OK: Answer sent to caller ✅')
      } else {
        console.warn('[WebRTC] Step 4 WARN: No buffered offer — room:', room_id, 'pending:', pendingOffer?.room_id)
      }

      startDurationTimer()
      console.log('[WebRTC] Call connected! ✅')
    } catch (err) {
      console.error('[WebRTC] answerCall CATCH:', err.message || err, err)
      // 에러 발생해도 화면 유지 — 수동으로 끊기 전까지
      // (endCall 버튼으로만 종료 가능)
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

  onUnmounted(() => { try { endCall(false) } catch {} })

  return {
    callStatus, callDuration, durationFormatted, isMuted, isSpeaker,
    currentCallId, currentRoomId, remoteUser, incomingCall,
    listenForSignals, startCall, answerCall, declineCall, endCall,
    toggleMute, toggleSpeaker,
  }
}
