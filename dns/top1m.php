<?php

$file = $argv[1];


$fp = fopen($file, 'r');

while(($line = fgets($fp, 256)) !== false) {
    $domain = explode(',', $line);
    $domain = $domain[1];

    if (strpos($domain, ".jp\n") === false) {
        continue;
    }

    echo trim($domain). "\n";
}
