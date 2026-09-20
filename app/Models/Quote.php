<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $fillable = [
        'ref',
        'period',
        'rate',
        'total',
        'is_tbc',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_tbc' => 'boolean',
        ];
    }

    public function isPriced(): bool
    {
        return $this->rate !== null;
    }

    public function items()
    {
        return $this->hasMany(TripRequest::class, 'quote_id');
    }
}
