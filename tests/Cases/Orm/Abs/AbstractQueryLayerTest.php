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

namespace Phact\Tests\Cases\Orm\Abs;

use Modules\Test\Models\Note;
use Phact\Orm\Q;
use Phact\Orm\Query;
use Phact\Tests\Templates\DatabaseTest;

abstract class AbstractQueryLayerTest extends DatabaseTest
{
    public function useModels()
    {
        return [
            new Note()
        ];
    }

    public function testDBALQueryQParam()
    {
        $note = new Note();
        $note->name = 'Some note name';
        $note->save();

        $query = new Query();

        $queryBuilder = $query->getQueryBuilder();
        $queryBuilder->select(['id'])->from(Note::getTableName());
        $queryBuilder->where("id = ?");
        $queryBuilder->setParameter(0, $note->id);
        $this->assertEquals($queryBuilder->execute()->fetchAll(), [[
            'id' => '1'
        ]]);

        $queryBuilder = $query->getQueryBuilder();
        $queryBuilder->select(['id'])->from(Note::getTableName());
        $queryBuilder->where("id = " . $queryBuilder->createPositionalParameter($note->id));
        $this->assertEquals($queryBuilder->execute()->fetchAll(), [[
            'id' => '1'
        ]]);
    }

    public function testPrepareSubQuery()
    {
        $qs = Note::objects()->getQuerySet();
        $query = new Query();

        $srcQueryBuilder = $query->getQueryBuilder();
        $dstQueryBuilder = $query->getQueryBuilder();

        $srcQueryBuilder->from(Note::getTableName());
        $srcQueryBuilder->select(['id']);

        $srcQueryBuilder->where("id = ?");
        $srcQueryBuilder->setParameter(0, 12);

        $srcQueryBuilder->andWhere("name = :name");
        $srcQueryBuilder->setParameter('name', 'Some name');

        $dstQueryBuilder->select(['name'])->from(Note::getTableName());
        $dstQueryBuilder->where("name = " . $dstQueryBuilder->createNamedParameter('Some another name'));
        $dstQueryBuilder->andWhere("id > :id");
        $dstQueryBuilder->setParameter('id', 0);

        $sql = $query->prepareSubQuery($srcQueryBuilder, $dstQueryBuilder);

        $this->assertEquals("SELECT id FROM test_note WHERE (id = :dcValue2) AND (name = :dcValue3)", $sql);

        $dstQueryBuilder->andWhere("id IN ({$sql})");

        $dstSql = $dstQueryBuilder->getSQL();

        $this->assertEquals("SELECT name FROM test_note WHERE (name = :dcValue1) AND (id > :id) AND (id IN (SELECT id FROM test_note WHERE (id = :dcValue2) AND (name = :dcValue3)))", $dstSql);

        $dstQuery = $query->getSQL($dstQueryBuilder);

        $this->assertEquals("SELECT name FROM test_note WHERE (name = 'Some another name') AND (id > 0) AND (id IN (SELECT id FROM test_note WHERE (id = 12) AND (name = 'Some name')))", $dstQuery);
    }

    public function testFilterManyParamsSubQuery()
    {
        $note = new Note(['name' => 'first']);
        $note->save();
        $filter = [
            'id__gte' => 0,
            Q::buildQ([
                [Q::buildQ([['id' => 1], ['name' => 'first']], 'and'),],
                [Q::buildQ([['id' => 2], ['name' => 'second']], 'and'),],
                [Q::buildQ([['id' => 3], ['name' => 'third']], 'and'),],
                [Q::buildQ([['id' => 4], ['name' => 'four']], 'and'),],
                [Q::buildQ([['id' => 5], ['name' => 'five']], 'and'),],
            ], 'or')
        ];

        $subQs = Note::objects()->filter($filter)->select(['id']);

        $result = Note::objects()->filter(['id__in' => $subQs,])->all();

        $this->assertCount(1, $result);
    }

}