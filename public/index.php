<?php

declare(strict_types=1);

/** @var \Ract\Application $app */
$app = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
$app->run();
