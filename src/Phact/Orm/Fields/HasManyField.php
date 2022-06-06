<?php
/**
 *
 *
 * All rights reserved.
 *
 * @author Okulov Anton
 * @email qantus@mail.ru
 * @version 1.0
 * @date 19/04/16 08:29
 */

namespace Phact\Orm\Fields;

use Phact\Helpers\Configurator;
use Phact\Orm\HasManyManager;
use Phact\Orm\FieldManagedInterface;
use Phact\Orm\Manager;

/**
 * Class HasManyField
 *
 * @property $to string Related model field
 * @property $from string Current model field
 *
 * @package Phact\Orm\Fields
 */
class HasManyField extends RelationField implements FieldManagedInterface
{
    protected $_to = null;
    protected $_from = 'id';
    protected $_throughFor;

    public $editable = false;

    public $managerClass = HasManyManager::class;

    public function setThroughFor($throughFor)
    {
        $this->_throughFor = $throughFor;
    }

    public function getThroughFor()
    {
        return $this->_throughFor;
    }

    public function getFrom()
    {
        return $this->_from;
    }

    public function setFrom($from)
    {
        $this->_from = $from;
    }

    public function getTo()
    {
        if ($this->_to) {
            return $this->_to;
        }
        $model = $this->getOwnerModelClass();
        $name = $model::classNameUnderscore();
        $from = $this->_from;
        $columnName = "{$name}_{$from}";

        return $this->getRelationModel()?->fetchField($columnName)?->from ?: $columnName;
    }

    public function setTo($to)
    {
        $this->_to = $to;
    }

    public function getAttributeName()
    {
        return null;
    }

    public function getRelationJoins()
    {
        $relationModelClass = $this->getRelationModelClass();
        return [
            [
                'table' => $relationModelClass::getTableName(),
                'from' => $this->getFrom(),
                'to' => $this->getTo()
            ]
        ];
    }

    public function getIsMany()
    {
        return true;
    }

    public function getValue($aliasConfig = NULL)
    {
        return $this->getManager();
    }

    public function getManager(): Manager
    {
        $relationModel = $this->getRelationModel();
        $manager = new $this->managerClass($relationModel);

        return Configurator::configure($manager, [
            'fieldName' => $this->name,
            'toField' => $this->getTo(),
            'fromField' => $this->getFrom(),
            'ownerModel' => $this->getModel()
        ]);
    }
}