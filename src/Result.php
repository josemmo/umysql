<?php
namespace UMySQL;

use mysqli;
use mysqli_result;
use const PHP_VERSION_ID;

class Result {
    /** @var mysqli_result|true */
    protected $result;

    /** @var string */
    protected $rowCount;

    /** @var string */
    protected $insertId;

    /** @var boolean */
    protected $closed = false;

    /**
     * Instance constructor
     * 
     * @param mysqli             $conn   MySQLi connection
     * @param mysqli_result|true $result MySQLi query result
     */
    public function __construct(mysqli $conn, $result) {
        $this->result = $result;
        $this->rowCount = ($result === true) ? (string) $conn->affected_rows : (string) $result->num_rows;
        $this->insertId = (string) $conn->insert_id;
    }

    /**
     * Instance destructor
     * 
     * @return void
     */
    public function __destruct() {
        $this->free();
    }

    /**
     * Fetch next row
     *
     * @return array<string,string|null>|false Row values casted as strings or `false` if no more rows
     */
    public function fetchRow() {
        if ($this->result === true) {
            return false;
        }
        return $this->result->fetch_assoc() ?? false;
    }

    /**
     * Fetch first column from next row
     *
     * @return string|null|false Column value or `false` if no more rows
     */
    public function fetchColumn() {
        if ($this->result === true) {
            return false;
        }

        // Are we running PHP >=8.1?
        if (PHP_VERSION_ID >= 80100) {
            $value = $this->result->fetch_column();
            return ($value === null || $value === false) ? $value : (string) $value;
        }

        // Fallback to traditional approach
        /** @var string[]|null|false */
        $row = $this->result->fetch_row();
        return ($row === null || $row === false) ? false : $row[0];
    }

    /**
     * Get number of affected rows
     *
     * @return string Number of affected rows casted as a string
     */
    public function rowCount(): string {
        return $this->rowCount;
    }

    /**
     * Get last insert ID value for this query
     *
     * @return string Insert ID casted as a string, `0` for N/A
     */
    public function insertId(): string {
        return $this->insertId;
    }

    /**
     * Free results from memory
     */
    public function free(): void {
        // Prevent freeing multiple times
        if ($this->closed) {
            return;
        }
        $this->closed = true;

        // Free associated resource (if any)
        if ($this->result !== true) {
            $this->result->free();
        }
    }
}
