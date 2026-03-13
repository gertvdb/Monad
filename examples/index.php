<?php

declare(strict_types=1);

use Gertvdb\Monad\Option;use Gertvdb\Monad\Result;
use Gertvdb\Monad\Trace\TraceCommon;
use Symfony\Component\HttpFoundation\Request;
use function Gertvdb\Monad\alias;
use function Gertvdb\Monad\factory;
use function Gertvdb\Monad\param;
use function Gertvdb\Monad\services;

require __DIR__ . '/../vendor/autoload.php';

function pipe(mixed $arg, callable ...$fns): mixed
{
    foreach ($fns as $fn) {
        $arg = $fn($arg);
    }
    return $arg;
}

final class ExampleBind
{
    public function __invoke(Result $result): Result
    {
        return $result->bind(
            function (string $string) {
                try {
                    return
                        Result::ok(str_replace('Franken', 'Gert', $string))
                            ->withTrace(TraceCommon::from('ExampleBind', time()));
                } catch (Throwable $e) {
                    return Result::err($e);
                }
            }
        );
    }
}

function exampleMap(Result $result): Result
{
    return $result->map(function (string $string, LowerCase $lowerCase) {
        return $lowerCase->doIt(str_replace('Gert', 'Franken', $string));
    })->withTrace(TraceCommon::from('exampleMap', time()));
}

final class ExampleMap
{
    public function __invoke(Result $result): Result
    {
        return $result->map(
            function (string $string) {
                return str_replace('Gert', 'Franken', $string);
            }
        ) ->withTrace(TraceCommon::from('ExampleMap', time()));
    }
}

final class Capitalize {

    public function doIt(string $value): ?string {
        $done = mb_strtoupper($value);
        return !is_string($done) ? null : $done;
    }
}

final class LowerCase {

    public function doIt(string $value): ?string {
        $done = mb_strtolower($value);
        return !is_string($done) ? null : $done;
    }
}

final class ExampleBindWithEnv
{
    public function __invoke(Result $result): Result
    {
        return $result->bind(
            function (string $string, Capitalize $capitalize, Database $db, Serialize $serialize, ?Missing $missing) use ($result) {
                $string = $capitalize->doIt($string) ?? '';

                try {
                    return
                        Result::ok(str_replace('Franken', 'Gert', $string))
                            ->withTrace(TraceCommon::from('ExampleBindWithEnv', time()));
                } catch (Throwable $e) {
                    return Result::err($e);
                }
            }
        );
    }
}

final class ExampleMapWithEnv
{
    public function __invoke(Result $result): Result
    {
        return $result->map(
            function (string $string, LowerCase $lowercase) {
                return str_replace('Gert', 'Franken', $lowercase->doIt($string));
            }
        ) ->withTrace(TraceCommon::from('ExampleMapWithEnv', time()));
    }
}

final class ExampleApplyWithEnv
{
    public function __invoke(Result $result): Result
    {
        return $result->apply(
            Result::ok(
                static fn (string $string, LowerCase $lowerCase) =>
                $lowerCase->doIt($string)
            )
        )->withTrace(TraceCommon::from('ExampleApplyWithEnv', time()));
    }
}

interface  LoggerInterface {
    public function log(string $level, string $message): void;
}

class NullLogger implements LoggerInterface {
    public function log(string $level, string $message) : void {

    }
}

class Database {
    public function __construct(
        public readonly string $tenant,
        public readonly LoggerInterface $logger
    ) {

    }
}

abstract class Serialize {

    abstract public function serialize(): string;
}

class JsonSerialize extends Serialize {
    public function serialize() :string {
        return 'hallo';
    }
}

class Missing {
}

ignore_user_abort(true);

frankenphp_handle_request(static function () {

    // Tell the browser to render HTML
    header('Content-Type: text/html; charset=utf-8');

    $request = Request::createFromGlobals();
    $rawTenant = $request->query->get('tenant');
    $tenant = $rawTenant ? Option::some($rawTenant) : Option::none();

    $capitalize = new Capitalize();
    $lowercase = new LowerCase();

    /** @var Result $result */
    $result = pipe(
        Result::ok('Hello from FrankenPHP worker'),
        static fn(Result $r) => param($r, 'tenant', $tenant->unwrapOr('default')),
        static fn(Result $r) => alias($r, LoggerInterface::class, NullLogger::class),
        static fn(Result $r) => factory($r, Database::class, fn(string $tenant, LoggerInterface $logger) => new Database($tenant, $logger)),
        static fn(Result $r) => services($r, $capitalize, $lowercase),
        static fn(Result $r) => alias($r, Serialize::class, JsonSerialize::class),
        new ExampleBind(),
        new ExampleMap(),
        'exampleMap',
        new ExampleBindWithEnv(),
        new ExampleMapWithEnv(),
        new ExampleApplyWithEnv(),
    );

    $response = $result->fold(
        onOk: fn($value, $env, $writer) => sprintf(
            '<div style="
            background-color: #dfd;
            color: #060;
            padding: 1em;
            border: 1px solid #090;
            font-family: monospace;
            white-space: pre-wrap;
            line-height: 1.4em;
        ">
            <strong>Success:</strong> %s
        </div>',
            htmlspecialchars((string)$value, ENT_QUOTES)
        ),
        onErr: fn(Throwable $error, $env, $writer) => sprintf(
            '<div style="
            background-color: #fdd;
            color: #900;
            padding: 1em;
            border: 1px solid #900;
            font-family: monospace;
            white-space: pre-wrap;
            line-height: 1.4em;
        ">
            <strong>Error:</strong> %s

            <strong>Trace:</strong>
            %s
        </div>',
            htmlspecialchars($error->getMessage(), ENT_QUOTES),
            htmlspecialchars($error->getTraceAsString(), ENT_QUOTES)
        ),
    );

    echo $response;

    foreach ($result->traces() as $trace) {
        echo sprintf(
            '<br/><div style="
            background-color: #ffed8e;
            color: #dc7103;
            padding: 1em;
            border: 1px solid #ff7d2b;
            font-family: monospace;
            white-space: pre-wrap;
            line-height: 1.4em;
        ">
            <strong>Trace:</strong> %s
        </div><br/>',
            htmlspecialchars((string)$trace->read(), ENT_QUOTES)
        );
    }
});
