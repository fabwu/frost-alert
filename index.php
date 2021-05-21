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

// get geolocation id
$params = http_build_query([
   'latitude' => $config['latitude'],
   'longitude' => $config['longitude'],
]);
curl_setopt($ch, CURLOPT_URL, BASE_URL . '/srf-meteo/geolocations?' . $params);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);

$result = curl_exec($ch);

if(curl_error($ch)) {
    throw new Exception('Getting geolocation failed - ' . curl_error($ch));
}

$geolocations = json_decode($result, true);

$id = $geolocations[0]['id'];

// get forecast
curl_setopt($ch, CURLOPT_URL, BASE_URL . '/srf-meteo/forecast/' . $id);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);

$result = curl_exec($ch);

if(curl_error($ch)) {
    throw new Exception('Getting forecast failed - ' . curl_error($ch));
}

curl_close($ch);

$forecast = json_decode($result, true);

$tomorrow = 1;
$min_temp_label = 'TN_C';
$min_temp = $forecast['forecast']['day'][$tomorrow][$min_temp_label];

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
