<?php

namespace App\Models;

use App\Traits\HasShardKeyTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasShardKeyTrait;

    protected $table = 'posts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'author_user_id',
        'text',
        'shard_key',
    ];

    protected static function booted()
    {
        static::creating(function (Post $post) {
            if (!$post->id) {
                $post->id = (string) Str::uuid();
            }

            $post->shard_key = self::generateShardKey($post->author_user_id);
        });
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_user_id', 'user_id');
    }
}