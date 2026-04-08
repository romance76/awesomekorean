import { ref, computed, onUnmounted } from 'vue'
import axios from 'axios'
import { startRingtone, stopRingtone } from '@/services/RingtoneService'

const ICE_SERVERS = {
  iceServers: [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
  ],
}

/**
 * useCommsWebRTC()
 * Uses window.Echo (already initialized in bootstrap.js) for signaling.
 * Uses axios (JWT auto-attached) for API calls to /api/comms/calls/...
 *
 * Renamed from useWebRTC to avoid conflict with existing useWebRTC.js (poker).
 */
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

  // ── PeerConnection ──────────────────────────────────────────────
  function createPeerConnection(roomId, targetUserId) {
    pc = new RTCPeerConnection(ICE_SERVERS)

    pc.onicecandidate = ({ candidate }) => {
      if (candidate) {
        axios.post('/api/comms/calls/signal', {
          target_user_id: targetUserId,
          room_id:        roomId,
          type:           'ice-candidate',
          payload:        { candidate: candidate.toJSON() },
        })
      }
    }

    pc.ontrack = (event) => {
      const audio = document.getElementById('sk-remote-audio')
      if (audio) {
        audio.srcObject = event.streams[0]
        audio.play().catch(() => {})
      }
    }

    pc.onconnectionstatechange = () => {
      if (['failed', 'disconnected', 'closed'].includes(pc.connectionState)) {
        handleCallEnded()
      }
    }

    return pc
  }

  async function getLocalStream() {
    localStream = await navigator.mediaDevices.getUserMedia({
      audio: true,
      video: false,
    })
    return localStream
  }

  // ── Timers ──────────────────────────────────────────────────────
  function startDurationTimer() {
    callDuration.value = 0
    durationTimer = setInterval(() => callDuration.value++, 1000)
  }

  function stopDurationTimer() {
    clearInterval(durationTimer)
    durationTimer = null
  }

  // ── Call ended cleanup ──────────────────────────────────────────
  function handleCallEnded() {
    stopDurationTimer()
    stopRingtone()
    clearTimeout(missedTimer)
    localStream?.getTracks().forEach(t => t.stop())
    pc?.close()
    pc = null
    localStream = null
    callStatus.value = 'ended'
    setTimeout(() => {
      callStatus.value    = 'idle'
      currentCallId.value = null
      currentRoomId.value = null
      remoteUser.value    = null
    }, 2000)
  }

  // ── Listen for incoming signals via window.Echo ─────────────────
  function listenForSignals(myUserId) {
    if (!window.Echo) {
      console.warn('[useCommsWebRTC] window.Echo not available.')
      return
    }

    window.Echo.private(`user.${myUserId}`)
      .listen('.webrtc.signal', async (event) => {
        const { type, payload, room_id } = event
        if (room_id !== currentRoomId.value) return

        if (type === 'offer') {
          await pc.setRemoteDescription(new RTCSessionDescription(payload.sdp))
          const answer = await pc.createAnswer()
          await pc.setLocalDescription(answer)
          axios.post('/api/comms/calls/signal', {
            target_user_id: incomingCall.value?.caller_id,
            room_id,
            type:    'answer',
            payload: { sdp: answer },
          })
        } else if (type === 'answer') {
          await pc.setRemoteDescription(new RTCSessionDescription(payload.sdp))
        } else if (type === 'ice-candidate') {
          await pc.addIceCandidate(new RTCIceCandidate(payload.candidate))
          if (callStatus.value === 'calling') {
            callStatus.value = 'connected'
            startDurationTimer()
          }
        } else if (type === 'call-answered') {
          callStatus.value = 'connected'
        } else if (type === 'call-ended') {
          await endCall(false)
        }
      })
      .listen('.call.initiated', (event) => {
        incomingCall.value = event
        callStatus.value   = 'ringing'
        startRingtone()

        // 30 seconds missed call timeout
        missedTimer = setTimeout(() => {
          if (callStatus.value === 'ringing') {
            stopRingtone()
            if (incomingCall.value?.call_id) {
              axios.post(`/api/comms/calls/${incomingCall.value.call_id}/end`)
            }
            incomingCall.value = null
            callStatus.value   = 'idle'
          }
        }, 30000)
      })
  }

  // ── Outgoing call ───────────────────────────────────────────────
  async function startCall(targetUser) {
    if (callStatus.value !== 'idle') return
    remoteUser.value = targetUser
    callStatus.value = 'calling'

    try {
      const { data } = await axios.post('/api/comms/calls/initiate', {
        callee_id: targetUser.id,
      })
      currentCallId.value = data.call_id
      currentRoomId.value = data.room_id

      await getLocalStream()
      createPeerConnection(data.room_id, targetUser.id)
      localStream.getTracks().forEach(t => pc.addTrack(t, localStream))

      const offer = await pc.createOffer()
      await pc.setLocalDescription(offer)
      await axios.post('/api/comms/calls/signal', {
        target_user_id: targetUser.id,
        room_id:        data.room_id,
        type:           'offer',
        payload:        { sdp: offer },
      })
    } catch (err) {
      callStatus.value = 'idle'
      throw err
    }
  }

  // ── Answer incoming call ────────────────────────────────────────
  async function answerCall() {
    if (!incomingCall.value) return
    stopRingtone()
    clearTimeout(missedTimer)

    const { call_id, room_id, caller_id } = incomingCall.value
    currentCallId.value = call_id
    currentRoomId.value = room_id
    callStatus.value    = 'connected'

    await axios.post(`/api/comms/calls/${call_id}/answer`)
    await getLocalStream()
    createPeerConnection(room_id, caller_id)
    localStream.getTracks().forEach(t => pc.addTrack(t, localStream))
    incomingCall.value = null
    startDurationTimer()
  }

  // ── Decline incoming call ───────────────────────────────────────
  async function declineCall() {
    if (!incomingCall.value) return
    stopRingtone()
    clearTimeout(missedTimer)
    await axios.post(`/api/comms/calls/${incomingCall.value.call_id}/end`)
    incomingCall.value = null
    callStatus.value   = 'idle'
  }

  // ── End active call ─────────────────────────────────────────────
  async function endCall(notifyServer = true) {
    if (notifyServer && currentCallId.value) {
      await axios.post(`/api/comms/calls/${currentCallId.value}/end`)
    }
    handleCallEnded()
  }

  // ── Toggle mute ─────────────────────────────────────────────────
  function toggleMute() {
    localStream?.getAudioTracks().forEach(t => (t.enabled = !t.enabled))
    isMuted.value = !isMuted.value
  }

  // ── Formatted duration ──────────────────────────────────────────
  const durationFormatted = computed(() => {
    const m = Math.floor(callDuration.value / 60).toString().padStart(2, '0')
    const s = (callDuration.value % 60).toString().padStart(2, '0')
    return `${m}:${s}`
  })

  // ── Cleanup on unmount ──────────────────────────────────────────
  onUnmounted(() => {
    endCall(false)
  })

  return {
    callStatus,
    callDuration,
    durationFormatted,
    isMuted,
    isSpeaker,
    currentCallId,
    currentRoomId,
    remoteUser,
    incomingCall,
    listenForSignals,
    startCall,
    answerCall,
    declineCall,
    endCall,
    toggleMute,
  }
}
