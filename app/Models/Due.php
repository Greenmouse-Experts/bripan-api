<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Due extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    public function category(): HasOne
    {
        return $this->hasOne(Category::class, 'id', 'payment_category_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'due_id', 'id');
    }
}
