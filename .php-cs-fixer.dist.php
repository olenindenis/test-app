<?php

$finder = new PhpCsFixer\Finder()
    ->in([__DIR__ . '/app', __DIR__ . '/config', __DIR__ . '/database', __DIR__ . '/routes', __DIR__ . '/tests'])
    ->append([__DIR__ . '/bootstrap/app.php', __DIR__ . '/.php-cs-fixer.dist.php']);

return new PhpCsFixer\Config()
    ->setRules([
        '@PER-CS3x0' => true,
        '@PHP8x4Migration' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const']],
        'no_unused_imports' => true,
        'single_quote' => true,
        'no_extra_blank_lines' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters', 'match']],
    ])
    ->setFinder($finder);
