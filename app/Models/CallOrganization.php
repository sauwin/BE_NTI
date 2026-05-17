<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallOrganization extends Model
{
    protected $table = 'call_organizations';

    protected $fillable = [
        'call_id',
        'organization_id',
        'product_owner_user_id',
        'budget',
        'brief',
        'status',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class, 'call_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function productOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'product_owner_user_id');
    }
}