<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\User;
use App\Services\NotificationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StoreAsRecentPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    protected int $userId;
    protected array $payload;
    protected string $tempDir;

    public function __construct(int $userId, array $payload, string $tempDir)
    {
        $this->userId = $userId;
        $this->payload = $payload;
        $this->tempDir = $tempDir;
    }

    public function handle(NotificationService $notificationService): void
    {
        try {
            $user = User::findOrFail($this->userId);
            $image = null;
            $allImagePaths = [];

            if (!empty($this->payload['image'])) {
                $image = $this->promoteTempImage($this->payload['image']);
                $allImagePaths[] = $image;
            }

            if (!empty($this->payload['images']) && is_array($this->payload['images'])) {
                $images = [];
                foreach ($this->payload['images'] as $tempPath) {
                    $images[] = $this->promoteTempImage($tempPath);
                }
                $image = implode(',', $images);
                $allImagePaths = $images;
            }

            $post = Post::create([
                'user_id' => $user->id,
                'title' => $this->payload['title'],
                'image' => $image,
                'score' => $this->payload['score'] ?? null,
                'analysis' => $this->payload['analysis'] ?? null,
                'measurements' => isset($this->payload['measurements']) ? json_encode($this->payload['measurements']) : null,
                'antler_points' => isset($this->payload['antler_points']) ? json_encode($this->payload['antler_points']) : null,
                'deer_age_estimate' => $this->payload['deer_age_estimate'] ?? false,
                'growth_projection' => $this->payload['growth_projection'] ?? false,
                'estimated_age' => $this->payload['estimated_age'] ?? null,
                'years_age' => isset($this->payload['years_age']) ? json_encode($this->payload['years_age']) : null,
                'is_public' => $this->payload['is_public'] ?? false,
                'is_private' => $this->payload['is_private'] ?? false,
                'caption' => $this->payload['caption'] ?? null,
                'state' => $this->payload['state'] ?? $user->state,
                'county' => $this->payload['county'] ?? $user->county,
                'harvest_type' => $this->payload['harvest_type'] ?? null,
                'hunt_date' => $this->payload['hunt_date'] ?? null,
                'location' => $this->payload['location'] ?? null,
                'notes' => $this->payload['notes'] ?? null,
                'is_trophy' => false,
            ]);

            $user->increment('analysis_count');

            if (!empty($this->payload['ref_data']) && is_array($this->payload['ref_data'])) {
                foreach ($this->payload['ref_data'] as $item) {
                    $refImage = null;
                    if (!empty($item['image'])) {
                        $refImage = $this->promoteTempImage($item['image']);
                        $allImagePaths[] = $refImage;
                    }

                    Post::create([
                        'user_id' => $user->id,
                        'title' => $item['title'] ?? null,
                        'image' => $refImage,
                        'score' => $item['score'] ?? null,
                        'analysis' => $item['analysis'] ?? null,
                        'ref_id' => $post->id,
                    ]);
                }
            }

            $post->update([
                'image' => implode(',', $allImagePaths),
            ]);

            $this->notifyPreviousTopScoreBeaten($post->fresh(), $user, $notificationService);
        } finally {
            $this->cleanupTempDir();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('StoreAsRecentPostJob failed', [
            'user_id' => $this->userId,
            'error' => $exception?->getMessage(),
        ]);

        $this->cleanupTempDir();
    }

    private function promoteTempImage(string $tempPath): string
    {
        $localPath = Storage::disk('local')->path($tempPath);

        if (!File::exists($localPath)) {
            throw new Exception("Temp image not found: {$tempPath}");
        }

        $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION) ?: 'jpg');
        $fileName = 'post-image' . time() . Str::random(10) . '.' . $extension;

        $uploaded = uploadLocalImageToPublic($localPath, 'post-image', $fileName);

        return $uploaded['path'];
    }

    private function cleanupTempDir(): void
    {
        if ($this->tempDir && Storage::disk('local')->exists($this->tempDir)) {
            Storage::disk('local')->deleteDirectory($this->tempDir);
        }
    }

    private function notifyPreviousTopScoreBeaten(Post $post, User $beater, NotificationService $notificationService): void
    {
        if ($post->score === null || $post->score === '') {
            return;
        }

        try {
            $previousTop = Post::with('user')
                ->where('is_delete', false)
                ->whereNull('ref_id')
                ->where('is_trophy', true)
                ->where('id', '!=', $post->id)
                ->excludeRescoreVersions()
                ->orderByDesc('score')
                ->first();

            if (!$previousTop || !$previousTop->user_id) {
                return;
            }

            if ((float) $post->score <= (float) $previousTop->score) {
                return;
            }

            if ($post->user_id == $previousTop->user_id) {
                return;
            }

            $receiver = $previousTop->user;
            if (!$receiver) {
                return;
            }

            $notificationService->send(
                $post->user_id,
                $previousTop->user_id,
                'high_score',
                "{$beater->username} beat your high score.",
                'High Score Beaten',
                [
                    'type' => 'high_score',
                    'post_id' => $post->id,
                    'new_score' => $post->score,
                    'previous_score' => $previousTop->score,
                    'previous_post_id' => $previousTop->id,
                    'receiver_username' => $receiver->username,
                ]
            );
        } catch (Exception $e) {
            Log::error('Failed to send high score beaten notification from StoreAsRecentPostJob', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
