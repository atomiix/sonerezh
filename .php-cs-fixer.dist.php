<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/config',
        __DIR__ . '/webroot'
    ]);

$config = new PhpCsFixer\Config();
return $config->setRules([
    '@PSR12' => true,
    '@PHP80Migration' => true,
    '@PHP80Migration:risky' => true,
    'strict_param' => true,
    'array_syntax' => ['syntax' => 'short'],
])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
