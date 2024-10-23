<?php

namespace hyper;

use PDO;
use PDOStatement;

class database
{
    public PDO $pdo;
    public function __construct(public array $config = [])
    {
    }

    public function getPdo(): PDO
    {
        if (!isset($this->pdo)) {
            $this->resetPdo();
        }
        return $this->pdo;
    }

    public function query($statement, ...$args): PDOStatement|false
    {
        debugger('query', $statement);
        return $this->getPdo()->query($statement, ...$args);
    }

    public function prepare(string $statement, array $options = []): PDOStatement|false
    {
        debugger('query', $statement);
        return $this->getPdo()->prepare($statement, $options);
    }

    public function __call(string $name, array $args)
    {
        debugger('query', $args);
        return call_user_func_array([$this->getPdo(), $name], $args);
    }

    public function resetPdo(): self
    {
        $dsn = $this->buildDsn();
        $this->pdo = new PDO(
            $dsn,
            $this->config['user'] ?? null,
            $this->config['password'] ?? null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        if ($this->config['driver'] === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys = ON;');
        }
        debugger('app', "database initialized for: {$dsn}");
        return $this;
    }

    private function buildDsn(): string
    {
        return match ($this->config['driver']) {
            'sqlite' => sprintf('sqlite:%s', $this->config['file']),
            default => sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s;',
                $this->config['driver'],
                $this->config['host'],
                $this->config['port'],
                $this->config['name'],
                $this->config['charset'] ?? 'utf8mb4'
            ),
        };
    }
}
