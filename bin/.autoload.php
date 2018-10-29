<?php

(function (): void {
    foreach (
        [
            // project/vendor/%developer/%package/bin/%program
            __DIR__ . '/../../../autoload.php',
            // this package is root
            __DIR__ . '/../vendor/autoload.php',
        ]
        as $file
    ) {
        if (file_exists($file)) {
            require $file;
            return;
        }
    }

    fwrite(STDERR, 'E! Could not find autoload.php provided by composer' . PHP_EOL);
    die;
})();
