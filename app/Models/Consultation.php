<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Mentorship;

class Consultation extends Model
{
    protected $fillable = ['mentorship_id', 'date', 'summary', 'duration_minutes'];

    protected $casts = [
        'date' => 'string',
        'duration_minutes' => 'integer'
    ];

    public function mentorship()
    {
        return $this->belongsTo(Mentorship::class);
    }
}
