<?php

/**
 * @author    Yuriy Davletshin <yuriy.davletshin@gmail.com>
 * @copyright 2019 Yuriy Davletshin
 * @license   MIT
 */

declare(strict_types=1);

namespace Satori\Nano;

/**
 * Dependency injection container.
 * Sets, checks service definition or returns service instance.
 *
 * @param string   $id         The unique name of the service.
 * @param string   $operation  The name of the operation.
 * @param callable $definition The closure or invokable object.
 *
 * @throws \BadFunctionCallException If the service definition not passed to function.
 * @throws \LogicException           If the service is not defined.
 *
 * @return object|bool|void The service instance or boolean check result.
 */
function app(string $id, string $operation = null, callable $definition = null)
{
    static $services = [];

    switch ($operation) {
        case '?':
        case 'has':
            return isset($services[$id]);

        case '=':
        case 'set':
            if (!$definition) {
                throw new \BadFunctionCallException('Definition not passed to function.');
            }
            if (ltrim($id, '_') !== $id) {
                $services[$id] = $definition;
            } else {
                $services[$id] = function () use ($definition) {
                    static $service;
                    if (!isset($service)) {
                        $service = $definition();
                    }

                    return $service;
                };
            }
            break;

        default:
            if (isset($services[$id])) {
                return $services[$id]();
            }
            throw new \LogicException(sprintf('Service (object) "%s" is not defined.', $id));
    }
}

/**
 * Parameter container.
 * Sets, checks, removes or returns a value.
 *
 * @param string $key       The unique key of the parameter.
 * @param string $operation The name of the operation.
 * @param mixed  $value     The default value.
 *
 * @throws \LogicException If the parameter is not defined.
 *
 * @return mixed|bool|void The value or boolean check result.
 */
function param(string $key, string $operation = null, $value = null)
{
    static $parameters = [];

    switch ($operation) {
        case '?':
        case 'has':
            return array_key_exists($key, $parameters);

        case '=':
        case 'set':
            $parameters[$key] = $value;
            break;

        case 'x':
        case 'del':
            unset($parameters[$key]);
            break;

        case '??':
            return $parameters[$key] ?? $value;

        default:
            if (array_key_exists($key, $parameters)) {
                return $parameters[$key];
            }
            throw new \LogicException(sprintf('Parameter "%s" is not defined.', $key));
    }
}

/**
 * Event dispatcher.
 * Registers event handler or emits an event.
 *
 * @param string $event     The unique name of the event.
 * @param string $operation The name of the operation.
 * @param mixed  $arguments Additional arguments.
 *
 * @throws \BadFunctionCallException If the listener not passed to function.
 * @throws \LogicException           If the operation is not defined.
 *
 * @return void
 */
function event(string $event, string $operation, ...$arguments): void
{
    static $events = [];
    static $subscriptions = [];

    switch ($operation) {
        case '@':
        case 'on':
            if (is_string($arguments[0]) && is_callable($arguments[1])) {
                $callbackKey = $event . ' ' . $arguments[0];
                $events[$event][] = $callbackKey;
                $subscriptions[$callbackKey] = $arguments[1];
                break;
            }
            throw new \BadFunctionCallException('Listener not passed to function.');

        case '>>':
        case 'emit':
            if (isset($events[$event])) {
                foreach ($events[$event] as $callbackKey) {
                    $output = $subscriptions[$callbackKey](...$arguments);
                    if (isset($output['stop']) && true === $output['stop']) {
                        break;
                    }
                }
            }
            break;

        default:
            throw new \LogicException(sprintf('Operation "%s" is not defined.', $operation));
    }
}

/**
 * Middleware system.
 * Registers or runs middlewares.
 *
 * @param string            $stackId      The unique name of the middleware stack.
 * @param callable          {argument #1} If argument is the callable then will be registered.
 * @param array<int, mixed> $arguments    Else stack middlewares will run with these arguments.
 *
 * @throws \LogicException If the stack is not defined.
 *
 * @return array<int, mixed>|null Arguments after all middlewares.
 */
function middleware(string $stackId, ...$arguments): ?array
{
    static $stacks = [];
    if (is_callable($arguments[0] ?? null) && !isset($stacks[$stackId])) {
        $stacks[$stackId] = function (string $stackId, ...$arguments): ?array {
            static $middlewares = [0 => null];
            if (is_callable($arguments[0] ?? null)) {
                $middlewares[] = $arguments[0];

                return null;
            }
            $lastKey = array_keys($middlewares)[count($middlewares) - 1] ?? null;
            if (key($middlewares) === $lastKey) {
                reset($middlewares);
            }

            return next($middlewares)($stackId, ...$arguments);
        };
    }
    if (isset($stacks[$stackId])) {
        return $stacks[$stackId]($stackId, ...$arguments);
    }
    throw new \LogicException(sprintf('Stack "%s" is not defined.', $stackId));
}

/**
 * Blank middleware.
 *
 * @param array<int, mixed> $arguments Middleware arguments.
 *
 * @return array<int, mixed> Arguments to pass in next middleware.
 */
function turnBack(...$arguments): array
{
    return $arguments;
}
