/**
 * useCommsWebRTC — 브라우저 WebRTC 음성 통화 (별도 PeerJS 서버 없이, 사이트의 웹소켓(Reverb)으로 연결 신호를 주고받음)
 *
 * 흐름
 *  1. 거는 쪽: POST /calls/initiate  → 서버가 받는 사람의 모든 기기에 벨(call.initiated)을 보냄 (+ 푸시)
 *  2. 받는 쪽: 수락한 기기 하나가 POST /calls/{id}/answer(device_id) → 나머지 기기는 벨이 멈춤, 거는 쪽에 "받았다(call-answered)" 전달
 *  3. 거는 쪽이 offer 를 보내고 → 받는 쪽이 answer → 서로 ICE 후보를 교환 → 음성 연결(직접 또는 TURN 중계)
 *  4. 끊기/거절/응답없음/실패는 모두 POST /calls/{id}/end(reason) 로 기록 → 관리자 통화 로그
 *
 * 기기(탭)마다 deviceId 가 있어서, 같은 사람이 휴대폰과 컴퓨터에 동시에 로그인해도 "받은 그 기기"와만 연결된다.
 * 이벤트를 놓쳐도(휴대폰 화면 꺼짐 등) 3초마다 서버 상태를 확인하는 폴링과 /calls/ringing 확인이 보완한다.
 */
import { ref, computed, onUnmounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { startRingtone, stopRingtone } from '@/services/RingtoneService'

const CALLER_RING_TIMEOUT = 45000   // 거는 쪽: 이 시간 안에 안 받으면 "응답 없음"
const CALLEE_RING_TIMEOUT = 50000   // 받는 쪽: 벨을 이 시간까지만 울림
const CONNECT_TIMEOUT     = 25000   // 받은 뒤 이 시간 안에 음성이 연결 안 되면 실패
const OFFER_RETRY_MS      = 4000
const TERMINAL = ['ended', 'missed', 'declined', 'failed']

const END_NOTICE = {
  declined:  '상대가 전화를 거절했어요',
  cancelled: '상대가 전화를 취소했어요',
  no_answer: '전화를 받지 못했어요 (부재중)',
  failed:    '연결에 실패했어요',
  completed: '통화가 종료됐어요',
}

export function useCommsWebRTC() {
  // ── 상태 (기존 인터페이스 유지) ────────────────────────────────
  const callStatus         = ref('idle')   // idle | calling | ringing | connecting | connected | ended
  const callDuration       = ref(0)
  const isMuted            = ref(false)
  const isSpeaker          = ref(true)
  const currentCallId      = ref(null)
  const currentRoomId      = ref(null)
  const remoteUser         = ref(null)
  const incomingCall       = ref(null)
  const remoteAudioBlocked = ref(false)
  const callNotice         = ref('')       // 종료/실패 안내 문구 (화면에 잠깐 표시)

  const deviceId = Math.random().toString(36).slice(2, 10)   // 이 탭/기기 고유 번호
  let myUserId = null

  let pc = null
  let localStream = null
  let remoteAudioEl = null
  let role = null                // 'caller' | 'callee'
  let peerDevice = null          // 상대 기기 번호
  let pendingIce = []
  let haveRemoteDesc = false
  let offerStarted = false
  let connectedOnce = false
  let lastOffer = null

  let durationTimer = null, ringTimer = null, callerTimer = null, connectTimer = null
  let monitorTimer = null, offerRetryTimer = null, disconnectTimer = null, idleTimer = null
  let iceServersCache = null

  // ── 공통 도우미 ───────────────────────────────────────────────
  const log = (...a) => console.log('[Call]', ...a)

  function clearTimers() {
    for (const t of [durationTimer, ringTimer, callerTimer, connectTimer, monitorTimer, offerRetryTimer, disconnectTimer]) {
      if (t) { clearInterval(t); clearTimeout(t) }
    }
    durationTimer = ringTimer = callerTimer = connectTimer = monitorTimer = offerRetryTimer = disconnectTimer = null
  }

  async function getIceServers() {
    if (iceServersCache) return iceServersCache
    try {
      const { data } = await axios.get('/api/comms/ice-servers')
      if (Array.isArray(data?.iceServers) && data.iceServers.length) return (iceServersCache = data.iceServers)
    } catch {}
    return [{ urls: 'stun:stun.l.google.com:19302' }]
  }

  async function getLocalStream() {
    if (localStream) return localStream
    try {
      localStream = await navigator.mediaDevices.getUserMedia({
        audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
        video: false,
      })
      return localStream
    } catch (err) {
      console.error('[Call] mic failed:', err.name)
      return null
    }
  }

  function sendSignal(type, payload = {}) {
    const to = remoteUser.value?.id
    if (!to || !currentRoomId.value) return Promise.resolve()
    return axios.post('/api/comms/calls/signal', {
      target_user_id: to,
      room_id: currentRoomId.value,
      type,
      payload: { ...payload, from_device: deviceId, to_device: peerDevice },
    }).catch(e => console.warn('[Call] signal failed:', type, e.response?.status))
  }

  function startDurationTimer() {
    if (durationTimer) clearInterval(durationTimer)
    callDuration.value = 0
    durationTimer = setInterval(() => callDuration.value++, 1000)
  }

  function playRemoteStream(stream) {
    if (remoteAudioEl) { try { remoteAudioEl.pause() } catch {} }
    remoteAudioEl = new Audio()
    remoteAudioEl.srcObject = stream
    remoteAudioEl.autoplay = true
    remoteAudioEl.setAttribute('playsinline', '')
    remoteAudioEl.play().then(() => { remoteAudioBlocked.value = false }).catch(e => {
      console.warn('[Call] audio blocked:', e.name)
      remoteAudioBlocked.value = true
      const dom = document.getElementById('sk-remote-audio')
      if (dom) { dom.srcObject = stream; dom.play().catch(() => {}) }
    })
  }

  function unblockRemoteAudio() {
    if (remoteAudioEl) remoteAudioEl.play().then(() => { remoteAudioBlocked.value = false }).catch(() => {})
  }

  // ── 종료/정리 ─────────────────────────────────────────────────
  function resetCallState() {
    clearTimers()
    stopRingtone()
    remoteAudioBlocked.value = false
    if (remoteAudioEl) { try { remoteAudioEl.pause(); remoteAudioEl.srcObject = null } catch {} }
    remoteAudioEl = null
    if (pc) { try { pc.ontrack = pc.onicecandidate = pc.oniceconnectionstatechange = null; pc.close() } catch {} }
    pc = null
    localStream?.getTracks().forEach(t => t.stop())
    localStream = null
    pendingIce = []; haveRemoteDesc = false; offerStarted = false; connectedOnce = false; lastOffer = null
    role = null; peerDevice = null
    isMuted.value = false
  }

  /** 화면에 "종료" 상태를 잠깐 보여준 뒤 대기 상태로 */
  function handleCallEnded(notice = '') {
    resetCallState()
    callNotice.value = notice
    callStatus.value = 'ended'
    incomingCall.value = null
    if (idleTimer) clearTimeout(idleTimer)
    idleTimer = setTimeout(() => {
      callStatus.value = 'idle'
      currentCallId.value = null
      currentRoomId.value = null
      remoteUser.value = null
      callNotice.value = ''
    }, notice ? 3500 : 2000)
  }

  /** 즉시 대기 상태로 (다른 기기에서 받았을 때 등 — 종료 화면 없이) */
  function silentIdle() {
    resetCallState()
    callStatus.value = 'idle'
    incomingCall.value = null
    currentCallId.value = null
    currentRoomId.value = null
    remoteUser.value = null
  }

  async function failCall(note, userMessage) {
    log('FAIL', note)
    if (currentCallId.value) {
      await axios.post(`/api/comms/calls/${currentCallId.value}/end`, { reason: 'failed', note }).catch(() => {})
    }
    handleCallEnded(userMessage || '연결에 실패했어요. 네트워크를 확인하고 다시 시도해 주세요.')
  }

  // ── WebRTC 연결 ───────────────────────────────────────────────
  async function createPc() {
    if (pc) return pc
    pc = new RTCPeerConnection({ iceServers: await getIceServers() })
    localStream.getTracks().forEach(t => pc.addTrack(t, localStream))

    pc.ontrack = (e) => playRemoteStream(e.streams[0] || new MediaStream([e.track]))
    pc.onicecandidate = (e) => { if (e.candidate) sendSignal('ice-candidate', { candidate: e.candidate.toJSON() }) }
    pc.oniceconnectionstatechange = () => {
      const st = pc?.iceConnectionState
      log('ice state', st)
      if (st === 'connected' || st === 'completed') {
        if (disconnectTimer) { clearTimeout(disconnectTimer); disconnectTimer = null }
        markConnected()
      } else if (st === 'failed') {
        failCall(connectedOnce ? 'ice_failed_midcall' : 'ice_failed', connectedOnce ? '통화 연결이 끊어졌어요' : undefined)
      } else if (st === 'disconnected') {
        // 잠깐 끊겼다 돌아오는 경우가 많아서 8초 기다려 본다
        if (disconnectTimer) clearTimeout(disconnectTimer)
        disconnectTimer = setTimeout(() => {
          if (pc && ['disconnected', 'failed'].includes(pc.iceConnectionState)) failCall('ice_disconnected', '통화 연결이 끊어졌어요')
        }, 8000)
      }
    }
    return pc
  }

  function markConnected() {
    if (connectedOnce) return
    connectedOnce = true
    if (connectTimer) { clearTimeout(connectTimer); connectTimer = null }
    if (offerRetryTimer) { clearInterval(offerRetryTimer); offerRetryTimer = null }
    callStatus.value = 'connected'
    startDurationTimer()
    if (role === 'caller') setTimeout(reportStats, 2500)
  }

  /** 직접 연결인지 중계(TURN)인지, 왕복 지연이 얼마인지 측정해서 서버(관리자 통화 로그)에 남긴다 */
  async function reportStats() {
    try {
      if (!pc || !currentCallId.value) return
      const stats = await pc.getStats()
      let pair = null
      stats.forEach(r => { if (r.type === 'transport' && r.selectedCandidatePairId) pair = stats.get(r.selectedCandidatePairId) })
      if (!pair) stats.forEach(r => { if (r.type === 'candidate-pair' && r.state === 'succeeded' && (r.nominated || !pair)) pair = r })
      if (!pair) return
      const local = stats.get(pair.localCandidateId), remote = stats.get(pair.remoteCandidateId)
      const relay = local?.candidateType === 'relay' || remote?.candidateType === 'relay'
      const rtt = typeof pair.currentRoundTripTime === 'number' ? Math.round(pair.currentRoundTripTime * 1000) : null
      await axios.post(`/api/comms/calls/${currentCallId.value}/report`, { conn_type: relay ? 'relay' : 'direct', rtt_ms: rtt })
    } catch {}
  }

  function armConnectTimeout() {
    if (connectTimer) clearTimeout(connectTimer)
    connectTimer = setTimeout(() => {
      if (!connectedOnce && callStatus.value !== 'idle' && callStatus.value !== 'ended') {
        failCall(role === 'caller' ? 'connect_timeout_caller' : 'connect_timeout_callee')
      }
    }, CONNECT_TIMEOUT)
  }

  async function flushIce() {
    const list = pendingIce; pendingIce = []
    for (const c of list) { try { await pc.addIceCandidate(c) } catch (e) { console.warn('[Call] addIce', e.message) } }
  }

  /** 거는 쪽: 상대가 받으면 offer 를 보낸다 (이벤트/폴링 어느 쪽으로 알게 돼도 한 번만) */
  async function beginOffer(calleeDevice) {
    if (role !== 'caller' || offerStarted || !localStream) return
    offerStarted = true
    peerDevice = calleeDevice || peerDevice
    callStatus.value = 'connecting'
    if (callerTimer) { clearTimeout(callerTimer); callerTimer = null }
    armConnectTimeout()
    try {
      await createPc()
      const offer = await pc.createOffer()
      await pc.setLocalDescription(offer)
      lastOffer = { type: offer.type, sdp: offer.sdp }
      await sendSignal('offer', { description: lastOffer })
      // 신호가 유실될 수 있어서 answer 가 올 때까지 몇 번 다시 보낸다
      let tries = 0
      offerRetryTimer = setInterval(() => {
        if (haveRemoteDesc || connectedOnce || ++tries > 4) { clearInterval(offerRetryTimer); offerRetryTimer = null; return }
        sendSignal('offer', { description: lastOffer })
      }, OFFER_RETRY_MS)
    } catch (e) {
      failCall('offer_error:' + (e.message || '').slice(0, 80))
    }
  }

  /** 받는 쪽: offer 를 받으면 answer 를 돌려준다 */
  // 서버(TrimStrings 미들웨어)가 SDP 끝의 줄바꿈을 잘라내면 파싱이 실패하므로 복원한다
  const fixSdp = (d) => (d && typeof d.sdp === 'string' && !d.sdp.endsWith('\n')) ? { type: d.type, sdp: d.sdp + '\r\n' } : d

  async function handleOffer(payload) {
    if (role !== 'callee' || haveRemoteDesc) return        // 중복 offer 무시
    peerDevice = payload.from_device || peerDevice
    try {
      await getLocalStreamPromise
      await createPc()
      await pc.setRemoteDescription(fixSdp(payload.description))
      haveRemoteDesc = true
      await flushIce()
      const answer = await pc.createAnswer()
      await pc.setLocalDescription(answer)
      await sendSignal('answer', { description: { type: answer.type, sdp: answer.sdp } })
    } catch (e) {
      failCall('answer_error:' + (e.message || '').slice(0, 80))
    }
  }

  async function handleAnswer(payload) {
    if (role !== 'caller' || !pc || haveRemoteDesc) return
    try {
      await pc.setRemoteDescription(fixSdp(payload.description))
      haveRemoteDesc = true
      await flushIce()
    } catch (e) {
      failCall('set_answer_error:' + (e.message || '').slice(0, 80))
    }
  }

  async function handleIce(payload) {
    if (!payload.candidate) return
    if (pc && haveRemoteDesc) { try { await pc.addIceCandidate(payload.candidate) } catch (e) { console.warn('[Call] addIce', e.message) } }
    else pendingIce.push(payload.candidate)
  }

  // ── 서버 상태 확인(폴링) — 이벤트를 놓쳐도 통화가 멈추지 않도록 ───────────
  function startCallMonitor() {
    if (monitorTimer) clearInterval(monitorTimer)
    monitorTimer = setInterval(async () => {
      if (!currentCallId.value || ['idle', 'ended'].includes(callStatus.value)) { clearInterval(monitorTimer); monitorTimer = null; return }
      try {
        const { data } = await axios.get(`/api/comms/calls/${currentCallId.value}/status`)
        if (TERMINAL.includes(data.status)) {
          if (callStatus.value === 'calling' || callStatus.value === 'connecting' || callStatus.value === 'connected' || callStatus.value === 'ringing') {
            handleCallEnded(END_NOTICE[data.end_reason] || '통화가 종료됐어요')
          }
        } else if (role === 'caller' && data.status === 'answered' && !offerStarted) {
          beginOffer(data.callee_device)
        }
      } catch {}
    }, 3000)
  }

  // ── 수신 벨 ───────────────────────────────────────────────────
  function ring(info) {
    if (callStatus.value === 'ended') silentIdle()   // 종료 안내가 떠 있는 중이면 바로 치우고 새 전화를 받는다
    if (callStatus.value !== 'idle') return
    if (idleTimer) { clearTimeout(idleTimer); idleTimer = null }
    incomingCall.value = info
    currentRoomId.value = info.room_id
    callNotice.value = ''
    callStatus.value = 'ringing'
    startRingtone()
    ringTimer = setTimeout(() => {
      if (callStatus.value === 'ringing') { stopRingtone(); silentIdle() }   // 서버 통화는 거는 쪽/자동정리가 마무리
    }, CALLEE_RING_TIMEOUT)
  }

  /** 웹소켓 벨을 놓쳤을 수 있을 때(화면 켜짐, 알림 클릭, 재접속) 서버에 "지금 울리는 전화"가 있는지 확인 */
  async function checkRinging() {
    if (callStatus.value !== 'idle') return
    try {
      const { data } = await axios.get('/api/comms/calls/ringing')
      if (data?.call) ring(data.call)
    } catch {}
  }

  /** 푸시 알림을 눌러 들어온 경우 */
  async function ringFromPush(payload) {
    if (callStatus.value !== 'idle') return
    const id = payload?.call_id
    if (!id) return checkRinging()
    try {
      const { data } = await axios.get(`/api/comms/calls/${id}/status`)
      if (data.status === 'ringing') {
        ring({
          call_id: Number(id), room_id: payload.room_id, caller_id: parseInt(payload.caller_id),
          caller_name: payload.caller_name, caller_avatar: payload.caller_avatar, call_type: payload.call_type || 'friend',
        })
      } else {
        remoteUser.value = { id: parseInt(payload.caller_id) || null, name: payload.caller_name, avatar: payload.caller_avatar }
        handleCallEnded('이미 끝났거나 다른 기기에서 받은 전화예요')
      }
    } catch {}
  }

  // ── WebSocket 수신 ────────────────────────────────────────────
  function listenForSignals(userId) {
    myUserId = userId
    if (!window.Echo) return

    window.Echo.private(`user.${userId}`)
      .listen('.call.initiated', (event) => { log('ring', event.call_id); ring(event) })
      .listen('.webrtc.signal', (event) => {
        const { type, room_id: room, payload = {} } = event
        if (room !== currentRoomId.value) return
        switch (type) {
          case 'call-answered':
            // 거는 쪽: 상대가 받았다 → offer 시작
            if (role === 'caller') beginOffer(payload.device_id)
            break
          case 'call-answered-elsewhere':
            // 받는 사람의 다른 기기에서 받았다 → 이 기기의 벨은 멈춤
            if (callStatus.value === 'ringing' && payload.device_id !== deviceId) silentIdle()
            break
          case 'offer':
            if (payload.to_device === deviceId) handleOffer(payload)
            break
          case 'answer':
            if (payload.to_device === deviceId) handleAnswer(payload)
            break
          case 'ice-candidate':
            if (payload.to_device === deviceId) handleIce(payload)
            break
          case 'call-ended':
            if (['idle', 'ended'].includes(callStatus.value)) break
            if (callStatus.value === 'ringing') { stopRingtone(); handleCallEnded(END_NOTICE[payload.reason] || '전화가 끊어졌어요') }
            else handleCallEnded(END_NOTICE[payload.reason] || '통화가 종료됐어요')
            break
        }
      })

    // 웹소켓이 재연결되거나 화면이 다시 켜지면, 그 사이 놓친 전화가 있는지 확인
    try { window.Echo.connector.pusher.connection.bind('connected', checkRinging) } catch {}
    document.addEventListener('visibilitychange', onVisible)
    checkRinging()
  }
  function onVisible() { if (document.visibilityState === 'visible') checkRinging() }

  // ── 발신 ───────────────────────────────────────────────────────
  async function startCall(targetUser) {
    if (callStatus.value === 'ended') silentIdle()
    if (callStatus.value !== 'idle') return
    if (idleTimer) { clearTimeout(idleTimer); idleTimer = null }
    remoteUser.value = targetUser
    callNotice.value = ''
    role = 'caller'
    callStatus.value = 'calling'

    const stream = await getLocalStream()
    if (!stream) { handleCallEnded('마이크를 사용할 수 없어요. 브라우저의 마이크 권한을 허용해 주세요.'); return }

    try {
      const { data } = await axios.post('/api/comms/calls/initiate', { callee_id: targetUser.id, device_id: deviceId })
      currentCallId.value = data.call_id
      currentRoomId.value = data.room_id
      log('calling', targetUser.id, data.call_id)
      callerTimer = setTimeout(() => {
        if (callStatus.value === 'calling') endCall(true, 'no_answer', '상대가 응답하지 않아요')
      }, CALLER_RING_TIMEOUT)
      startCallMonitor()
    } catch (err) {
      const msg = err.response?.data?.error || '전화를 걸지 못했어요. 잠시 후 다시 시도해 주세요.'
      console.warn('[Call] initiate failed:', err.response?.status, msg)
      handleCallEnded(msg)
    }
  }

  // ── 수신 수락 ──────────────────────────────────────────────────
  let getLocalStreamPromise = Promise.resolve()

  async function answerCall() {
    if (!incomingCall.value) return
    const info = { ...incomingCall.value }
    if (ringTimer) { clearTimeout(ringTimer); ringTimer = null }
    stopRingtone()

    currentCallId.value = info.call_id
    currentRoomId.value = info.room_id
    remoteUser.value = { id: info.caller_id, name: info.caller_name, avatar: info.caller_avatar, call_type: info.call_type }
    incomingCall.value = null
    role = 'callee'
    callStatus.value = 'connecting'
    const isElder = info.call_type === 'elder'

    // 안심서비스 확인 전화는 음성 연결 없이 "받으면 체크인 완료" — 마이크 없이 바로 통화 상태로
    if (!isElder) {
      getLocalStreamPromise = getLocalStream()
      const stream = await getLocalStreamPromise
      if (!stream) {
        await axios.post(`/api/comms/calls/${info.call_id}/end`, { reason: 'failed', note: 'no_mic_callee' }).catch(() => {})
        handleCallEnded('마이크를 사용할 수 없어요. 브라우저의 마이크 권한을 허용해 주세요.')
        return
      }
    }

    try {
      const { data } = await axios.post(`/api/comms/calls/${info.call_id}/answer`, { device_id: deviceId })
      peerDevice = data.caller_device || null
    } catch (err) {
      handleCallEnded(err.response?.data?.error || '전화를 받지 못했어요. 이미 끝났거나 다른 기기에서 받은 전화일 수 있어요.')
      return
    }

    if (isElder) { callStatus.value = 'connected'; connectedOnce = true; startDurationTimer() }
    else armConnectTimeout()
    startCallMonitor()
  }

  // ── 수신 거부 ──────────────────────────────────────────────────
  async function declineCall() {
    if (!incomingCall.value) return
    const id = incomingCall.value.call_id
    const isElder = incomingCall.value.call_type === 'elder'
    stopRingtone()
    if (id && !isElder) await axios.post(`/api/comms/calls/${id}/end`, { reason: 'declined' }).catch(() => {})
    silentIdle()
  }

  // ── 통화 종료 (끊기) ───────────────────────────────────────────
  async function endCall(notifyServer = true, reason = null, notice = '') {
    const id = currentCallId.value
    const wasConnected = connectedOnce
    if (notifyServer && id) {
      const r = reason || (wasConnected ? 'completed' : 'cancelled')
      await axios.post(`/api/comms/calls/${id}/end`, { reason: r }).catch(e => console.warn('[Call] end failed:', e.response?.status))
    }
    handleCallEnded(notice || (wasConnected ? '통화가 종료됐어요' : ''))
  }

  // ── 컨트롤 ────────────────────────────────────────────────────
  function toggleMute() {
    localStream?.getAudioTracks().forEach(t => (t.enabled = !t.enabled))
    isMuted.value = !isMuted.value
  }

  async function toggleSpeaker() {
    isSpeaker.value = !isSpeaker.value   // 모바일은 스피커 전환 API가 없어 화면 표시만 바뀜
  }

  const durationFormatted = computed(() => {
    const m = Math.floor(callDuration.value / 60).toString().padStart(2, '0')
    const s = (callDuration.value % 60).toString().padStart(2, '0')
    return `${m}:${s}`
  })

  onUnmounted(() => {
    document.removeEventListener('visibilitychange', onVisible)
    try { if (currentCallId.value && !['idle', 'ended'].includes(callStatus.value)) endCall(true, connectedOnce ? 'completed' : 'cancelled') } catch {}
    resetCallState()
  })

  return {
    callStatus, callDuration, durationFormatted, isMuted, isSpeaker,
    currentCallId, currentRoomId, remoteUser, incomingCall, callNotice,
    remoteAudioBlocked, unblockRemoteAudio,
    listenForSignals, startCall, answerCall, declineCall, endCall, ringFromPush, checkRinging,
    toggleMute, toggleSpeaker,
  }
}
