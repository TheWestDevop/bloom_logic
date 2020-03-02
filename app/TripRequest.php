<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TripRequest extends Model
{
    //
    
    protected $dates=[
        'created_at','updated_at','wait_time'
    ];
}
