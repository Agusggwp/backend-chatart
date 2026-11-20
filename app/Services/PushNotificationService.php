<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send push notification to offline participants of a conversation.
     *
     * @param Message $message
     * @param array<int> $recipientUserIds
     */
    public function sendChatNotification(Message $message, array $recipientUserIds): void
    {
        $sender = $message->sender;
        $title = $sender?->name ?? 'Pesan Baru';

        $body = match ($message->type) {
            'image' => '📷 Mengirim foto',
            'voice' => '🎤 Mengirim pesan suara',
            'document' => '📄 Mengirim dokumen: ' . ($message->file_name ?? 'File'),
            default => $message->body,
        };

        $data = [
            'type' => 'chat_message',
            'conversation_id' => (string) $message->conversation_id,
            'message_id' => (string) $message->id,
            'sender_id' => (string) ($sender?->id ?? ''),
            'sender_name' => $sender?->name ?? '',
        ];

        $this->sendToUsers($recipientUserIds, $title, $body, $data);
    }

    /**
     * Send push notification to target users via their registered device tokens.
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): void
    {
        if (empty($userIds)) {
            return;
        }

        $tokens = DeviceToken::whereIn('user_id', $userIds)
            ->pluck('token')
            ->unique()
            ->values()
            ->all();

        if (empty($tokens)) {
            Log::info('PushNotification: Tidak ada device token terdaftar untuk user: ' . implode(',', $userIds));
            return;
        }

        $fcmServerKey = config('services.fcm.server_key') ?: env('FCM_SERVER_KEY');
        if (empty($fcmServerKey)) {
            Log::info("PushNotification simulated for " . count($tokens) . " devices: [$title] $body (Set FCM_SERVER_KEY in .env for production delivery)");
            return;
        }

        try {
            foreach (array_chunk($tokens, 500) as $chunk) {
                $response = Http::withHeaders([
                    'Authorization' => 'key=' . $fcmServerKey,
                    'Content-Type' => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'registration_ids' => $chunk,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'sound' => 'default',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'icon' => 'ic_launcher',
                    ],
                    'data' => $data,
                    'priority' => 'high',
                ]);

                if ($response->successful()) {
                    Log::info('Push notification berhasil dikirim ke ' . count($chunk) . ' perangkat.');
                } else {
                    Log::warning('FCM response error: ' . $response->body());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim push notification via FCM: ' . $e->getMessage());
        }
    }
}
