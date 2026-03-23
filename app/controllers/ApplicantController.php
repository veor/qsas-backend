<?php
declare(strict_types=1);
use Phalcon\Http\Response;

class ApplicantController extends \Phalcon\Mvc\Controller
{
    //////////////////////////////////////
    // -------- Application Form ------ //
    //////////////////////////////////////
    // Fetch District and Municipality for cascading dropdown
    public function fetchDistrictAndMunicipalityAction()
    {
        $this->view->disable(); 

        $districts = Districts::find([
            'order' => 'name ASC'
        ])->toArray();

        $municipalities = Municipalities::find([
            'order' => 'name ASC'
        ])->toArray();

        $result = [
            'districts' => array_map(function($d) {
                return [
                    'id' => $d['id'],
                    'name' => $d['name']
                ];
            }, $districts),

            'municipalities' => array_map(function($m) {
                return [
                    'id' => $m['id'],
                    'district_id' => $m['district_id'],
                    'name' => $m['name'],
                    'points' => $m['points'] ?? 0
                ];
            }, $municipalities),
        ];

        return $this->response->setJsonContent($result);
    }
    //get locations from location point system 
    public function getLocationOptionsAction($category)
    {
        $this->view->disable(); 
    
        // Validate category
        if (!in_array($category, ['hometown', 'barangay_accessibility', 'hard_to_reach'])) {
            return $this->response->setJsonContent([
                'error' => 'Invalid category'
            ])->setStatusCode(400);
        }

        // Fetch options from LocationPointSystem
        $options = LocationPointSystem::find([
            'conditions' => 'category = :category:',
            'bind' => ['category' => $category],
            'order' => 'id ASC'
        ]);

        $result = [];
        foreach ($options as $option) {
            $result[] = [
                'value' => $option->option_value,
                'points' => $option->points
            ];
        }

        return $this->response->setJsonContent($result);
    }
    // Submit New Applicant 
    public function submitAction()
    {
        $request = $this->request;
        $response = new \Phalcon\Http\Response();

        if (!$request->isPost()) {
            return $response->setJsonContent(['error' => 'Invalid request'])->setStatusCode(400);
        }

        $data = $request->getPost();
        $files = $request->getUploadedFiles();

        $uploadDir = dirname(APP_PATH) . '/public/applicant-profile/';
        $gradeUploadDir = dirname(APP_PATH) . '/public/applicant-grades/';

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if (!is_dir($gradeUploadDir)) mkdir($gradeUploadDir, 0755, true);

        $picturePath = null;
        $gradePdfPath = null;

        foreach ($files as $file) {
            if ($file->getKey() === 'picture' && $file->getSize() > 0) {
                $fileName = uniqid() . '_' . $file->getName();
                $picturePath = 'applicant-profile/' . $fileName;
                $file->moveTo($uploadDir . $fileName);
            }
            if ($file->getKey() === 'grade_pdf' && $file->getSize() > 0) {
                $fileName = uniqid() . '_' . $file->getName();
                $gradePdfPath = 'applicant-grades/' . $fileName;
                $file->moveTo($gradeUploadDir  . $fileName);
            }
        }

        // Hash secret answer
        if (!empty($data['secret_answer'])) {
            $data['secret_answer'] = password_hash($data['secret_answer'], PASSWORD_DEFAULT);
        }

        // Format names
        $formatName = function($value) {
            return !empty($value) ? ucfirst(strtolower(trim($value))) : null;
        };

        // For school/course/status (all uppercase)
        $formatUpper = function($value) {
            if (empty($value)) return null;
            $value = preg_replace('/\s+/', ' ', $value); 
            return strtoupper(trim($value));
        };
        // Format names
        foreach ([
            'applicant_first','applicant_middle','applicant_last','applicant_extension',
            'father_first','father_middle','father_last','father_extension',
            'mother_first','mother_middle','mother_last','mother_extension'
        ] as $field) {
            if (isset($data[$field])) $data[$field] = $formatName($data[$field]);
        }
        // For school/course/status
        foreach (['applicant_course', 'current_course', 'current_school'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = $formatUpper($data[$field]);
            }
        }

        $hometownLocation = $data['hometown_location'] ?? null;
        $brgyAccessibility = $data['barangay_accessibility'] ?? null;
        $hardToReachBarangay = $data['hard_to_reach_barangays'] ?? null;
        $schoolYearStart = $data['school_year_start'] ?? null;
        $schoolYearEnd   = $data['school_year_end'] ?? null;
        $gradingPeriod   = $data['grading_period'] ?? null;

        // Fetch points dynamically from location_point_system
        $hometown_pts = 0;
        $brgyAccessibility_pts = 0;
        $hard_to_reach_brgy_pts = 0;
        $municipality_pts = 0;

        // Hometown points
        if (!empty($hometownLocation)) {
            $row = LocationPointSystem::findFirst([
                'conditions' => 'category = :cat: AND option_value = :val:',
                'bind' => [
                    'cat' => 'hometown',
                    'val' => $hometownLocation
                ]
            ]);

            if ($row) {
                $hometown_pts = (int) $row->points;
            }
        }
        // Barangay Accessibility points
        if (!empty($brgyAccessibility)) {
            $row = LocationPointSystem::findFirst([
                'conditions' => 'category = :cat: AND option_value = :val:',
                'bind' => [
                    'cat' => 'barangay_accessibility',
                    'val' => $brgyAccessibility
                ]
            ]);

            if ($row) {
                $brgyAccessibility_pts = (int) $row->points;
            }
        }
        // Hard-to-reach barangays points
        if (!empty($hardToReachBarangay)) {
            $row = LocationPointSystem::findFirst([
                'conditions' => 'category = :cat: AND option_value = :val:',
                'bind' => [
                    'cat' => 'hard_to_reach',
                    'val' => $hardToReachBarangay
                ]
            ]);

            if ($row) {
                $hard_to_reach_brgy_pts = (int) $row->points;
            }
        }
        // Municipality points
        if (!empty($data['municipality'])) {
            $municipalityRow = Municipalities::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => $data['municipality']]
            ]);

            if ($municipalityRow) {
                $municipality_pts = $municipalityRow->points;
            }
        }

        // --- Grades ---
        $grades = [];
        if (!empty($data['grades'])) {
            $grades = json_decode($data['grades'], true) ?? [];
        }

        $total_grade_points = array_sum(array_map(function($g) {
            return isset($g['numeric_grade']) ? (float)$g['numeric_grade'] : 0;
        }, $grades));

        // Application Ref No
        $year = date('y');
        $latestApplicant = Applicants::findFirst([
            'conditions' => "application_ref_no LIKE :year:",
            'bind'       => ['year' => $year . '-%'],
            'order'      => 'id DESC'
        ]);

        $nextNumber = $latestApplicant
            ? intval(explode('-', $latestApplicant->application_ref_no)[1]) + 1
            : 0;

        $applicationRefNo = sprintf("%s-%05d", $year, $nextNumber);

        // Save applicant
        $applicant = new Applicants();
        $applicant->assign(array_merge($data, [
            'picture' => $picturePath,
            'grade_pdf' => $gradePdfPath,
            'application_ref_no' => $applicationRefNo,
            'hometown_pts' => $hometown_pts,
            'brgy_accessibility_pts' => $brgyAccessibility_pts,
            'hard_to_reach_brgy_pts' => $hard_to_reach_brgy_pts,
            'municipality_pts' => $municipality_pts,
            'hometown_location' => $hometownLocation,
            'barangay_accessibility' => $brgyAccessibility,
            'hard_to_reach_barangays' => $hardToReachBarangay,
            'total_grade_points' => $total_grade_points, 
            'grades' => json_encode($grades),
            'total_grade_points_editable' => $total_grade_points, 
            'grades_editable' => json_encode($grades),
            'school_year_start' => $schoolYearStart,
            'school_year_end' => $schoolYearEnd,
            'grading_period' => $gradingPeriod
        ]));

        if ($applicant->save()) {
            return $response->setJsonContent([
                'success' => true,
                'applicant' => [
                    'id' => $applicant->id,
                    'application_ref_no' => $applicant->application_ref_no,
                    'name' => trim($applicant->applicant_first . ' ' . ($applicant->applicant_middle ?? '') . ' ' . $applicant->applicant_last),
                    'picture' => $picturePath ? '/' . $picturePath : null,
                    'grade_pdf' => $gradePdfPath ? '/' . $gradePdfPath : null,
                    'total_grade_points' => $total_grade_points,
                    'total_grade_points_editable' => $total_grade_points,
                    'created_at' => $applicant->created_at
                ]
            ]);
        }

        return $response->setJsonContent([
            'error' => 'Failed to save applicant',
            'messages' => $applicant->getMessages()
        ])->setStatusCode(500);
    }
    // Check for existing applicant data 
    public function checkDuplicateAction()
    {
        $request = $this->request;

        if (!$request->isPost()) {
            return (new Response())->setJsonContent(['error' => 'Invalid request'])->setStatusCode(400);
        }

        $data = $request->getJsonRawBody(true); 

        $first = $data['applicant_first'] ?? null;
        $last = $data['applicant_last'] ?? null;
        $fatherFirst = $data['father_first'] ?? null;
        $fatherLast = $data['father_last'] ?? null;
        $motherFirst = $data['mother_first'] ?? null;
        $motherLast = $data['mother_last'] ?? null;

        $applicant = Applicants::findFirst([
            'conditions' => 'LOWER(applicant_first) = LOWER(:first:) 
                            AND LOWER(applicant_last) = LOWER(:last:) 
                            AND LOWER(father_first) = LOWER(:father_first:) 
                            AND LOWER(father_last) = LOWER(:father_last:) 
                            AND LOWER(mother_first) = LOWER(:mother_first:) 
                            AND LOWER(mother_last) = LOWER(:mother_last:)',
            'bind' => [
                'first' => $first,
                'last' => $last,
                'father_first' => $fatherFirst,
                'father_last' => $fatherLast,
                'mother_first' => $motherFirst,
                'mother_last' => $motherLast,
            ]
        ]);

        if ($applicant) {
            return (new Response())->setJsonContent([
                'exists' => true,
                'id' => $applicant->id,
                'secret_question' => $applicant->secret_question
            ]);
        }

        return (new Response())->setJsonContent([
            'exists' => false,
            'checked' => [
                'first' => $first,
                'last' => $last,
                'father_first' => $fatherFirst,
                'father_last' => $fatherLast,
                'mother_first' => $motherFirst,
                'mother_last' => $motherLast,
            ]
        ]);
    }
    // Verify secret answer if correct 
    public function verifySecretAction()
    {
        $request = $this->request;

        if (!$request->isPost()) {
            return (new Response())->setJsonContent(['error' => 'Invalid request'])->setStatusCode(400);
        }

        $data = $request->getJsonRawBody(true);

        $id = $data['applicantId'] ?? null;
        $answer = $data['answer'] ?? null;

        $applicant = Applicants::findFirstById($id);

        if ($applicant && $answer && password_verify($answer, $applicant->secret_answer)) {
            // return applicant data too
            return (new Response())->setJsonContent([
                'valid' => true,
                'applicant' => $applicant->toArray()
            ]);
        }

        return (new Response())->setJsonContent(['valid' => false]);
    }

    //////////////////////////////
    // -- Applicant Setting -- //
    /////////////////////////////
    // Update applicant details 
    public function updateAction()
    {
        $request = $this->request;

        if (!$request->isPost()) {
            return (new Response())->setJsonContent(['error' => 'Invalid request'])->setStatusCode(400);
        }

        $id = $request->getPost('id');
        $applicant = Applicants::findFirstById($id);

        if (!$applicant) {
            return (new Response())->setJsonContent(['error' => 'Applicant not found'])->setStatusCode(404);
        }

        $data = $request->getPost();
        $files = $request->getUploadedFiles();

        if (!empty($files)) {
            foreach ($files as $file) {
                if ($file->getKey() === 'picture' && $file->getSize() > 0) {
                    $uploadDir = dirname(APP_PATH) . '/public/applicant-profile/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    // remove old picture if exists
                    if (!empty($applicant->picture)) {
                        $oldFile = dirname(APP_PATH) . '/public/' . $applicant->picture;
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }

                    // Save new picture
                    $fileName = uniqid() . '_' . $file->getName();
                    $picturePath = 'applicant-profile/' . $fileName;
                    $file->moveTo($uploadDir . $fileName);

                    $data['picture'] = $picturePath;
                }
            }
        }

        // re-hash secret answer if changed
        if (!empty($data['secret_answer'])) {
            $data['secret_answer'] = password_hash($data['secret_answer'], PASSWORD_DEFAULT);
        } else {
            unset($data['secret_answer']); 
        }

        if (empty($data['secret_question'])) {
            unset($data['secret_question']);
        }
        
        $applicant->assign($data);

        if ($applicant->save()) {
            $applicationRefNo = $applicant->application_ref_no;
            $hasRefNo = !empty($applicationRefNo);
            $hasAssessmentAnswers = false;

            if ($hasRefNo) {
                $assessmentAnswer = AssessmentAnswers::findFirst([
                    'conditions' => 'application_ref_no = :ref:',
                    'bind' => ['ref' => $applicationRefNo]
                ]);
                $hasAssessmentAnswers = $assessmentAnswer !== null;
            }

            return (new Response())->setJsonContent([
                'success' => true,
                'picture' => !empty($data['picture']) ? '/' . $data['picture'] : $applicant->picture,
                'application_ref_no' => $applicationRefNo,
                'has_ref_no' => $hasRefNo,
                'has_assessment_answers' => $hasAssessmentAnswers,
            ]);
        }
        // if ($applicant->save()) {
        //     return (new Response())->setJsonContent([
        //         'success' => true,
        //         'picture' => !empty($data['picture']) ? '/' . $data['picture'] : $applicant->picture
        //     ]);
        // }

        return (new Response())->setJsonContent([
            'error' => 'Failed to update applicant',
            'messages' => $applicant->getMessages()
        ])->setStatusCode(500);
    }
    // Get applicant with that secret question and answer 
    public function getApplicantAction($id) {
        $applicant = Applicants::findFirstById($id);

        if (!$applicant) {
            return $this->response->setJsonContent(['success' => false]);
        }

        return $this->response->setJsonContent([
            'success' => true,
            'data' => [
                'secret_question' => $applicant->secret_question,
                'hasSecretAnswer' => !empty($applicant->secret_answer) 
            ]
        ]);
    }
    ///////////////////////////
    // -- Applicant List -- ///
    ///////////////////////////
    // Fetch applicant's answer and used in comparison with the assessor's answer 
    public function getAssessmentAnswersAction($applicationRefNo)
    {
        $answers = AssessmentAnswers::findFirstByApplicationRefNo($applicationRefNo);

        if (!$answers) {
            return $this->response->setJsonContent(['success' => false, 'data' => null]);
        }

        return $this->response->setJsonContent([
            'success' => true,
            'data' => json_decode($answers->answers, true)
        ]);
    }
    /////////////////////////////
    // -- Program Selection -- //
    /////////////////////////////
    public function getAppliedScholarshipsAction()
    {
        $refNo = $this->request->getQuery('ref_no', 'string');
        
        $applications = ScholarshipApplications::find([
            'conditions' => 'application_ref_no = :ref_no:',
            'bind' => ['ref_no' => $refNo],
            'columns' => 'scholarship_type'
        ]);

        $types = array_map(function($app) {
            return $app['scholarship_type'];
        }, $applications->toArray());

        return $this->response->setJsonContent(['data' => $types]);
    }
    public function applyScholarshipAction()
    {
        $request = $this->request;

        if (!$request->isPost()) {
            return $this->response->setJsonContent(['error' => 'Invalid request'])->setStatusCode(400);
        }

        $data = $request->getJsonRawBody(true);
        $applicationRefNo = $data['applicationRefNo'] ?? null;
        $scholarshipType = $data['scholarshipType'] ?? null;

        if (!$applicationRefNo || !$scholarshipType) {
            return $this->response->setJsonContent(['error' => 'Missing data'])->setStatusCode(400);
        }

        $applicant = Applicants::findFirstByApplicationRefNo($applicationRefNo);
        if (!$applicant) {
            return $this->response->setJsonContent(['error' => 'Applicant not found'])->setStatusCode(404);
        }

        $existing = ScholarshipApplications::findFirst([
            'conditions' => 'applicant_id = :id: AND scholarship_type = :type:',
            'bind' => ['id' => $applicant->id, 'type' => $scholarshipType]
        ]);
        if ($existing) {
            return $this->response->setJsonContent(['error' => 'Already applied to this scholarship'])->setStatusCode(409);
        }

        // Fetch assessment answers
        $assessment = AssessmentAnswers::findFirstByApplicationRefNo($applicationRefNo);
        if (!$assessment) {
            return $this->response->setJsonContent(['error' => 'No assessment answers found'])->setStatusCode(400);
        }

        $answers = json_decode($assessment->answers, true);
        if (!$answers || !is_array($answers)) {
            return $this->response->setJsonContent(['error' => 'Invalid assessment answers'])->setStatusCode(400);
        }

        // Fetch question weights
        $questionCodes = array_column($answers, 'id');
        $questions = AssessmentQuestions::find([
            'conditions' => 'question_code IN ({codes:array})',
            'bind' => ['codes' => $questionCodes]
        ]);

        $questionMap = [];
        foreach ($questions as $q) {
            $questionMap[$q->question_code] = [
                'id' => $q->id,
                'weight' => (float)$q->weight
            ];
        }

        // Compute weighted grade dynamically
        $assessmentWeight = 0.0;
        foreach ($answers as $answer) {
            $qCode = $answer['id'];
            $text = $answer['answer'] ?? null;
            if (!$text || !isset($questionMap[$qCode])) continue;

            $qid = $questionMap[$qCode]['id'];
            $weight = $questionMap[$qCode]['weight'];

            $option = AssessmentOptions::findFirst([
                'conditions' => 'question_id = :qid: AND option_text = :text:',
                'bind' => ['qid' => $qid, 'text' => $text]
            ]);

            if ($option) {
                $points = (int)$option->points;
                $assessmentWeight += ($weight / 100) * $points;
            }
        }

        // Round to 2 decimal places for display
        $assessmentWeight = number_format(round($assessmentWeight, 2), 2, '.', '');
        // Compute Priority Courses Assessment Weight
        $prioAssessWeight = number_format(round($assessmentWeight * 0.35, 2), 2, '.', ''); 
        // Compute Geographic Location Weight
        $hometownScore = (float)$applicant->hometown_pts * 0.20;
        $brgyAccessScore = (float)$applicant->brgy_accessibility_pts * 0.30;
        $hardToReachScore = (float)$applicant->hard_to_reach_brgy_pts * 0.30;
        $municipalityScore = (float)$applicant->municipality_pts * 0.20;

        $geoBaseTotal = $hometownScore + $brgyAccessScore + $hardToReachScore + $municipalityScore;
        // Apply 35% weight and round 2 decimals
        $geoLocWeight = number_format(round($geoBaseTotal * 0.35, 2), 2, '.', '');
        // --- Compute grade points weight as 30% of total_grade_points ---
        $totalGradePoints = (float)$applicant->total_grade_points;
        $gradePointsWeight = number_format(round($totalGradePoints * 0.30, 2), 2, '.', '');
        // Compute total priority weight
        $priorityWeight = number_format(
            round($prioAssessWeight + $geoLocWeight + $gradePointsWeight, 2),
            2,
            '.',
            ''
        );
        // Save scholarship application
        $application = new ScholarshipApplications();
        $application->applicant_id = $applicant->id;
        $application->application_ref_no = $applicationRefNo;
        $application->scholarship_type = $scholarshipType;
        $application->status = 'pending';
        $application->applied_at = date('Y-m-d H:i:s');
        $application->assessment_weight = $assessmentWeight;
        $application->prio_assess_weight = $prioAssessWeight;
        $application->geo_loc_weight = $geoLocWeight;
        $application->grade_points_weight = $gradePointsWeight;
        $application->priority_weight = $priorityWeight;

        if (!$application->save()) {
            return $this->response->setJsonContent([
                'error' => 'Failed to apply for scholarship',
                'messages' => $application->getMessages()
            ])->setStatusCode(500);
        }

        // Link existing assessment answers to this scholarship application
        $assessment->scholarship_application_id = $application->id;
        $assessment->save();

        return $this->response->setJsonContent([
            'success' => true,
            'assessment_weight' => $assessmentWeight,
            'prio_assess_weight' => $prioAssessWeight,
            'geo_loc_weight' => $geoLocWeight,
            'grade_points_weight' => $gradePointsWeight,
            'priority_weight' => $priorityWeight,
            'scholarship_type' => $scholarshipType
        ]);
    }
}

