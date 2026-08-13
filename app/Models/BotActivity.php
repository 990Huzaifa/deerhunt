<?PHP
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotActivity extends Model
{
    protected $fillable = [
        'bot_id', 
        'target_post_id', 
        'target_user_id', 
        'action_type', 
        'metadata'
    ];

    // Accessor taaki JSON handling asan ho agar metadata array hai
    protected $casts = [
        'metadata' => 'array',
    ];

    public function bot() {
        return $this->belongsTo(User::class, 'bot_id');
    }

    public function targetUser() {
        $selectFields =['id', 'full_name', 'username', 'is_active', 'avatar', 'created_at'];
        return $this->belongsTo(User::class, 'target_user_id')->select($selectFields);
    }

    public function post() {
        return $this->belongsTo(Post::class, 'target_post_id');
    }
}