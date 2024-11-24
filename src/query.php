<?php

namespace hyper;

use hyper\utils\paginator;
use PDO;

/**
 * Class query
 *
 * This class provides methods to build and execute SQL queries for CRUD operations and 
 * joins in a structured and dynamic way.
 * 
 * @package hyper
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class query
{
    /**
     * Holds the SQL and bind parameters for the WHERE clause.
     * 
     * @var array
     */
    protected array $where = ['sql' => '', 'bind' => []];

    /**
     * Holds the SQL structure, join conditions, and join count.
     * 
     * @var array
     */
    protected array $query = ['sql' => '', 'joins' => '', 'join_num' => 0];

    /**
     * Array to store data mappers for processing retrieved data.
     * 
     * @var array
     */
    protected array $dataMapper = [];

    /**
     * Constructor to initialize the database connection and table name.
     * 
     * @param database $database Database connection instance.
     * @param string $table The table on which the query will be performed.
     */
    public function __construct(protected database $database, protected string $table)
    {
    }

    /**
     * Adds a data mapper callback to process query results.
     * 
     * @param callable $callback The callback function to process data.
     * @return self Returns the query object.
     */
    public function addMapper(callable $callback): self
    {
        $this->dataMapper[] = $callback;
        return $this;
    }

    /**
     * Inserts data into the database with optional configurations.
     * 
     * @param array $data The data to insert.
     * @param array $config Optional configurations ['ignore' => false, 'replace' => false, 'conflict' => ['id'], 'update' => []]
     * @return int
     */
    public function insert(array $data, array $config = []): int
    {
        // Ignore insert when data is empty.
        if (empty($data)) {
            return 0;
        }

        // Transform Single record into multiple.
        if (!(isset($data[0]) && is_array($data[0]))) {
            $data = [$data];
        }

        // Extract database tables fields from first record.
        $fields = array_keys($data[0]);

        // Create and run sql insert command.
        $statement = $this->database->prepare(
            sprintf(
                // sql insert command.
                "%s %s INTO {$this->table} (%s) VALUES %s %s;",

                // create or replace data into database 
                isset($config['replace']) && $config['replace'] === true ?
                'REPLACE' : 'INSERT',

                // use ignore when failed
                isset($config['ignore']) && $config['ignore'] === true ?
                ($this->database->config['driver'] === 'sqlite' ? 'OR IGNORE' : 'IGNORE') : '',

                // join all the database table field using "," comma.
                join(',', $fields),

                // use placeholder of records and bind value later, to avoid sql injection.
                $this->createPlaceholder($data),

                // bulk update database records on conflict.
                isset($config['update']) && !empty($config['update']) ?
                ($this->database->config['driver'] === 'sqlite' ?
                        // bulk update records when pdo driver is sqlite.
                    ('ON CONFLICT(' . join(',', ($config['conflict'] ?? ['id'])) . ') DO UPDATE SET ' . (join(
                        ', ',
                        array_map(
                            fn($key, $value) => sprintf('%s = excluded.%s', $key, $value),
                            array_keys($config['update']),
                            array_values($config['update'])
                        )
                    )))
                    // bulk update records when pdo driver is mysql.
                    : ('ON DUPLICATE KEY UPDATE ' . (join(
                        ', ',
                        array_map(
                            fn($key, $value) => sprintf('%s = VALUES(%s)', $key, $value),
                            array_keys($config['update']),
                            array_values($config['update'])
                        )
                    )))
                ) : ''
            )
        );

        // Bind records value into statement.
        foreach ($data as $serial => $row) {
            foreach ($fields as $column) {
                $statement->bindValue(
                    sprintf(':%s_%s', $column, $serial),
                    isset($row[$column]) && is_array($row[$column]) ?
                    ($row[$column]['text'] ?? null) : ($row[$column] ?? null)
                );
            }
        }

        // Execute insert query command.
        $statement->execute();

        // Returns the last inserted ID.
        return $this->database->pdo->lastInsertId();
    }

    /**
     * Update multiple records into the database with optional configurations.
     * 
     * @param array $data 
     * @param array $config 
     * @return int 
     */
    public function bulkUpdate(array $data, array $config = []): int
    {
        // Transfor single records into multiple.
        if (!(isset($data[0]) && is_array($data[0]))) {
            $data = [$data];
        }

        // Add default update close, if provided none.
        if (!isset($config['conflict'])) {
            $config['conflict'] = ['id'];
        }

        // Add default update fields, if provided none.
        if (!isset($config['update'])) {
            // Extract all fields except those are in $config['conflict'].
            $fields = array_filter(
                array_keys($data[0]),
                fn($field) => !in_array($field, $config['conflict'])
            );

            // Add extracted fields to be updated on conflict.
            $config['update'] = array_merge(...array_map(fn($field) => [$field => $field], $fields));
        }

        // Returns to base insert method. integer on success else, 0 on fails. 
        return $this->insert($data, $config);
    }

    /**
     * Adds a condition to the WHERE clause with a specified type (AND/OR).
     *
     * @param mixed $conditions The condition(s) to be applied, either as a string or array.
     * @param string $type The logical operator to join conditions (default is 'AND').
     * @return self
     */
    public function where(mixed $conditions = null, string $type = 'AND'): self
    {
        if ($conditions !== null) {
            return $this->addWhere($type, $conditions);
        }

        return $this;
    }

    /**
     * Adds an additional condition to the WHERE clause using the AND operator.
     *
     * @param mixed $conditions The condition(s) to be added to the WHERE clause.
     * @return self
     */
    public function andWhere(mixed $conditions): self
    {
        return $this->addWhere('AND', $conditions);
    }

    /**
     * Adds an additional condition to the WHERE clause using the OR operator.
     * 
     * @param mixed $conditions The condition(s) to be applied, either as a string or array.
     * @return self
     */
    public function orWhere(mixed $conditions): self
    {
        return $this->addWhere('OR', $conditions);
    }

    /**
     * Updates records in the database based on specified data and conditions.
     *
     * @param array $data  Key-value pairs of columns and their respective values to update.
     * @param mixed $where  Optional WHERE clause to specify which records to update.
     * @return bool
     */
    public function update(array $data, mixed $where = null): bool
    {
        // Apply WHERE condition if provided
        $this->where($where);

        // Abort if no WHERE condition is set to avoid accidental updates on all records
        if (!$this->hasWhere()) {
            return false;
        }

        // Prepare the SQL update statement
        $statement = $this->database->prepare(
            sprintf(
                "UPDATE `{$this->table}` SET %s %s",
                implode(', ', array_map(fn($attr) => "$attr=:$attr", array_keys($data))),
                $this->getWhereSql()
            )
        );

        // Bind the values for update
        foreach ($data as $key => $val) {
            $statement->bindValue(":$key", $val);
        }

        // Bind the WHERE clause parameters
        $this->bindWhere($statement);

        // Execute the statement and reset the WHERE clause
        $statement->execute();
        $this->resetWhere();

        // Returns true if records are successfully updated, false otherwise.
        return $statement->rowCount() > 0;
    }

    /**
     * Deletes records from the database based on specified conditions.
     *
     * @param mixed $where  Optional WHERE clause to specify which records to delete.
     * @return bool
     */
    public function delete(mixed $where = null): bool
    {
        // Apply WHERE condition if provided
        $this->where($where);

        // Abort if no WHERE condition is set to avoid accidental deletion of all records
        if (!$this->hasWhere()) {
            return false;
        }

        // Prepare the SQL delete statement
        $statement = $this->database->prepare("DELETE FROM {$this->table} {$this->getWhereSql()}");

        // Bind the WHERE clause parameters
        $this->bindWhere($statement);

        // Execute the statement and reset the WHERE clause
        $statement->execute();

        // Reset current query builder.
        $this->resetWhere();

        // Returns true if records are successfully deleted, false otherwise.
        return $statement->rowCount() > 0;
    }

    /**
     * Selects fields from the database.
     *
     * @param array|string $fields  List of fields to select, or '*' for all fields.
     * @return self 
     */
    public function select(array|string $fields = '*'): self
    {
        // Convert array of fields to a comma-separated string if necessary
        if (is_array($fields)) {
            $fields = implode(',', $fields);
        }

        // Build the initial SELECT SQL query
        $this->query['sql'] = "SELECT {$fields} FROM {$this->table} AS p";

        // Returns the current instance for method chaining.
        return $this;
    }

    /**
     * Adds an INNER JOIN clause to the query.
     *
     * @param string $table  The table to join.
     * @param string $condition  The join condition.
     * @return self
     */
    public function join(string $table, string $condition): self
    {
        return $this->addJoin('INNER', $table, $condition);
    }

    /**
     * Adds a LEFT JOIN clause to the query.
     *
     * @param string $table  The table to join.
     * @param string $condition  The join condition.
     * @return self
     */
    public function leftJoin(string $table, string $condition): self
    {
        return $this->addJoin('LEFT', $table, $condition);
    }

    /**
     * Adds a RIGHT JOIN clause to the query.
     *
     * @param string $table  The table to join.
     * @param string $condition  The join condition.
     * @return self
     */
    public function rightJoin(string $table, string $condition): self
    {
        return $this->addJoin('RIGHT', $table, $condition);
    }

    /**
     * Adds a CROSS JOIN clause to the query.
     *
     * @param string $table  The table to join.
     * @param string $condition  The join condition.
     * @return self
     */
    public function crossJoin(string $table, string $condition): self
    {
        return $this->addJoin('CROSS', $table, $condition);
    }

    /**
     * Sets the ordering clause for the query.
     *
     * @param ?string $sort Order by clause as a string (e.g., 'field ASC').
     * @return self
     */
    public function order(?string $sort = null): self
    {
        if ($sort !== null) {
            $this->query['order'] = $sort;
        }

        return $this;
    }

    /**
     * Sets ascending order for a specified field.
     *
     * @param string $field Field to order by in ascending order, defaults to 'p.id'.
     * @return self
     */
    public function orderAsc(string $field = 'p.id'): self
    {
        $this->query['order'] = $field . ' ASC';
        return $this;
    }

    /**
     * Sets descending order for a specified field.
     *
     * @param string $field Field to order by in descending order, defaults to 'p.id'.
     * @return self
     */
    public function orderDesc(string $field = 'p.id'): self
    {
        $this->query['order'] = $field . ' DESC';
        return $this;
    }

    /**
     * Sets the GROUP BY clause for the query.
     *
     * @param string $group Group by clause as a string.
     * @return self
     */
    public function group(string $group): self
    {
        $this->query['group'] = $group;
        return $this;
    }

    /**
     * Sets the HAVING clause for the query.
     *
     * @param string $having Having clause as a string.
     * @return self
     */
    public function having(string $having): self
    {
        $this->query['having'] = $having;
        return $this;
    }

    /**
     * Sets a limit and optional offset for the query.
     *
     * @param int|null $offset Starting point for the query, if specified.
     * @param int|null $limit Number of records to fetch.
     * @return self
     */
    public function limit(?int $offset = null, ?int $limit = null): self
    {
        if ($offset !== null) {
            $this->query['limit'] = sprintf(" %s%s", $offset, $limit !== null ? ", $limit" : '');
        }

        return $this;
    }

    /**
     * Specifies the fetch mode(s) for the query results.
     *
     * @param mixed ...$fetch PDO fetch styles (e.g., PDO::FETCH_ASSOC).
     * @return self
     */
    public function fetch(...$fetch): self
    {
        $this->query['fetch'] = $fetch;
        return $this;
    }

    /**
     * Retrieves the first result from the query.
     *
     * @return mixed
     */
    public function first()
    {
        // Execute current select query by limiting to single record.
        $this->limit(1)->executeSelectQuery();

        // Fetch first record from database and apply mapper if exists.
        $result = $this->applyMapper(
            $this->query['sql']
                ->fetchAll(
                    ...($this->query['fetch'] ?? [PDO::FETCH_OBJ])
                )
        );

        // Reset current query builder.
        $this->resetQuery();

        // The first result as an object or false if none found.
        return $result[0] ?? false;
    }

    /**
     * Retrieves the last result by applying descending order and fetching the first.
     *
     * @return mixed
     */
    public function last()
    {
        // The last result as an object or false if none found.
        return $this->orderDesc()->first();
    }

    /**
     * Retrieves the latest results by ordering in descending order.
     *
     * @return array
     */
    public function latest(): array
    {
        // Array of the latest results.
        return $this->orderDesc()->result();
    }

    /**
     * Retrieves all results from the executed query.
     *
     * @return array Array of query results.
     */
    public function result(): array
    {
        // Execute current sql swlwct command.
        $this->executeSelectQuery();

        // Fetch all results from database.
        $result = $this->query['sql']
            ->fetchAll(
                ...($this->query['fetch'] ?? [PDO::FETCH_OBJ])
            );

        // Reset current query builder.
        $this->resetQuery();

        // Apply data mapper if exists in current query.
        return $this->applyMapper($result);
    }

    /**
     * Paginates query results.
     *
     * @param int $limit Number of items per page.
     * @param string $keyword URL query parameter name for pagination.
     * @return paginator
     */
    public function paginate(int $limit = 10, string $keyword = 'page'): paginator
    {
        // Select records & Create a paginator object.
        if (empty($this->query['sql'])) {
            $this->select();
        }

        $paginator = new paginator($limit, $limit, $keyword, false);

        // Count total records from exisitng command only for serverside database driver.
        if ($this->database->config['driver'] !== 'sqlite') {
            $this->query['sql'] = preg_replace('/SELECT /', 'SELECT SQL_CALC_FOUND_ROWS ', $this->query['sql'], 1);
        }

        // Set pagination count to limit database records, and execute query.
        $this->limit(
            ceil($limit * ($paginator->getKeywordValue() - 1)),
            $limit
        )
            ->executeSelectQuery();

        // Get total record count, from sqlite database and update it to paginator class.
        if ($this->database->config['driver'] === 'sqlite') {
            $paginator->total = $this->count();
        } else {
            // Get number of records from exisitng query command.
            $total = $this->database->prepare('SELECT FOUND_ROWS()');
            $total->execute();

            // Update number of iterms into paginator class.
            $paginator->total = $total->fetch(PDO::FETCH_COLUMN);
        }

        // Set database records into paginator class.
        $paginator->setData(
            $this->applyMapper($this->query['sql']->fetchAll(...($this->query['fetch'] ?? [PDO::FETCH_OBJ])))
        );

        // Re-initialize paginator pages.
        $paginator->resetPaginator();

        // Reset current query builder.
        $this->resetQuery();

        // A paginator instance containing paginated results.
        return $paginator;
    }

    /**
     * Counts the number of rows matching the current query.
     *
     * @return int The number of matching rows.
     */
    public function count(): int
    {
        // Create sql command to count rows.
        $statement = $this->database->prepare(
            "SELECT COUNT(1) FROM {$this->table} AS p "
            . (!empty($this->query['joins']) ? $this->query['joins'] : '')
            . $this->getWhereSql()
            . (isset($this->query['group']) ? ' GROUP BY ' . trim($this->query['group']) : '')
            . (isset($this->query['having']) ? ' HAVING ' . trim($this->query['having']) : '')
        );

        // Apply where statement if exists.
        $this->bindWhere($statement);

        // Execute sql command.
        $statement->execute();

        // Returns number of found rows.
        return $statement->fetch(PDO::FETCH_COLUMN);
    }

    /** @Add helpers methods for this query builder class */

    /**
     * Applies all data mappers to a dataset.
     * 
     * @param array $data Data to process.
     * @return array Processed data after all mappers are applied.
     */
    protected function applyMapper(array $data): array
    {
        foreach ($this->dataMapper as $key => $mapper) {
            unset($this->dataMapper[$key]);
            $data = call_user_func($mapper, $data);
        }

        return $data;
    }

    /**
     * Adds a join clause to the query with a specified join type.
     *
     * @param string $type  The type of join (INNER, LEFT, RIGHT, CROSS).
     * @param string $table  The table to join.
     * @param string $condition  The join condition.
     * @return self
     */
    protected function addJoin(string $type, string $table, string $condition): self
    {
        // Generate an alias for the joined table
        $alias = sprintf('t%s', ++$this->query['join_num']);

        // Build and add the join clause to the SQL query
        $this->query['joins'] .= sprintf(
            " %s JOIN %s %s ON %s ",
            $type,
            $table,
            stripos($table, ' AS ') === false ? ' AS ' . $alias : '',
            $condition
        );

        // Returns the current instance for method chaining.
        return $this;
    }

    /**
     * Creates placeholders for SQL values in bulk insertions.
     * 
     * @param array $data The data array for placeholders.
     * @return string
     */
    protected function createPlaceholder(array $data): string
    {
        // Holds all records, to be going to inserted into the database.
        $values = [];

        // Add placeholders on each records, instead of actual value.
        foreach ($data as $serial => $row) {

            // Create dynamic placeholder, depands on parameters.
            $params = array_map(
                fn($attr, $value) => sprintf(
                    '%s:%s_%s%s',

                    // create placeholder from array value ex: ['prefix' => 'DATE('].
                    is_array($value) && isset ($value['prefix']) ?
                    $value['prefix'] : '',

                    // Placeholder main part.
                    $attr,
                    $serial,

                    // create placeholder from array value ex: ['suffix' => ')'].
                    is_array($value) && isset ($value['suffix']) ?
                    $value['suffix'] : ''
                ),
                array_keys($row),
                array_values($row)
            );

            // Push this record by "," comma into $values.
            $values[] = join(',', $params);
        }

        // Returns a string of placeholders for the SQL statement.
        return '(' . join('), (', $values) . ')';
    }

    /**
     * Executes a SELECT query with the built query parts.
     *
     * @return void
     */
    protected function executeSelectQuery(): void
    {
        // Prepare select command.
        if (empty($this->query['sql'])) {
            $this->select();
        }

        // Build complete select command with condition, order, and limit.
        $statement = $this->database->prepare(
            $this->query['sql']
            . $this->query['joins']
            . $this->getWhereSql()
            . (isset($this->query['group']) ? ' GROUP BY ' . trim($this->query['group']) : '')
            . (isset($this->query['having']) ? ' HAVING ' . trim($this->query['having']) : '')
            . (isset($this->query['order']) ? ' ORDER BY ' . trim($this->query['order']) : '')
            . (isset($this->query['limit']) ? ' LIMIT ' . trim($this->query['limit']) : '')
        );

        // Bind/Add conditions to filter records.
        $this->bindWhere($statement);

        // Execute current select command.
        $statement->execute();

        // Set select statement into query to modify dynamically.
        $this->query['sql'] = $statement;
    }

    /**
     * Helper method for adding conditions to the WHERE clause.
     *
     * @param string $method The method type, either 'AND' or 'OR', to join the conditions.
     * @param mixed $conditions The condition(s) to be added to the WHERE clause.
     * @return self
     */
    protected function addWhere(string $method, mixed $conditions): self
    {
        // Holds a conditional clouse for database.
        $command = '';

        if (is_array($conditions)) {
            // Create a where clouse from array conditions.
            $command = sprintf(
                "%s %s",
                $method,
                implode(
                    " {$method} ",
                    array_map(
                        fn($attr, $value) => $attr . (is_array($value) ?
                            // Create a where clouse to match IN(), Ex: "id IN(:id_0, :id_1, :id_2, :id_3)" .
                            sprintf(
                                " IN (%s)",
                                join(",", array_map(fn($index) => ':' . str_replace('.', '', $attr) . '_' . $index, array_keys($value)))
                            )
                            // Create a where close to match is equal, Ex. "id = :id_0"
                            : " = :" . str_replace('.', '', $attr)
                        ),
                        array_keys($conditions),
                        array_values($conditions)
                    )
                )
            );

            // Append where clouse binding values, safe & GOOD PDO practice.
            $this->where['bind'] = array_merge($this->where['bind'], $conditions);
        } elseif (is_string($conditions)) {
            // Simply add a where clouse from string.
            $command = "{$method} {$conditions}";
        }

        // Register the where clouse into current query builder.
        $this->where['sql'] .= sprintf(' %s ', empty($this->where['sql']) ? ltrim($command, $method . ' ') : $command);

        // Returns the current instance for method chaining.
        return $this;
    }

    /**
     * Checks if any conditions have been set in the WHERE clause.
     *
     * @return bool
     */
    protected function hasWhere(): bool
    {
        // Returns true if conditions are set, otherwise false.
        return !empty(trim($this->where['sql']));
    }

    /**
     * Generates the SQL string for the WHERE clause based on conditions added.
     *
     * @return string
     */
    protected function getWhereSql(): string
    {
        // Returns the SQL string for the WHERE clause.
        return $this->hasWhere() ? ' WHERE ' . trim($this->where['sql']) . ' ' : '';
    }

    /**
     * Binds the values of the WHERE clause conditions to the SQL statement.
     *
     * @param \PDOStatement $statement The prepared PDO statement to bind values.
     * @return void
     */
    protected function bindWhere(&$statement): void
    {
        // Bind where clouse values to filter records.
        foreach ($this->where['bind'] ?? [] as $param => $value) {
            /** 
             * Create a placeholder of the parameter exactly added into the where clouse.
             * Ex. "id = :id", ==> :id is the parameter.
             */
            $param = ':' . str_replace('.', '', $param);

            if (is_array($value)) {
                // binds clouse values from a array condition, Ex. "id IN(1, 2, 3, 4)".
                foreach ($value as $index => $val) {
                    // Add multiple parameter into IN(), Ex. :id_0 => $value, :id_1 => $value;
                    $statement->bindValue($param . '_' . $index, $val);
                }
            } else {
                // binds clouse values from a string condition, Ex. "id = 1".
                $statement->bindValue($param, $value);
            }
        }
    }

    /**
     * Resets the WHERE clause and clears any existing conditions.
     *
     * @return void
     */
    protected function resetWhere(): void
    {
        $this->where = ['sql' => '', 'bind' => []];
    }

    /**
     * Resets the query components for reuse.
     *
     * @return void
     */
    protected function resetQuery(): void
    {
        // Reset Select query parameters.
        $this->query = ['sql' => '', 'joins' => '', 'join_num' => 0];

        // Reset where query parameters.
        $this->resetWhere();
    }
}
