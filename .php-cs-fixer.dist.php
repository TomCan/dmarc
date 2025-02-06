<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'nullable_type_declaration_for_default_null_value' => false,
    ])
    ->setFinder($finder)
;
