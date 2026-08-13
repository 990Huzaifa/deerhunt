<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{

    // init firebase service
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function send($sender_id, $receiver_id, $type, $title, $message, $data = [])
    {
        // 1. Save in DB
        $notification = Notification::create([
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'title' => $title,
            'type' => $type,
            'message' => $message,
            'data' => $data
        ]);

        // 2. Send FCM
        if ($receiver = $notification->receiver) {
            if ($receiver->fcm_token) {
                $this->firebaseService->sendToDevice(
                    $receiver->fcm_token,
                    $title,
                    $message,
                    $data
                    );
            }
        }

        return $notification;
    }
}
