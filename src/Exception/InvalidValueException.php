<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Exception;

class InvalidValueException extends InvalidArgumentException
{
}

if (!class_exists(\ApiPlatform\Core\Exception\InvalidValueException::class, false)) {
    class_alias(InvalidValueException::class, \ApiPlatform\Core\Exception\InvalidValueException::class);
}
