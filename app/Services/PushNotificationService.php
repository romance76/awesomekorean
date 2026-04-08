<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Push notification service stub.
 * Firebase (kreait/laravel-firebase) is NOT installed yet.
 * All methods log warnings instead of actually sending push notifications.
 * Replace with real Firebase implementation when the package is installed.
 */
class PushNotificationService
{
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
        Log::warning('[PushNotificationService] sendIncomingCall STUB — Firebase not installed.', [
            'fcm_token'    => substr($fcmToken, 0, 10) . '...',
            'call_id'      => $callId,
            'room_id'      => $roomId,
            'caller_id'    => $callerId,
            'caller_name'  => $callerName,
        ]);
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
        Log::warning('[PushNotificationService] sendNewMessage STUB — Firebase not installed.', [
            'fcm_token'       => substr($fcmToken, 0, 10) . '...',
            'sender_name'     => $senderName,
            'conversation_id' => $conversationId,
            'preview'         => mb_substr($messageBody, 0, 30),
        ]);
    }
}
