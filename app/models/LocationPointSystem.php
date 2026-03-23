<?php

class LocationPointSystem extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var string
     */
    public $category;

    /**
     *
     * @var string
     */
    public $option_value;

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
        $this->setSource("location_point_system");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return LocationPointSystem[]|LocationPointSystem|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return LocationPointSystem|\Phalcon\Mvc\Model\ResultInterface|\Phalcon\Mvc\ModelInterface|null
     */
    public static function findFirst($parameters = null): ?\Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters);
    }

}
