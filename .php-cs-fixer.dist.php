<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
	->in([__DIR__ . '/inc', __DIR__ . '/tests'])
	->append([__DIR__ . '/functions.php']);

return (new PhpCsFixer\Config())
	->setRiskyAllowed(true)
	->setRules([
		'@PSR12'                 => true,
		'@PSR12:risky'           => true,
		'declare_strict_types'   => true,
		'array_syntax'           => ['syntax' => 'short'],
		'no_unused_imports'      => true,
		'ordered_imports'        => [
			'sort_algorithm' => 'alpha',
			'imports_order'  => ['class', 'function', 'const'],
		],
		'native_function_invocation' => [
			'include' => ['@compiler_optimized'],
			'scope'   => 'namespaced',
		],
		'global_namespace_import' => [
			'import_classes'   => true,
			'import_functions' => false,
			'import_constants' => false,
		],
		'statement_indentation' => false,
	])
	->setFinder($finder)
	->setCacheFile(__DIR__ . '/build/.php-cs-fixer.cache');
