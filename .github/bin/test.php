<?php

$response = json_decode(file_get_contents('result.json'), true);

if (! is_array($response)) {
    echo 'Response is not an array';
    exit(1);
}

if ($response['statusCode'] !== 200) {
    echo 'Status code is not 200';
    exit(1);
}

if ($response['statusMessage'] !== 'OK') {
    echo 'Status message is not OK';
    exit(1);
}

if (! isset($response['server_time'])) {
    echo 'Server time is not set';
    exit(1);
}

echo 'All checks passed!';
