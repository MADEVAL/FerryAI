<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/packages')
    ->exclude('vendor')
    ->exclude('tests/fixtures')
    ->notPath('#/FFI/|/Runtime/#')
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        '@PER-CS2.0:risky' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'single_quote' => true,
        'no_extra_blank_lines' => true,
        'phpdoc_align' => true,
        'phpdoc_order' => true,
        'phpdoc_trim' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try', 'if', 'for', 'foreach', 'while', 'do', 'switch'],
        ],
        'declare_equal_normalize' => ['space' => 'none'],
        'dir_constant' => true,
        'is_null' => true,
        'modernize_strpos' => true,
        'no_alias_functions' => true,
        'no_trailing_comma_in_singleline' => true,
    ])
    ->setFinder($finder);
