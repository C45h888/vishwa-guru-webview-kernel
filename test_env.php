<?php
require "/app/vendor/autoload.php";
$app = require "/app/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Illuminate\Http\Request::create("/test", "GET"));
echo $response->getContent();
