<?php

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/public', __DIR__.'/bin'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2x0' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => true,
        'ordered_imports' => true,
        'single_quote' => true,
    ])
    ->setFinder($finder);
