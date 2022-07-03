<?php
namespace UMySQL;

use UMySQL\Exceptions\ConnectionException;
use UMySQL\Exceptions\ParseException;
use UMySQL\Exceptions\QueryException;
use mysqli;
use mysqli_sql_exception;
use function count;
use function floor;
use function gettype;
use function implode;
use function is_float;
use function is_int;
use function is_iterable;
use function is_scalar;
use function is_string;
use function preg_match;
use function preg_split;
use function str_replace;
use function strstr;
use const PREG_SPLIT_DELIM_CAPTURE;

/**
 * Uncomplicated MySQL Abstraction Layer (UMySQL)
 * 
 * Supported placeholders are:
 * - `?s` for strings, decimals and dates
 * - `?i` for integers
 * - `?n` for identifiers (table and column names)
 * - `?a` for arrays of strings
 * - `?u` for maps (associative arrays), useful in UPDATE queries
 * - `?p` for already parsed query parts
 * 
 * Some common examples:
 * ```
 * $db->parse('SELECT * FROM movies');
 * // SELECT * FROM movies
 * 
 * $db->parse('SELECT * FROM ?n WHERE username=?s AND points>=?i', 'users', 'nick', 100);
 * // SELECT * FROM `users` WHERE username='Kevin' AND points>=100
 * 
 * $db->parse('SELECT * FROM products WHERE id IN (?a)', [10, null, 30]);
 * // SELECT * FROM products WHERE id IN ('10', NULL, '30')
 * 
 * $db->parse('INSERT INTO metrics SET ?u', ['rtt' => 132.22, 'unit' => 'ms']);
 * // INSERT INTO metrics SET `rtt`='132.22', `unit`='ms'
 * 
 * $db->parse('SELECT * FROM places WHERE city=?s ORDER BY ?n ?p', 'London', 'name', 'ASC');
 * // SELECT * FROM places WHERE city='London' ORDER BY `name` ASC
 * ```
 */
class UMySQL {
    /** @var mysqli */
    protected $conn;

    /** @var boolean */
    protected $closed = false;

    /**
     * Instance constuctor
     * 
     * Example usage:
     * ```
     * // Using connection options
     * $db = new UMySQL([
     *   'hostname' => 'localhost',
     *   'username' => 'root',
     *   'password' => '',
     *   'database' => 'blog',
     *   'charset' => 'utf8mb4'
     * ]);
     * 
     * // Using an existent connection
     * $db = new UMySQL(mysqli_connect('localhost', 'root', '', 'blog'));
     * ```
     * 
     * @param mysqli|array{
     *   hostname?: string,
     *   host?: string,
     *   username?: string,
     *   user?: string,
     *   password?: string,
     *   pass?: string,
     *   database?: string,
     *   db?: string,
     *   port?: int,
     *   socket?: string,
     *   charset?: string
     * } $opts Connection instance or options
     * @throws ConnectionException if failed to connect to database
     */
    public function __construct($opts = []) {
        // Wrap existing instance
        if ($opts instanceof mysqli) {
            $this->conn = $opts;
            return;
        }

        try {
            // Try to connect to database
            $this->conn = @new mysqli(
                $opts['hostname'] ?? $opts['host'] ?? 'localhost',
                $opts['username'] ?? $opts['user'] ?? 'root',
                $opts['password'] ?? $opts['pass'] ?? '',
                $opts['database'] ?? $opts['db'] ?? '',
                $opts['port'] ?? 3306,
                $opts['socket'] ?? null
            );
            $errNumber = $this->conn->connect_errno;
            if ($errNumber) {
                throw new ConnectionException("[$errNumber] {$this->conn->connect_error}", $errNumber);
            }

            // Set charset
            @$this->conn->set_charset($opts['charset'] ?? 'utf8mb4');
            $errNumber = $this->conn->errno;
            if ($errNumber) {
                throw new ConnectionException("[$errNumber] {$this->conn->error}", $errNumber);
            }
        } catch (mysqli_sql_exception $e) { // Handle failure in case of strict report mode (MYSQLI_REPORT_STRICT)
            $errNumber = $e->getCode();
            throw new ConnectionException("[$errNumber] {$e->getMessage()}", $errNumber);
        }
    }

    /**
     * Instance destructor
     * 
     * @return void
     */
    public function __destruct() {
        $this->disconnect();
    }

    /**
     * Disconnect from database
     * 
     * NOTE: after calling this method on an instance, it will become unusable.
     */
    public function disconnect(): void {
        // Prevent disconnecting multiple times
        if ($this->closed) {
            return;
        }
        $this->closed = true;

        // Close connection
        $this->conn->close();
    }

    /**
     * Parse query with placeholders
     *
     * @param  string $query     Query with placeholders
     * @param  mixed  ...$params Values for placeholders
     * @return string            Parsed query
     * @throws ParseException if failed to bind parameters to placeholders
     */
    public function parse(string $query, ...$params): string {
        $parts = preg_split('/(\?[sinaup])/u', $query, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            throw new ParseException('Failed to extract placeholders from query');
        }

        // Validate number of placeholders
        $numOfPlaceholders = floor(count($parts) / 2);
        $numOfParams = count($params);
        if ($numOfPlaceholders != $numOfParams) {
            throw new ParseException("Expected $numOfPlaceholders parameters, found $numOfParams instead");
        }

        // Bind parameters to placeholders
        for ($i=0; $i<$numOfParams; $i++) {
            $placeholder =& $parts[$i*2+1];
            $value =& $params[$i];
            switch ($placeholder) {
                case '?s':
                    $placeholder = $this->escapeString($value);
                    break;
                case '?i':
                    $placeholder = $this->escapeInteger($value);
                    break;
                case '?n':
                    $placeholder = $this->escapeIdentifier($value);
                    break;
                case '?a':
                    $placeholder = $this->escapeArray($value);
                    break;
                case '?u':
                    $placeholder = $this->escapeMap($value);
                    break;
                case '?p':
                    $placeholder = $this->escapePart($value);
                    break;
            }
        }

        // Rebuild query
        return implode('', $parts);
    }

    /**
     * Get escaped string (?s)
     *
     * @param  mixed  $input Unsafe string
     * @return string        Escaped (and quoted) string
     * @throws ParseException if input cannot be cast to string
     */
    protected function escapeString($input): string {
        if ($input === null) {
            return 'NULL';
        }
        if (!is_scalar($input)) {
            throw new ParseException('Expected scalar value for string placeholder, found ' . gettype($input) . ' instead');
        }
        return "'" . $this->conn->real_escape_string("$input") . "'";
    }

    /**
     * Get escaped integer (?i)
     *
     * @param  mixed  $input Unsafe integer
     * @return string        Escaped integer
     * @throws ParseException if not a numeric input
     */
    protected function escapeInteger($input): string {
        if ($input === null) {
            return 'NULL';
        }

        // Is input already an integer?
        if (is_int($input)) {
            return (string) $input;
        }

        // Is input a float?
        if (is_float($input)) {
            $input = (string) $input;
            $output = strstr($input, '.', true);
            return ($output === false) ? $input : $output;
        }

        // Is input a parseable numeric string?
        if (is_string($input) && preg_match('/^-?[0-9]+(\.([0-9]+)?)?$/', $input)) {
            $output = strstr($input, '.', true);
            return ($output === false) ? $input : $output;
        }

        // Failed to parse input
        throw new ParseException('Expected numeric value for integer placeholder, found ' . gettype($input) . ' instead');
    }

    /**
     * Get escaped identifier (?n)
     *
     * @param  mixed  $input Unsafe identifier
     * @return string        Escaped identifier
     * @throws ParseException if input cannot be cast to string, is empty or contains invalid characters
     */
    protected function escapeIdentifier($input): string {
        if (!is_scalar($input)) {
            throw new ParseException('Expected scalar value for identifier placeholder, found ' . gettype($input) . ' instead');
        }

        // Validate string value of identifier
        $output = (string) $input;
        if ($output === '') {
            throw new ParseException('Identifier value cannot be empty');
        }
        if (preg_match('/^[\x{0001}-\x{ffff}]+$/u', $output) !== 1) {
            throw new ParseException('Identifier contains invalid characters');
        }

        // Add quotes
        $output = '`' . str_replace('`', '``', $output) . '`';
        return $output;
    }

    /**
     * Get escaped part (?p)
     *
     * @param  mixed  $input Unsafe part
     * @return string        Escaped (yet still unsafe) part
     * @throws ParseException if input cannot be cast to string
     */
    protected function escapePart($input): string {
        if (!is_scalar($input)) {
            throw new ParseException('Expected scalar value for part placeholder, found ' . gettype($input) . ' instead');
        }
        return (string) $input;
    }

    /**
     * Get escaped array (?a)
     *
     * @param  mixed  $input Unsafe array
     * @return string        Escaped array
     * @throws ParseException if input is empty or not iterable
     */
    protected function escapeArray($input): string {
        if (empty($input)) {
            throw new ParseException('Array value cannot be empty');
        }
        if (!is_iterable($input)) {
            throw new ParseException('Expected iterable value for array placeholder, found ' . gettype($input) . ' instead');
        }

        // Escape each array item
        $output = [];
        foreach ($input as &$item) {
            $output[] = $this->escapeString($item);
        }

        // Build array
        return implode(', ', $output);
    }

    /**
     * Get escaped map (?u)
     *
     * @param  mixed  $input Unsafe map
     * @return string        Escaped map
     * @throws ParseException if input is empty or not iterable
     */
    protected function escapeMap($input): string {
        if (empty($input)) {
            throw new ParseException('Map value cannot be empty');
        }
        if (!is_iterable($input)) {
            throw new ParseException('Expected iterable value for map placeholder, found ' . gettype($input) . ' instead');
        }

        // Escape each map pair
        $output = [];
        foreach ($input as $key=>&$value) {
            $output[] = $this->escapeIdentifier($key) . '=' . $this->escapeString($value);
        }

        // Build map
        return implode(', ', $output);
    }

    /**
     * Execute query
     *
     * @param  string $query     Query with placeholders
     * @param  mixed  ...$params Values for placeholders
     * @return Result            Query result instance
     * @throws ParseException if failed to bind parameters to placeholders
     * @throws QueryException if failed to execute query
     */
    public function query(string $query, ...$params): Result {
        try {
            $result = @$this->conn->query($this->parse($query, ...$params));
            if ($result === false) {
                throw new QueryException("[{$this->conn->errno}] {$this->conn->error}", $this->conn->errno);
            }
            return new Result($this->conn, $result);
        } catch (mysqli_sql_exception $e) { // Handle failure in case of strict report mode (MYSQLI_REPORT_STRICT)
            $errNumber = $e->getCode();
            throw new QueryException("[$errNumber] {$e->getMessage()}", $errNumber);
        }
    }

    /**
     * Get all result rows
     *
     * @param  string                      $query     Query with placeholders
     * @param  mixed                       ...$params Values for placeholders
     * @return array<string,string|null>[]            Result rows
     * @throws ParseException if failed to bind parameters to placeholders
     * @throws QueryException if failed to execute query
     */
    public function getAll(string $query, ...$params): array {
        $result = $this->query($query, ...$params);
        $rows = [];
        while ($row = $result->fetchRow()) {
            $rows[] = $row;
        }
        $result->free();
        return $rows;
    }

    /**
     * Get first result row
     *
     * @param  string                         $query     Query with placeholders
     * @param  mixed                          ...$params Values for placeholders
     * @return array<string,string|null>|null            First result row or `null` if no rows found
     * @throws ParseException if failed to bind parameters to placeholders
     * @throws QueryException if failed to execute query
     */
    public function getRow(string $query, ...$params): ?array {
        $result = $this->query($query, ...$params);
        $row = $result->fetchRow();
        $result->free();
        return ($row === false) ? null : $row;
    }

    /**
     * Get first column of result rows
     *
     * @param  string          $query     Query with placeholders
     * @param  mixed           ...$params Values for placeholders
     * @return (string|null)[]            First column of result rows
     * @throws ParseException if failed to bind parameters to placeholders
     * @throws QueryException if failed to execute query
     */
    public function getCol(string $query, ...$params): array {
        $result = $this->query($query, ...$params);
        $column = [];
        while (($scalar = $result->fetchColumn()) !== false) {
            $column[] = $scalar;
        }
        $result->free();
        return $column;
    }

    /**
     * Get first scalar from result rows
     *
     * @param  string            $query     Query with placeholders
     * @param  mixed             ...$params Values for placeholders
     * @return string|null|false            First column of the first result row or `false` if no rows found
     * @throws ParseException if failed to bind parameters to placeholders
     * @throws QueryException if failed to execute query
     */
    public function getOne(string $query, ...$params) {
        $result = $this->query($query, ...$params);
        $scalar = $result->fetchColumn();
        $result->free();
        return $scalar;
    }
}
