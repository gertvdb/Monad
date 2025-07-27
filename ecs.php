<?php
// ecs.php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

/**
 * Configure ecs config.
 */
return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->paths([__DIR__ . '/src', __DIR__ . '/tests']);
    $ecsConfig->cacheDirectory('.ecs_cache');

    $ecsConfig->sets([SetList::PSR_12]);
};
