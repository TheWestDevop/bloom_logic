<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Vehicle;

class VehicleController extends Controller
{
    //
    
    public function store(Request $request){
        $driver_id=$request->driver_id;
        $make=strtolower($request->make);
        $model=strtolower($request->model);
        $year=$request->year;
        $plate_number=strtolower($request->plate_number);
        $color=strtolower($request->color);
        $seats=$request->seats;
        
        $inspection_report=base64_decode($request->inspection_report_file);
        $inspection_report_name=$request->inspection_report_file_name;
        
        $road_worthiness=base64_decode($request->road_worthiness_file);
        $road_worthiness_name=$request->road_worthiness_file_name;
        
        $insurance=base64_decode($request->insurance_file);
        $insurance_name=$request->insurance_file_name;
        
        //check existing license plates
        
        $check_vehicle=Vehicle::where('plate_number',$plate_number)->where('status','!=',3)->first();
        
        if($check_vehicle!=null){
            $response=[
                'status'=>false,
                'message'=>'Vehicle already exists'
            ];
            
            return response()->json($response, 400);
        }
        
        else{
            
            /*vehicle inspection report*/
            if($inspection_report==null){
                $response=[
                    'status'=>false,
                    'message'=>'Attach inspection report'
                ];

                return response()->json($response, 400);
            }else{
                $ext=explode(".",$inspection_report_name)[count(explode(".",$inspection_report_name))-1];
                $report_file_name=str_replace(" ","",$plate_number)."_".time().".".$ext;

                file_put_contents(public_path('/uploads/vehicle-inspection')."/".$report_file_name,$inspection_report);
            }
            /*end vehicle inspection report*/
            
            /*vehicle insurance report*/
            if($insurance==null){
                $response=[
                    'status'=>false,
                    'message'=>'Attach vehicle insurance certificate'
                ];

                return response()->json($response, 400);
            }else{
                $ext=explode(".",$insurance_name)[count(explode(".",$insurance_name))-1];
                $insurance_file_name=str_replace(" ","",$plate_number)."_".time().".".$ext;

                file_put_contents(public_path('/uploads/insurance')."/".$insurance_file_name,$insurance);
            }
            /*end vehicle insurance report*/
            
            /*vehicle road worthiness certificate*/
            if($road_worthiness==null){
                $response=[
                    'status'=>false,
                    'message'=>'Attach inspection report'
                ];

                return response()->json($response, 400);
            }else{
                $ext=explode(".",$road_worthiness_name)[count(explode(".",$road_worthiness_name))-1];
                $road_worthiness_file_name=str_replace(" ","",$plate_number)."_".time().".".$ext;

                file_put_contents(public_path('/uploads/road-worthiness')."/".$road_worthiness_file_name,$road_worthiness);
            }
            /*end road worthiness certificate*/
            
            //add new vehicle
            $vehicle=new Vehicle;
            
            $vehicle->driver_id=$driver_id;
            $vehicle->make=$make;
            $vehicle->model=$model;
            $vehicle->year=$year;
            $vehicle->plate_number=$plate_number;
            $vehicle->is_default=false;
            $vehicle->color=$color;
            $vehicle->seats=$seats;
            $vehicle->report=$report_file_name;
            $vehicle->insurance=$insurance_file_name;
            $vehicle->road_worthiness=$road_worthiness_file_name;
            
            $vehicle->save();
            
            $response=[
                'status'=>true,
                'message'=>'Vehicle added successfully',
                'data'=>[
                    'id'=>$vehicle->id,
                    'driver_id'=>intval($vehicle->driver_id),
                    'make'=>$vehicle->make,
                    'model'=>$vehicle->model,
                    'year'=>intval($vehicle->year),
                    'plate_number'=>$vehicle->plate_number,
                    'is_default'=>$vehicle->is_default,
                    'color'=>$vehicle->color,
                    'seats'=>intval($vehicle->seats),
                    'status'=>0,
                ],
            ];
        }
        
        return $response;
    }
    
    public function list_vehicles(Request $request){
        $driver_id=$request->driver_id;
        
        //status of 3 means deleted
        $vehicles=Vehicle::where('driver_id',$driver_id)->where('status','!=',3)->get();
        
        if($vehicles==null){
            $response=[
                'status'=>false,
                'message'=>'No registered vehicles'
            ];
            
            return response()->json($response, 400);
        }
        
        $response=[
            'status'=>true,
            'data'=>$vehicles
        ];
        
        return $response;
    }
    
    public function delete_vehicle(Request $request){
        $driver_id=$request->driver_id;
        $vehicle_id=$request->vehicle_id;
        
        $vehicle=Vehicle::where('driver_id',$driver_id)->where('id',$vehicle_id)->first();
        
        if($vehicle==null){
            $response=[
                'status'=>false,
                'message'=>'Vehicle does not exist'
            ];
            
            return response()->json($response, 400);
        }
        
        $vehicle->is_default=false;
        $vehicle->status=3;
        $vehicle->save();
        
        $vehicle['year']=intval($vehicle['year']);
        
        $response=[
            "status"=>true,
            'message'=>'Vehicle deleted',
            "data"=>$vehicle,
        ];
        
        return $response;
    }
    
    public function set_default(Request $request){
        $driver_id=$request->driver_id;
        $vehicle_id=$request->vehicle_id;
        
        
        $vehicle=Vehicle::where('driver_id',$driver_id)->where('id',$vehicle_id)->first();
        
        if($vehicle!=null){
            
            if($vehicle->status==1){
                
                Vehicle::where('driver_id',$driver_id)->update(['is_default'=>false]);
                
                $vehicle->is_default=true;
                $vehicle->save();
                
                $vehicle['year']=intval($vehicle['year']);
                
                $response=[
                    'status'=>true,
                    'message'=>'Default vehicle updated',
                    "data"=>$vehicle,
                ];
                
            }else{
                $response=[
                    'status'=>false,
                    'message'=>'Vehicle still pending approval'
                ];
                
                return response()->json($response, 400);
            }
            
        }else{
            $response=[
                'status'=>false,
                'message'=>'Invalid vehicle'
            ];
            
            return response()->json($response, 400);
        }
        
        return $response;
    }
}
