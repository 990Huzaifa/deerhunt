<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAIService {


    public function generateBotProfile() 
    {
        // List of different categories/personas to ensure variety
        $categories = [
            'Deer Hunting & Tracking',
            'Fly Fishing & River Life',
            'Mountain Hiking & Survivalism',
            'Archery & Bowhunting',
            'Camping & Bushcraft',
            'Wildlife Photography',
            'Waterfowl & Duck Hunting',
            'Off-roading & Overland Adventure'
        ];

        // Pick a random category
        $selectedCategory = $categories[array_rand($categories)];

        // We add a 'random seed' or instruction for unique naming
        $prompt = "Generate a unique user profile for someone interested in: {$selectedCategory}. 
                Theme: Rugged, authentic, and specific to the niche.
                Important: Do NOT use common names like 'John Doe'. Use a variety of realistic names.
                You must return a JSON object with EXACTLY these keys: 'username', 'full_name', and 'bio'.";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-3.5-turbo-0125',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a creative assistant that outputs unique, diverse, and realistic user profiles in JSON format.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.9, // Higher temperature increases randomness/creativity
        ]);

        $data = json_decode($response->json('choices.0.message.content'), true);

        return [
            'username'  => $data['username'] ?? 'user_' . uniqid(),
            'full_name' => $data['full_name'] ?? 'Outdoor Explorer',
            'bio'       => $data['bio'] ?? 'Exploring the great outdoors.',
        ];
    }

    public function generateSmartComment($postCaption) 
    {
        $prompt = "Write a short, casual, and enthusiastic social media comment for a hunting post.
                Post Caption: '{$postCaption}'
                Tone: Friendly hunter/outdoorsman.
                Rules: Max 15 words, NO hashtags, ONLY JSON output with key 'comment'.";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a social media engagement bot.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => ['type' => 'json_object']
        ]);

        $data = json_decode($response->json('choices.0.message.content'), true);
        return $data['comment'] ?? "Great post!"; // Fallback
    }
}