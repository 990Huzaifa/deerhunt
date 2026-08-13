<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Filesystem\FilesystemAdapter;

function uploadImageToSpaces(UploadedFile $file, string $folder, string $prefix): array
{
    $extension = strtolower($file->getClientOriginalExtension());

    if (!$extension) {
        $extension = $file->guessExtension() ?: 'jpg';
    }

    $fileName = $prefix . time() . Str::random(10) . '.' . $extension;

    $path = trim($folder, '/') . '/' . $fileName;

    /** @var FilesystemAdapter $disk */
    $disk = Storage::disk('spaces');

    $disk->put(
        $path,
        file_get_contents($file->getRealPath()),
        [
            'visibility' => 'public',
            'ContentType' => $file->getMimeType(),
        ]
    );

    return [
        'path' => $path,
        'url' => $disk->url($path),
    ];
}


function deleteImageFromSpaces(?string $path): bool
{
    if (!$path) {
        return false;
    }

    $disk = Storage::disk('spaces');
    if (!$disk->exists($path)) {
        return false;
    }

    return $disk->delete($path);
}


function uploadLocalImageToSpaces(string $localFilePath, string $folder, string $fileName): array
{
    if (!File::exists($localFilePath)) {
        throw new Exception('Local image file not found.');
    }

    $path = trim($folder, '/') . '/' . $fileName;

    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
    $disk = Storage::disk('spaces');

    $disk->put(
        $path,
        File::get($localFilePath),
        [
            'visibility' => 'public',
            'ContentType' => File::mimeType($localFilePath) ?: 'image/jpeg',
        ]
    );

    return [
        'path' => $path,
        'url' => $disk->url($path),
    ];
}