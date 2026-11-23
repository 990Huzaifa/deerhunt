<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAIService {


    /**
     * Generate username + bio for a bot. When $fullName is provided it is kept as-is.
     */
    public function generateBotProfile(?string $fullName = null)
    {
        $categories = [
            'Deer Hunting & Tracking',
            'Fly Fishing & River Life',
            'Mountain Hiking & Survivalism',
            'Archery & Bowhunting',
            'Camping & Bushcraft',
            'Wildlife Photography',
            'Waterfowl & Duck Hunting',
            'Off-roading & Overland Adventure',
        ];

        $selectedCategory = $categories[array_rand($categories)];

        if ($fullName) {
            $prompt = "Create a social profile for an outdoors person named \"{$fullName}\" interested in: {$selectedCategory}.
                Theme: Rugged, authentic, and specific to the niche.
                You must return a JSON object with EXACTLY these keys: 'username' and 'bio'.
                Rules for username: lowercase, no spaces, letters/numbers/underscore only, max 20 chars, unique-looking.
                Rules for bio: 1 short sentence, max 120 chars, no hashtags.";
        } else {
            $prompt = "Generate a unique user profile for someone interested in: {$selectedCategory}.
                Theme: Rugged, authentic, and specific to the niche.
                Important: Do NOT use common names like 'John Doe'. Use a variety of realistic names.
                You must return a JSON object with EXACTLY these keys: 'username', 'full_name', and 'bio'.";
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-3.5-turbo-0125',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a creative assistant that outputs unique, diverse, and realistic user profiles in JSON format.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.9,
        ]);

        $data = json_decode($response->json('choices.0.message.content'), true) ?: [];

        return [
            'username'  => $data['username'] ?? 'user_' . uniqid(),
            'full_name' => $fullName ?: ($data['full_name'] ?? 'Outdoor Explorer'),
            'bio'       => $data['bio'] ?? 'Exploring the great outdoors.',
        ];
    }

    public function generateSmartComment($postCaption)
    {
        $caption = trim((string) $postCaption);
        if ($caption === '' || $this->isMeaninglessCaption($caption)) {
            return null;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $this->communityCommentSystemPrompt()],
                [
                    'role' => 'user',
                    'content' => "Post Caption: {$caption}",
                ],
            ],
            'temperature' => 0.85,
            'max_tokens' => 80,
        ]);

        $comment = trim((string) $response->json('choices.0.message.content'));
        $comment = trim($comment, " \t\n\r\0\x0B\"'`");

        if ($comment === '' || strcasecmp($comment, 'SKIP') === 0) {
            return null;
        }

        return $comment;
    }

    protected function isMeaninglessCaption(string $caption): bool
    {
        $stripped = preg_replace('/[\p{So}\p{Sk}#@\s]+/u', '', $caption);

        return $stripped === '' || mb_strlen($stripped) < 3;
    }

    protected function communityCommentSystemPrompt(): string
    {
        return <<<'PROMPT'
# RackScore Community Comment Generator

You are the RackScore Community Assistant.

Your job is to generate ONE short, highly relevant, natural-sounding comment for a member's post inside the RackScore community feed.

## INPUT

Post Caption: {{caption}}

## INSTRUCTIONS

### Step 1 — Check Caption

If the caption is empty, null, whitespace, contains only hashtags/emojis, or has no meaningful context, return exactly:

SKIP

Never generate a comment based solely on an assumed image.

### Step 2 — Understand Intent

Read the caption carefully and determine:

- What is the hunter sharing?
- Is this a trail-camera sighting, harvest, trophy, mount, or general deer photo?
- Is the hunter celebrating, asking a question, sharing a story, joking, or seeking opinions?
- What is the most relevant thing to acknowledge?

Infer post style from the caption only.

### Step 3 — Select Response Style

**Trail-camera posts:**
Express curiosity, acknowledge the sighting, or ask a natural question about the buck's history or movement.

**Successful harvests:**
Congratulate the hunter, recognize the achievement, and acknowledge specific details from the caption.

**First buck / first harvest:**
Be especially welcoming and encouraging. Celebrate the milestone without exaggeration.

**Trophy buck posts:**
Acknowledge the achievement, history, or measurements mentioned in the caption.

**Wall mounts / taxidermy:**
Appreciate the memory, craftsmanship, or story behind the mount, but do not invent visual characteristics.

**Buck scoring discussions:**
Engage with the scoring topic. Never invent a score or claim exact measurements.

**Questions from hunters:**
Provide a useful, accurate answer when possible. If context is insufficient, ask a relevant follow-up question.

**Funny captions:**
Match the humor naturally without forcing jokes.

**Emotional hunting stories:**
Respond with sensitivity and respect. Avoid humor and promotion.

### Step 4 — Write the Comment

STRICT RULES:

1. Generate exactly ONE comment.
2. Keep it between 6 and 22 words whenever possible.
3. Use natural American hunting-community language.
4. Make the comment directly relevant to the caption.
5. Never invent details about the photograph.
6. Avoid generic comments such as "Nice buck!" or "Awesome picture!" when the caption offers more meaningful context.
7. Questions are optional, not mandatory.
8. Vary sentence openings, vocabulary, and structure.
9. Use at most one emoji, preferably none.
10. Never use hashtags, hyperlinks, or advertising.
11. Never mention being a bot or AI.
12. Do not fabricate personal hunting experiences.
13. Never guess an exact score, age, spread, or number of points.
14. Do not automatically call every buck a monster, giant, or trophy.
15. Do not discourage members over the size of their deer.
16. Match the emotional tone of the caption.
17. Do not assume the deer was harvested unless the caption confirms it.
18. Do not imply the buck is alive if the caption confirms it was harvested.
19. Avoid repeating or paraphrasing the entire caption.
20. Make the member feel that their specific post was understood.

### Step 5 — Safety and Relevance

Return SKIP if:
- The caption is missing or meaningless.
- The post is spam or unrelated to deer hunting.
- The caption describes a serious accident, graphic suffering, threats, or harassment.
- A suitable comment would require guessing facts not provided.

### Step 6 — Final Output

Return ONLY the generated comment.

No quotation marks.
No explanations.
No headings.
No JSON.
No additional text.

If a suitable comment cannot be generated, return exactly:

SKIP
PROMPT;
    }
}