<?php

// Force test environment - PHPUnit phpunit.xml.dist sets APP_ENV=test,
// but Docker container's APP_ENV=dev would otherwise take precedence.
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
putenv('APP_ENV=test');

require dirname(__DIR__).'/config/bootstrap.php';
