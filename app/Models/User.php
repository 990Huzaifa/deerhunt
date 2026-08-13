<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'username',
        'bio',
        'email',
        'avatar',
        'state',
        
        'phone',
        'county',
        'species',
        'hunt_type',
        'starting_price',
        'highlight_photos',
        'is_premium',
        'is_featured',

        'password',
        'personal_site',
        'google_id',
        'facebook_id',
        'apple_id',
        'listen_from',
        'analysis_count',
        'fcm_token',
        'is_active',
        'is_delete',
        'last_login_at',
        'device_id',
        'app_version',
        'is_bot',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function wallet()
    {
        return $this->hasOne(CreditsWallet::class, 'user_id', 'id');
    }
    

    public function subscriptions()
    {
        return $this->hasOne(Subscription::class, 'user_id', 'id');
    }


    public function followers()
    {
        return $this->hasMany(Follow::class, 'following_id', 'id');
    }
    
    public function followings()
    {
        return $this->hasMany(Follow::class, 'follower_id', 'id');
    }

    public function isFollowing(User $user): bool
    {
        return $this->followings()->where('following_id', $user->id)->exists();
    }
    public function follow(User $user): void
    {
        if (!$this->isFollowing($user)) {
            $this->followings()->create(['following_id' => $user->id]);
        }
    }

    public function unfollow(User $user): void
    {
        if ($this->isFollowing($user)) {
            $this->followings()->where('following_id', $user->id)->delete();
        }
    }

    public function preferences()
    {
        return $this->hasOne(UserPreference::class, 'user_id', 'id');
    }

    public function like()
    {
        return $this->hasMany(PostLike::class, 'user_id', 'id');
    }

    public function post()
    {
        // where is_public = 1
        return $this->hasMany(Post::class, 'user_id', 'id')->where('is_public', 1)->where('is_delete', 0);
    }

    public function premium()
    {
        return $this->hasOne(Premium::class, 'user_id', 'id');
    }

    public function botConfig()
    {
        return $this->hasOne(BotConfig::class, 'user_id');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }
}
