<?php

declare(strict_types=1);

// Inside a Contao project (vendor/webexmachina/contao-audiotracks), use the
// project autoloader
foreach ([__DIR__.'/../vendor/autoload.php', __DIR__.'/../../../autoload.php'] as $autoload) {
    if (is_file($autoload)) {
        require $autoload;

        break;
    }
}

// The bundle may not be in the project autoloader yet (standalone checkout)
foreach (['WEM\\AudioTracksBundle\\Tests\\' => __DIR__.'/', 'WEM\\AudioTracksBundle\\' => __DIR__.'/../src/'] as $prefix => $dir) {
    spl_autoload_register(
        static function (string $class) use ($prefix, $dir): void {
            if (str_starts_with($class, $prefix)) {
                $file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

                if (is_file($file)) {
                    require $file;
                }
            }
        },
    );
}
