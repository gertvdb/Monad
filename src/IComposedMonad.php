<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Gertvdb\Monad\Env\IEnv;
use Gertvdb\Monad\Writer\IWriter;

/**
 * Extension of {@see IMonad} that composes the Reader (Env) and Writer effects.
 *
 * - Reader via {@see IComposedMonad::env()} and env-aware operations
 * - Writer via {@see IComposedMonad::writer()} and log-like channels
 */
interface IComposedMonad extends IMonad
{
    // ------------------------------------------------------------
    //  Env (Reader)
    // ------------------------------------------------------------

    /**
     * Access the current immutable environment.
     *
     * ```
     * use Gertvdb\Monad\Result;
     *
     * $result = Result::ok(10)
     *    ->withEnv(new Locale("nl"));
     *
     * $locale = $result->env()->get(Locale::class);
     * ```
     *
     */
    public function env(): IEnv;

    /**
     * Return a new instance with a dependency added/replaced in the env.
     *
     * ```
     * use Gertvdb\Monad\Result;
     *
     * $result = Result::ok(new User("John"))
     *     ->withEnv(new Locale("en"))
     * ```
     *
     * @param object $dependency Typically a service instance
     * @return self
     *
     */
    public function withEnv(object $dependency): self;

    /**
     * Bind with access to resolved dependencies from the env.
     *
     * ```
     * use Gertvdb\Monad\Result;
     *
     * $result = Result::ok(new User("John"))
     *     ->withEnv(new Locale("en"))
     *     ->bindWithEnv(
     *         [Locale::class],
     *         fn(User $user, Locale $locale) => Result::ok($user->name . " ({$locale->code})")
     *     );
     * ```
     *  @param list<class-string> $dependencies
     *  @param callable $fn function(mixed $value, array $env): self
     *  @return self
     */
    public function bindWithEnv(array $dependencies, callable $fn): self;

    /**
     * Map with access to resolved dependencies from the env.
     *
     *  ```
     *  use Gertvdb\Monad\Result;
     *
     *  $result = Result::ok(new User("John"))
     *      ->withEnv(new Locale("en"))
     *      ->mapWithEnv(
     *          [Locale::class],
     *          fn(User $user, Locale $locale) => $user->name . " ({$locale->code})"
     *      );
     *  ```
     *
     * @param list<class-string> $dependencies
     * @param callable $fn function(mixed $value, array $env): mixed
     * @return self
     */
    public function mapWithEnv(array $dependencies, callable $fn): self;

    // ------------------------------------------------------------
    //  Writer
    // ------------------------------------------------------------

    /**
     * Access the writer that collects side-effects by channel.
     *
     * ```
     * use Gertvdb\Monad\Result;
     *
     * $result = Result::ok(10)
     *       ->writeTo("logs", "starting process")
     *
     * $writer = $result->writer()->get("logs");
     * ```
     */
    public function writer(): IWriter;

    /**
     * Add a value to a writer channel and return a new instance.
     *
     * ```
     * use Gertvdb\Monad\Result;
     *
     * $result = Result::ok(10)
     *     ->writeTo("logs", "starting process")
     * ```
     *
     * @param string $channel Arbitrary channel name (e.g. 'log', 'events')
     * @param mixed $value Any serializable value
     * @return self
     */
    public function writeTo(string $channel, mixed $value): self;

    /**
     * Retrieve all values written to a channel.
     *
     * ```
     * use Gertvdb\Monad\Result;
     *
     * $result = Result::ok(10)
     *      ->writerOutput("logs")
     * ```
     *
     * @return array<int, mixed>
     */
    public function writerOutput(string $channel): array;
}
