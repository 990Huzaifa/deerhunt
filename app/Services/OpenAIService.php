<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            'model' => $this->chatModel(),
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

    /**
     * @param  array<int, string>  $existingComments
     */
    public function generateSmartComment($postCaption, array $existingComments = [])
    {
        $caption = trim((string) $postCaption);
        if ($caption === '') {
            return null;
        }

        $existingComments = $this->normalizeExistingComments($existingComments);

        $comment = $this->requestComment($caption, $existingComments);
        if ($this->isUsableUniqueComment($comment, $existingComments)) {
            return $comment;
        }

        $comment = $this->requestComment(
            $caption,
            $existingComments,
            'Do not return SKIP. Write one short hunting-community comment that is clearly different from any existing comments listed above.'
        );

        if ($this->isUsableUniqueComment($comment, $existingComments)) {
            return $comment;
        }

        $fallback = $this->uniqueFallbackComment($existingComments);
        if ($fallback !== null) {
            Log::info('BotActivity: openai_forced_fallback', [
                'caption_preview' => mb_substr($caption, 0, 80),
                'raw' => $comment,
                'fallback' => $fallback,
            ]);

            return $fallback;
        }

        Log::info('BotActivity: openai_no_unique_comment', [
            'caption_preview' => mb_substr($caption, 0, 80),
            'raw' => $comment,
            'existing_count' => count($existingComments),
        ]);

        return null;
    }

    /**
     * @param  array<int, string>  $existingComments
     */
    protected function requestComment(string $caption, array $existingComments = [], ?string $extraInstruction = null): string
    {
        $userContent = "Post Caption: {$caption}";

        if (!empty($existingComments)) {
            $lines = array_map(
                fn ($c) => '- "' . str_replace('"', "'", $c) . '"',
                array_slice($existingComments, 0, 15)
            );
            $userContent .= "\n\nExisting comments on this post (do NOT repeat or closely paraphrase any of these):\n"
                . implode("\n", $lines);
        }

        if ($extraInstruction) {
            $userContent .= "\n\n{$extraInstruction}";
        }

        $response = Http::timeout(30)->withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->chatModel(),
            'messages' => [
                ['role' => 'system', 'content' => $this->communityCommentSystemPrompt()],
                ['role' => 'user', 'content' => $userContent],
            ],
            'temperature' => 1.0,
            'max_tokens' => 80,
        ]);

        Log::info('BotActivity: openai_http', [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'model' => $this->chatModel(),
        ]);

        if (!$response->successful()) {
            return '';
        }

        $comment = trim((string) $response->json('choices.0.message.content'));

        return trim($comment, " \t\n\r\0\x0B\"'`");
    }

    protected function chatModel(): string
    {
        return (string) (config('services.openai.model') ?: 'gpt-3.5-turbo-0125');
    }

    protected function isUsableComment(?string $comment): bool
    {
        $comment = trim((string) $comment);

        return $comment !== '' && strcasecmp($comment, 'SKIP') !== 0;
    }

    /**
     * @param  array<int, string>  $existingComments
     */
    protected function isUsableUniqueComment(?string $comment, array $existingComments): bool
    {
        if (!$this->isUsableComment($comment)) {
            return false;
        }

        return !$this->isDuplicateComment($comment, $existingComments);
    }

    /**
     * @param  array<int, string>  $existingComments
     */
    public function isDuplicateComment(string $comment, array $existingComments): bool
    {
        $normalized = $this->normalizeComment($comment);
        if ($normalized === '') {
            return true;
        }

        foreach ($existingComments as $existing) {
            $other = $this->normalizeComment($existing);
            if ($other === '') {
                continue;
            }

            if ($normalized === $other) {
                return true;
            }

            similar_text($normalized, $other, $percent);
            if ($percent >= 85) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $existingComments
     */
    protected function uniqueFallbackComment(array $existingComments): ?string
    {
        $fallbacks = $this->fallbackComments();
        shuffle($fallbacks);

        foreach ($fallbacks as $fallback) {
            if (!$this->isDuplicateComment($fallback, $existingComments)) {
                return $fallback;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function fallbackComments(): array
    {
        return [
            'Appreciate you sharing this one with the crew.',
            'Love seeing hunts like this get shared here.',
            'That looks like a solid outing — thanks for posting it.',
            'Always cool to see moments like this from the woods.',
            'Respect for getting out there and putting in the work.',
            'Great share — stories like this keep the community going.',
            'Nice one. Hope you had a good time behind that outing.',
            'Thanks for putting this up for everyone to see.',
            'Looks like a memorable hunt — glad you shared it.',
            'Solid post. Always good to catch these moments here.',
        ];
    }

    /**
     * @param  array<int, string>  $existingComments
     * @return array<int, string>
     */
    protected function normalizeExistingComments(array $existingComments): array
    {
        $normalized = [];

        foreach ($existingComments as $comment) {
            $text = trim((string) $comment);
            if ($text !== '') {
                $normalized[] = $text;
            }
        }

        return array_values(array_unique($normalized));
    }

    protected function normalizeComment(string $comment): string
    {
        $comment = mb_strtolower(trim($comment));
        $comment = preg_replace('/\s+/u', ' ', $comment) ?? $comment;

        return $comment;
    }

    protected function communityCommentSystemPrompt(): string
    {
        return <<<'PROMPT'
# RackScore Community Comment Generator

You are the RackScore Community Assistant.

Your job is to generate ONE short, highly relevant, natural-sounding comment for a member's post inside the RackScore community feed.

## INPUT

Post Caption: {{caption}}

Optional: a list of existing comments already on the post.

## INSTRUCTIONS

### Step 1 — Caption is always usable

A caption will always be provided. Never refuse. Never return SKIP. Never say you cannot comment.
Never generate a comment based solely on an assumed image — use only the caption.

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
21. If existing comments are provided, your comment must be clearly different in wording and angle from all of them.

### Step 5 — Safety and Relevance

Keep the comment friendly and hunting-related. Do not invent photo details.
If the caption is thin, still write a short supportive hunting-community comment.

### Step 6 — Final Output

Return ONLY the generated comment.

No quotation marks.
No explanations.
No headings.
No JSON.
No additional text.
Never return SKIP.
PROMPT;
    }
}
