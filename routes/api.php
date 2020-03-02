<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

//user authentication and sign up
Route::post('login','UserController@login');
Route::post('password','UserController@password');
Route::post('register','UserController@register');
//user logout
Route::post('logout','UserController@logout');

//upload id card, photo, inspection reports & any docuemnt 
Route::post('upload','UserController@upload');

//update profile and settings
Route::post('change-password','UserController@update_password');
Route::post('update-profile','UserController@update_profile');
Route::post('trusted-contact','UserController@trusted_contact');

//Get user profile (rider / driver)
Route::any('profile','UserController@profile');

//trip request
Route::post('request-trip','TripController@create_request');
Route::post('cancel-request','TripController@cancel_request');

//get trip history
Route::any('trip-history','TripController@history');

//rating and review
Route::post('trip-rating','TripController@rating');

/**driver endpoints**/
//get driver trip history
Route::get('driver-trip-history','DriverController@history');

//driver accept trip request
Route::post('accept-request','DriverController@accept_request');

//start trip
Route::post('start-trip','DriverController@start_trip');

//drop off / remove rider
Route::post('drop-off','DriverController@drop_off');

//add new vehicle
Route::post('add-vehicle','VehicleController@store');

//delete vehicle
Route::post('delete-vehicle','VehicleController@delete_vehicle');

//list registered vehicles
Route::any('vehicles','VehicleController@list_vehicles');

//set default vehicle
Route::post('set-default-vehicle','VehicleController@set_default');

//transaction & subscription apis

//get subscription packages
Route::get('packages','TransactionController@subscriptions');

//verify transaction
Route::any('verify-transaction','TransactionController@verify');

//test apis
Route::any('find-requests','TripController@find_requests');

Route::any('get-otp','UserController@getOtp');

Route::any('test-otp',function(){
    
    
    $password=rand(100000,999999);
    
    return implode('-',str_split($password,3));
    
    $endpoint = 'https://rest.nexmo.com/sms/json';
    $otpArray = array(
        "api_key"=>"f1c0068b",
        "api_secret"=>"I9c9R0WZfZkGBXaU",
        "to"=>"+2348084049966",
        "from"=>"NEXMO",
        "text"=>"Use 087-654 to verify your number"
    );
    $params = http_build_query($otpArray);
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL,$endpoint); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); 
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POST, 1); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params); 
//    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$jwt,'Content-Type: application/json','Accept: application/json'));
    $response = curl_exec($ch); 
    curl_close($ch); 
    
    $response=json_decode($response);
    
    if($response->messages[0]->status==0){
        return TRUE;
    }else{
        return FALSE;
    }
    
//    return uniqid();
//    
//    $password=implode('-',str_split(rand(100000,999999),2));
//    return $password;
});
