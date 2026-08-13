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
        $bots = User::where('is_bot', true)
            ->where('is_delete', 0)
            ->whereHas('botConfig', function ($q) {
                $q->whereRaw('total_actions_today < (daily_like_limit + daily_comment_limit)');
            })
            ->inRandomOrder()
            ->limit(5)
            ->get();

        foreach ($bots as $bot) {

            $config = $bot->botConfig;

            $totalAllowed = (int) $config->daily_like_limit + (int) $config->daily_comment_limit;

            if ((int) $config->total_actions_today >= $totalAllowed) {
                continue;
            }

            // Remaining counts calculate karo
            $remainingLikes = (int) $config->daily_like_limit - ($config->likes_done_today ?? 0);
            $remainingComments = (int) $config->daily_comment_limit - ($config->comments_done_today ?? 0);

            // 🔥 Likes perform karo
            if ($remainingLikes > 0) {
                for ($i = 0; $i < $remainingLikes; $i++) {

                    $post = $this->getRandomPost($bot);
                    if (!$post) continue;

                    $this->performLike($bot, $post);

                    $config->increment('total_actions_today');
                    $config->increment('likes_done_today');
                }
            }

            // 🔥 Comments perform karo
            if ($remainingComments > 0) {
                for ($i = 0; $i < $remainingComments; $i++) {

                    $post = $this->getRandomPost($bot);
                    if (!$post) continue;

                    $this->performComment($bot, $post);

                    $config->increment('total_actions_today');
                    $config->increment('comments_done_today');
                }
            }

            // Last active update
            $config->update([
                'last_active_at' => now()
            ]);
        }
    }

    private function getRandomPost($bot)
    {
        return Post::where('is_trophy', true)
            ->where('is_public', true)
            ->where('is_delete', false)
            ->where('user_id', '!=', $bot->id) // apni post avoid
            ->inRandomOrder()
            ->first();
    }

    private function performComment($bot, $post) 
    {
        $aiService = new OpenAIService();
        // $commentText = $aiService->generateSmartComment($post->caption ?? 'Nice harvest!');
        $commentText = 'Nice Buck';

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
    }

    private function performLike($bot, $post) {
        $check_like = PostLike::where('post_id',$post->id)->where('user_id',$bot->id)->first();
        if(!$check_like){
            $post->increment('like_count');
            PostLike::create([
                'post_id' => $post->id,
                'user_id' => $bot->id
            ]);

            BotActivity::create([
                'bot_id'         => $bot->id,
                'target_post_id' => $post->id,
                'target_user_id' => $post->user_id, // Owner of the post
                'action_type'    => 'like'
            ]);

            // send notification
            
            $this->notificationService->send(
                $bot->id,
                $post->user_id,
                'like',
                "{$bot->username} liked your post.",
                'New Like on Your Post',
                ['type' => 'like', 'post_id' => $post->id, 'receiver_username' => $post->user->username]
            );
        }
    }

    // BotActivity::create([
    //     'bot_id'         => $bot->id,
    //     'target_user_id' => $someUserId,
    //     'action_type'    => 'follow'
    // ]);
}
