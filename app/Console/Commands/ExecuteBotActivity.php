<?php

namespace App\Console\Commands;

use App\Models\BotActivity;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExecuteBotActivity extends Command
{

    protected $signature = 'app:execute-bot-activity';


    protected $description = 'Command description';
    
    protected $notificationService;
    public function __construct(NotificationService $notificationService)
    {
        parent::__construct(); // Ye zaroori hai
        $this->notificationService = $notificationService;
    }
    // public function handle()
    // {
    //     $bots = User::where('is_bot', true)->where('is_delete',0)
    //     ->whereHas('botConfig', function($q) {
    //         $q->whereRaw('total_actions_today < (daily_like_limit + daily_comment_limit)');
    //     })
    //     ->inRandomOrder()
    //     ->limit(5) // Ek waqt mein 5 bots active honge
    //     ->get();

    //     foreach ($bots as $bot) {
    //     // 2. Kisi real user ki random post uthao (Jo bot ki apni na ho)
    //         $post = Post::where('is_trophy', true) // Sirf real users ki posts
    //             ->where('is_public', true)
    //             ->where('is_delete', false)
    //             ->inRandomOrder()
    //             ->first();
    //         // $post = Post::find(140258);

    //         if (!$post) continue;

    //         // Safety check again (per bot)
    //         $totalAllowedActions = (int) $bot->botConfig->daily_like_limit + (int) $bot->botConfig->daily_comment_limit;

    //         if ((int) $bot->botConfig->total_actions_today >= $totalAllowedActions) {
    //             continue;
    //         }

    //         // 3. Random Action: 70% chance Like, 30% chance Comment
    //         $action = rand(1, 10) <= 7 ? 'like' : 'comment';

    //         if ($action == 'like') {
    //             $this->performLike($bot, $post);
    //         } else {
    //             $this->performComment($bot, $post);
    //         }
            
    //         // 4. Update Bot Quota & Last Active
    //         $bot->botConfig->increment('total_actions_today');
    //         $bot->botConfig->update(['last_active_at' => now()]);
    //     }
    // }


    public function handle()
    {
        $this->botLog('run_start');

        $bots = User::where('is_bot', true)
            ->where('is_delete', 0)
            ->where('is_active', 'active')
            ->whereHas('botConfig', function ($q) {
                $q->whereRaw('total_actions_today < (daily_like_limit + daily_comment_limit)');
            })
            ->inRandomOrder()
            ->limit(5)
            ->get();

        $this->botLog('bots_loaded', ['count' => $bots->count(), 'ids' => $bots->pluck('id')->all()]);

        if ($bots->isEmpty()) {
            $this->botLog('no_eligible_bots');
            return self::SUCCESS;
        }

        foreach ($bots as $bot) {
            $config = $bot->botConfig;

            $this->botLog('bot_start', [
                'bot_id' => $bot->id,
                'username' => $bot->username,
                'daily_like_limit' => $config->daily_like_limit ?? null,
                'daily_comment_limit' => $config->daily_comment_limit ?? null,
                'likes_done_today' => $config->likes_done_today ?? null,
                'comments_done_today' => $config->comments_done_today ?? null,
                'total_actions_today' => $config->total_actions_today ?? null,
            ]);

            $totalAllowed = (int) $config->daily_like_limit + (int) $config->daily_comment_limit;

            if ((int) $config->total_actions_today >= $totalAllowed) {
                $this->botLog('bot_skip_quota_full', [
                    'bot_id' => $bot->id,
                    'total_actions_today' => $config->total_actions_today,
                    'total_allowed' => $totalAllowed,
                ]);
                continue;
            }

            $remainingLikes = (int) $config->daily_like_limit - ($config->likes_done_today ?? 0);
            $remainingComments = (int) $config->daily_comment_limit - ($config->comments_done_today ?? 0);

            $likesThisRun = min(max($remainingLikes, 0), 3);
            $commentsThisRun = min(max($remainingComments, 0), 2);

            $this->botLog('bot_quotas', [
                'bot_id' => $bot->id,
                'remaining_likes' => $remainingLikes,
                'remaining_comments' => $remainingComments,
                'likes_this_run' => $likesThisRun,
                'comments_this_run' => $commentsThisRun,
            ]);

            for ($i = 0; $i < $commentsThisRun; $i++) {
                $this->botLog('comment_attempt', ['bot_id' => $bot->id, 'attempt' => $i + 1]);

                $post = $this->getRandomPostWithCaption($bot);
                if (!$post) {
                    $this->botLog('comment_no_captioned_post', ['bot_id' => $bot->id]);
                    break;
                }

                $this->botLog('comment_post_found', [
                    'bot_id' => $bot->id,
                    'post_id' => $post->id,
                    'caption_preview' => mb_substr(trim((string) $post->caption), 0, 80),
                ]);

                if (!$this->performComment($bot, $post)) {
                    $this->botLog('comment_failed_or_skipped', [
                        'bot_id' => $bot->id,
                        'post_id' => $post->id,
                    ]);
                    continue;
                }

                $config->increment('total_actions_today');
                $config->increment('comments_done_today');
                $this->botLog('comment_quota_incremented', ['bot_id' => $bot->id]);
            }

            for ($i = 0; $i < $likesThisRun; $i++) {
                $this->botLog('like_attempt', ['bot_id' => $bot->id, 'attempt' => $i + 1]);

                $post = $this->getRandomPost($bot);
                if (!$post) {
                    $this->botLog('like_no_public_post', ['bot_id' => $bot->id]);
                    continue;
                }

                $this->botLog('like_post_found', ['bot_id' => $bot->id, 'post_id' => $post->id]);
                $this->performLike($bot, $post);
                $config->increment('total_actions_today');
                $config->increment('likes_done_today');
            }

            $config->update([
                'last_active_at' => now()
            ]);
            $this->botLog('bot_done', ['bot_id' => $bot->id]);
        }

        $this->botLog('run_end');

        return self::SUCCESS;
    }

    private function publicPostsQuery($bot)
    {
        return Post::query()
            ->where('is_public', 1)
            ->where('is_delete', 0)
            ->where('user_id', '!=', $bot->id)
            ->whereNull('ref_id');
    }

    private function getRandomPost($bot)
    {
        return $this->publicPostsQuery($bot)
            ->inRandomOrder()
            ->first();
    }

    private function getRandomPostWithCaption($bot)
    {
        $base = $this->publicPostsQuery($bot);
        $publicCount = (clone $base)->count();
        $captionCount = (clone $base)
            ->whereNotNull('caption')
            ->where('caption', '!=', '')
            ->whereRaw("CHAR_LENGTH(TRIM(caption)) > 2")
            ->count();

        $this->botLog('caption_query_counts', [
            'bot_id' => $bot->id,
            'public_posts' => $publicCount,
            'public_with_caption' => $captionCount,
        ]);

        return $this->publicPostsQuery($bot)
            ->whereNotNull('caption')
            ->where('caption', '!=', '')
            ->whereRaw("CHAR_LENGTH(TRIM(caption)) > 2")
            ->whereNotExists(function ($q) use ($bot) {
                $q->selectRaw('1')
                    ->from('post_comments')
                    ->whereColumn('post_comments.post_id', 'posts.id')
                    ->where('post_comments.user_id', $bot->id);
            })
            ->inRandomOrder()
            ->first();
    }

    private function performComment($bot, $post): bool
    {
        $caption = trim((string) $post->caption);
        $this->botLog('comment_caption_check', [
            'bot_id' => $bot->id,
            'post_id' => $post->id,
            'caption_length' => mb_strlen($caption),
        ]);

        if ($caption === '') {
            $this->botLog('comment_empty_caption', ['bot_id' => $bot->id, 'post_id' => $post->id]);
            return false;
        }

        $existingComments = PostComment::where('post_id', $post->id)
            ->whereNull('parent_id')
            ->pluck('comment')
            ->filter()
            ->map(fn ($c) => trim((string) $c))
            ->filter()
            ->values()
            ->all();

        $this->botLog('comment_existing_loaded', [
            'bot_id' => $bot->id,
            'post_id' => $post->id,
            'existing_count' => count($existingComments),
        ]);

        $aiService = new OpenAIService();
        $commentText = '';

        try {
            $this->botLog('comment_ai_start', ['bot_id' => $bot->id, 'post_id' => $post->id]);
            $commentText = trim((string) $aiService->generateSmartComment($caption, $existingComments));
            $this->botLog('comment_ai_result', [
                'bot_id' => $bot->id,
                'post_id' => $post->id,
                'ai_text' => $commentText !== '' ? mb_substr($commentText, 0, 120) : null,
                'ai_empty' => $commentText === '',
            ]);
        } catch (\Throwable $e) {
            $this->error("Comment AI failed for post {$post->id}: {$e->getMessage()}");
            $this->botLog('comment_ai_exception', [
                'bot_id' => $bot->id,
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($commentText === '') {
            $this->botLog('comment_ai_empty_after_generate', [
                'bot_id' => $bot->id,
                'post_id' => $post->id,
            ]);
            return false;
        }

        if ($aiService->isDuplicateComment($commentText, $existingComments)) {
            $this->botLog('comment_duplicate_skipped', [
                'bot_id' => $bot->id,
                'post_id' => $post->id,
                'comment_text' => $commentText,
            ]);
            return false;
        }

        // Race-safe: another bot may have saved the same text while we were generating.
        $alreadySaved = PostComment::where('post_id', $post->id)
            ->whereRaw('LOWER(TRIM(comment)) = ?', [mb_strtolower($commentText)])
            ->exists();

        if ($alreadySaved) {
            $this->botLog('comment_duplicate_db_skipped', [
                'bot_id' => $bot->id,
                'post_id' => $post->id,
                'comment_text' => $commentText,
            ]);
            return false;
        }

        // Aapke comments table mein entry
        $comment = PostComment::create([
            'post_id' => $post->id,
            'user_id' => $bot->id,
            'comment' => $commentText,
            'parent_id' => null
        ]);

        BotActivity::create([
            'bot_id'         => $bot->id,
            'target_post_id' => $post->id,
            'target_user_id' => $post->user_id,
            'action_type'    => 'comment',
            'metadata'       => ['comment_text' => $commentText]
        ]);
        $post->increment('comment_count');
        $this->notificationService->send(
            $bot->id,
            $post->user_id,
            'comment',
            "{$bot->username} commented on your post.",
            'New Comment received',
            ['type' => 'comment', 'post_id' => $post->id, 'comment_id' => $comment->id, 'receiver_username' => $post->user->username]
        );

        $this->botLog('comment_saved', [
            'bot_id' => $bot->id,
            'post_id' => $post->id,
            'comment_id' => $comment->id,
            'comment_text' => $commentText,
        ]);

        return true;
    }

    private function botLog(string $step, array $context = []): void
    {
        Log::info('BotActivity: ' . $step, $context);
        $this->line($step . ' ' . json_encode($context));
    }

    private function performLike($bot, $post)
    {
        $check_like = PostLike::where('post_id', $post->id)->where('user_id', $bot->id)->first();
        if ($check_like) {
            $this->botLog('like_already_exists', ['bot_id' => $bot->id, 'post_id' => $post->id]);
            return;
        }

        $post->increment('like_count');
        PostLike::create([
            'post_id' => $post->id,
            'user_id' => $bot->id
        ]);

        BotActivity::create([
            'bot_id'         => $bot->id,
            'target_post_id' => $post->id,
            'target_user_id' => $post->user_id,
            'action_type'    => 'like'
        ]);

        $this->notificationService->send(
            $bot->id,
            $post->user_id,
            'like',
            "{$bot->username} liked your post.",
            'New Like on Your Post',
            ['type' => 'like', 'post_id' => $post->id, 'receiver_username' => $post->user->username]
        );

        $this->botLog('like_saved', ['bot_id' => $bot->id, 'post_id' => $post->id]);
    }

    // BotActivity::create([
    //     'bot_id'         => $bot->id,
    //     'target_user_id' => $someUserId,
    //     'action_type'    => 'follow'
    // ]);
}
