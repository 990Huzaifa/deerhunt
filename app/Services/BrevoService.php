<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sendinblue\Client\Configuration;
use Sendinblue\Client\Api\EmailCampaignsApi;
use Sendinblue\Client\Model\CreateEmailCampaign;
use GuzzleHttp\Client;
use Exception;

class BrevoService
{
    protected $apiUrl = 'https://api.brevo.com/v3/smtp/email';
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = env('BREVO_API_KEY');
    }

    public function sendContact($user)
    {
        $apiKey = config('services.brevo.api_key', env('BREVO_API_KEY'));
        $listId = config('services.brevo.list_id', env('BREVO_LIST_ID', 3));

        $data = [
            "email" => $user->email,
            "attributes" => [
                "USER_ID" => $user->id,
                "FIRSTNAME" => $user->full_name,
                "SIGN_UP_DATE" => $user->created_at->format('Y-m-d'),
                "LAST_LOGIN_AT"  => optional($user->last_login_at)->format('Y-m-d H:i:s'),
                "ANALYSIS_COUNT" => $user->analysis_count ?? 0,
                "LISTEN_FROM"    => $user->listen_from,
                "STATE"          => $user->state,

            ],
            "emailBlacklisted" => false,
            "smsBlacklisted" => false,
            "listIds" => [$listId],
            "updateEnabled" => true,
        ];

        try {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'api-key' => $apiKey,
                'content-type' => 'application/json',
            ])->post('https://api.brevo.com/v3/contacts', $data);

            if ($response->failed()) {
                Log::error('Brevo contact sync failed', [
                    'response' => $response->body(),
                    'user' => $user->id,
                ]);
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Brevo API Exception: ' . $e->getMessage());
            return false;
        }
    }

    public function updateContact($user)
    {
        $apiKey = config('services.brevo.api_key', env('BREVO_API_KEY'));
        $listId = config('services.brevo.list_id', env('BREVO_LIST_ID', 3));

        $data = [
            "email" => $user->email, // 🔑 unique identifier
            "attributes" => [
                "USER_ID"        => (string) $user->id,
                "FIRSTNAME"      => $user->full_name,
                "SIGN_UP_DATE"   => $user->created_at->format('Y-m-d'),
                "LAST_LOGIN_AT"  => optional($user->last_login_at)->format('Y-m-d H:i:s'),
                "ANALYSIS_COUNT" => $user->analysis_count ?? 0,
                "LISTEN_FROM"    => $user->listen_from,
                "STATE"          => $user->state,
            ],
            "emailBlacklisted" => false,
            "smsBlacklisted"   => false,
            "listIds"          => [$listId],
            "updateEnabled"    => true, // 🔥 THIS enables update by email
        ];

        try {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'api-key' => $apiKey,
                'content-type' => 'application/json',
            ])->post('https://api.brevo.com/v3/contacts', $data);

            if ($response->failed()) {
                Log::error('Brevo contact sync failed', [
                    'response' => $response->body(),
                    'user' => $user->id,
                ]);
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Brevo API Exception: ' . $e->getMessage());
            return false;
        }
    }

    public function sendMail(string $subject,string $toEmail,string $toName,string $htmlContent,string $fromEmail = null,string $fromName = null)
    {
        $fromEmail = $fromEmail ?? env('MAIL_FROM_ADDRESS');
        $fromName = $fromName ?? env('MAIL_FROM_NAME', 'App Mailer');

        $payload = [
            'sender' => [
                'name' => $fromName,
                'email' => $fromEmail,
            ],
            'to' => [
                [
                    'email' => $toEmail,
                    'name' => $toName,
                ],
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
        ];
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'api-key' => $this->apiKey,
            'content-type' => 'application/json',
        ])->post($this->apiUrl, $payload);

        if ($response->successful()) {
            return ['success' => true, 'response' => $response->json()];
        }

        return [
            'success' => false,
            'error' => $response->json(),
            'status' => $response->status(),
        ];
    }

    public function sendServerAlertMail($diskUsage, $ramUsage)
    {
        $fromEmail = $fromEmail ?? env('MAIL_FROM_ADDRESS');
        $fromName = $fromName ?? env('MAIL_FROM_NAME', 'App Mailer');

        $htmlContent = "
            <h1>Server Alert</h1>
            <p>The server has detected high resource usage:</p>
            <ul>
                <li>Disk Usage: {$diskUsage}%</li>
                <li>RAM Usage: {$ramUsage}%</li>
            </ul>
            <p>Please take the necessary actions to investigate and resolve the issue.</p>
        ";
        $payload = [
            'sender' => [
                'name' => $fromName,
                'email' => $fromEmail,
            ],
            'to' => [
                [
                    'email' => "racklineai@gmail.com",
                    'name' => "Admin",
                ],
            ],
            'subject' => "Server Alert: High Resource Usage Detected",
            'htmlContent' => $htmlContent,
        ];
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'api-key' => $this->apiKey,
            'content-type' => 'application/json',
        ])->post($this->apiUrl, $payload);

        if ($response->successful()) {
            return ['success' => true, 'response' => $response->json()];
        }

        return [
            'success' => false,
            'error' => $response->json(),
            'status' => $response->status(),
        ];
    }
}
