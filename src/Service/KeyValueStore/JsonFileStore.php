<?php

/*
 * This file is part of the webmozart/key-value-store package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service\KeyValueStore;

use App\Service\KeyValueStore\Api\CountableStore;
use App\Service\KeyValueStore\Api\NoSuchKeyException;
use App\Service\KeyValueStore\Api\SortableStore;
use App\Service\KeyValueStore\Api\UnsupportedValueException;
use App\Service\KeyValueStore\Util\KeyUtil;
use stdClass;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * A key-value store backed by a JSON file.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonFileStore implements SortableStore, CountableStore
{
    /**
     * This seems to be the biggest float supported by json_encode()/json_decode().
     */
    const MAX_FLOAT = 1.0E+14;

    /**
     * @var string
     */
    private $path;

    /**
     * @var JsonEncoder
     */
    private $serializer;

    public function __construct($path, SerializerInterface $serializer)
    {
        $this->path = $path;

        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        KeyUtil::validate($key);

        if (is_float($value) && $value > self::MAX_FLOAT) {
            throw new UnsupportedValueException('The JSON file store cannot handle floats larger than 1.0E+14.');
        }

        $data = $this->load();
        $data[$key] = $this->serializeValue($value);

        $this->save($data);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        KeyUtil::validate($key);

        $data = $this->load();

        if (!array_key_exists($key, $data)) {
            return $default;
        }

        return $this->unserializeValue($data[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrFail($key)
    {
        KeyUtil::validate($key);

        $data = $this->load();

        if (!array_key_exists($key, $data)) {
            throw NoSuchKeyException::forKey($key);
        }

        return $this->unserializeValue($data[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys, $default = null)
    {
        $values = [];
        $data = $this->load();

        foreach ($keys as $key) {
            KeyUtil::validate($key);

            if (array_key_exists($key, $data)) {
                $value = $this->unserializeValue($data[$key]);
            } else {
                $value = $default;
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultipleOrFail(array $keys)
    {
        $values = [];
        $data = $this->load();

        foreach ($keys as $key) {
            KeyUtil::validate($key);

            if (!array_key_exists($key, $data)) {
                throw NoSuchKeyException::forKey($key);
            }

            $values[$key] = $this->unserializeValue($data[$key]);
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        KeyUtil::validate($key);

        $data = $this->load();

        if (!array_key_exists($key, $data)) {
            return false;
        }

        unset($data[$key]);

        $this->save($data);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($key)
    {
        KeyUtil::validate($key);

        $data = $this->load();

        return array_key_exists($key, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->save(new stdClass());
    }

    /**
     * {@inheritdoc}
     */
    public function keys()
    {
        return array_keys($this->load());
    }

    /**
     * {@inheritdoc}
     */
    public function sort($flags = SORT_REGULAR)
    {
        $data = $this->load();

        ksort($data, $flags);

        $this->save($data);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        $data = $this->load();

        return count($data);
    }

    private function load()
    {
        if (!file_exists($this->path)) {
            return [];
        }
        $content = file_get_contents($this->path);

        return $this->serializer->decode($content, 'json');
    }

    private function save($data)
    {
        $content = $this->serializer->encode($data, 'json');

        if (!file_exists($dir = dirname($this->path))) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($this->path, $content);
    }

    private function serializeValue($value)
    {
        // Serialize if we have a string and string serialization is enabled...
        $serializeValue = is_string($value)
            // or we have an array and array serialization is enabled...
            || is_array($value)
            // or we have any other non-scalar, non-null value
            || (null !== $value && !is_scalar($value) && !is_array($value));

        if ($serializeValue) {
            return serialize($value);
        }

        // If we have an array and array serialization is disabled, serialize
        // its entries if necessary
        if (is_array($value)) {
            return array_map([$this, 'serializeValue'], $value);
        }

        return $value;
    }

    private function unserializeValue($value)
    {
        if (is_string($value)) {
            return unserialize($value);
        }

        if (is_array($value)) {
            return array_map([$this, 'unserializeValue'], $value);
        }

        return $value;
    }
}
