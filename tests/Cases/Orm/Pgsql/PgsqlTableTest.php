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

namespace Phact\Tests\Cases\Orm\Pgsql;

use Phact\Tests\Cases\Orm\Abs\AbstractTableTest;

class PgsqlTableTest extends AbstractTableTest
{
    protected $defaultConnection = 'pgsql';

    protected array $expectedConstraint = [
        'movie' => [
            'onUpdate' => 'CASCADE',
            'onDelete' => 'RESTRICT'
        ],

        'genre' => [
            'onUpdate' => 'CASCADE',
            'onDelete' => null
        ],

        'film_company' => [
            'onUpdate' => 'CASCADE',
            'onDelete' => 'SET NULL'
        ],
    ];
}