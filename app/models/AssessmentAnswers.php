<?php

class AssessmentAnswers extends \Phalcon\Mvc\Model
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
    public $scholarship_application_id;

    /**
     *
     * @var string
     */
    public $application_ref_no;

    /**
     *
     * @var string
     */
    public $answers;

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
     * @var string
     */
    public $created_at;

    /**
     *
     * @var string
     */
    public $updated_at;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        // $this->setSchema("quezongovict_qsas");
        $this->setSchema("qsasdb");
        $this->setSource("assessment_answers");
        $this->belongsTo('scholarship_application_id', '\ScholarshipApplications', 'id', ['alias' => 'ScholarshipApplications']);
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return AssessmentAnswers[]|AssessmentAnswers|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return AssessmentAnswers|\Phalcon\Mvc\Model\ResultInterface|\Phalcon\Mvc\ModelInterface|null
     */
    public static function findFirst($parameters = null): ?\Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters);
    }

}
