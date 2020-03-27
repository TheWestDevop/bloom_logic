<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\UserDocument;
use App\TripRequest;
use App\Trip;
use App\Booking;
use App\Vehicle;
use App\PushId;
use App\TripRating;
use Carbon\Carbon;

class TripController extends Controller
{
    //

    public function create_request(Request $request){
        $rider_id=$request->rider_id;
        $from=$request->from;
        $destination=$request->destination;
        $private_trip=(boolean)$request->private_trip;
        $wait_time=Carbon::parse($request->wait_time);
        $latlng=$request->latlng;
        $price=$request->cost;

        $user=User::where('id',$rider_id)->first();

        $check=TripRequest::where('user_id',$user->id)->where('status','>=',1)->first();

        if($check==null){

//            //flip date
//            if($wait_time->format('A')=="AM"){
//                $wait_time=$wait_time->addHours(12);
//            }else{
//                $wait_time=$wait_time->subHours(12);
//            }

            $tripRequest=new TripRequest;

            $tripRequest->user_id=$user->id;
            $tripRequest->from=$from;
            $tripRequest->destination=$destination;
            $tripRequest->private_trip=$private_trip;
            $tripRequest->wait_time=$wait_time;
            $tripRequest->latlng=$latlng;
            $tripRequest->price=$price;
            $tripRequest->save();

            $response=[
                'status'=>true,
                'data'=>[
                    'user_id'=>$user->id,
                    'name'=>$user->name,
                    'request_details'=>$tripRequest,
                ],
            ];
        }else{
            $response=[
                'status'=>false,
                'message'=>'Multiple trip requests not allowed'
            ];

            return response()->json($response, 400);
        }

        return $response;
    }

    public function cancel_request(Request $request){
        $rider_id=$request->rider_id;
        $request_id=$request->request_id;

        $user=User::where('id',$rider_id)->first();

        $tripRequest=TripRequest::where('id',$request_id)->where('user_id',$user->id)->first();

        if($tripRequest!=null){

            if($tripRequest->status==0){

                //check booking
                $check_booking=Booking::where('rider_id',$rider_id)->where('status',1)->first();

                if($check_booking!=null){
                    $check_trip=Trip::where('id',$check_booking->trip_id)->first();

                    if($check_trip->from==$tripRequest->from && $check_trip->to==$tripRequest->destination){

                        //invalidate / cancel booking
                        $check_booking->status=0;
                        $check_booking->save();
                    }
                }
            }else{
                //invalidate / cancel request
                $tripRequest->status=0;
                $tripRequest->save();
            }

            $response=[
                'status'=>true,
                'message'=>'Trip request cancelled',
                'data'=>[
                    'user_id'=>$user->id,
                    'user'=>$user->name,
                    'request_details'=>$tripRequest,
                ],
            ];

        }else{
            $response=[
                'status'=>false,
                'message'=>'Unable to find trip request',
            ];

            return response()->json($response, 400);
        }

        return $response;

    }

    public function history(Request $request){
        $user_id=$request->rider_id;
        $app=$request->app;

        if($app=='driver'){

            $trips=Trip::where('driver_id',$user_id)->orderBy('id','DESC')->get();

            $trip_list=array();

            foreach($trips as $trip){
                if($trip->status==1){
                    $status='pending';
                }else if($trip->status==2){
                    $status='ongoing';
                }else if($trip->status==3){
                    $status="completed";
                }else{
                    $status='cancelled';
                }

                $vehicle=Vehicle::where('id',$trip->vehicle_id)->first();

                $trip_list[]=[
                    'id'=>$trip->id,
                    'from'=>$trip->from,
                    'destination'=>$trip->to,
                    'vehicle'=>$vehicle->make." ".$vehicle->model,
                    'plate_number'=>$vehicle->plate_number,
                    'date'=>$trip->date->format("d M · h:i A"),
                    'status'=>$status,
                    'is_complete'=>$trip->is_complete,
                    'riders'=>[],
                    'rate_rider'=>false,
                ];

            }

        }else{
            $trip_id=Booking::where('rider_id',$user_id)->orderBy('id','DESC')->pluck('trip_id')->toArray();

            $trips=Trip::whereIn('id',$trip_id)->orderBy('id','DESC')->get();

            $trip_list=[];

            foreach($trips as $trip){

                if($trip->status==1){
                    $status='pending';
                }else if($trip->status==2){
                    $status='ongoing';
                }else if($trip->status==3){
                    $status="completed";
                }else{
                    $status='cancelled';
                }

                $driver=User::where('id',$trip->driver_id)->first();
                $vehicle=Vehicle::where('id',$trip->vehicle_id)->first();

                $trip_list[]=[
                    'id'=>$trip->id,
                    'driver_name'=>$driver->name,
                    'driver_phone'=>$driver->phone,
                    'from'=>$trip->from,
                    'destination'=>$trip->to,
                    'date'=>$trip->date->format('F d, Y h:i:s A'),
                    'vehicle'=>$vehicle->make." ".$vehicle->model,
                    'license_plate'=>$vehicle->plate_number,
                    'is_completed'=>$trip->is_complete,
                    'trip_status'=>$status,
                    'rating'=>TripRating::where('trip_id',$trip->id)->where('user_id',$user_id)->value('rating'),
                ];
            }
        }



        $response=[
            'status'=>true,
            'message'=>count($trips).' trips',
            'data'=>$trip_list,
        ];

        return $response;
    }

    public function find_requests(Request $request){
        $driver_id=$request->driver_id;
        $from=$request->from;
        $destination=$request->destination;
        $date=Carbon::parse($request->date);
        $type=$request->type;

        if($date->isToday()){
            $date=Carbon::now();
        }
        if(Carbon::now()->subMinutes(1)->gt($date)){
            $response=[
                'status'=>false,
                'message'=>'Please provide a valid departure date',
            ];
            return response()->json($response, 400);
        }

        if(User::can_drive($driver_id)==false){
            $response=[
                'status'=>false,
                'message'=>'If you have uploaded all your document.Please allow up to 6 hours for Verification Process to be completed'
            ];
            return response()->json($response, 400);
        }


        $trip_requests=TripRequest::where('user_id','!=',$driver_id)->where('from','LIKE',"%$from%")->where('destination','LIKE',"%$destination%")->where('wait_time','>=',$date)->where('status',1)->where('private',$type)->get();

        if($trip_requests->isEmpty()){
            $response=[
                'status'=>false,
                'message'=>"No trip requests",
                'data'=>$trip_requests,
            ];

            return response()->json($response, 400);
        }
        else{
            $data=[];

            foreach($trip_requests as $trip_request){

                $avatar=UserDocument::where('user_id',$trip_request->user_id)->where('document_name','avatar')->value('file_loc');

                if($avatar!=null){
                    $avatar=\Config::get('values.app_api').'/uploads/avatar/'.$avatar;
                }

                $data[]=[
                    'id'=>$trip_request->id,
                    'user_id'=>$trip_request->user_id,
                    'rider'=>User::where('id',$trip_request->user_id)->value('name'),
                    'rider_phone'=>User::where('id',$trip_request->user_id)->value('phone'),
                    'from'=>$trip_request->from,
                    'destination'=>$trip_request->destination,
                    'rider_avatar'=>$avatar,
                    'price'=>$trip_request->price,
                    'wait_time'=>$trip_request->wait_time->format("d M · h:i A"),
                ];
            }

            $response=[
                'status'=>true,
                'data'=>$data,
            ];
        }

        return $response;
    }

    //rating and review

    public function rating(Request $request){
        $user_id=$request->user_id;
        $trip_id=$request->trip_id;
        $object_id=$request->object_id;

        $rating=$request->rating;
        $review=$request->review;

        if($object_id==null){
            //passenger doing the rating
            $object_id=Trip::where('id',$trip_id)->value('driver_id');

            $role='rider';
            //trip is not valid
            if($object_id==null){
                $response=[
                    'status'=>false,
                    'message'=>'Invalid trip',
                ];

                return response()->json($response, 400);
            }
        }else{
            $role='driver';
        }

        $trip_rating=TripRating::where('user_id',$user_id)->where('trip_id',$trip_id)->where('object_id',$object_id)->first();

        if($trip_rating==null){
            $trip_rating=new TripRating;
        }

        $trip_rating->user_id=$user_id;
        $trip_rating->trip_id=$trip_id;
        $trip_rating->object_id=$object_id;

        $trip_rating->rating=$rating;
        $trip_rating->review=$review;
        $trip_rating->user_role=$role;

        if($trip_rating->save()){
            $response=[
                'status'=>true,
                'message'=>'Trip rating successful',
            ];

        }else{
            $response=[
                'status'=>false,
                'message'=>'Unable to complete rating',
            ];

            return response()->json($response, 400);
        }

        return $response;
    }
}
