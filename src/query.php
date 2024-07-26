<?php

namespace hyper;

use hyper\utils\paginator;
use PDO;

class query
{
    private array $where = ['sql' => '', 'bind' => []];
    private array $query = ['sql' => '', 'joins' => '', 'join_num' => 0];
    private array $dataMapper = [];

    public function __construct(private database $database, private string $table)
    {
    }

    public function addMapper(callable $callback): self
    {
        $this->dataMapper[] = $callback;
        return $this;
    }

    private function applyMapper(array $data): array
    {
        foreach ($this->dataMapper as $key => $mapper) {
            unset($this->dataMapper[$key]);
            $data = call_user_func($mapper, $data);
        }
        return $data;
    }

    /**
     * @param array $config ['ignore' = false, 'replace' => false, 'conflict' => ['id'], update' = []]
     */
    public function insert(array $data, array $config = []): int
    {
        if (empty($data)) {
            return 0;
        }
        if (!(isset($data[0]) && is_array($data[0]))) {
            $data = [$data];
        }
        $fields     = array_keys($data[0]);
        $statement  = $this->database->prepare(
            sprintf(
                // base sql schema
                "%s %s INTO `{$this->table}` (%s) VALUES %s %s;",
                // create or replace data into database 
                isset($config['replace']) && $config['replace'] === true ? 'REPLACE' : 'INSERT',
                // use ignore when failed
                isset($config['ignore']) && $config['ignore'] === true ? ($this->database->config['driver'] === 'sqlite' ? 'OR IGNORE' : 'IGNORE') : '',
                // join all the field using ,
                join(',', $fields),
                // use placeholder and bind value later
                $this->createPlaceholder($data),
                // bulk update data when conflict
                isset($config['update']) && !empty($config['update']) ?
                    ($this->database->config['driver'] === 'sqlite' ?
                        // bulk update sqlite driver method
                        ('ON CONFLICT(' . join(',', ($config['conflict'] ?? ['id'])) . ') DO UPDATE SET ' . (join(
                            ', ',
                            array_map(
                                fn ($key, $value) => sprintf('%s = excluded.%s', $key, $value),
                                array_keys($config['update']),
                                array_values($config['update'])
                            )
                        )))
                        // else bulk update non-sqlite driver method
                        : ('ON DUPLICATE KEY UPDATE ' . (join(
                            ', ',
                            array_map(
                                fn ($key, $value) => sprintf('%s = VALUES(%s)', $key, $value),
                                array_keys($config['update']),
                                array_values($config['update'])
                            )
                        )))
                    ) : ''
            )
        );
        // Bind Data Into Statement Placeholder.
        foreach ($data as $serial => $row) {
            foreach ($fields as $column) {
                $statement->bindValue(
                    sprintf(':%s_%s', $column, $serial),
                    isset($row[$column]) && is_array($row[$column]) ? ($row[$column]['text'] ?? null) : ($row[$column] ?? null)
                );
            }
        }
        $statement->execute();
        return $this->database->pdo->lastInsertId();
    }

    public function bulkUpdate(array $data, array $config = []): int
    {
        if (!(isset($data[0]) && is_array($data[0]))) {
            $data = [$data];
        }
        if (!isset($config['conflict'])) {
            $config['conflict'] = ['id'];
        }
        if (!isset($config['update'])) {
            $fields = array_filter(array_keys($data[0]), fn ($field) => !in_array($field, $config['conflict']));
            $config['update'] = array_map(fn ($field) => [$field => $field], $fields);
        }
        return $this->insert($data, $config);
    }

    private function createPlaceholder(array $data): string
    {
        $values = [];
        foreach ($data as $serial => $row) {
            $params = array_map(
                fn ($attr, $value) => sprintf(
                    '%s:%s_%s%s',
                    // create placeholder from array value ex: ['prefix' => 'DATE(']
                    is_array($value) && isset($value['prefix']) ? $value['prefix'] : '',
                    // base placeholder
                    $attr,
                    $serial,
                    // create placeholder from array value ex: ['suffix' => ')']
                    is_array($value) && isset($value['suffix']) ? $value['suffix'] : ''
                ),
                array_keys($row),
                array_values($row)
            );
            $values[] = join(',', $params);
        }
        return '(' . join('), (', $values) . ')';
    }

    public function where($condition = null, string $type = 'AND'): self
    {
        if ($condition !== null) {
            return $this->addWhere($type, $condition);
        }
        return $this;
    }

    public function andWhere($conditions): self
    {
        return $this->addWhere('AND', $conditions);
    }

    public function orWhere($conditions): self
    {
        return $this->addWhere('OR', $conditions);
    }

    private function addWhere(string $method, $conditions): self
    {
        $command = '';
        if (is_array($conditions)) {
            $command = sprintf(
                "%s %s",
                $method,
                implode(
                    " {$method} ",
                    array_map(
                        fn ($attr, $value) => $attr . (is_array($value) ?
                            sprintf(
                                " IN (%s)",
                                join(",", array_map(fn ($index) => ':' . str_replace('.', '', $attr) . '_' . $index, array_keys($value)))
                            )
                            : " = :" . str_replace('.', '', $attr)
                        ),
                        array_keys($conditions),
                        array_values($conditions)
                    )
                )
            );

            $this->where['bind'] = array_merge($this->where['bind'], $conditions);
        } elseif (is_string($conditions)) {
            $command = "{$method} {$conditions}";
        }
        $this->where['sql'] .= sprintf(' %s ', empty($this->where['sql']) ? ltrim($command, $method . ' ') : $command);
        return $this;
    }

    private function hasWhere(): bool
    {
        return !empty(trim($this->where['sql']));
    }

    private function getWhereSql(): string
    {
        return $this->hasWhere() ? ' WHERE ' .  trim($this->where['sql']) . ' ' : '';
    }

    private function bindWhere(&$statement): void
    {
        foreach ($this->where['bind'] ?? [] as $attr => $value) {
            $attr = ':' . str_replace('.', '', $attr);
            if (is_array($value)) {
                foreach ($value as $index => $val) {
                    $statement->bindValue($attr . '_' . $index, $val);
                }
            } else {
                $statement->bindValue($attr, $value);
            }
        }
    }

    private function resetWhere(): void
    {
        $this->where = ['sql' => '', 'bind' => []];
    }

    public function update(array $data, $where = null): bool
    {
        $this->where($where);
        if (!$this->hasWhere()) {
            return false;
        }
        $statement = $this->database->prepare(
            sprintf(
                "UPDATE `{$this->table}` SET %s %s",
                implode(', ', array_map(fn ($attr) => "$attr=:$attr", array_keys($data))),
                $this->getWhereSql()
            )
        );
        foreach ($data as $key => $val) {
            $statement->bindValue(":$key", $val);
        }
        $this->bindWhere($statement);
        $statement->execute();
        $this->resetWhere();
        return $statement->rowCount();
    }

    public function delete($where = null): bool
    {
        $this->where($where);
        if (!$this->hasWhere()) {
            return false;
        }
        $statement = $this->database->prepare("DELETE FROM `{$this->table}` " . $this->getWhereSql());
        $this->bindWhere($statement);
        $statement->execute();
        $this->resetWhere();
        return $statement->rowCount();
    }

    public function select(array|string $fields = '*'): self
    {
        if (is_array($fields)) {
            $fields = implode(',', $fields);
        }
        $this->query['sql'] = sprintf("SELECT %s FROM `%s` AS p", $fields, $this->table);
        return $this;
    }

    public function join(string $table, string $condition): self
    {
        return $this->addJoin('INNER', $table, $condition);
    }

    public function leftJoin(string $table, string $condition): self
    {
        return $this->addJoin('LEFT', $table, $condition);
    }

    public function rightJoin(string $table, string $condition): self
    {
        return $this->addJoin('RIGHT', $table, $condition);
    }

    public function crossJoin(string $table, string $condition): self
    {
        return $this->addJoin('CROSS', $table, $condition);
    }

    private function addJoin(string $type, string $table, string $condition): self
    {
        $alias = sprintf('t%s', ++$this->query['join_num']);
        $this->query['joins'] .= sprintf(
            " %s JOIN %s %s ON %s ",
            $type,
            $table,
            stripos($table, ' AS ') === false ? ' AS ' . $alias : '',
            $condition
        );
        return $this;
    }

    public function order(?string $sort = null): self
    {
        if ($sort !== null) {
            $this->query['order'] = $sort;
        }
        return $this;
    }

    public function orderAsc(string $field = 'p.id'): self
    {
        $this->query['order'] = $field . ' ASC';
        return $this;
    }

    public function orderDesc(string $field = 'p.id'): self
    {
        $this->query['order'] = $field . ' DESC';
        return $this;
    }

    public function group(string $group): self
    {
        $this->query['group'] = $group;
        return $this;
    }

    public function having(string $having): self
    {
        $this->query['having'] = $having;
        return $this;
    }

    public function limit(?int $offset = null, ?int $limit = null): self
    {
        if ($offset !== null) {
            $this->query['limit'] = sprintf(" $offset%s", $limit !== null ? ", $limit" : '');
        }
        return $this;
    }

    public function fetch(...$fetch): self
    {
        $this->query['fetch'] = $fetch;
        return $this;
    }

    private function executeSelectQuery(): void
    {
        if (empty($this->query['sql'])) {
            $this->select();
        }
        $statement = $this->database->prepare(
            $this->query['sql']
                . $this->query['joins']
                . $this->getWhereSql()
                . (isset($this->query['group']) ? ' GROUP BY ' . trim($this->query['group']) : '')
                . (isset($this->query['having']) ? ' HAVING ' . trim($this->query['having']) : '')
                . (isset($this->query['order']) ? ' ORDER BY ' . trim($this->query['order']) : '')
                . (isset($this->query['limit']) ? ' LIMIT ' . trim($this->query['limit']) : '')
        );
        $this->bindWhere($statement);
        $statement->execute();
        $this->query['sql'] = $statement;
    }

    public function first()
    {
        $this->limit(1)->executeSelectQuery();
        $result = $this->applyMapper($this->query['sql']->fetchAll(...($this->query['fetch'] ?? [PDO::FETCH_OBJ])));
        $this->resetQuery();
        return $result[0] ?? false;
    }

    public function last()
    {
        return $this->orderDesc()->first();
    }

    public function latest(): array
    {
        return $this->orderDesc()->result();
    }

    public function result(): array
    {
        $this->executeSelectQuery();
        $result = $this->query['sql']->fetchAll(...($this->query['fetch'] ?? [PDO::FETCH_OBJ]));
        $this->resetQuery();
        return $this->applyMapper($result);
    }

    public function paginate(int $limit = 10, string $keyword = 'page'): paginator
    {
        if (empty($this->query['sql'])) {
            $this->select();
        }
        $paginator = new paginator($limit, $limit, $keyword, false);
        if ($this->database->config['driver'] !== 'sqlite') {
            $this->query['sql'] = preg_replace('/SELECT /', 'SELECT SQL_CALC_FOUND_ROWS ', $this->query['sql'], 1);
        }
        $this->limit(ceil($limit * ($paginator->getKeywordValue() - 1)), $limit)->executeSelectQuery();
        $paginator->setData(
            $this->applyMapper($this->query['sql']->fetchAll(...($this->query['fetch'] ?? [PDO::FETCH_OBJ])))
        );
        // get total record
        if ($this->database->config['driver'] === 'sqlite') {
            $paginator->total = $this->count();
        } else {
            $total = $this->database->prepare('SELECT FOUND_ROWS()');
            $total->execute();
            $paginator->total = $total->fetch(PDO::FETCH_COLUMN);
        }
        $paginator->resetPaginator();
        $this->resetQuery();
        return $paginator;
    }

    public function count(): int
    {
        $statement = $this->database->prepare(
            "SELECT COUNT(1) FROM {$this->table} as p "
                . (!empty($this->query['joins']) ? $this->query['joins'] : '')
                . $this->getWhereSql()
                . (isset($this->query['group']) ? ' GROUP BY ' . trim($this->query['group']) : '')
                . (isset($this->query['having']) ? ' HAVING ' . trim($this->query['having']) : '')
        );
        $this->bindWhere($statement);
        $statement->execute();
        return $statement->fetch(PDO::FETCH_COLUMN);
    }

    private function resetQuery(): void
    {
        $this->query = ['sql' => '', 'joins' => '', 'join_num' => 0];
        $this->resetWhere();
    }
}
