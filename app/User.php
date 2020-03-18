<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\PushId;
use App\TripRating;
use App\Subscription;
use App\UserDocument;
use App\Vehicle;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    
    public function getNameAttribute($value){
        return ucwords($value);
    }
    
    public static function notification_receiver($user_id){
        $device_token=PushId::where('user_id',$user_id)->where('status',1)->orderBy('id','DESC')->first();
        
        return $device_token->device_id;
    }
    
    public static function avg_rating($user_id){
        $score=TripRating::where('object_id',$user_id)->avg('rating');
        
        if($score==null){
            return 5;
        }
        
        return intval($score);
    }
    
    public static function can_drive($user_id){
        $check_documents=UserDocument::where('user_id',$user_id)->where('status',1)->count();
        $check_vehicle=Vehicle::where('driver_id',$user_id)->where('status',1)->count();
        $check_subscription=Subscription::where('user_id',$user_id)->where('is_active',true)->first();
        
        if($check_documents > 1 && $check_vehicle > 0 && $check_subscription != null){
            return true;
        }
        
        return false;
    }
    
    public static function profile($user,$app){
        if($app=='driver'){
            //driver profile
            
            //get avatar
            $avatar=UserDocument::where('user_id',$user->id)->where('document_name','avatar')->value('file_loc');
            
            if($avatar!=null){
                $avatar=\Config::get('values.app_api').'/uploads/avatar/'.$avatar;
            }
            
            //get subscription status
            $subscription=Subscription::where('user_id',$user->id)->where('is_active',true)->first();
            if($subscription==null){
                $subscription=new Subscription;
            }
            
            //get trip status
            $trip=Trip::where('driver_id',$user->id)->where('status','>=',1)->where('status','<',3)->orderBy('id','DESC')->first();
            if($trip!=null){
                //has an active trip
                $bookings=Booking::where('trip_id',$trip->id)->where('status','>=',1)->where('status','<',3)->get();
                
                $riders=array();
                foreach($bookings as $booking){
                    
                    //get avatar
                    $rider_photo=UserDocument::where('user_id',$booking->rider_id)->where('document_name','avatar')->value('file_loc');
                    
                    if($rider_photo!=null){
                        $rider_photo=\Config::get('values.app_api').'/uploads/avatar/'.$rider_photo;
                    }
                    
                    $trip_request=TripRequest::where('user_id',$booking->rider_id)->orderBy('id','DESC')->first();
                    $riders[]=[
                        'id'=>$booking->rider_id,
                        'rider_name'=>User::where('id',$booking->rider_id)->value('name'),
                        'rider_phone'=>User::where('id',$booking->rider_id)->value('phone'),
                        'from'=>$trip_request->from,
                        'destination'=>$trip_request->destination,
                        'wait_time'=>$trip_request->wait_time->format("d M · h:i A"),
                        'rider_photo'=>$rider_photo,
                        'rating'=>User::avg_rating($booking->rider_id),
                        'price'=>$trip_request->price,
                    ];
                }
                
                if($trip->status==1){
                    $status="pending";
                }else if($trip->status==2){
                    $status="active";
                }else{
                    $status="idle";
                }
                
                $vehicle=Vehicle::where('id',$trip->vehicle_id)->first();
                $active_trip=[
                    'id'=>$trip->id,
                    'from'=>$trip->from,
                    'destination'=>$trip->to,
                    'vehicle'=>$vehicle->model." ".$vehicle->make,
                    'plate_number'=>$vehicle->plate_number,
                    'date'=>$trip->date->format("d M · h:i A"),
                    'status'=>$status,
                    'is_complete'=>$trip->is_complete,
                    'riders'=>$riders,
                ];
            }else{
                //has no active trip
                $active_trip=null;
            }
            $package=Package::where('id',$subscription->package_id)->first();
            if($package==null){
                $user_subscription=null;
            }else{
                $user_subscription=[
                    'package'=>$package->title,
                    'price_paid'=>number_format($subscription->price_paid),
                    'reference_no'=>$subscription->reference_no,
                    'is_active'=>$subscription->is_active,
                    'duration'=>$package->duration." Months",
                    'expires_at'=>$subscription->expires_at->format('F d, Y'),
                ];
            }
            $response=[
                'status'=>true,
                'data'=>[
                    'id'=>$user->id,
                    'name'=>$user->name,
                    'phone'=>$user->phone,
                    'email'=>$user->email,
                    'avatar'=>$avatar,
                    'rating'=>User::avg_rating($user->id),
                    'can_drive'=>User::can_drive($user->id),
                    'subscription'=>$user_subscription,
                    'active_trip'=>$active_trip,
                ]
            ];
            //end driver profile
        
        }
        else{
            //user profile
            $avatar=UserDocument::where('user_id',$user->id)->where('document_name','avatar')->value('file_loc');
            
            if($avatar!=null){
                $avatar=\Config::get('values.app_api').'/uploads/avatar/'.$avatar;
            }
            $trip_id=Booking::where('rider_id',$user->id)->where('status','>=',1)->where('status','<',3)->value('trip_id');
            if($trip_id!=null){
                //has an active trip
                $trip_details=Trip::where('id',$trip_id)->first();
                $driver_photo=UserDocument::where('user_id',$trip_details->driver_id)->where('document_name','avatar')->value('file_loc');

                if($driver_photo!=null){
                    $driver_photo=\Config::get('values.app_api').'/uploads/avatar/'.$driver_photo;
                }
                $active_trip=[
                    "trip_id"=>$trip_details->id,
                    "vehicle_name"=>Vehicle::name($trip_details->vehicle_id),
                    "license_plate"=>Vehicle::plate_number($trip_details->vehicle_id),
                    "driver_name"=>User::where("id",$trip_details->driver_id)->value("name"),
                    "driver_phone"=>User::where("id",$trip_details->driver_id)->value("phone"),
                    "driver_photo"=>$driver_photo,
                    "from"=>$trip_details->from,
                    "destination"=>$trip_details->to,
                    "price"=>TripRequest::where('user_id',$user->id)->orderBy('id','DESC')->value('price'),
                    "date"=>$trip_details->date->format('F d, Y h:i:s A'),
                    "created_on"=>$trip_details->created_at,
                ];
            }else{
                //has no active trip
                $trip=TripRequest::where('user_id',$user->id)->where('status',1)->orderBy('id','DESC')->first();
                if($trip==null){
                    $active_trip=null;
                }else{
                    $active_trip=[
                        "trip_id"=>$trip->id,
                        "vehicle_name"=>null,
                        "license_plate"=>null,
                        "driver_name"=>null,
                        "driver_phone"=>null,
                        "driver_photo"=>null,
                        "from"=>$trip->from,
                        'price'=>$trip->price,
                        "destination"=>$trip->destination,
                        "date"=>$trip->wait_time->format('F d, Y h:i:s A'),
                        "created_on"=>$trip->created_at,
                    ];
                }
                
            }
            
            $response=[
                'status'=>true,
                'data'=>[
                    'id'=>$user->id,
                    'name'=>$user->name,
                    'phone'=>$user->phone,
                    'email'=>$user->email,
                    'avatar'=>$avatar,
                    'rating'=>User::avg_rating($user->id),
                    'active_trip'=>$active_trip,
                ]
            ];
            //end user profile
        }
        
        return $response;
    }
}
