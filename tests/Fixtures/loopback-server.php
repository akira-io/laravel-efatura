<?php

declare(strict_types=1);

[, $mode] = $argv;

$server = stream_socket_server('tcp://127.0.0.1:0') ?: exit(1);
$name   = (string) stream_socket_get_name($server, false);

fwrite(STDOUT, substr($name, (int) strrpos($name, ':') + 1) . PHP_EOL);

$client = stream_socket_accept($server, 30) ?: exit(1);
$head   = '';
while (! str_contains($head, "\r\n\r\n") && ! feof($client)) {
    $head .= (string) fread($client, 8192);
}

[$headers, $body] = explode("\r\n\r\n", $head, 2);
$length           = preg_match('/^content-length:\s*(\d+)/mi', $headers, $match) === 1 ? (int) $match[1] : 0;
while (strlen($body) < $length && ! feof($client)) {
    $body .= (string) fread($client, 8192);
}

fwrite(STDOUT, 'received ' . strlen($body) . PHP_EOL);

if ($mode === 'oversized') {
    fwrite($client, "HTTP/1.1 200 OK\r\nContent-Type: application/xml\r\nConnection: close\r\n\r\n");
    for ($chunk = 0; $chunk < 6; $chunk++) {
        fwrite($client, str_repeat('a', 1024 * 1024));
    }
}

if ($mode === 'stall') {
    $waiting = [$client];
    $none    = null;
    stream_select($waiting, $none, $none, 30);
}

fclose($client);
