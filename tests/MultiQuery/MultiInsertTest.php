<?php declare(strict_types=1);

namespace Composite\DB\Tests\MultiQuery;

use Composite\DB\MultiQuery\MultiInsert;

class MultiInsertTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider multiInsertQuery_dataProvider
     */
    public function test_multiInsertQuery($tableName, $rows, $expectedSql, $expectedParameters)
    {
        $multiInserter = new MultiInsert($tableName, $rows);

        $this->assertEquals($expectedSql, $multiInserter->sql);
        $this->assertEquals($expectedParameters, $multiInserter->parameters);
    }

    public static function multiInsertQuery_dataProvider()
    {
        return [
            [
                'testTable',
                [],
                '',
                []
            ],
            [
                'testTable',
                [
                    ['a' => 'value1_1', 'b' => 'value2_1'],
                ],
                "INSERT INTO `testTable` (`a`, `b`) VALUES (:a0, :b0);",
                ['a0' => 'value1_1', 'b0' => 'value2_1']
            ],
            [
                'testTable',
                [
                    ['a' => 'value1_1', 'b' => 'value2_1'],
                    ['a' => 'value1_2', 'b' => 'value2_2']
                ],
                "INSERT INTO `testTable` (`a`, `b`) VALUES (:a0, :b0), (:a1, :b1);",
                ['a0' => 'value1_1', 'b0' => 'value2_1', 'a1' => 'value1_2', 'b1' => 'value2_2']
            ],
            [
                'testTable',
                [
                    ['column1' => 'value1_1'],
                    ['column1' => 123]
                ],
                "INSERT INTO `testTable` (`column1`) VALUES (:column10), (:column11);",
                ['column10' => 'value1_1', 'column11' => 123]
            ]
        ];
    }
}
