<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

Tester\Environment::setup();

/** Temporary directory unique to the running test file. */
function tempDir(): string
{
	$dir = sys_get_temp_dir() . '/nette-sso-tests/' . getmypid() . '-' . bin2hex(random_bytes(4));
	@mkdir($dir, 0777, true);
	return $dir;
}
