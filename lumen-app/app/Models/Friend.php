<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasShardKeyTrait;

class Friend extends Model
{
    use HasShardKeyTrait;

    protected $table = 'friends';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'friend_id',
        'shard_key',
    ];

    protected static function booted()
    {
        static::creating(function (Friend $friend) {
            $friend->shard_key = self::generateShardKey($friend->user_id);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function friendUser()
    {
        return $this->belongsTo(User::class, 'friend_id', 'user_id');
    }
}