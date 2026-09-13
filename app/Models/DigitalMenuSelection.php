<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigitalMenuSelection extends Model
{
    protected $fillable = [
        'digital_menu_session_id',
        'item_id',
        'quantity',
    ];

    public function session()
    {
        return $this->belongsTo(DigitalMenuSession::class, 'digital_menu_session_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}