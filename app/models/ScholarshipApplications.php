<?php

class ScholarshipApplications extends \Phalcon\Mvc\Model
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
    public $applicant_id;

    /**
     *
     * @var string
     */
    public $application_ref_no;

    /**
     *
     * @var string
     */
    public $scholarship_type;

    /**
     *
     * @var double
     */
    public $assessment_weight;

    /**
     *
     * @var double
     */
    public $prio_assess_weight;

    /**
     *
     * @var double
     */
    public $geo_loc_weight;

    /**
     *
     * @var double
     */
    public $grade_points_weight;

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
    public $applied_at;

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
        $this->setSource("scholarship_applications");
        $this->hasMany('id', 'AssessmentAnswers', 'scholarship_application_id', ['alias' => 'AssessmentAnswers']);
        $this->belongsTo('applicant_id', '\Applicants', 'id', ['alias' => 'Applicants']);
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ScholarshipApplications[]|ScholarshipApplications|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ScholarshipApplications|\Phalcon\Mvc\Model\ResultInterface|\Phalcon\Mvc\ModelInterface|null
     */
    public static function findFirst($parameters = null): ?\Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters);
    }

}
