<?php

namespace src\app;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use Service;
use src\router\CliRouter;
use src\router\Command;
use Throwable;

class CliApp
{
    protected CliRouter $router;
    protected Service $service;

    public function __construct(array $commands, Service $service, array $args)
    {
        $this->router = new CliRouter($args, $commands);
        $this->service = $service;
    }

    public function execute(): void
    {
        $command = $this->router->getMatch();

        if (!$command) {
            Stdio::commandNotFound($this->router->getCommands());
            return;
        }

        $args = $this->getArgs($command, $this->service);
        if (!$args) {
            return;
        }

        $modifiedArgs = $this->executeMiddleware($args, $command->getMiddleware());
        if (!$modifiedArgs) {
            return;
        }

        $callable = $command->getCallable();
        if (is_array($callable)) {
            $callable = $this->arrayToCallable($callable);

            if (!$callable) {
                return;
            }
        }

        try {
            $callable(...$args);
        } catch (Throwable $error) {
            $this->handleException($error);
        }
    }

    protected function arrayToCallable(array $callable): ?callable
    {
        $className = $callable[0];
        $methodName = $callable[1];
        $controller = new $className();

        if (!method_exists($controller, $methodName)) {
            Stdio::errorFLn('class does not have a public method named: "%s"', $methodName);
            return null;
        }

        return [$controller, $methodName];
    }

    protected function handleException(Throwable $error): void
    {
        Stdio::errorLn('Error:');
        Stdio::errorFLn('Text: %s', $error->getMessage());
        Stdio::errorFLn('Type: %s', $error::class);
        Stdio::errorFLn('File: %s', $error->getFile());
        Stdio::errorFLn('Line: %s', $error->getLine());
        Stdio::errorLn('Trace:');

        $traces = $error->getTrace();
        foreach ($traces as $trace) {
            Stdio::errorFLn(
                "- %s:%s\t%s%s%s",
                $trace['file'] ?? '',
                $trace['line'] ?? '',
                $trace['class'] ?? '',
                $trace['type'] ?? '',
                $trace['function'] ?? '',
            );
        }
    }

    protected function getArgs(Command $command, Service $service): ?array
    {
        $serviceMethods = get_class_methods($service);

        $callable = $command->getCallable();

        if (is_array($callable)) {
            $arguments = $this->getMethodArgs($callable[0], $callable[1]);
        } else {
            $arguments = $this->getFunctionArgs($callable);
        }

        $args = [];
        foreach ($arguments as $argument) {
            $name = $argument->getName();

            if ($name == 'flags') {
                $args['flags'] = $command->getFlags();
                continue;
            }

            if ($name == 'words') {
                $args['words'] = $command->getWords();
                continue;
            }

            if (!in_array($name, ['flags', 'words', ...$serviceMethods])) {
                $args[$name] = null;
                continue;
            }

            $callable = [$service, $name];
            $args[$name] = $callable();
        }

        return $args;
    }

    protected function getFunctionArgs(string|Closure $callable): array
    {
        $reflectionFunction = new ReflectionFunction($callable);
        return $reflectionFunction->getParameters();
    }

    protected function getMethodArgs(string|object $class, string $method): array
    {
        $reflectionMethod = new ReflectionMethod($class, $method);
        return $reflectionMethod->getParameters();
    }

    protected function executeMiddleware(array $args, array $middleware): ?array
    {
        $modifiedArgs = $args;

        foreach ($middleware as $middlewareClass) {
            $callable = [(new $middlewareClass()), 'execute'];
            $modifiedArgs = $callable($modifiedArgs);

            if (!$modifiedArgs) {
                return null;
            }
        }

        return $modifiedArgs;
    }
}

?>