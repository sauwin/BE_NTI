<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Application;
use App\Models\Consultations;

class Mentorship extends Model
{
    protected $fillable = ['application_id', 'mentor_id', 'assigned_at', 'ended_at', 'notes'];

    protected $casts = [
        'assigned_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }
}
