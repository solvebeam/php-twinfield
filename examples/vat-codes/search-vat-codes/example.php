<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\VatCode\VatCodeType;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Optional type and name pattern from the command line, e.g. `purchase '*21*'`.
$type = VatCodeType::tryFrom($argv[1] ?? 'sales')
    ?? exit('Type must be sales or purchase.' . PHP_EOL);
$pattern = $argv[2] ?? '*';

$search = $twinfield->vatCodes()->search($pattern)->inName()->vatType($type);

echo $search->count(), ' ', $type->value, ' VAT codes match ', $pattern, PHP_EOL;

foreach ($search->limit(10)->get() as $vatCode) {
    echo $vatCode->code, '  ', $vatCode->name, PHP_EOL;
}
