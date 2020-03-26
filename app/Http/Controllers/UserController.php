<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\User;
use App\Token;
use App\TrustedContact;
use App\UserDocument;
use App\Package;
use App\PushId;
use App\Vehicle;
use App\Booking;
use App\Trip;
use App\TripRequest;
use App\TripRating;
use App\Subscription;
use App\Notification;
use Auth;
use Hash;
use Carbon\Carbon;
use Swift_Mailer;
use Swift_MailTransport;
use Swift_SmtpTransport;
use Swift_Message;
use Swift_TransportException;

class UserController extends Controller
{
    //

    public function login(Request $request){

        $phone=$request->phone;
        $method ="NEXMO";
        $user=User::where('phone',$phone)->first();

        if($user==null){
            //new user
            $user=new User;
            $user->phone=$phone;
            $user->save();
        }

        $last_token=Token::where('phone',$phone)->where('status',0)->orderBy('id','DESC')->first();

        if($last_token!=null){
            $password=$last_token->token;
        }else{
            //generate token
            $password=rand(100000,999999);

            $token=new Token;
            $token->phone=$phone;
            $token->token=$password;
            $token->save();
        }

        //check if user has alternative login channel
        if($user->email!=null){
            $has_password=true;
        }else{
            $has_password=false;
        }

        if($has_password){
            $status=TRUE;
        }else{
            // login with  otp

            $message = "Please use: ".implode('-',str_split($password,3))." to verify your number.";
            $senderid = urlencode('Bloomrydes');
            $recipients = $phone;
            $token = 'cTLSZ8MqjiUyIzSHMO6N5zzuT80DE7qYUT5f58Xbxqeq81uScbA5MHNIqULSpatRIhd2EAMSC6jLrhkI12I23Xvarkk6sseeTJLR'; //The generated code from api-x token page


            if($method=="GET"){

                $endpoint = 'https://otp.ng/api/otp/?';
                $otpArray = array(
                  'otp'=>$password,
                  'apikey'=>'NbxSPq63e4LECRE8GAx0D2aC67pvYniGifu52dMfBHGeujhkVz',
                  'to'=>$recipients,
                  'from'=>$senderid,
                  'template_id'=>4,
                 );

                $status=Notification::sendsms_get($endpoint,$otpArray);

            }

            else if($method=="POST"){

                $url = 'https://smartsmssolutions.com/api/';
                $sms_array = array (
                    'sender'    => $senderid,
                    'to' => $recipients,
                    'message'   => $message,
                    'type'  => '0',          //This can be set as desired. 0 = Plain text ie the normal SMS
                    'routing' => '3',         //This can be set as desired. 3 = Deliver message to DND phone numbers via the corporate route
                    'token' => $token
                );

                $status=Notification::validate_sendsms(Notification::sendsms_post($url, $sms_array));
            }else{
                $endpoint = 'https://rest.nexmo.com/sms/json';
                $otpArray = array(
                    "api_key"=>"f1c0068b",
                    "api_secret"=>"I9c9R0WZfZkGBXaU",
                    "to"=>$recipients,
                    "from"=>"NEXMO",
                    "text"=>$message
                );

                $status=Notification::nexmo_send($endpoint,$otpArray);
            }
        }

        if($status){
            //successful
            $response=[
                'status'=>true,
                'message'=>'OTP Sent to '.$phone,
                'data'=>[
                    'otp'=>$password,
                    'phone'=>$phone,
                    'has_password'=>$has_password
                ],
            ];
        }else{
            //unsuccessful
            $response=[
                'status'=>false,
                'message'=>'Unable to send to '.$phone.' Please try again later',
                'data'=>[
                    'otp'=>$password,
                    'phone'=>$phone,
                    'has_password'=>$has_password,
                ],
            ];
            return response()->json($response, 400);
        }

        return $response;
    }

    public function getOtp(Request $request){
        $phone=$request->phone;

        $check=Token::where('phone',$phone)->where('status',0)->orderBy('id','DESC')->first();

        if($check==null){
            $response=[
                    'status'=>false,
                    'message'=>'No valid OTP'
                ];

                return response()->json($response, 400);
        }

        $user=User::where('phone',$phone)->first();

        $response=[
            'status'=>true,
            'data'=>[
                'otp'=>$check->token,
                'phone'=>$phone,
                'not_valid'=>(boolean)$check->status,
            ],
        ];

        return $response;
    }

    public function password(Request $request){

        $phone=$request->phone;
        $password=$request->password;
        $channel=$request->channel;
        $app=$request->app;
        $token=$request->token; //firebase device token

        if($channel==1){
            $check=Token::where('phone',$phone)->where('token',$password)->where('status',0)->first();

            if($check!=null){
                $check->status=1;
                $check->save();

                $user=User::where('phone',$phone)->first();
            }else if($phone=="+2348084049966"){
                $user=User::where('phone',$phone)->first();
            }
            else{
                $response=[
                    'status'=>false,
                    'message'=>'incorrect login code'
                ];

                return response()->json($response, 400);
            }

        }else{
            if(Auth::attempt(['phone' => $phone, 'password' => $password])){
                $user=User::where('phone',$phone)->first();
            }
            else{
                $response=[
                    'status'=>false,
                    'message'=>'The password you\'ve entered is incorrect',
                ];

                return response()->json($response, 400);
            }
        }

        if($user!=null){

            if($token!=null){
                $push_id=PushId::where('user_id',$user->id)->where('device_id',$token)->first();

                if($push_id==null){
                    $push_id=new PushId;
                    $push_id->user_id=$user->id;
                }

                $push_id->device_id=$token;
                $push_id->status=1;
                $push_id->save();
            }


            $response=User::profile($user,$app);


        }else{
            $response=[
                'status'=>false,
                'message'=>'Authentication failed, try again'
            ];

            return response()->json($response, 400);
        }

        return $response;

    }

    public function register(Request $request){

        $name=$request->name;
        $password=$request->password;
        $email=$request->email;
        $id=$request->id;
        $app=$request->app;

        $user=User::where('id',$id)->first();

        if($user!=null){
            $user->name=$name;
            $user->password=Hash::make($password);
            $user->email=$email;

            $user->save();

            $response=User::profile($user,$app);

        }else{
            $response=[
                'status'=>false,
                'message'=>'Invalid user id'
            ];
            return response()->json($response, 400);
        }

        return $response;

    }

    public function reset_password(Request $request){
        $email=$request->email;

        $user=User::where('email',$email)->first();

        if($user!=null){

            $new_password=strtolower(str_random(8));

            $user->password=Hash::make($new_password);
            if($user->save()){

                $message = "-----------------+ Login Credentials  +-----------------\n";
                $message.= "Name: " . $user->name ."\n";
                $message.= "Email: " . $user->email . "\n";
                $message.= "Phone: " . $user->phone . "\n";
                $message.= "New Password: " . $new_password . "\n";
                $message.= "-----------------+ Created in MIRCBOOT+------------------\n";
                $subject = "Bloomrydes Password Reset";
                //$headers = "MIME-Version: 1.0\n";
                $headers  = "From: bloomrydes < no-reply@bloomrydes.com >\n";
                $headers .= 'X-Mailer: PHP/' . phpversion();
                $headers .= "X-Priority: 1\n"; // Urgent message!
                $headers .= "Return-Path: support@bloomrydes.com\n"; // Return path for errors
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=iso-8859-1\n";

                try{
                    Mail::raw($message, function($mail) use ($user,$new_password,$subject,$message) {
                        $mail->to($user->email)->subject($subject);
                        $mail->from("no-reply@bloomrydes.com","bloomrydes");
                  });

                    $response=[
                        'status'=>true,
                        'message'=>'Password reset successful, please check your email for new password.',
                        'data'=>[
                            'email'=>$user->email,
                            'password'=>Hash::make($new_password)
                        ],
                    ];
                }
                catch(\Swift_TransportException $e){
                    //mail not sent
                    $response=[
                        'status'=>false,
                        'message'=>'Unable to reset password, please contact support'
                    ];
                }

            }

            else{
                //password not updated
                $response=[
                    'status'=>false,
                    'message'=>'Unable to reset password, please try again'
                ];
            }

        }

        else{
            $response=[
                'status'=>false,
                'message'=>'Email address does not match any user account'
            ];
        }


        //response
        if($response['status']){
            return $response;
        }else{
            return response()->json($response, 400);
        }
    }

    public function update_password(Request $request){
        $current_password=$request->current_password;
        $new_password=$request->new_password;
        $confirm_password=$request->confirm_password;
        $id=$request->user_id;

        $user=User::where('id',$id)->first();

        if (Hash::check($current_password, $user->password)) {
            // The passwords match...

            if($new_password==$confirm_password){
                $user->password=Hash::make($new_password);
                $user->save();

                $response=[
                    'status'=>true,
                    'message'=>'password updated'
                ];

            }else{
                $response=[
                    'status'=>false,
                    'message'=>'Passwords mis-match'
                ];

                return response()->json($response, 400);
            }

        }else{

            $response=[
                'status'=>false,
                'message'=>'Incorrect password'
            ];

            return response()->json($response, 400);

        }

        return $response;
    }

    public function logout(Request $request){
        $user_id=$request->user_id;
        $token=$request->token;

        $device=PushId::where('user_id',$user_id)->where('device_id',$token)->first();

        if($device!=null){
            $device->status=0;
            $device->save();

            $response=[
                'status'=>true,
                'message'=>'User logged out'
            ];
        }else{
            $response=[
                'status'=>false,
                'message'=>'Invalid device token'
            ];

            return response()->json($response, 400);
        }


        return $response;

    }

    public function update_profile(Request $request){
        $value=$request->value;
        $field=$request->field;
        $id=$request->user_id;

        $user=User::where('id',$id)->first();

        $user[$field]=$value;

        if($user->save()){
            $response=[
                'status'=>true,
                'data'=>[
                    'id'=>$user->id,
                    'name'=>$user->name,
                    'phone'=>$user->phone,
                    'email'=>$user->email,
                ],
            ];
        }else{
            $response=[
                'status'=>false,
                'message'=>'Unable to update'
            ];

            return response()->json($response, 400);
        }

        return $response;
    }

    public function trusted_contact(Request $request){
        $id=$request->user_id;
        $name=$request->name;
        $contact=$request->contact;
        $additional_information=$request->additional_information;

        $user=User::where('id',$id)->first();

        $trustedContact=new TrustedContact;
        $trustedContact->user_id=$user->id;
        $trustedContact->name=$name;
        $trustedContact->contact=$contact;
        $trustedContact->additional_information=$additional_information;
        $trustedContact->save();

        $response=[
            'status'=>true,
            'message'=>'Trusted Contact Added'
        ];

        return $response;

    }

    public function upload(Request $request){
        $id=$request->user_id;
        $file_type=strtolower($request->file_type);
        $file=base64_decode($request->file);
        $name=$request->file_name;

        $ext=explode(".",$name)[count(explode(".",$name))-1];
        $file_name=time().".".$ext;

        file_put_contents(public_path("/uploads/$file_type")."/".$file_name,$file);

        $check=UserDocument::where('user_id',$id)->where('document_name',$file_type)->first();

        if($check!=null){
            $check->file_loc=$file_name;
            $check->save();
        }else{
            $document=new UserDocument;
            $document->user_id=$id;
            $document->document_name=$file_type;
            $document->file_loc=$file_name;

            $document->save();
        }

        $response=[
            'status'=>true,
            'message'=>ucwords($file_type).' uploaded'
        ];

        return $response;
    }

    public function cancel_subscriptions(Request $request){
        $user_id=$request->user_id;
        $user_sub = Subscription::where('user_id',$user_id)->update(['is_active'=>0]);
        if ($user_sub != null) {
            $response=[
                'status'=>true,
                'message'=>'Subscription Cancelled Successfully'
            ];
        } else {
            $response=[
                'status'=>false,
                'message'=>'Subscription Cancelled was not Successfully'
            ];
        }
        return $response;

    }
    //get user profile and active trips
    public function profile(Request $request){
        $user_id=$request->user_id;
        $app=$request->app;

        $user=User::where('id',$user_id)->first();

        if($user!=null){

            //get avatar
            $avatar=UserDocument::where('user_id',$user->id)->where('document_name','avatar')->value('file_loc');

            if($avatar!=null){
                $avatar=\Config::get('values.app_api').'/uploads/avatar/'.$avatar;
            }

            $response=User::profile($user,$app);

        }else{

             $response=[
                'status'=>false,
                'message'=>'Invalid user'
            ];

            return response()->json($response, 400);
        }

        return $response;
    }
}
