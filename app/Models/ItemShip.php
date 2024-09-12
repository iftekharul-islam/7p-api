<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemShip extends Model
{
  protected $table = 'items_ships';

  public function item()
  {
    return $this->belongsTo(Item::class, 'item_id');
  }

  public function ship()
  {
    return $this->belongsTo(Ship::class, 'ship_id');
  }
}
