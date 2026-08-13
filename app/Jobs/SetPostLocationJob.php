<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SetPostLocationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */

    protected $state;
    protected $county;
    protected $userId;
    public function __construct($state, $county, $userId)
    {
        $this->state = $state;
        $this->county = $county;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Post location set karne ka logic yahan likhen
        // For example, aap database mein post location update kar sakte hain
        // Post::where('user_id', $this->userId)->update(['state' => $this->state, 'county' => $this->county]);

        Post::where('user_id', $this->userId)->update([
            'state' => $this->state,
            'county' => $this->county
        ]);
    }
}
