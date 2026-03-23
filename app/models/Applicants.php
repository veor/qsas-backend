<?php

use Phalcon\Filter\Validation;
use Phalcon\Filter\Validation\Validator\Email as EmailValidator;

class Applicants extends \Phalcon\Mvc\Model
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
    public $applicant_first;

    /**
     *
     * @var string
     */
    public $applicant_middle;

    /**
     *
     * @var string
     */
    public $applicant_last;

    /**
     *
     * @var string
     */
    public $applicant_extension;

    /**
     *
     * @var string
     */
    public $father_first;

    /**
     *
     * @var string
     */
    public $father_middle;

    /**
     *
     * @var string
     */
    public $father_last;

    /**
     *
     * @var string
     */
    public $father_extension;

    /**
     *
     * @var string
     */
    public $mother_first;

    /**
     *
     * @var string
     */
    public $mother_middle;

    /**
     *
     * @var string
     */
    public $mother_last;

    /**
     *
     * @var string
     */
    public $mother_extension;

    /**
     *
     * @var string
     */
    public $birthdate;

    /**
     *
     * @var string
     */
    public $gender;

    /**
     *
     * @var string
     */
    public $assigned_sex;

    /**
     *
     * @var integer
     */
    public $num_children;

    /**
     *
     * @var integer
     */
    public $hometown_pts;

    /**
     *
     * @var integer
     */
    public $hard_to_reach_brgy_pts;

    /**
     *
     * @var integer
     */
    public $brgy_accessibility_pts;

    /**
     *
     * @var string
     */
    public $applicant_course;

    /**
     *
     * @var string
     */
    public $current_academic_status;

    /**
     *
     * @var string
     */
    public $current_course;

    /**
     *
     * @var string
     */
    public $current_school;

    /**
     *
     * @var string
     */
    public $grade_pdf;

    /**
     *
     * @var string
     */
    public $civil_status;

    /**
     *
     * @var integer
     */
    public $children;

    /**
     *
     * @var string
     */
    public $contact;

    /**
     *
     * @var string
     */
    public $email;

    /**
     *
     * @var string
     */
    public $house_no;

    /**
     *
     * @var string
     */
    public $street;

    /**
     *
     * @var string
     */
    public $purok;

    /**
     *
     * @var string
     */
    public $district;

    /**
     *
     * @var string
     */
    public $municipality;

    /**
     *
     * @var double
     */
    public $municipality_pts;

    /**
     *
     * @var string
     */
    public $barangay;

    /**
     *
     * @var string
     */
    public $secret_question;

    /**
     *
     * @var string
     */
    public $secret_answer;

    /**
     *
     * @var string
     */
    public $picture;

    /**
     *
     * @var string
     */
    public $created_at;

    /**
     *
     * @var string
     */
    public $grades;

    /**
     *
     * @var double
     */
    public $total_grade_points;

    /**
     *
     * @var string
     */
    public $grades_editable;

    /**
     *
     * @var double
     */
    public $total_grade_points_editable;

    /**
     *
     * @var string
     */
    public $hometown_location;

    /**
     *
     * @var string
     */
    public $barangay_accessibility;

    /**
     *
     * @var string
     */
    public $hard_to_reach_barangays;

    /**
     *
     * @var string
     */
    public $school_year_start;

    /**
     *
     * @var string
     */
    public $school_year_end;

    /**
     *
     * @var string
     */
    public $grading_period;

    /**
     * Validations and business logic
     *
     * @return boolean
     */
    public function validation()
    {
        $validator = new Validation();

        $validator->add(
            'email',
            new EmailValidator(
                [
                    'model'   => $this,
                    'message' => 'Please enter a correct email address',
                ]
            )
        );

        return $this->validate($validator);
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        // $this->setSchema("quezongovict_qsas");
        $this->setSchema("qsasdb");
        $this->setSource("applicants");
        $this->hasMany('id', 'ScholarshipApplications', 'applicant_id', ['alias' => 'ScholarshipApplications']);
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Applicants[]|Applicants|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Applicants|\Phalcon\Mvc\Model\ResultInterface|\Phalcon\Mvc\ModelInterface|null
     */
    public static function findFirst($parameters = null): ?\Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters);
    }

}
