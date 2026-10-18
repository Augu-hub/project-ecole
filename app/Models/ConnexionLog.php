<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConnexionLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'parent_id', 'ip_address', 'user_agent'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Parents::class, 'parent_id');
    }
}
