<?php

use src\controllers\FileController;
use src\filesystem\Filesystem;
use src\middleware\CORSMiddleware;
use src\router\Route;

if ($_ENV['FILES_ENABLED']) {
    $files = Filesystem::scandirRecursive(
        getFileDir(),
        false
    );

    foreach ($files as $file) {
        $route = Route::create()
            ->setPath(
                sprintf(
                    '/%s',
                    appendToBaseUrl(FILEDIR, $file)
                )
            )
            ->setVar(
                'filePath',
                appendToBaseDir(
                    getFileDir(),
                    $file
                )
            )
            ->setCallable([FileController::class, 'serveFile'])
            ->setMethods(['GET']);

        if ($_ENV['FILES_CORS']) {
            $route->setMiddleware([
                [CORSMiddleware::class, 'execute']
            ]);
        }

        if ($_ENV['FILES_HIDE_ROUTE']) {
            $route->setHide();
        }

        $routes[] = $route;
    }
}