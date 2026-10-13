<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = (string) Config::get('database.host');
        $port = (int) Config::get('database.port', 3306);
        $name = (string) Config::get('database.database');
        $user = (string) Config::get('database.username');
        $pass = (string) Config::get('database.password');
        $charset = (string) Config::get('database.charset', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        try {
            self::$connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            Logger::error('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException(
                'Unable to connect to the database. '
                . 'Host=' . $host . '; Database=' . $name . '; User=' . $user . '. '
                . 'MySQL: ' . $e->getMessage()
            );
        }

        return self::$connection;
    }

    public static function connectWithoutDatabase(): PDO
    {
        $host = (string) Config::get('database.host');
        $port = (int) Config::get('database.port', 3306);
        $user = (string) Config::get('database.username');
        $pass = (string) Config::get('database.password');
        $charset = (string) Config::get('database.charset', 'utf8mb4');
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId() ?: '0';
    }

    public static function reset(): void
    {
        self::$connection = null;
    }
}
