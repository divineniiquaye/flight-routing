<?php

$paths = [
    '../../../vendor/autoload.php',
    __DIR__. '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__. '/../../vendor/autoload.php',
];


foreach ($paths as $vendor) {
    if (file_exists($vendor)) {
        return require $vendor;
    }
}
