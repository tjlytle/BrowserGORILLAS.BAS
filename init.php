<?php
require_once __DIR__ . '/vendor/autoload.php';
$config = include __DIR__ . '/config.php';

$ni = new Nexmo\Insight($config['nexmo']);
$data = $ni->basic([
   'number' => $config['nexmo']['from']
]);

$config['number'] = $data['national_format_number'];

return $config;