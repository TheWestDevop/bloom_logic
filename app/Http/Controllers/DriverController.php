<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\TripRequest;
use App\TripRating;
use App\Trip;
use App\Booking;
use App\Vehicle;
use App\Notification;
use App\UserDocument;
use App\PushId;
use Carbon\Carbon;

class DriverController extends Controller
{
    
    //accept request
    public function accept_request(Request $request){
        $driver_id=$request->driver_id;
        $request_id=$request->request_id;
        
        $trip_request=TripRequest::where('id',$request_id)->first();
        
        if($trip_request==null){
            $response=[
                'status'=>false,
                'message'=>'Invalid trip request'
            ];
            
            return response()->json($response, 400);
        }
        
        
        $from=$trip_request->from;
        $to=$trip_request->destination;
        $date=$trip_request->wait_time;
        
        //stop drivers from accepting their own trips
        if($trip_request->user_id==$driver_id){
            $response=[
                'status'=>false,
                'message'=>'Cannot accept a request you posted'
            ];
            
            return response()->json($response, 400);
        }
        else{
            if($trip_request->status==1){

                //get drivers default vehicle
                $vehicle=Vehicle::where('driver_id',$driver_id)->where('is_default',true)->first();

                if($vehicle==null){
                    //no default vehicle
                    $response=[
                        'status'=>false,
                        'message'=>"Default vehicle not set. Go to vehicles under your profile, swipe left to set default vehicle"
                    ];

                    return response()->json($response, 400);
                }else{
        
                    if(User::can_drive($driver_id)==false){
                        $response=[
                            'status'=>false,
                            'message'=>'Registration not completed'
                        ];

                        return response()->json($response, 400);
                    }

                    //check if driver has an active trip
                    $check_trip=Trip::where('driver_id',$driver_id)->where('status','>=',1)->where('is_complete',false)->first();

                    if($check_trip!=null){

                        $trip=$check_trip;

                    }
                    //create trip record
                    else{

                        $trip=new Trip;
                        $trip->driver_id=$driver_id;
                        $trip->vehicle_id=$vehicle->id;
                        $trip->from=$from;
                        $trip->to=$to;
                        $trip->date=Carbon::parse($date);
                        $trip->save();

                    }

                    //check number of passengers on booking
                    $num_passengers=count(Booking::where('trip_id',$trip->id)->where('status',1)->get());

                    if($num_passengers<$vehicle->seats){
                        //add user to booking table

                        $booking=new Booking;

                        $booking->driver_id=$driver_id;
                        $booking->rider_id=$trip_request->user_id;
                        $booking->trip_id=$trip->id;

                        $booking->save();

                        //update trip request status

                        $trip_request->status=0;
                        $trip_request->save();

                        //send passenger notification  
                        $device_token=User::notification_receiver($trip_request->user_id);
                        $driver_photo=UserDocument::where('user_id',$vehicle->driver_id)->where('document_name','avatar')->value('file_loc');

                        if($driver_photo!=null){
                            $driver_photo=\Config::get('values.app_api').'/uploads/avatar/'.$driver_photo;
                        }

                        $body='{
                          "to": "'.$device_token.'",
                          "notification": {
                            "title": "Trip Accepted",
                            "body": "A titan is on the way."
                          },
                          "data": {
                            "response": {
                                "action": "trip.accept",
                                "status": true,
                                "data": {
                                    "trip_id": '.$trip->id.',
                                    "vehicle_name": "'.$vehicle->make.' '.$vehicle->model.'",
                                    "license_plate": "'.$vehicle->plate_number.'",
                                    "driver_name": "'.User::where("id",$vehicle->driver_id)->value("name").'",
                                    "driver_phone": "'.User::where("id",$vehicle->driver_id)->value("phone").'",
                                    "driver_photo": "'.$driver_photo.'",
                                    "driver_rating": "'.User::avg_rating($vehicle->driver_id).'",
                                    "from": "'.$trip_request->from.'",
                                    "destination": "'.$trip_request->destination.'",
                                    "date": "'.$trip_request->wait_time->format('F d, Y h:i:s A').'",
                                    "created_on": "'.$trip_request->created_at.'"
                                }
                            }
                          }
                        }';
                        
                        Notification::send_notification($body);
                        $bookings=Booking::where('trip_id',$trip->id)->where('status',1)->get();
                        //get passengers currently on trip manifest
                        $passengers=array();

                        foreach($bookings as $booking){
                            
                            $rider_photo=UserDocument::where('user_id',$booking->rider_id)->where('document_name','avatar')->value('file_loc');
            
                            if($rider_photo!=null){
                                $rider_photo=\Config::get('values.app_api').'/uploads/avatar/'.$rider_photo;
                            }
                            
                            $trip_request=TripRequest::where('user_id',$booking->rider_id)->orderBy('id','DESC')->first();
                            
                            $passengers[]=[
                                'id'=>$booking->rider_id,
                                'rider_name'=>User::where('id',$booking->rider_id)->value('name'),
                                'rider_phone'=>User::where('id',$booking->rider_id)->value('phone'),
                                'from'=>$trip_request->from,
                                'destination'=>$trip_request->destination,
                                'wait_time'=>$trip_request->wait_time->format("d M · h:i A"),
                                'rider_photo'=>$rider_photo,
                                'rating'=>User::avg_rating($booking->rider_id)
                            ];
                        }

                        //build drivers response

                        $response=[
                            'status'=>true,
                            'message'=>'Trip Request Accepted',
                            'data'=>[
                                'id'=>$trip->id,
                                'from'=>$trip->from,
                                'destination'=>$trip->to,
                                'vehicle'=>$vehicle->model." ".$vehicle->make,
                                'plate_number'=>$vehicle->plate_number,
                                'date'=>$trip->date->format("d M · h:i A"),
                                'status'=>'pending',
                                'is_complete'=>$trip->is_complete,
                                'riders'=>$passengers,
                            ],
                        ];

                    }else{
                        $response=[
                            'status'=>false,
                            'message'=>'Maximum occupancy reached'
                        ];

                        return response()->json($response, 400);
                    }
                }

            }else{
                $response=[
                    'status'=>false,
                    'message'=>'Request not available'
                ];

                return response()->json($response, 400);
            }
        }  
        
        return $response;
    }
    
    //start trip
    public function start_trip(Request $request){
        $trip_id=$request->trip_id;
        $driver_id=$request->driver_id;
        
        $trip=Trip::where('id',$trip_id)->where('driver_id',$driver_id)->where('status',1)->first();
        
        if($trip!=null){
            
            //update bookings
            $bookings=Booking::where('trip_id',$trip->id)->where('status',1)->get();
        
            //trip manifest
            $passengers=array();
            foreach($bookings as $booking){
                
                $rider_photo=UserDocument::where('user_id',$booking->rider_id)->where('document_name','avatar')->value('file_loc');
                   
                if($rider_photo!=null){
                    $rider_photo=\Config::get('values.app_api').'/uploads/avatar/'.$rider_photo;
                }
                
                $trip_request=TripRequest::where('user_id',$booking->rider_id)->orderBy('id','DESC')->first();
                
                $passengers[]=[
                    'id'=>$booking->rider_id,
                    'rider_name'=>User::where('id',$booking->rider_id)->value('name'),
                    'rider_phone'=>User::where('id',$booking->rider_id)->value('phone'),
                    'from'=>$trip_request->from,
                    'destination'=>$trip_request->destination,
                    'wait_time'=>$trip_request->wait_time->format("d M · h:i A"),
                    'rider_photo'=>$rider_photo,
                    'rating'=>User::avg_rating($booking->rider_id),
                ];
                
                $booking->status=2;
                $booking->save();
            }
            
            if(count($bookings)==0){
                //invalid trip id or driver id
                $response=[
                    'status'=>false,
                    'message'=>'Cannot start trip without riders'
                ];

                return response()->json($response, 400);
            }
            
            //update trip status
            $trip->status=2;
            $trip->save();
            
            //drivers response
            
            $vehicle=Vehicle::where('id',$trip->vehicle_id)->first();
            
            $response=[
                'status'=>true,
                'message'=>'Trip started by driver',
                'data'=>[
                    'id'=>$trip->id,
                    'from'=>$trip->from,
                    'destination'=>$trip->to,
                    'vehicle'=>$vehicle->model." ".$vehicle->make,
                    'plate_number'=>$vehicle->plate_number,
                    'date'=>$trip->date->format("d M · h:i A"),
                    'status'=>'active',
                    'is_complete'=>$trip->is_complete,
                    'riders'=>$passengers,
                ],
            ];
            
        }else{
            //invalid trip id or driver id
            $response=[
                'status'=>false,
                'message'=>'Trip not available'
            ];
            
            return response()->json($response, 400);
        }
        
        return $response;
    }
    
    //drop off / remove rider from trip
    public function drop_off(Request $request){
        $rider_id=$request->rider_id;
        $driver_id=$request->driver_id;
        $trip_id=$request->trip_id;
        
        //get trip details
        
        $trip=Trip::where('id',$trip_id)->first();
        $booking=Booking::where('trip_id',$trip->id)->where('status','>',0)->where('status','<',3)->where('rider_id',$rider_id)->first();
        
        if($booking!=null && $trip!=null){
            
            //determine drop off or remove rider
            if($trip->status==1){
                //trip is yet to begin  - remove rider
                
                $booking->status=0;
                $booking->save();
                
                $trip_request=TripRequest::where('user_id',$booking->rider_id)->orderBy('id','DESC')->first();
                $trip_request->status=1;
                $trip_request->save();
                
                //notify rider he has been removed
                $device_token=User::notification_receiver($rider_id);
                
                $body='{
                    "to": "'.$device_token.'",
                    "notification": {
                        "title": "Trip Cancelled",
                        "body": "Your driver cancelled the trip"
                    },
                    "data": {
                        "response": {
                            "action": "trip.cancel",
                            "status": true,
                            "data": {
                                "trip_id": '.$trip->id.',
                                "driver": "'.User::where("id",$driver_id)->value("name").'",
                                "from": "'.$trip->from.'",
                                "to": "'.$trip->to.'",
                                "trip_date": "'.$trip->date->format('F d, Y h:i:s A').'",
                                "rate_driver": "false"
                            }
                        }
                    }
                }';
                
                Notification::send_notification($body);
                
                //TODO: automatically enlist rider request
                
                //TODO: fetch trip manifest
                $bookings=Booking::where('trip_id',$trip->id)->where('status',1)->get();
                //get passengers currently on trip manifest
                $passengers=array();
                foreach($bookings as $booking){
                    
                    $rider_photo=UserDocument::where('user_id',$booking->rider_id)->where('document_name','avatar')->value('file_loc');
                    
                    if($rider_photo!=null){
                        $rider_photo=\Config::get('values.app_api').'/uploads/avatar/'.$rider_photo;
                    }
                    
                    $trip_request=TripRequest::where('user_id',$booking->rider_id)->orderBy('id','DESC')->first();
                    
                    $passengers[]=[
                        'id'=>$booking->rider_id,
                        'rider_name'=>User::where('id',$booking->rider_id)->value('name'),
                        'rider_phone'=>User::where('id',$booking->rider_id)->value('phone'),
                        'from'=>$trip_request->from,
                        'destination'=>$trip_request->destination,
                        'wait_time'=>$trip_request->wait_time->format("d M · h:i A"),
                        'rider_photo'=>$rider_photo,
                        'rating'=>User::avg_rating($booking->rider_id),
                    ];
                }
                
                //build drivers response
                $vehicle=Vehicle::where('id',$trip->vehicle_id)->first();
                
                //cancel trip if last passenger is removed off
                $status='pending';
                if(count($passengers)==0){
                    $trip->status=0;
                    $trip->is_complete=false;
                    $trip->save();
                    
                    $status='idle';
                }
                
                $response=[
                    'status'=>true,
                    'message'=>'Rider has been removed',
                    'data'=>[
                        'id'=>$trip->id,
                        'from'=>$trip->from,
                        'destination'=>$trip->to,
                        'vehicle'=>$vehicle->model." ".$vehicle->make,
                        'plate_number'=>$vehicle->plate_number,
                        'date'=>$trip->date->format("d M · h:i A"),
                        'status'=>$status,
                        'is_complete'=>$trip->is_complete,
                        'riders'=>$passengers,
                        'rate_rider'=>false,
                    ],
                ];
            }
            else if($trip->status==2){
                //trip has begun - drop off
                
                $booking->status=3; //trip completed
                $booking->save();
                
                //notify rider he has been dropped off
                $device_token=User::notification_receiver($rider_id);
                
                $body='{
                    "to": "'.$device_token.'",
                    "notification": {
                        "title": "Trip Completed",
                        "body": "You have reached your destination"
                    },
                    "data": {
                        "response": {
                            "action": "trip.dropoff",
                            "status": true,
                            "data": {
                                "trip_id": '.$trip->id.',
                                "driver": "'.User::where("id",$driver_id)->value("name").'",
                                "from": "'.$trip->from.'",
                                "to": "'.$trip->to.'",
                                "trip_date": "'.$trip->date->format('F d, Y h:i:s A').'",
                                "rate_driver": "true"
                            }
                        }
                    }
                }';
                
                Notification::send_notification($body);
                
                //TODO: fetch trip manifest
                $bookings=Booking::where('trip_id',$trip->id)->where('status',2)->get();
                //get passengers currently on trip manifest
                $passengers=array();
                foreach($bookings as $booking){
                    
                    $rider_photo=UserDocument::where('user_id',$booking->rider_id)->where('document_name','avatar')->value('file_loc');
                    
                    if($rider_photo!=null){
                        $rider_photo=\Config::get('values.app_api').'/uploads/avatar/'.$rider_photo;
                    }
                    
                    $trip_request=TripRequest::where('user_id',$booking->rider_id)->orderBy('id','DESC')->first();
                    
                    $passengers[]=[
                        'id'=>$booking->rider_id,
                        'rider_name'=>User::where('id',$booking->rider_id)->value('name'),
                        'rider_phone'=>User::where('id',$booking->rider_id)->value('phone'),
                        'from'=>$trip_request->from,
                        'destination'=>$trip_request->destination,
                        'wait_time'=>$trip_request->wait_time->format("d M · h:i A"),
                        'rider_photo'=>$rider_photo,
                        'rating'=>User::avg_rating($booking->rider_id),
                    ];
                }
                
                //build drivers response
                
                $vehicle=Vehicle::where('id',$trip->vehicle_id)->first();
                
                //end trip if last passenger is dropped off
                $active_passengers=Booking::where('trip_id',$trip->id)->where('status','>',0)->where('status','<',3)->get();
                $status='active';
                if(count($active_passengers)==0){
                    $trip->status=3;
                    $trip->is_complete=true;
                    $trip->save();
                    
                    $status='idle';
                }
                
                //build drivers response
                $response=[
                    'status'=>true,
                    'message'=>'Rider has been removed',
                    'data'=>[
                        'id'=>$trip->id,
                        'from'=>$trip->from,
                        'destination'=>$trip->to,
                        'vehicle'=>$vehicle->model." ".$vehicle->make,
                        'plate_number'=>$vehicle->plate_number,
                        'date'=>$trip->date->format("d M · h:i A"),
                        'status'=>$status,
                        'is_complete'=>$trip->is_complete,
                        'riders'=>$passengers,
                        'rate_rider'=>true,
                    ],
                ];
            }
            
            else{
                //trip is either completed or cancelled
                $response=[
                    'status'=>false,
                    'message'=>'Cannot drop off or remove rider from this trip',
                    'data'=>[
                        'rate_rider'=>false
                    ]
                ];

                return response()->json($response, 400);
            }
            
        }else{
            $response=[
                'status'=>false,
                'message'=>'Invalid parameters passed',
                'data'=>[
                    'rate_rider'=>false
                ]
            ];
            
            return response()->json($response, 400);
        }
        
        return $response;
    }
    
    public function history(Request $request){
        $driver_id=$request->driver_id;
        
        $trip_records=array();
        $trips=Trip::where('driver_id',$driver_id)->where('status','!=',1)->where('status','!=',2)->orderBy('id','DESC')->get();
        
        foreach($trips as $trip){
            
            if($trip->status==1){
                $status="pending";
            }else if($trip->status==2){
                $status="ongoing";
            }else if($trip->status==3){
                $status="completed";
            }else{
                $status="cancelled";
            }
            
            //get passengers on trip manifest
            $passengers=array();
            
            $trip_records[]=[
                'from'=>$trip->from,
                'destination'=>$trip->to,
                'vehicle'=>Vehicle::where('id',$trip->vehicle_id)->value('make')." ".Vehicle::where('id',$trip->vehicle_id)->value('model'),
                'plate_number'=>Vehicle::where('id',$trip->vehicle_id)->value('plate_number'),
                'date'=>$trip->date,
                'status'=>$status,
                'is_complete'=>$trip->is_complete,
                'riders'=>$passengers,
            ];
            
        }
        
        $response=[
            'status'=>true,
            'data'=>[
                'trips'=>$trip_records,
                'total'=>count($trip_records),
            ]
        ];
        
        return $response;
    }
}
