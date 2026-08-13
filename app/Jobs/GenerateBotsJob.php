<?php
namespace App\Jobs;

use App\Models\User;
use App\Models\State;
use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class GenerateBotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $count;
    protected $configData;

    public function __construct($count, $configData)
    {
        $this->count = $count;
        $this->configData = $configData;
    }

    public function handle(OpenAIService $openAIService)
    {
        for ($i = 0; $i < $this->count; $i++) {
            // OpenAI se data lena
            $profile = $openAIService->generateBotProfile();
            
            // Avatar processing
            $avatarPath = $this->moveRandomAvatar();

            // Transaction use karna behtar hai taaki data incomplete na rahay
            DB::transaction(function () use ($profile, $avatarPath) {
                $user = User::create([
                    'username'  => $profile['username'],
                    'full_name' => $profile['full_name'],
                    'bio'       => $profile['bio'],
                    'avatar'    => $avatarPath,
                    'is_bot'    => true,
                ]);

                $user->botConfig()->create([
                    'daily_like_limit'    => $this->configData['like_limit'],
                    'daily_comment_limit' => $this->configData['comment_limit'],
                ]);
            });
        }
    }

    private function moveRandomAvatar()
    {
        $sourceDir = public_path('bot-avatar');
        $destinationDir = public_path('user-avatar');

        // 1. Check source directory
        if (!File::exists($sourceDir) || count(File::files($sourceDir)) === 0) {
            return null;
        }

        // 2. Create destination directory if not exists
        // if (!File::exists($destinationDir)) {
        //     File::makeDirectory($destinationDir, 0755, true);
        // }

        // 3. Pick random avatar
        $files = File::files($sourceDir);
        $randomFile = $files[array_rand($files)];

        // 4. Generate new image name
        $extension = strtolower($randomFile->getExtension() ?: 'jpg');

        $imageName = 'u-avatar-' . time() . '-' . rand(1000, 9999) . '.' . $extension;

        // 5. DB path
        $dbPath = 'user-avatar/' . $imageName;

        // 6. Upload same image to DigitalOcean Spaces
        uploadLocalImageToSpaces(
            $randomFile->getRealPath(),
            'user-avatar',
            $imageName
        );

        // 7. Copy image to local public/user-avatar
        // File::copy(
        //     $randomFile->getRealPath(),
        //     $destinationDir . '/' . $imageName
        // );

        // 8. Return path for database
        return $dbPath;
    }
}