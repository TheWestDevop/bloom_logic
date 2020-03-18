<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Subscription;
use App\Package;
use DB;
use Carbon\Carbon;

class TransactionController extends Controller
{
    //
    
    public function subscriptions(){
        
        $packages[0]=[
            "id"=>1,
            'title'=>'Basic',
            'duration'=>'1 month',
            'price'=>number_format(Package::where('id',1)->value('price')),
            'color1'=>0xffA37FFE,
            'color2'=>0xff786CFF,
        ];$packages[1]=[
            "id"=>2,
            'title'=>'Silver',
            'duration'=>'3 months',
            'price'=>number_format(Package::where('id',2)->value('price')),
            'color1'=>0xffE99696,
            'color2'=>0xffF6C69E,
        ];$packages[2]=[
            "id"=>3,
            'title'=>'Gold',
            'duration'=>'6 months',
            'price'=>number_format(Package::where('id',3)->value('price')),
            'color1'=>0xffF3B1BE,
            'color2'=>0xff9877D7,
        ];$packages[3]=[
            "id"=>4,
            'title'=>'Platinum',
            'duration'=>'12 months',
            'price'=>number_format(Package::where('id',4)->value('price')),
            'color1'=>0xff3ADFC4,
            'color2'=>0xff58BFF7,
        ];
        
        $response=[
            'status'=>true,
            'data'=>$packages,
        ];
        
        return $response;
    }
    
    public function verify(Request $request){
        
        $user_id=$request->driver_id;
        $package_id=$request->package_id;
        $reference=$request->reference_no;
        
//        return $request->all();
        
        /*pay online*/
        $curl = curl_init();
        if(!$reference){
            $response=[
                'status'=>false,
                'message'=>"No reference supplied"
            ];
        }
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "authorization: Bearer sk_live_203873034cffd1444540791a17638b47b842e454",
                "cache-control: no-cache"
            ],
        ));
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        if($err){
            // there was an error contacting the Paystack API
            
            $response=[
                'status'=>false,
                'message'=>'Curl returned error: ' . $err
            ];
        }
        
        $tranx = json_decode($response);
        
        if(!$tranx->status){
            // there was an error from the API
            
            $response=[
                'status'=>false,
                'message'=>'API returned error: ' . $tranx->message
            ];
        }
        
        if('success' == $tranx->data->status){
            //transaction successfull
            
            $package=Package::where('id',$package_id)->where('status',1)->first();
            
            if($package==null){
                $response=[
                    'status'=>false,
                    'message'=>'Package returned invalid',
                ];
            }else{
                
                $discount_amount=$package->price*($package->discount/100);
                
                $current_amount=$package->price-$discount_amount;
                
                $subscription=new Subscription;
                Subscription::where('user_id',$user_id)->update(['is_active'=>false]);
                
                $subscription->user_id=$user_id;
                $subscription->package_id=$package->id;
                $subscription->price_paid=$tranx->data->amount/100;
                $subscription->reference_no=$tranx->data->reference;
                $subscription->mode_of_payment=$tranx->data->channel;
                $subscription->is_active=true;
                $subscription->expires_at=Carbon::now()->addMonths($package->duration);
                $subscription->save();

                $response=[
                    'status'=>true,
                    'message'=>'Transaction successful',
                    'data'=>[
                        'package'=>$package->title,
                        'price_paid'=>number_format($subscription->price_paid),
                        'reference_no'=>$subscription->reference_no,
                        'is_active'=>$subscription->is_active,
                        'duration'=>$package->duration." Months",
                        'expires_at'=>$subscription->expires_at->format('F d, Y'),
                    ],
                ];
            }
            
                
        }else{
            // transaction was unsuccessful...
            $response=[
                'status'=>false,
                'message'=>"Transaction Unsuccessful"
            ];
        }
        /*end payment online*/
        
        if($response['status']==false){
            return response()->json($response, 400);
        }else{
            return $response;
        }
    }
}
