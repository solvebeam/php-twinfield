<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/examples',
        __DIR__ . '/bin',
    ])
    // Uses the lowest supported PHP version from composer.json.
    ->withPhpSets()
    ->withSkip([
        // The SOAP request classes are serialised in the order of their properties. Promoting
        // some of them to the constructor changes that order, and so the XML Twinfield gets.
        ClassPropertyAssignToConstructorPromotionRector::class,
    ]);
