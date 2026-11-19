<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GenerateBotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Seconds — OpenAI calls per bot can add up. */
    public $timeout = 900;

    public $tries = 1;

    /** @var list<string> */
    protected array $names;

    protected array $configData;

    /**
     * @param  list<string>  $names
     * @param  array{like_limit: int, comment_limit: int}  $configData
     */
    public function __construct(array $names, array $configData)
    {
        $this->names = $names;
        $this->configData = $configData;
    }

    public function handle(OpenAIService $openAIService): void
    {
        foreach ($this->names as $fullName) {
            try {
                $profile = $openAIService->generateBotProfile($fullName);
                $username = $this->uniqueUsername($profile['username'] ?? null, $fullName);

                DB::transaction(function () use ($profile, $username, $fullName) {
                    $user = User::create([
                        'username'  => $username,
                        'full_name' => $fullName,
                        'bio'       => $profile['bio'] ?? 'Exploring the great outdoors.',
                        'avatar'    => null,
                        'is_bot'    => true,
                        'is_active' => 'active',
                    ]);

                    $user->botConfig()->create([
                        'daily_like_limit'    => (int) $this->configData['like_limit'],
                        'daily_comment_limit' => (int) $this->configData['comment_limit'],
                    ]);
                });
            } catch (Throwable $e) {
                Log::error('GenerateBotsJob failed for name', [
                    'full_name' => $fullName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function uniqueUsername(?string $preferred, string $fullName): string
    {
        $base = $preferred ?: Str::slug(str_replace(' ', '', strtolower($fullName)), '');
        $base = preg_replace('/[^a-z0-9_]/i', '', (string) $base) ?: 'hunter';
        $base = Str::limit($base, 20, '');

        $username = $base;
        $attempts = 0;

        while (User::where('username', $username)->exists() && $attempts < 50) {
            $username = $base . random_int(10, 9999);
            $attempts++;
        }

        if (User::where('username', $username)->exists()) {
            $username = 'bot_' . Str::lower(Str::random(10));
        }

        return $username;
    }
}
