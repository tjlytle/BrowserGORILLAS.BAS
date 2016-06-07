<?php
require_once __DIR__ . '/vendor/autoload.php';
$config = include __DIR__ . '/config.php';

//$ni = new Nexmo\Insight($config['nexmo']);
//$data = $ni->basic([
//   'number' => $config['nexmo']['from']
//]);

if(isset($_GET['number'])){
    $config['nexmo']['from'] = $_GET['number'];
}

$config['number'] = $config['nexmo']['from'];

return $config;