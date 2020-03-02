<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    //
    
    public static function send_notification($body){
        
        $url1="https://fcm.googleapis.com/fcm/send";
        $auth1="key=AAAAKg_2CVg:APA91bHy9Kkb3k_cOxa2f4BS1oZcAkoMBKpn0AGI9su1nDMtZFvwIUDBCZvXKLOYChWFcagQt9x9FUvk5TkVehvKlOkGhEWiEiLVfhUtHrHr1qf68-y7z48iVvz4YEckVJHHFEQctKpF";
//        $url1=\Config::get('values.firebase_api');
//        $auth1=\Config::get('values.firebase_key');
        
        $ch = curl_init($url1); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: '.$auth1,'Content-Type: application/json'));
        $ch_result = curl_exec($ch);
        
        $ch_result=json_decode($ch_result);
        
        if($ch_result->success>0){
            return [
                'status'=>true,
                'message'=>'Message sent'
            ];
        }else{
            return [
                'status'=>false,
                'message'=>'Unable to send'
            ];
        }
        
    }
    
    public static function sendsms_post ($url, array $params) {
        $params = http_build_query($params);
        $ch = curl_init(); 

        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);   

        $output=curl_exec($ch);

        curl_close($ch);
        return $output;        
    }
    
    public static function sendsms_get($endpoint, array $otpArray){
        $params = http_build_query($otpArray);
        $send_otp = $endpoint.$params;
    
        $response = json_decode(file_get_contents($send_otp));
        
        if($response->success==true){
            return TRUE;
        }else{
            return FALSE;
        }
    }
    
    public static function nexmo_send($endpoint, array $otpArray){
        $params = http_build_query($otpArray);
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL,$endpoint); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); 
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POST, 1); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $response = curl_exec($ch); 
        curl_close($ch); 

        $response=json_decode($response);

        if($response->messages[0]->status==0){
            return TRUE;
        }else{
            return FALSE;
        }
    }
    
    //VALIDATION: If you need to validate if the message is send successfully you call the function below.
    public static function validate_sendsms ($response) {
        $validate = explode('||', $response);
        if ($validate[0] == '1000') {
            return TRUE;
            //return custom response here instead.
        } else {
            return FALSE;
            //return custom response here instead.
        }
    }
}
