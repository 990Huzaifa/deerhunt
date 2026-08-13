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
