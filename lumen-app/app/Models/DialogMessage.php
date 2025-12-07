<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasShardKeyTrait;
use Illuminate\Support\Str;

class DialogMessage extends Model
{
    use HasShardKeyTrait;

    protected $table = 'dialog_messages';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'id',
        'from_user_id',
        'to_user_id',
        'text',
        'shard_key',
    ];

    protected static function booted()
    {
        static::creating(function (DialogMessage $msg) {
            if (!$msg->id) {
                $msg->id = (string) Str::uuid();
            }

            $msg->shard_key = self::generateShardKey($msg->from_user_id);
        });
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'from_user_id', 'user_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'to_user_id', 'user_id');
    }
}