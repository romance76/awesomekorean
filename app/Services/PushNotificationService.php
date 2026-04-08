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
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification(Notification::create(
                    $callerName . '님의 전화',
                    '안심 서비스 음성 통화 수신 중...'
                ))
                ->withData([
                    'type'          => 'incoming_call',
                    'call_id'       => (string) $callId,
                    'room_id'       => $roomId,
                    'caller_id'     => (string) $callerId,
                    'caller_name'   => $callerName,
                    'caller_avatar' => $callerAvatar,
                ])
                ->withWebPushConfig(WebPushConfig::fromArray([
                    'notification' => [
                        'title'              => $callerName . '님의 전화',
                        'body'               => '안심 서비스 음성 통화 수신 중...',
                        'icon'               => '/images/icons/icon-192.png',
                        'badge'              => '/images/icons/icon-72.png',
                        'vibrate'            => [500, 200, 500, 200, 500],
                        'tag'                => 'incoming-call',
                        'renotify'           => true,
                        'requireInteraction' => true,
                        'actions'            => [
                            ['action' => 'answer', 'title' => '수락'],
                            ['action' => 'decline', 'title' => '거절'],
                        ],
                    ],
                    'fcm_options' => [
                        'link' => '/friends',
                    ],
                ]))
                ->withAndroidConfig(AndroidConfig::fromArray([
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'calls',
                        'sound'      => 'default',
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
}
