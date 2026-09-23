<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Product reminder's category (Pump, Motor, ...). New categories are just
 * new rows here — nothing on the reminders table has to change.
 */
#[Fillable(['name'])]
class ProductCategory extends Model
{
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'product_category_id');
    }
}
