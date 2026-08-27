<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function uploadImageToPublic(UploadedFile $file, string $folder, string $prefix): array
{
    $extension = strtolower($file->getClientOriginalExtension());

    if (!$extension) {
        $extension = $file->guessExtension() ?: 'jpg';
    }

    $fileName = $prefix . time() . Str::random(10) . '.' . $extension;
    $folder = trim($folder, '/');
    $directory = public_path($folder);

    if (!File::isDirectory($directory)) {
        File::makeDirectory($directory, 0755, true);
    }

    $file->move($directory, $fileName);

    $path = $folder . '/' . $fileName;

    return [
        'path' => $path,
        'url' => asset($path),
    ];
}

function deleteImageFromPublic(?string $path): bool
{
    if (!$path) {
        return false;
    }

    $fullPath = public_path($path);

    if (!File::exists($fullPath)) {
        return false;
    }

    return File::delete($fullPath);
}

function uploadLocalImageToPublic(string $localFilePath, string $folder, string $fileName): array
{
    if (!File::exists($localFilePath)) {
        throw new Exception('Local image file not found.');
    }

    $folder = trim($folder, '/');
    $directory = public_path($folder);

    if (!File::isDirectory($directory)) {
        File::makeDirectory($directory, 0755, true);
    }

    $path = $folder . '/' . $fileName;
    File::copy($localFilePath, public_path($path));

    return [
        'path' => $path,
        'url' => asset($path),
    ];
}

function myMailSend($to, $name, $subject, $message, $link = null, $data = null)
{
    $mailServiceUrl = config('services.mail_service.url');
    $apiKey = config('services.mail_service.api_key');
    $masterUser = config('services.mail_service.master_user');
    $fromEmail = config('mail.from.address') ?: ($masterUser ?? null);

    if (!$mailServiceUrl || !$apiKey || !$masterUser || !$fromEmail) {
        throw new RuntimeException('Mail service is not configured.');
    }

    $recipients = is_array($to) ? $to : [$to];
    $recipients = array_values(array_filter(array_map('trim', $recipients), fn ($email) => $email !== ''));

    if (empty($recipients)) {
        throw new RuntimeException('Recipient email is required.');
    }

    $bodyHtml = (string) $message;
    if (!empty($link)) {
        $bodyHtml .= '<p><a href="' . e($link) . '">Click here</a></p>';
    }

    $payload = [
        ['name' => 'master_user', 'contents' => $masterUser],
        ['name' => 'from_email', 'contents' => $fromEmail],
        ['name' => 'subject', 'contents' => $subject],
        ['name' => 'body_html', 'contents' => $bodyHtml],
    ];

    foreach ($recipients as $recipient) {
        $payload[] = ['name' => 'to_email', 'contents' => $recipient];
    }

    $response = Http::timeout((int) config('services.mail_service.timeout', 20))
        ->withHeaders([
            'x-api-key' => $apiKey,
            'Accept' => 'application/json',
        ])
        ->asMultipart()
        ->post($mailServiceUrl, $payload);

    if (!$response->successful()) {
        throw new RuntimeException('Failed to send email.');
    }

    return $response->json() ?? ['status' => 'success'];
}
