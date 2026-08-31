<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'image',
        'feed_images',
        'share_count',
        'like_count',
        'comment_count',
        'score',
        'analysis',
        'is_delete',
        'is_trophy',
        'is_public',
        'is_private',
        'ref_id',
        'antler_points',
        'measurements',
        'deer_age_estimate',
        'estimated_age',
        'growth_projection',
        'years_age',
        'comment',
        'caption',
        'state',
        'county',
        'harvest_type',
        'linked_post_id',

        // new fields
        'hunt_date',
        'notes',
        'location',

        'created_at'
    ];

    public function reports()
    {
        // 'reportable' is the name of the polymorphic relation in the Report model
        return $this->morphMany(Report::class, 'reportable');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function linkedPost()
    {
        return $this->belongsTo(Post::class, 'linked_post_id');
    }

    public function scopeExcludeRescoreVersions($query)
    {
        return $query->whereNotIn('posts.id', function ($sub) {
            $sub->select('linked_post_id')
                ->from('posts')
                ->whereNotNull('linked_post_id');
        });
    }

    public function getLatestInChain(int $maxDepth = 50): self
    {
        $current = $this;
        $depth = 0;

        while ($current->linked_post_id && $depth < $maxDepth) {
            $next = static::find($current->linked_post_id);
            if (!$next) {
                break;
            }
            $current = $next;
            $depth++;
        }

        return $current;
    }

    public function getRootPost(int $maxDepth = 50): self
    {
        $current = $this;
        $depth = 0;

        while ($depth < $maxDepth) {
            $parent = static::where('linked_post_id', $current->id)->first();
            if (!$parent) {
                break;
            }
            $current = $parent;
            $depth++;
        }

        return $current;
    }

    public function getRescoreChain(int $maxDepth = 50): array
    {
        $root = $this->getRootPost($maxDepth);
        $chain = [];
        $current = $root;
        $depth = 0;

        while ($current && $depth < $maxDepth) {
            $chain[] = $current;
            if (!$current->linked_post_id) {
                break;
            }
            $current = static::find($current->linked_post_id);
            $depth++;
        }

        return $chain;
    }

    public function isLatestInChain(): bool
    {
        return $this->linked_post_id === null;
    }

    public function withRescoreMetadata(): array
    {
        $latest = $this->getLatestInChain();
        $root = $this->getRootPost();
        $chain = $root->getRescoreChain();

        $data = $this->toArray();
        $data['root_post_id'] = $root->id;
        $data['latest_post_id'] = $latest->id;
        $data['is_latest_in_chain'] = $latest->id === $this->id;
        $data['rescore_count'] = max(count($chain) - 1, 0);

        return $data;
    }
}
