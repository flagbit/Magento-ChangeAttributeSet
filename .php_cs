<?php

$finder = \Symfony\CS\Finder\DefaultFinder::create()
    ->in(__DIR__ . '/src')
;

return \Symfony\CS\Config\Config::create()
    ->level(\Symfony\CS\FixerInterface::PSR1_LEVEL | \Symfony\CS\FixerInterface::PSR2_LEVEL)
    ->fixers(
        array('concat_with_spaces', 'extra_empty_lines', 'function_typehint_space', 'include', 'join_function',
              'multiline_array_trailing_comma', 'new_with_braces', 'no_blank_lines_after_class_opening',
              'no_empty_lines_after_phpdocs', 'phpdoc_indent', 'phpdoc_no_access', 'phpdoc_no_empty_return',
              'phpdoc_scalar', 'phpdoc_type_to_var', 'single_array_no_trailing_comma', 'single_quote',
              'standardize_not_equal')
    )
    ->finder($finder)
;
