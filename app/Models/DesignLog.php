<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignLog extends Model
{
  protected $table = 'design_log';

  public static function add($design_id, $text)
  {

    $note = new DesignLog;
    $note->design_id = $design_id;
    $note->text = $text;
    if (auth()->user()) {
      $note->user_id = auth()->user()->id;
    } else {
      $note->user_id = 87;
    }
    $note->save();
  }
}
