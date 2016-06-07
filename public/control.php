<?php
$config = include __DIR__ . '/../init.php';
$pubnub = new \Pubnub\Pubnub($config['pubnub']);
$channel = $config['channel'];

$nexmo = new \Nexmo\Client(
    new \Nexmo\Client\Credentials\Basic($config['nexmo']['api_key'], $config['nexmo']['api_secret']),
    [
        'url' => [
            'https://api.nexmo.com/' => 'https://api-nexmo-com-ohyt2ctr9l0z.runscope.net/',
            'https://rest.nexmo.com/' => 'https://rest-nexmo-com-ohyt2ctr9l0z.runscope.net/'
        ]
    ]
);

$inbound = \Nexmo\Message\InboundMessage::createFromGlobals();

$game = new \Nexmo\Gorillas\Game($config['dsn'], $nexmo, $pubnub, $config['channel']);

if($inbound->isValid()){
    try {
        error_log('inbound SMS');
        $game->process($inbound);
        return;
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
} elseif(isset($_POST['action'])) {
    error_log('ajax message');
    header('Content-Type: application/json');
    echo json_encode($game->control($_POST));
}

