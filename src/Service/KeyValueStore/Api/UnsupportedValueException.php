<?php

/*
 * This file is part of the webmozart/key-value-store package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service\KeyValueStore\Api;

use Exception;
use RuntimeException;

/**
 * Thrown when an unsupported value is stored in a key-value store.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UnsupportedValueException extends RuntimeException
{
    /**
     * Creates a new exception for the given value type.
     *
     * @param string         $type  the name of the unsupported type
     * @param KeyValueStore  $store the store that does not support the type
     * @param int            $code  the exception code
     * @param Exception|null $cause the exception that caused this exception
     *
     * @return static the new exception
     */
    public static function forType($type, KeyValueStore $store, $code = 0, Exception $cause = null)
    {
        return new static(sprintf(
            'Values of type %s are not supported by %s.',
            $type,
            get_class($store)
        ), $code, $cause);
    }

    /**
     * Creates a new exception for the given value.
     *
     * @param string         $value the unsupported value
     * @param KeyValueStore  $store the store that does not support the type
     * @param int            $code  the exception code
     * @param Exception|null $cause the exception that caused this exception
     *
     * @return static the new exception
     */
    public static function forValue($value, KeyValueStore $store, $code = 0, Exception $cause = null)
    {
        return new static(sprintf(
            'Values of type %s are not supported by %s.',
            gettype($value),
            get_class($store)
        ), $code, $cause);
    }
}
