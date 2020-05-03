<?php

$config = include('config.php'); 
$current_month = intval((new DateTimeImmutable('now'))->format('m'));

// Don't send frost alert from October to March
if($current_month <= 3 || $current_month >= 10) {
    die();
}

const BASE_URL = 'https://api.srgssr.ch';

$ch = curl_init();

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// get access token
curl_setopt($ch, CURLOPT_URL, BASE_URL . '/oauth/v1/accesstoken?grant_type=client_credentials');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Length: 0']);
curl_setopt($ch, CURLOPT_USERPWD, $config['srf_key']. ":" . $config['srf_secret']);  

$result = curl_exec($ch);

if(curl_error($ch)) {
    throw new Exception('Getting access token failed - ' . curl_error($ch));
}

$access_token = json_decode($result, true)['access_token'];

if(!$access_token) {
    throw new Exception('Invalid access token');
}

// get forecast
$params = http_build_query([
   'latitude' => $config['latitude'],
   'longitude' => $config['longitude'],
]);
curl_setopt($ch, CURLOPT_URL, BASE_URL . '/forecasts/v1.0/weather/7day?' . $params);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);

$result = curl_exec($ch);

if(curl_error($ch)) {
    throw new Exception('Getting forecast failed - ' . curl_error($ch));
}

$forecast = json_decode($result, true);

curl_close($ch);

// parse forecast
$next_day = (new DateTimeImmutable())
                ->add(DateInterval::createFromDateString('1 day'))
                ->format('Y-m-d');

$min_temp = NULL;

foreach ($forecast['7days'] as $day) {
    if($day['date'] == $next_day) {
        foreach ($day['values'] as $value) {
            if(isset($value['ttn'])) {
                $min_temp = floatval($value['ttn']);
                break;
            }
        }
        break;
    }
}

if($min_temp == NULL) {
    throw new Exception('Getting forecast failed');
}

// alert if frost
echo $min_temp . '°C: ';
if($min_temp < $config['frost_threshold_deg']) {
    echo  'FROST ALERT!';
	$to = $config['email_to'];
	$subject = 'Frost Alert!';
	$message = "Tomorrow will be " . $min_temp . "°C\r\n\r\nSave your plants!";
	$headers = [
    	'From' => $config['email_from'],
    	'X-Mailer' => 'PHP/' . phpversion()
	];

	$sent_success = mail($to, $subject, $message, $headers);
	
	if(!$sent_success) {
		throw new Exception('Sending mail failed');
	}
} else {
    echo 'No frost tomorrow...';
}
