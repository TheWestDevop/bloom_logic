<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');



Route::any('socket-send',function(){
    
//    return response()->json(['error' => 'Bad request.'], 400);
    
    $url1="https://fcm.googleapis.com/fcm/send";
    $url2="https://fcm.googleapis.com/v1/projects/bloomrydes-12e09/messages:send HTTP/1.1";
    $auth1="key=AAAAKg_2CVg:APA91bHy9Kkb3k_cOxa2f4BS1oZcAkoMBKpn0AGI9su1nDMtZFvwIUDBCZvXKLOYChWFcagQt9x9FUvk5TkVehvKlOkGhEWiEiLVfhUtHrHr1qf68-y7z48iVvz4YEckVJHHFEQctKpF";
    $auth2="Bearer AAAAKg_2CVg:APA91bHy9Kkb3k_cOxa2f4BS1oZcAkoMBKpn0AGI9su1nDMtZFvwIUDBCZvXKLOYChWFcagQt9x9FUvk5TkVehvKlOkGhEWiEiLVfhUtHrHr1qf68-y7z48iVvz4YEckVJHHFEQctKpF";
    
    $query = array(
        "to" => "topics/news",
        "data"=>[
            "story_id"=>"story_12345"
        ]
    );
    
    $test2='{
      "to": "dvVzqu_Xj2o:APA91bF8GKPU2NQEhhWAWbP5OzfbizpyQsjKjD1NWahVjWm95WphWHH49vB5ezYhrqC9wQvAPWK2eL0WclwXP9uEZjZczOHU1_4u-C4jKnN0wwQ1XaL-hUUXwCGAEwL-m2AImHhCrnr3",
      "notification": {
        "title": "Trip Request Accepted",
        "body": "Your driver is on the way."
      },
      "data": {
        "response": {
            "action": "trip.accept"
            "status": true,
            "data": {
                "vehicle_name": "Toyota Corrolla",
                "license_plate": "ABC 123 DE",
                "driver": "Peter P-square"
            }
        }
      }
    }';
    $data_string = json_encode($query);
    
//    return $ch_result;
    
    return App\Notification::send_notification($test2);
    
});