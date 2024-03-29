<?php

namespace src\controllers;

use src\app\Request;
use src\app\Response;
use src\app\Stdio;

class ExampleController extends Controller
{
    public function exampleHttp(Request $request, string $appName, string $appVersion, ?string $sessionId): void
    {
        Response::ok([$appName, $appVersion, $sessionId]);
    }

    public function exampleCli(array $words, array $flags): void
    {
        Stdio::printLn(json_encode([
            'words' => $words,
            'flags' => $flags
        ]));
    }
}
