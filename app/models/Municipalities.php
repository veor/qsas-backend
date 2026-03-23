<?php

class Municipalities extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var integer
     */
    public $district_id;

    /**
     *
     * @var string
     */
    public $name;

    /**
     *
     * @var double
     */
    public $points;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        // $this->setSchema("quezongovict_qsas");
        $this->setSchema("qsasdb");
        $this->setSource("municipalities");
        $this->belongsTo('district_id', '\Districts', 'id', ['alias' => 'Districts']);
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Municipalities[]|Municipalities|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Municipalities|\Phalcon\Mvc\Model\ResultInterface|\Phalcon\Mvc\ModelInterface|null
     */
    public static function findFirst($parameters = null): ?\Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters);
    }

}
