<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

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


function myMailSend($to, $name, $subject, $message, $link = null, $data = null){
    $payload = [
        "to"      => $to,
        "subject" => $subject,
        "name"    => $name,
        "message" => $message,
        "link"    => $link,
        "data"    => $data,
        "logo"    => 'https://tempmail.techvince.com/assets/images/logo.png',
        "from"    => 'TempMail Support',
    ];

    // Send using Guzzle HTTP client
    $client = new \GuzzleHttp\Client([
        'timeout' => 10,
        'verify'  => false, // if you have self‑signed certs
    ]);

    $response = $client->post('http://apluspass.zetdigi.com/form.php', [
        'json' => $payload,
    ]);

    // Optionally check for a successful response (e.g. HTTP 200 + success flag)
    if ($response->getStatusCode() !== 200) {
        // log, rollback, or throw
        throw new Exception('External mail API error: '.$response->getBody());
    }
    return true;
}