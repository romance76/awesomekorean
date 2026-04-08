import { ref, computed, onUnmounted } from 'vue'
import axios from 'axios'
import { startRingtone, stopRingtone, unlockAudio } from '@/services/RingtoneService'

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
  let disconnectTimer = null  // disconnected 상태 딜레이 타이머
  let pendingOffer = null  // 수신 중 도착한 offer 버퍼
  let pendingIceCandidates = []  // PC 생성 전 도착한 ICE 후보 버퍼

  // SDP 문자열 정리 (브라우저 간 호환성 문제 방지)
  function sanitizeSdp(sdpObj) {
    if (!sdpObj || !sdpObj.sdp) return sdpObj
    return {
      type: sdpObj.type,
      sdp: sdpObj.sdp
        .replace(/ +(\r?\n)/g, '$1')     // 줄 끝 공백 제거
        .replace(/\r?\n/g, '\r\n')        // 일관된 CRLF
    }
  }

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
        // 이미 unlockRemoteAudio()로 재생 중이면 srcObject만 바꿔도 OK
        // 아직 안 된 경우 다시 시도
        audio.play().catch(e => {
          console.warn('[WebRTC] Remote audio play failed:', e.name, '— will retry on user gesture')
        })
      }
    }

    pc.onconnectionstatechange = () => {
      const state = pc?.connectionState
      console.log('[WebRTC] Connection state:', state)

      if (state === 'connected') {
        console.log('[WebRTC] ✅ P2P Connected — audio should work!')
        // 연결 복구 시 disconnectTimer 취소
        if (disconnectTimer) { clearTimeout(disconnectTimer); disconnectTimer = null }
        if (callStatus.value !== 'connected') {
          callStatus.value = 'connected'
          startDurationTimer()
        }
      }
      if (state === 'failed') {
        console.error('[WebRTC] ❌ Connection failed')
        setTimeout(() => {
          if (pc?.connectionState === 'failed') handleCallEnded()
        }, 5000)
      }
      // disconnected는 일시적일 수 있음 → 2초 대기 후 여전히 disconnected면 종료
      if (state === 'disconnected') {
        console.log('[WebRTC] Peer disconnected — waiting 2s before ending')
        if (disconnectTimer) clearTimeout(disconnectTimer)
        disconnectTimer = setTimeout(() => {
          if (pc?.connectionState === 'disconnected' || pc?.connectionState === 'closed') {
            console.log('[WebRTC] Still disconnected after 2s — ending call')
            handleCallEnded()
          }
        }, 2000)
      }
      // closed는 즉시 종료 (의도적 종료)
      if (state === 'closed') {
        console.log('[WebRTC] Peer closed — ending call NOW')
        handleCallEnded()
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
      console.warn('[WebRTC] 마이크 접근 실패:', err.name, err.message)
      // throw하지 않고 null 리턴 — 호출자가 null 체크
      return null
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
    if (disconnectTimer) { clearTimeout(disconnectTimer); disconnectTimer = null }
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
            await pc.setRemoteDescription(sanitizeSdp(payload.sdp))
            // answer 설정 후 버퍼된 ICE 후보 처리
            if (pendingIceCandidates.length > 0) {
              console.log(`[WebRTC] Processing ${pendingIceCandidates.length} buffered ICE (caller)`)
              for (const c of pendingIceCandidates) {
                await pc.addIceCandidate(c).catch(() => {})
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
            await pc.addIceCandidate(payload.candidate).catch(() => {})
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

  // ── 모바일 원격 오디오 unlock ────────────────────────────────────
  // 사용자 제스처(전화걸기/수락) 직후 즉시 호출하여 <audio> 재생 허용
  function unlockRemoteAudio() {
    // 1. AudioContext unlock
    unlockAudio()

    // 2. 무음 MediaStream을 만들어서 <audio>에 소스로 설정 후 play
    //    소스 없이 play()하면 모바일에서 거부됨 → 반드시 소스 필요
    const audio = document.getElementById('sk-remote-audio')
    if (!audio) return

    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)()
      if (ctx.state === 'suspended') ctx.resume()
      // 무음 스트림 생성 (oscillator 없이 destination만)
      const dest = ctx.createMediaStreamDestination()
      audio.srcObject = dest.stream
      audio.play().then(() => {
        console.log('[WebRTC] ✅ Remote audio unlocked with silent stream')
      }).catch(e => {
        console.warn('[WebRTC] Remote audio unlock failed:', e.name)
      })
    } catch (e) {
      // AudioContext 실패 시 fallback
      audio.play().catch(() => {})
      console.warn('[WebRTC] Silent stream creation failed:', e)
    }
  }

  // ── 발신 ───────────────────────────────────────────────────────
  async function startCall(targetUser) {
    if (callStatus.value !== 'idle') return
    remoteUser.value = targetUser
    callStatus.value = 'calling'

    // ★ 사용자 제스처 직후 즉시: 오디오 unlock + 마이크 획득
    unlockRemoteAudio()

    // 마이크를 API 호출 전에 획득 (user gesture 체인 안에서!)
    // getUserMedia 성공 → 브라우저가 오디오 권한 부여 → 원격 오디오 재생도 허용
    const stream = await getLocalStream()
    if (stream) {
      console.log('[WebRTC] Microphone OK')
    } else {
      console.log('[WebRTC] No microphone — proceeding without')
    }

    try {
      const { data } = await axios.post('/api/comms/calls/initiate', { callee_id: targetUser.id })
      currentCallId.value = data.call_id
      currentRoomId.value = data.room_id
      console.log('[WebRTC] Call initiated:', data.call_id, data.room_id)

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

      // SDP를 명시적 문자열로 추출 (RTCSessionDescription 객체 직렬화 문제 방지)
      await axios.post('/api/comms/calls/signal', {
        target_user_id: targetUser.id,
        room_id: data.room_id,
        type: 'offer',
        payload: { sdp: { type: offer.type, sdp: offer.sdp } },
      })
      console.log('[WebRTC] ✅ Offer sent to', targetUser.name)
      startCallMonitor()
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

    // ★ 사용자 제스처 직후 즉시: 오디오 unlock + 마이크 획득
    unlockRemoteAudio()

    // 마이크를 최우선 획득 (user gesture 체인 안에서!)
    // getUserMedia 성공 → 오디오 권한 확보 → 원격 소리 재생 가능
    const stream = await getLocalStream()

    stopRingtone()
    if (missedTimer) { clearTimeout(missedTimer); missedTimer = null }

    const { call_id, room_id, caller_id, caller_name, caller_avatar } = incomingCall.value
    currentCallId.value = call_id
    currentRoomId.value = room_id
    remoteUser.value = { id: caller_id, name: caller_name, avatar: caller_avatar }
    callStatus.value = 'connected'
    incomingCall.value = null
    startDurationTimer()
    startCallMonitor()  // 상대방 끊었는지 3초마다 확인

    try {
      // 1. 서버에 수락 알림
      console.log('[WebRTC] Step 1: Answering call', call_id, 'room', room_id)
      try {
        const resp = await axios.post(`/api/comms/calls/${call_id}/answer`)
        console.log('[WebRTC] Step 1 OK: Server responded', resp.status, resp.data)
      } catch (apiErr) {
        console.error('[WebRTC] Step 1 FAILED: API error', apiErr.response?.status, apiErr.response?.data)
      }

      // 2. PeerConnection 생성 (마이크는 이미 획득됨)
      createPeerConnection(room_id, caller_id)
      if (stream) {
        stream.getTracks().forEach(t => pc.addTrack(t, stream))
        console.log('[WebRTC] Step 3 OK: Local tracks added')
      } else {
        console.log('[WebRTC] Step 3: No local tracks (listen only)')
      }

      // 4. 버퍼된 offer 처리
      if (pendingOffer && pendingOffer.room_id === room_id) {
        console.log('[WebRTC] Step 4: Processing buffered offer, SDP type:', pendingOffer.sdp?.type)
        await pc.setRemoteDescription(sanitizeSdp(pendingOffer.sdp))
        console.log('[WebRTC] Step 4a OK: Remote description set')

        // 4b. setRemoteDescription 후에 버퍼된 ICE 후보 처리 (순서 중요!)
        if (pendingIceCandidates.length > 0) {
          console.log(`[WebRTC] Step 4a-1: Processing ${pendingIceCandidates.length} buffered ICE candidates`)
          for (const c of pendingIceCandidates) {
            await pc.addIceCandidate(c).catch(e => console.warn('[WebRTC] ICE add failed:', e))
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
          payload: { sdp: { type: answer.type, sdp: answer.sdp } },
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
      // 1. 상대방에게 직접 call-ended 시그널 전송 (즉각 전달)
      if (remoteUser.value?.id && currentRoomId.value) {
        axios.post('/api/comms/calls/signal', {
          target_user_id: remoteUser.value.id,
          room_id: currentRoomId.value,
          type: 'call-ended',
          payload: {},
        }).catch(() => {})
      }
      // 2. 서버에 종료 알림 (DB 업데이트 + 서버측 broadcast 백업)
      try {
        await axios.post(`/api/comms/calls/${currentCallId.value}/end`)
        console.log('[WebRTC] End API sent OK')
      } catch (e) {
        console.warn('[WebRTC] End API failed:', e.message)
      }
    }
    handleCallEnded()
  }

  // 주기적으로 통화 상태 확인 (상대방이 끊었는지)
  function startCallMonitor() {
    const monitor = setInterval(async () => {
      if (!currentCallId.value || callStatus.value === 'idle' || callStatus.value === 'ended') {
        clearInterval(monitor)
        return
      }
      try {
        const { data } = await axios.get(`/api/comms/calls/${currentCallId.value}/status`)
        if (data.status === 'ended') {
          console.log('[WebRTC] Server says call ended — closing')
          clearInterval(monitor)
          handleCallEnded()
        }
      } catch {}
    }, 3000)
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
