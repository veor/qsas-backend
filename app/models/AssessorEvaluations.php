<?php

class AssessorEvaluations extends \Phalcon\Mvc\Model
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
    public $application_ref_no;

    /**
     *
     * @var string
     */
    public $assessor_id_no;

    /**
     *
     * @var string
     */
    public $answers;

    /**
     *
     * @var string
     */
    public $created_at;

    /**
     *
     * @var string
     */
    public $updated_at;

    /**
     *
     * @var integer
     */
    public $total_score;

    /**
     *
     * @var integer
     */
    public $max_score;

    /**
     *
     * @var double
     */
    public $assessment_weight;

    /**
     *
     * @var double
     */
    public $priority_weight;

    /**
     *
     * @var string
     */
    public $status;

    /**
     *
     * @var string
     */
    public $submitted_at;

    /**
     *
     * @var double
     */
    public $auto_score;

    /**
     *
     * @var double
     */
    public $manual_score;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        // $this->setSchema("quezongovict_qsas");
        $this->setSchema("qsasdb");
        $this->setSource("assessor_evaluations");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return AssessorEvaluations[]|AssessorEvaluations|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return AssessorEvaluations|\Phalcon\Mvc\Model\ResultInterface|\Phalcon\Mvc\ModelInterface|null
     */
    public static function findFirst($parameters = null): ?\Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters);
    }

}
