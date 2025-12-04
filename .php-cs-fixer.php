<?php

declare(strict_types=1);

use Ergebnis\PhpCsFixer\Config;
use DiabloMedia\PhpCsFixer\Config\RuleSet\Php81;

$config = Config\Factory::fromRuleSet(Php81::create());
$config->setCacheFile(__DIR__ . '/.php_cs.cache');
$config->getFinder()
    ->exclude('vendor')
    ->files()
    ->in(__DIR__)
;

return $config;
