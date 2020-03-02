<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    //
    
    public static function name($id){
        return Vehicle::where('id',$id)->value('make')." ".Vehicle::where('id',$id)->value('model');
    }
                                                                          
    public static function plate_number($id){
        return Vehicle::where('id',$id)->value('plate_number');
    }
}
