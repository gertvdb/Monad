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
     * Return a new instance with a replaced env.
     *
     * ```
     * use Gertvdb\Monad\Result;
     *
     * $env = Env::empty();
     * $env = $env->addService($myService);
     *
     * $result = Result::ok(new User("John"))
     *     ->withEnv($env)
     * ```
     *
     * @param IEnv $env Typically a service instance
     * @return self
     *
     */
    public function withEnv(IEnv $env): self;


    /**
     * Return a new instance with a dependency added/replaced in the env.
     *
     * ```
     * use Gertvdb\Monad\Result;
     *
     * $result = Result::ok(new User("John"))
     *     ->withService(new Locale("en"))
     * ```
     *
     * @param object $dependency Typically a service instance
     * @return self
     *
     */
    public function withService(object $dependency): self;

    /**
     * Return a new instance with multiple dependency added/replaced in the env.
     *
     * ```
     * use Gertvdb\Monad\Result;
     *
     * $result = Result::ok(new User("John"))
     *     ->withServices(new Locale("en"), new Language())
     * ```
     *
     * @param object $dependencies
     * @return self
     *
     */
    public function withServices(object ...$dependencies): self;

    /**
     * Return a new Env with an alias of a interface to a concrete class.
     *
     * ```
     * $env = $env->withAlias(LanguageInterface::class, Language::class);
     * ```
     */
    public function withAlias(string $alias, object|string $implementation): self;

    /**
     * Return a new Env with an factory to create a concrete class.
     *
     * ```
     * $env = $env->withFactory(Database::class, fn () => new Database());
     * ```
     */
    public function withFactory(string $class, callable $factory): self;

    /**
     * Return a new Env with an param value.
     *
     * ```
     * $env = $env->withParam('host', 'localhost');
     * ```
     */
    public function withParam(string $name, string|int|float|bool|array $value): self;

    /**
     * Return a new Env with a tagged service
     *
     * ```
     * $env = $env->withTag('logger', new Logger());
     * ```
     */
    public function withTag(string $tag, object $service): self;

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
