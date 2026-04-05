import { ref, onUnmounted } from 'vue'

const ICE_SERVERS = [
  { urls: 'stun:stun.l.google.com:19302' },
  { urls: 'stun:stun1.l.google.com:19302' },
]

export function useWebRTC() {
  const localStream = ref(null)
  const remoteStream = ref(null)
  const peerConnection = ref(null)
  const callState = ref('idle') // idle, calling, ringing, connected, ended
  const callType = ref('audio') // audio, video
  const callDuration = ref(0)
  const isMuted = ref(false)
  const isVideoOff = ref(false)
  const remoteUser = ref(null) // { id, name }

  let durationTimer = null
  let socket = null
  let ringtoneTimeout = null

  function setSocket(s) { socket = s }
  function getSocket() { return socket }

  // -- Socket event listeners --
  function setupSocketListeners() {
    if (!socket) return

    socket.on('webrtc:call-request', handleCallRequest)
    socket.on('webrtc:offer', handleOffer)
    socket.on('webrtc:answer', handleAnswerSignal)
    socket.on('webrtc:ice-candidate', handleRemoteIceCandidate)
    socket.on('webrtc:hangup', handleRemoteHangup)
    socket.on('webrtc:reject', handleRemoteReject)
  }

  function removeSocketListeners() {
    if (!socket) return
    socket.off('webrtc:call-request', handleCallRequest)
    socket.off('webrtc:offer', handleOffer)
    socket.off('webrtc:answer', handleAnswerSignal)
    socket.off('webrtc:ice-candidate', handleRemoteIceCandidate)
    socket.off('webrtc:hangup', handleRemoteHangup)
    socket.off('webrtc:reject', handleRemoteReject)
  }

  // -- Incoming: call-request (ringing) --
  function handleCallRequest(data) {
    if (callState.value !== 'idle') return
    callState.value = 'ringing'
    callType.value = data.callType || 'audio'
    remoteUser.value = { id: data.from, name: data.callerName || '알 수 없음' }

    // Auto-reject after 30 seconds
    ringtoneTimeout = setTimeout(() => {
      if (callState.value === 'ringing') {
        rejectCall()
      }
    }, 30000)
  }

  // -- Incoming: offer (SDP) --
  async function handleOffer(data) {
    if (peerConnection.value && data.offer) {
      try {
        await peerConnection.value.setRemoteDescription(new RTCSessionDescription(data.offer))
        const answer = await peerConnection.value.createAnswer()
        await peerConnection.value.setLocalDescription(answer)

        if (socket) {
          socket.emit('webrtc:answer', {
            targetUserId: data.from,
            answer: peerConnection.value.localDescription
          })
        }
      } catch (err) {
        console.error('[WebRTC] handleOffer error:', err)
      }
    }
  }

  // -- Incoming: answer --
  async function handleAnswerSignal(data) {
    if (peerConnection.value && data.answer) {
      try {
        await peerConnection.value.setRemoteDescription(new RTCSessionDescription(data.answer))
        callState.value = 'connected'
        startDurationTimer()
      } catch (err) {
        console.error('[WebRTC] handleAnswer error:', err)
      }
    }
  }

  // -- Incoming: ice-candidate --
  async function handleRemoteIceCandidate(data) {
    if (peerConnection.value && data.candidate) {
      try {
        await peerConnection.value.addIceCandidate(new RTCIceCandidate(data.candidate))
      } catch (err) {
        console.error('[WebRTC] addIceCandidate error:', err)
      }
    }
  }

  // -- Incoming: hangup --
  function handleRemoteHangup() {
    endCall(false)
  }

  // -- Incoming: reject --
  function handleRemoteReject() {
    endCall(false)
  }

  // -- Create PeerConnection --
  function createPeerConnection(targetUserId) {
    const pc = new RTCPeerConnection({ iceServers: ICE_SERVERS })

    pc.onicecandidate = (e) => {
      if (e.candidate && socket) {
        socket.emit('webrtc:ice-candidate', {
          targetUserId,
          candidate: e.candidate
        })
      }
    }

    pc.ontrack = (e) => {
      if (!remoteStream.value) {
        remoteStream.value = new MediaStream()
      }
      e.streams[0].getTracks().forEach(track => {
        remoteStream.value.addTrack(track)
      })
    }

    pc.onconnectionstatechange = () => {
      const state = pc.connectionState
      if (state === 'connected') {
        callState.value = 'connected'
        startDurationTimer()
      }
      if (['disconnected', 'failed', 'closed'].includes(state)) {
        endCall(false)
      }
    }

    if (localStream.value) {
      localStream.value.getTracks().forEach(track => {
        pc.addTrack(track, localStream.value)
      })
    }

    peerConnection.value = pc
    return pc
  }

  // -- Outgoing: start call --
  async function startCall(targetUser, type = 'audio') {
    if (callState.value !== 'idle') return
    callType.value = type
    callState.value = 'calling'
    remoteUser.value = targetUser // { id, name, _callerName }

    try {
      const constraints = type === 'video'
        ? { audio: true, video: { width: 640, height: 480 } }
        : { audio: true, video: false }

      localStream.value = await navigator.mediaDevices.getUserMedia(constraints)
    } catch (err) {
      console.error('[WebRTC] getUserMedia failed:', err)
      alert(type === 'video' ? '카메라/마이크 권한이 필요합니다.' : '마이크 권한이 필요합니다.')
      callState.value = 'idle'
      return
    }

    createPeerConnection(targetUser.id)

    // Send call-request (ring the other side)
    if (socket) {
      socket.emit('webrtc:call-request', {
        targetUserId: targetUser.id,
        callerName: targetUser._callerName || '사용자',
        callType: type
      })
    }

    // Create and send offer
    try {
      const offer = await peerConnection.value.createOffer()
      await peerConnection.value.setLocalDescription(offer)

      if (socket) {
        socket.emit('webrtc:offer', {
          targetUserId: targetUser.id,
          offer: peerConnection.value.localDescription,
          callType: type
        })
      }
    } catch (err) {
      console.error('[WebRTC] createOffer failed:', err)
      endCall()
    }

    // Auto-end after 30 seconds if no answer
    ringtoneTimeout = setTimeout(() => {
      if (callState.value === 'calling') {
        endCall()
      }
    }, 30000)
  }

  // -- Incoming: answer the call --
  async function answerCall() {
    if (callState.value !== 'ringing') return
    clearTimeout(ringtoneTimeout)

    const type = callType.value
    try {
      const constraints = type === 'video'
        ? { audio: true, video: { width: 640, height: 480 } }
        : { audio: true, video: false }

      localStream.value = await navigator.mediaDevices.getUserMedia(constraints)
    } catch (err) {
      console.error('[WebRTC] getUserMedia failed:', err)
      alert(type === 'video' ? '카메라/마이크 권한이 필요합니다.' : '마이크 권한이 필요합니다.')
      rejectCall()
      return
    }

    createPeerConnection(remoteUser.value.id)
    callState.value = 'connected'
    // Offer will be processed in handleOffer
  }

  // -- Incoming: reject the call --
  function rejectCall() {
    clearTimeout(ringtoneTimeout)
    if (remoteUser.value && socket) {
      socket.emit('webrtc:reject', { targetUserId: remoteUser.value.id })
    }
    cleanup()
  }

  // -- End call --
  function endCall(sendHangup = true) {
    clearTimeout(ringtoneTimeout)
    if (sendHangup && remoteUser.value && socket) {
      socket.emit('webrtc:hangup', { targetUserId: remoteUser.value.id })
    }

    if (peerConnection.value) {
      peerConnection.value.close()
      peerConnection.value = null
    }
    if (localStream.value) {
      localStream.value.getTracks().forEach(t => t.stop())
      localStream.value = null
    }
    remoteStream.value = null
    callState.value = 'ended'
    stopDurationTimer()

    setTimeout(() => {
      callState.value = 'idle'
      callDuration.value = 0
      isMuted.value = false
      isVideoOff.value = false
      remoteUser.value = null
    }, 2000)
  }

  function cleanup() {
    clearTimeout(ringtoneTimeout)
    if (peerConnection.value) {
      peerConnection.value.close()
      peerConnection.value = null
    }
    if (localStream.value) {
      localStream.value.getTracks().forEach(t => t.stop())
      localStream.value = null
    }
    remoteStream.value = null
    callState.value = 'idle'
    callDuration.value = 0
    isMuted.value = false
    isVideoOff.value = false
    remoteUser.value = null
    stopDurationTimer()
  }

  // -- Toggle mute --
  function toggleMute() {
    if (!localStream.value) return
    const audioTrack = localStream.value.getAudioTracks()[0]
    if (audioTrack) {
      audioTrack.enabled = !audioTrack.enabled
      isMuted.value = !audioTrack.enabled
    }
    return !isMuted.value
  }

  // -- Toggle video --
  function toggleVideo() {
    if (!localStream.value) return
    const videoTrack = localStream.value.getVideoTracks()[0]
    if (videoTrack) {
      videoTrack.enabled = !videoTrack.enabled
      isVideoOff.value = !videoTrack.enabled
    }
    return !isVideoOff.value
  }

  // -- Duration timer --
  function startDurationTimer() {
    stopDurationTimer()
    durationTimer = setInterval(() => callDuration.value++, 1000)
  }
  function stopDurationTimer() {
    if (durationTimer) { clearInterval(durationTimer); durationTimer = null }
  }
  function formatDuration(sec) {
    const m = Math.floor(sec / 60).toString().padStart(2, '0')
    const s = (sec % 60).toString().padStart(2, '0')
    return `${m}:${s}`
  }

  onUnmounted(() => {
    cleanup()
    removeSocketListeners()
  })

  return {
    localStream, remoteStream, peerConnection,
    callState, callType, callDuration,
    isMuted, isVideoOff, remoteUser,
    setSocket, getSocket,
    setupSocketListeners, removeSocketListeners,
    startCall, answerCall, rejectCall, endCall,
    toggleMute, toggleVideo, formatDuration,
  }
}
