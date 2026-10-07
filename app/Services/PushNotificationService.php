<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\WebPushConfig;

class PushNotificationService
{
    private $messaging = null;

    public function __construct()
    {
        try {
            $credentialsPath = config('services.firebase.credentials');
            if ($credentialsPath && file_exists($credentialsPath)) {
                $factory = (new Factory)->withServiceAccount($credentialsPath);
                $this->messaging = $factory->createMessaging();
            }
        } catch (\Throwable $e) {
            Log::warning('[FCM] Firebase init failed: ' . $e->getMessage());
        }
    }

    /**
     * Send push notification for incoming call.
     */
    public function sendIncomingCall(
        string $fcmToken,
        int    $callId,
        string $roomId,
        int    $callerId,
        string $callerName,
        string $callerAvatar
    ): void {
        if (!$this->messaging) {
            Log::warning('[FCM] sendIncomingCall — messaging not initialized');
            return;
        }

        try {
            // ★ data-only 메시지 — notification 필드 없음!
            // notification 필드가 있으면 Firebase SDK가 가로채서 sw.js push 이벤트 안 옴
            // data-only면 항상 sw.js push 이벤트 발생 → 우리가 직접 알림 표시
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withData([
                    'type'          => 'incoming_call',
                    'call_id'       => (string) $callId,
                    'room_id'       => $roomId,
                    'caller_id'     => (string) $callerId,
                    'caller_name'   => $callerName,
                    'caller_avatar' => $callerAvatar,
                    'title'         => $callerName . '님의 전화',
                    'body'          => '안심 서비스 음성 통화 수신 중...',
                ])
                ->withAndroidConfig(AndroidConfig::fromArray([
                    'priority' => 'high',
                ]))
                ->withWebPushConfig(WebPushConfig::fromArray([
                    'headers' => [
                        'Urgency' => 'high',
                        'TTL'     => '60',
                    ],
                ]));

            $this->messaging->send($message);
            Log::info('[FCM] Incoming call sent to ' . substr($fcmToken, 0, 10) . '...');
        } catch (\Throwable $e) {
            Log::warning('[FCM] sendIncomingCall failed: ' . $e->getMessage());
        }
    }

    /**
     * Send push notification for new message.
     */
    public function sendNewMessage(
        string $fcmToken,
        string $senderName,
        string $messageBody,
        int    $conversationId
    ): void {
        if (!$this->messaging) {
            Log::warning('[FCM] sendNewMessage — messaging not initialized');
            return;
        }

        try {
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification(Notification::create(
                    $senderName,
                    mb_substr($messageBody, 0, 100)
                ))
                ->withData([
                    'type'            => 'new_message',
                    'conversation_id' => (string) $conversationId,
                    'sender_name'     => $senderName,
                    'body'            => mb_substr($messageBody, 0, 100),
                ])
                ->withWebPushConfig(WebPushConfig::fromArray([
                    'notification' => [
                        'title'    => $senderName,
                        'body'     => mb_substr($messageBody, 0, 100),
                        'icon'     => '/images/icons/icon-192.png',
                        'tag'      => 'message-' . $conversationId,
                        'renotify' => true,
                    ],
                ]));

            $this->messaging->send($message);
            Log::info('[FCM] Message notification sent to ' . substr($fcmToken, 0, 10) . '...');
        } catch (\Throwable $e) {
            Log::warning('[FCM] sendNewMessage failed: ' . $e->getMessage());
        }
    }

    /**
     * P2B-4: 범용 push 발송 (title/body/data).
     * Firebase 미설정 시 조용히 skip.
     */
    /** @return string|null 실패 사유(성공이면 null) — 호출하는 쪽은 무시해도 됨 */
    public function sendToToken(
        string $fcmToken,
        string $title,
        string $body,
        array  $data = []
    ): ?string {
        if (!$this->messaging) {
            Log::info('[FCM] sendToToken skipped — Firebase not configured');
            return '서버의 Firebase 서비스 계정 파일을 읽지 못했어요 (파일이 없거나 형식이 잘못됨)';
        }
        try {
            // data-only 메시지 — notification 필드가 있으면 Firebase SDK 와 sw.js 가 둘 다 알림을 띄워 두 번 보일 수 있어서,
            // 다른 푸시(전화/대화)처럼 sw.js 가 한 번만 직접 표시하게 한다.
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withData(array_map('strval', array_merge($data, [
                    'title' => $title,
                    'body'  => mb_substr($body, 0, 200),
                ])))
                ->withWebPushConfig(WebPushConfig::fromArray([
                    'headers' => ['Urgency' => 'high', 'TTL' => '3600'],
                ]));
            $this->messaging->send($message);
            Log::info('[FCM] sendToToken sent to ' . substr($fcmToken, 0, 10) . '...');
            return null;
        } catch (\Throwable $e) {
            Log::warning('[FCM] sendToToken failed: ' . $e->getMessage());
            return mb_substr($e->getMessage(), 0, 200);
        }
    }
}
