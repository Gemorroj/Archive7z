<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('fixtures')
    ->in(['src', 'tests']);

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP80Migration' => true,
        '@PHP80Migration:risky' => true,

        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'compact_nullable_typehint' => true,
        'linebreak_after_opening_tag' => true,
        'native_function_invocation' => ['scope' => 'all', 'include' => ['@all']],
        'native_constant_invocation' => true,
        'no_null_property_initialization' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'phpdoc_order' => true,
        'strict_comparison' => true,
        'combine_nested_dirname' => true,
        'use_arrow_functions' => false,
        'declare_strict_types' => false,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
