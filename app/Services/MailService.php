<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use RuntimeException;

class MailService
{
    public function sendEmail(
        string $toEmail,
        string $subject,
        string $bodyHtml,
        ?string $fromEmail = null
    ): array {
        $url = config('services.mail_service.url');
        $apiKey = config('services.mail_service.api_key');
        $masterUser = config('services.mail_service.master_user');
        $timeout = (int) config('services.mail_service.timeout', 20);
        $fromEmail = $fromEmail ?: config('mail.from.address');

        if (!$url || !$apiKey || !$masterUser || !$fromEmail) {
            throw new RuntimeException('Mail service is not configured.');
        }

        $response = Http::asMultipart()
            ->timeout($timeout)
            ->withHeaders([
                'x-api-key' => $apiKey,
            ])
            ->post($url, [
                'master_user' => $masterUser,
                'from_email' => $fromEmail,
                'to_email' => $toEmail,
                'subject' => $subject,
                'body_html' => $bodyHtml,
            ]);

        if (!$response->successful()) {
            Log::error('Mail service request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'to' => $toEmail,
                'subject' => $subject,
            ]);

            throw new RuntimeException('Failed to send email.');
        }

        return $response->json() ?? ['success' => true];
    }

    /**
     * Backward-compatible alias used by myMailSend().
     */
    public function send(
        string $toEmail,
        string $subject,
        string $body,
        ?string $fromEmail = null,
        string $type = 'html'
    ): array {
        $bodyHtml = $type === 'plain'
            ? nl2br(e($body))
            : $body;

        return $this->sendEmail($toEmail, $subject, $bodyHtml, $fromEmail);
    }

    public function renderTemplate(string $templateName, array $data = []): string
    {
        return View::make("mails.{$templateName}", $data)->render();
    }

    public function renderVerifyEmailTemplate(array $data): string
    {
        return $this->renderTemplate('verify-email', $this->withDefaults($data));
    }

    public function renderResetPasswordTemplate(array $data): string
    {
        return $this->renderTemplate('reset-password-email', $this->withDefaults($data));
    }

    public function sendForgotPasswordOtp(string $toEmail, string $name, string|int $otp): array
    {
        $bodyHtml = $this->renderResetPasswordTemplate([
            'name' => $name,
            'otp' => (string) $otp,
        ]);

        return $this->sendEmail(
            $toEmail,
            'Forgot Password Mail',
            $bodyHtml
        );
    }

    public function sendVerifyEmailOtp(string $toEmail, string $name, string|int $otp): array
    {
        $bodyHtml = $this->renderVerifyEmailTemplate([
            'name' => $name,
            'otp' => (string) $otp,
        ]);

        return $this->sendEmail(
            $toEmail,
            'Verify Your Email',
            $bodyHtml
        );
    }

    private function withDefaults(array $data): array
    {
        $logoPath = public_path('logo.png');

        return array_merge([
            'logoUrl' => file_exists($logoPath) ? asset('logo.png') : null,
            'year' => (int) date('Y'),
            'appName' => config('app.name'),
        ], $data);
    }
}
