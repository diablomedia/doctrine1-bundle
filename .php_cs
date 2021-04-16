<?php

declare(strict_types=1);

use DiabloMedia\PhpCsFixer\Config\RuleSet\Php71;
use Ergebnis\PhpCsFixer\Config;

$config = Config\Factory::fromRuleSet(new Php71());

$config->setCacheFile(__DIR__ . '/.php_cs.cache');
$config->getFinder()
    ->exclude('vendor')
    ->files()
    ->in(__DIR__)
;

return $config;
