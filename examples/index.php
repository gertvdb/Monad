<?php

declare(strict_types=1);

use Gertvdb\Monad\Result;use Gertvdb\Monad\Trace\TraceCommon;

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
        return $result->bindWithEnv(
            [Capitalize::class],
            function (string $string, Capitalize $capitalize) {
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
        return $result->mapWithEnv(
            [LowerCase::class],
            function (string $string, LowerCase $lowercase) {
                return str_replace('Gert', 'Franken', $lowercase->doIt($string));
            }
        ) ->withTrace(TraceCommon::from('ExampleMapWithEnv', time()));
    }
}

ignore_user_abort(true);

frankenphp_handle_request(static function (){

    header('Content-Type: text/plain');

    $capitalize = new Capitalize();
    $lowercase = new LowerCase();

    /** @var Result $result */
    $result = pipe(
        Result::ok('Hello from FrankenPHP worker'),
        new ExampleBind(),
        new ExampleMap(),
        static fn (Result $r) => $r->withEnv($capitalize),
        new ExampleBindWithEnv(),
        static fn (Result $r) => $r->withEnv($lowercase),
        new ExampleMapWithEnv(),
    );

    var_dump($result->traces());

    $response = $result->fold(
        onOk: fn ($value, $env, $writer) => $value,
        onErr: fn (Throwable $error, $env, $writer) => throw $error,
    );



    echo is_string($response) ? $response : (string) json_encode($response);

});
