# Uncomplicated MySQL
[![Build Status](https://github.com/josemmo/umysql/actions/workflows/ci.yml/badge.svg)](https://github.com/josemmo/umysql/actions)
[![Latest Version](https://img.shields.io/packagist/v/josemmo/umysql)](https://packagist.org/packages/josemmo/umysql)
[![Minimum PHP Version](https://img.shields.io/packagist/php-v/josemmo/umysql)](#installation)
[![License](https://img.shields.io/github/license/josemmo/umysql)](LICENSE)

UMySQL is an *extremely* simple PHP library for communicating with MySQL databases with ease while keeping overhead to
a bare minimum. It aims to be an almost 1-to-1 and modern replacement for [SafeMySQL](https://github.com/colshrapnel/safemysql).

It doesn't provide any ORM, migration, events, caching, *etc.* functionality: Just the bare minimum to get you started.

## Installation
First of all, make sure your environment meets the following requirements:

- PHP 7.1 or higher
- [MySQLi extension](https://www.php.net/manual/en/mysqli.installation.php)

Then, you should be able to install this library using Composer:
```
composer require josemmo/umysql
```

## Usage

### Creating a new instance
Typically, you'll want to create a new database instance using connection options:
```php
$db = new UMySQL([
  'hostname' => '127.0.0.1', // Defaults to "localhost"
  'username' => 'app',       // Defaults to "root"
  'password' => 'S3cret',    // Defaults to "" (empty string)
  'database' => 'blog',      // Defaults to none selected
  'port'     => 3306,        // Defaults to 3306
  'charset'  => 'utf8mb4'    // Defaults to "utf8mb4"
]);
```

You can also connect to a UNIX socket:
```php
$db = new UMySQL([
    'socket'   => '/run/mysqld/mysqld.sock',
    'username' => 'root',
    'password' => 'toor',
]);
```

As an alternative to options, you can wrap a `mysqli` instance around a database connection:
```php
$db = new UMySQL(mysqli_connect('localhost', 'root', '', 'blog'));
```

### Writing queries
UMySQL supports various placeholders to safely replace values into queries:

- `?s` for strings, decimals and dates
- `?i` for integers
- `?n` for identifiers (table and column names)
- `?a` for arrays of strings
- `?u` for maps (associative arrays), useful in UPDATE queries
- `?p` for already parsed query parts

Here are some common examples on how to use them:
```php
$db->parse('SELECT * FROM movies');
// SELECT * FROM movies

$db->parse('SELECT * FROM ?n WHERE username=?s AND points>=?i', 'users', 'nick', 100);
// SELECT * FROM `users` WHERE username='nick' AND points>=100

$db->parse('SELECT * FROM products WHERE id IN (?a)', [10, null, 30]);
// SELECT * FROM products WHERE id IN ('10', NULL, '30')

$db->parse('INSERT INTO metrics SET ?u', ['rtt' => 132.22, 'unit' => 'ms', 'verified' => 1]);
// INSERT INTO metrics SET `rtt`='132.22', `unit`='ms', `verified`=1

$db->parse('SELECT * FROM places WHERE city=?s ORDER BY ?n ?p', 'London', 'name', 'ASC');
// SELECT * FROM places WHERE city='London' ORDER BY `name` ASC
```

### Fetching results
The database instance comes with built-in helpers for retrieving rows from the database in a straightforward manner:

- `$db->getAll()` to get all rows in a result set
- `$db->getRow()` to get only the first row or `null` in case of an empty result set
- `$db->getCol()` to get the values from the first column of a result set
- `$db->getOne()` to get the first column from the first row or `false` in case of an empty result set

Some examples are:
```php
$movies = $db->getAll('SELECT title, year FROM movies');
// [['title' => '...', 'year' => '...'], ['title' => '...', 'year' => '...'], ...]

$product = $db->getRow('SELECT * FROM products WHERE id=?i', 123);
// ['name' => '...', 'price' => '...']

$metrics = $db->getCol('SELECT rtt FROM metrics WHERE created_at>=?s', gmdate('Y-m-d 00:00:00'));
// ['112.12', '128.93', '120.66', '119.34', ...]

$userId = $db->getOne('SELECT id FROM users WHERE username=?s', 'some-username');
// '123'
```

### Executing other queries
For non-SELECT and more advanced queries, UMySQL has a `$db->query()` method that returns a custom `Result` instance.

Typically, you'll use this method when you don't care about the result of an operation or when there's no result set:
```php
$db->query('TRUNCATE metrics');
// [\UMySQL\Result]
```

Result instances are also useful in UPDATE/DELETE operations to get the number of affected rows:
```php
$affectedRows = $db->query('DELETE FROM users WHERE banned=1')->rowCount();
// '123'
```

Similarly, you can get the last insert ID of an auto-increment column in INSERT operations:
```php
$productId = $db->query('INSERT INTO products (name, price) VALUES (?s, ?s)', 'Something', 12.34)->insertId();
// '321'
```

These instances can also be used to read a result set at your own pace:
```php
$result = $db->query('SELECT * FROM large_table');
while ($row = $result->fetchRow()) {
    // Do something with `$row`
}
$result->free(); // Optional, will get called after `unset($result)`
```

## Running the test suite
If you want to contribute to this project, please make sure to run the tests before committing new changes.

Tests are run against a MySQL database, so you'll need to define the following environment variables beforehand:

- `DB_HOSTNAME`
- `DB_USERNAME`
- `DB_PASSWORD` (optional)
- `DB_DATABASE`
