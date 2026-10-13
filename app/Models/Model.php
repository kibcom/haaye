<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

abstract class Model
{
    public static function table(): string
    {
        return '';
    }

    public static function hydrate(array $row): static
    {
        $model = new static();
        $reflection = new \ReflectionObject($model);

        foreach ($row as $key => $value) {
            if (!property_exists($model, $key)) {
                continue;
            }

            $type = $reflection->getProperty($key)->getType();
            if ($type instanceof \ReflectionNamedType) {
                if ($value === null && $type->allowsNull()) {
                    $model->{$key} = null;
                    continue;
                }

                $name = $type->getName();
                if ($name === 'int') {
                    $model->{$key} = (int) $value;
                    continue;
                }
                if ($name === 'string') {
                    $model->{$key} = (string) $value;
                    continue;
                }
            }

            $model->{$key} = $value;
        }

        return $model;
    }

    public static function find(int $id): ?static
    {
        $statement = Database::query('SELECT * FROM ' . static::table() . ' WHERE id = ? LIMIT 1', [$id]);
        $row = $statement->fetch();
        return $row ? static::hydrate($row) : null;
    }
}
