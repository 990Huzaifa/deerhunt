<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use RuntimeException;

class MailingApiService
{
    /**
     * Send email via TechVince mailing API.
     *
     * @param  string|array<int, string>  $toEmail
     */
    public function send(
        string|array $toEmail,
        string $subject,
        string $bodyHtml,
        ?string $name = null,
        ?string $body = null,
        ?string $fromEmail = null
    ): array {
        $url = config('services.mailing_api.url');
        $apiKey = config('services.mailing_api.api_key');
        $timeout = (int) config('services.mailing_api.timeout', 20);
        $fromEmail = $fromEmail ?: config('services.mailing_api.from_email', 'info@rackscore.us');

        if (!$url || !$apiKey || !$fromEmail) {
            throw new RuntimeException('Mailing API is not configured.');
        }

        $recipients = is_array($toEmail) ? $toEmail : [$toEmail];
        $recipients = array_values(array_filter(array_map('trim', $recipients), fn ($email) => $email !== ''));

        if (empty($recipients)) {
            throw new RuntimeException('Recipient email is required.');
        }

        $multipart = [
            ['name' => 'subject', 'contents' => $subject],
            ['name' => 'name', 'contents' => $name ?: config('app.name', 'RackScore')],
            ['name' => 'body', 'contents' => $body ?? strip_tags($bodyHtml)],
            ['name' => 'from_email', 'contents' => $fromEmail],
            ['name' => 'body_html', 'contents' => $bodyHtml],
        ];

        foreach ($recipients as $recipient) {
            $multipart[] = ['name' => 'to_email', 'contents' => $recipient];
        }

        $response = Http::timeout($timeout)
            ->withHeaders([
                'x-api-header' => $apiKey,
                'Accept' => 'application/json',
            ])
            ->asMultipart()
            ->post($url, $multipart);

        if (!$response->successful()) {
            Log::error('Mailing API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'to' => $recipients,
                'subject' => $subject,
            ]);

            throw new RuntimeException('Failed to send email via mailing API.');
        }

        return $response->json() ?? ['success' => true];
    }

    public function renderTemplate(string $templateName, array $data = []): string
    {
        return View::make("mails.{$templateName}", $data)->render();
    }
}
