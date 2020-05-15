<?php

$paths = [
    dirname(__DIR__, 4) . '/storage/dev-autoload.php',
    dirname(__DIR__, 3) . '/autoload.php',
    dirname(__DIR__) . '/vendor/autoload.php',
];

return require current(array_filter($paths, 'file_exists'));
