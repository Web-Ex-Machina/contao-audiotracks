<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

/* Usage
 * vendor/bin/rector process src --dry-run //permet de faire un test
 * vendor/bin/rector process src  //re-ecrit les fichier
 * Tu peux faire src/dossier ou src-dossier/monfichier.php pour réduire sa porté
 * attention il peut faire de la merde, toujours relire ce qu’il fait mais
 * 95% du temps c’est ok.
 *
*/
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withSets([
        SetList::CODING_STYLE,
        SetList::CODE_QUALITY,
        LevelSetList::UP_TO_PHP_80,
        LevelSetList::UP_TO_PHP_81,
        LevelSetList::UP_TO_PHP_82,
        SetList::DEAD_CODE
    ]);