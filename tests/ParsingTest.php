<?php
namespace UMySQL\Tests;

use UMySQL\Exceptions\ParseException;

final class ParsingTest extends BaseTest {
    public function testCanParseQueriesWithoutPlaceholders(): void {
        $this->assertEquals(
            'SELECT * FROM table_name WHERE field=123',
            self::$db->parse('SELECT * FROM table_name WHERE field=123')
        );
        $this->assertEquals(
            'SHOW PROCESSLIST();',
            self::$db->parse('SHOW PROCESSLIST();')
        );
        $this->assertEquals(
            "SELECT * FROM `weird_name?` WHERE aa='?w' AND bb=?I",
            self::$db->parse("SELECT * FROM `weird_name?` WHERE aa='?w' AND bb=?I")
        );
    }

    public function testCannotParseQueriesWithInvalidNumberOfPlaceholders(): void {
        $this->expectExceptionForAll(ParseException::class, function($params) {
            self::$db->parse('SELECT a FROM ?n WHERE id=?i', ...$params);
        }, [
            [],
            ['table_name'],
            ['table_name', 1, 2],
        ]);

        $this->expectExceptionForAll(ParseException::class, function($params) {
            self::$db->parse('SELECT * FROM something', ...$params);
        }, [
            ['a_string'],
            [1, 2, 3],
        ]);
    }

    public function testCanParseQueriesWithStringPlaceholders(): void {
        $this->assertEquals(
            "SELECT * FROM a WHERE b LIKE 'something something' ORDER BY c",
            self::$db->parse('SELECT * FROM a WHERE b LIKE ?s ORDER BY c', 'something something')
        );
        $this->assertEquals(
            "SELECT * FROM a WHERE b='aaaa' OR b=NULL OR b='null' ORDER BY c",
            self::$db->parse('SELECT * FROM a WHERE b=?s OR b=?s OR b=?s ORDER BY c', 'aaaa', null, 'null')
        );
        $this->assertEquals(
            "SELECT * FROM a WHERE b='1234' OR b='43.21' OR b='1' OR b=''",
            self::$db->parse('SELECT * FROM a WHERE b=?s OR b=?s OR b=?s OR b=?s', 1234, 43.21, true, false)
        );
        $this->assertEquals(
            "SELECT * FROM a WHERE b='0' OR b='0' OR b='-0' OR b='0.123' OR b='-0.123'",
            self::$db->parse('SELECT * FROM a WHERE b=?s OR b=?s OR b=?s OR b=?s OR b=?s', 0, -0, -0., 0.123, -0.123)
        );
        $this->assertEquals(
            "SELECT * FROM a WHERE b LIKE 'A \\\"quoted\\\" word\\nAnother \\'quoted\\' word' ORDER BY c",
            self::$db->parse('SELECT * FROM a WHERE b LIKE ?s ORDER BY c', "A \"quoted\" word\nAnother 'quoted' word")
        );
        $this->assertEquals(
            "SELECT * FROM a WHERE b='//Slashes\\\\ and \\0 NULL bytes'",
            self::$db->parse('SELECT * FROM a WHERE b=?s', "//Slashes\\ and \0 NULL bytes")
        );
        $this->assertEquals(
            "SELECT * FROM a WHERE b='😁' AND c='👨🏽‍🦳\\'🚲' ",
            self::$db->parse('SELECT * FROM a WHERE b=?s AND c=?s ', '😁', "👨🏽‍🦳'🚲")
        );
    }

    public function testCannotParseQueriesWithInvalidStringParameters(): void {
        $this->expectExceptionForAll(ParseException::class, function($value) {
            self::$db->parse('SELECT * FROM a WHERE something=?s', $value);
        }, [
            new class() {},
            $this,
            [1, 2, 3],
        ]);
    }

    public function testCanParseQueriesWithIntegerPlaceholders(): void {
        $this->assertEquals(
            'SELECT * FROM a WHERE (b BETWEEN -100 AND 200) AND c=300 AND d=NULL',
            self::$db->parse('SELECT * FROM a WHERE (b BETWEEN ?i AND ?i) AND c=?i AND d=?i', -100, 200, '300', null)
        );
        $this->assertEquals(
            'SELECT * FROM a WHERE b=12 AND c=67 AND d=-432',
            self::$db->parse('SELECT * FROM a WHERE b=?i AND c=?i AND d=?i', 12.34, 67.999999, -432.999999)
        );
        $this->assertEquals(
            'SELECT * FROM a WHERE b=12 AND c=67 AND d=-432',
            self::$db->parse('SELECT * FROM a WHERE b=?i AND c=?i AND d=?i', '12.34', '67.999999', '-432.999999')
        );
        $this->assertEquals(
            'SELECT * FROM a WHERE b<=5000 AND c>1450000',
            self::$db->parse('SELECT * FROM a WHERE b<=?i AND c>?i', 5e3, 1.45e6)
        );
        $this->assertEquals(
            'SELECT * FROM a WHERE b=0 OR b=0 OR c=0 OR c=-0 OR d=0 OR d=-0',
            self::$db->parse('SELECT * FROM a WHERE b=?i OR b=?i OR c=?i OR c=?i OR d=?i OR d=?i', 0, -0, 0., -0., 0.9, -0.9)
        );
        $this->assertEquals(
            'SELECT * FROM a WHERE b=0 OR b=-0 OR c=0 OR c=-0',
            self::$db->parse('SELECT * FROM a WHERE b=?i OR b=?i OR c=?i OR c=?i', '0.', '-0.', '0.9', '-0.9')
        );
        $this->assertEquals(
            'SELECT * FROM a WHERE b=10 OR b=-123456789123456789123456789123456789',
            self::$db->parse('SELECT * FROM a WHERE b=?i OR b=?i', 10, '-123456789123456789123456789123456789')
        );
    }

    public function testCannotParseQueriesWithInvalidIntegerParameters(): void {
        $this->expectExceptionForAll(ParseException::class, function($value) {
            self::$db->parse('SELECT * FROM a WHERE something=?i', $value);
        }, [
            new class() {},
            $this,
            [1, 2, 3],
            true,
            false,
            'NOT numeric',
            '123 ',
            ' 123',
            '0x1234',
            '0b110011011',
            '-5e2',
            '2.65e3',
            '',
            '    ',
        ]);
    }

    public function testCanParseQueriesWithIdentifierPlaceholders(): void {
        $this->assertEquals(
            "SELECT * FROM `table_name` WHERE id=123 OR `1`='hey' OR `0`='ho'",
            self::$db->parse('SELECT * FROM ?n WHERE id=?i OR ?n=?s OR ?n=?s', 'table_name', 123, true, 'hey', 0, 'ho')
        );
        $this->assertEquals(
            'SELECT * FROM `Table Name` WHERE `"field"`=123 AND `a``b`=321',
            self::$db->parse('SELECT * FROM ?n WHERE ?n=?i AND ?n=?i', 'Table Name', '"field"', 123, 'a`b', 321)
        );
    }

    public function testCannotParseQueriesWithInvalidIdentifierParameters(): void {
        $this->expectExceptionForAll(ParseException::class, function($value) {
            self::$db->parse('SELECT * FROM a WHERE ?n=?i', $value, 123);
        }, [
            new class() {},
            $this,
            [1, 2, 3],
            false,
            null,
            '',
            "ab\0cd",
            '🍣',
        ]);
    }

    public function testCanParseQueriesWithArrayPlaceholders(): void {
        $this->assertEquals(
            "SELECT * FROM a WHERE id IN ('0', '1', '-12.34', 'hey', NULL, 'a\\'b')",
            self::$db->parse('SELECT * FROM a WHERE id IN (?a)', [0, 1, -12.34, 'hey', null, "a'b"])
        );
        $this->assertEquals(
            "SELECT * FROM a WHERE id IN ('0')",
            self::$db->parse('SELECT * FROM a WHERE id IN (?a)', [0])
        );
        $this->assertEquals(
            "SELECT * FROM a WHERE id IN (NULL)",
            self::$db->parse('SELECT * FROM a WHERE id IN (?a)', [null])
        );
        $this->assertEquals(
            "SELECT * FROM a WHERE id IN ('1', '2', '3')",
            self::$db->parse('SELECT * FROM a WHERE id IN (?a)', [
                'one' => 1,
                'two' => '2',
                'three' => 3,
            ])
        );
    }

    public function testCannotParseQueriesWithInvalidArrayParameters(): void {
        $this->expectExceptionForAll(ParseException::class, function($value) {
            self::$db->parse('SELECT * FROM a WHERE id IN (?a)', $value);
        }, [
            new class() {},
            $this,
            [],
            true,
            false,
            null,
            '',
            1,
            'abc',
        ]);
    }

    public function testCanParseQueriesWithMapPlaceholders(): void {
        $this->assertEquals(
            "UPDATE a SET `one`=1, `two`='2', `three`='3.25', `This is Null`=NULL",
            self::$db->parse('UPDATE a SET ?u', [
                'one' => 1,
                'two' => '2',
                'three' => 3.25,
                'This is Null' => null,
            ])
        );
        $this->assertEquals(
            "UPDATE a SET `0`=0, `1`=1, `2`='hey', `3`=NULL, `4`='a\\'b'",
            self::$db->parse('UPDATE a SET ?u', [0, 1, 'hey', null, "a'b"])
        );
    }

    public function testCannotParseQueriesWithInvalidMapParameters(): void {
        $this->expectExceptionForAll(ParseException::class, function($value) {
            self::$db->parse('UPDATE a SET ?u', $value);
        }, [
            new class() {},
            $this,
            [],
            true,
            false,
            null,
            '',
            1,
            'abc',
            ['a' => '1234', 'b' => $this],
            ['valid_identifier' => 'abc', "invalid\0identifier" => 'def'],
        ]);
    }

    public function testCanParseQueriesWithPartPlaceholders(): void {
        $this->assertEquals(
            'SELECT * FROM a ORDER BY id DESC',
            self::$db->parse('SELECT * FROM a ORDER BY id ?p', 'DESC')
        );
        $this->assertEquals(
            'SELECT * FROM a WHERE id = 123 LIMIT 10',
            self::$db->parse('SELECT * FROM a WHERE ?p LIMIT ?i', 'id = 123', 10)
        );
        $this->assertEquals(
            'SELECT * FROM a WHERE id=123  ORDER BY id  LIMIT 100',
            self::$db->parse('SELECT * FROM a WHERE id=?i ?p ORDER BY id ?p LIMIT ?p', 123, '', false, 100)
        );
    }

    public function testCannotParseQueriesWithInvalidPartParameters(): void {
        $this->expectExceptionForAll(ParseException::class, function($value) {
            self::$db->parse('SELECT * FROM a WHERE ?p AND id=?i', $value, 123);
        }, [
            new class() {},
            $this,
            [1, 2, 3],
            null,
        ]);
    }
}
