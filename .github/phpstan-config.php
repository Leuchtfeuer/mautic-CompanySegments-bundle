<?php

$localPhpStanConfig = file_get_contents(__DIR__.'/../phpstan.neon');

if (false === $localPhpStanConfig) {
    throw new \RuntimeException('Unable to read phpstan.neon.');
}

$configParts = explode('###', $localPhpStanConfig);

if (3 !== count($configParts)) {
    throw new \RuntimeException('Local phpstan.neon contains invalid data.');
}

$phpStanPatch = $configParts[2];
\assert(is_string($phpStanPatch));
$phpStanPatch = str_replace(
    ['    ', 'path: '],
    ["\t", 'path: plugins/LeuchtfeuerCompanySegmentsBundle/'],
    $phpStanPatch
);

$mauticPhpStanConfig = file_get_contents(__DIR__.'/../../../phpstan.neon');

if (false === $mauticPhpStanConfig) {
    throw new \RuntimeException('Unable to read Mautic phpstan.neon.');
}

$phpStanPatch = \str_replace(
    'ignoreErrors:',
    'ignoreErrors:'.PHP_EOL.$phpStanPatch,
    $mauticPhpStanConfig
);

$result = file_put_contents(__DIR__.'/../../../phpstan.neon', $phpStanPatch);

if (false === $result) {
    throw new \RuntimeException('Unable to write Mautic phpstan.neon.');
}

exit(0);
