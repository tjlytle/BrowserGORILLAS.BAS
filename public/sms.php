<?php
$config = include __DIR__ . '/../init.php';
$pubnub = new \Pubnub\Pubnub($config['pubnub']);
$channel = $config['channel'];
$nexmo = new \Nexmo\Sms($config['nexmo']);

$request = array_merge($_GET, $_POST);

if(!isset($request['msisdn']) OR !isset($request['text']) OR !isset($request['to'])){
    error_log('not an inbound message');
    return;
}

$reply = function($text) use ($request, $nexmo) {
    error_log($text);
    $nexmo->send([
        'to' => $request['msisdn'],
        'from' => $request['to'],
        'text' => $text
    ]);
};

if(!preg_match('#(\d+)\D+(\d+)#', $request['text'], $match)){
    return $reply('Please reply with an angle (1-90) and a velocity (1-100)');
}

if($match[1] < 1 or $match[1] > 90) {
    return $reply('Angle must be between 1 and 90');
}

if($match[1] < 1 or $match[2] > 100) {
    return $reply('Velocity must be between 1 and 90');
}

$pubnub->publish($channel, [
    'from' => md5($request['msisdn']),
    'angle' => $match[1],
    'velocity' => $match[2]
]);


