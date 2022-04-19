<?php
/**
 *
 *
 * All rights reserved.
 *
 * @author Okulov Anton
 * @email qantus@mail.ru
 * @version 1.0
 * @date 10/04/16 10:14
 */

namespace Phact\Tests\Cases\Orm\Mysql;

use Phact\Tests\Cases\Orm\Abs\AbstractTableTest;

class MysqlTableTest extends AbstractTableTest
{
    protected array $expectedConstraint = [
        'movie' => [
            'onUpdate' => 'CASCADE',
            'onDelete' => null
        ],

        'genre' => [
            'onUpdate' => 'CASCADE',
            'onDelete' => 'NO ACTION'
        ],

        'film_company' => [
            'onUpdate' => 'CASCADE',
            'onDelete' => 'SET NULL'
        ],
    ];
}