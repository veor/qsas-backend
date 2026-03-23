<?php
declare(strict_types=1);
use Phalcon\Http\Response;

class AdminController extends \Phalcon\Mvc\Controller
{
    ///////////////////////
    // --- Dashboard --- //
    ///////////////////////
    // fetch and count all the total scholarship type each applicant 
    public function getScholarshipCountsAction()
    {
        $this->view->disable(); 
        $applicants = ScholarshipApplications::find();

        $counts = [];

        foreach ($applicants as $applicant) {
            $type = $applicant->scholarship_type ?? 'Unknown';
            if (!isset($counts[$type])) {
                $counts[$type] = 0;
            }
            $counts[$type]++;
        }

        return (new Response())->setJsonContent($counts);
    }
    // Priority Courses for TOP 35 Per Municipality Applied
    public function getTopByCourseForPriorityCoursesAction()
    {
        $this->view->disable();

        $sql = "
            SELECT
                sa.application_ref_no,
                sa.priority_weight,
                a.current_course
            FROM scholarship_applications sa
            INNER JOIN applicants a ON a.id = sa.applicant_id
            WHERE sa.scholarship_type = 'Priority Courses Scholarship'
            AND sa.status = 'pending'
            AND sa.priority_weight IS NOT NULL
            ORDER BY sa.priority_weight DESC
            LIMIT 35
        ";

        $connection = $this->getDI()->get('db');
        $result     = $connection->query($sql);
        $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        $rows = $result->fetchAll();

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'application_ref_no' => $row['application_ref_no'],
                'current_course'     => !empty($row['current_course']) ? $row['current_course'] : 'N/A',
                'priority_weight'    => $row['priority_weight'] !== null
                    ? (float) number_format((float) $row['priority_weight'], 2, '.', '')
                    : null,
            ];
        }

        return $this->response->setJsonContent($data);
    }
    // 1Poor and STAN C for TOP 35 Per Municipality Applied
    public function getTopByMunicipalityAction()
    {
        $this->view->disable();

        $scholarshipType = $this->request->getQuery('scholarship_type', 'string', '');
        $municipality    = $this->request->getQuery('municipality', 'string', '');
        $page            = max(1, (int) $this->request->getQuery('page', 'int', 1));
        $limit           = 4;
        $offset          = ($page - 1) * $limit;

        // Build WHERE clause
        if ($scholarshipType && in_array($scholarshipType, [
            'One Family One College Graduate Scholarship',
            'STAN C'
        ])) {
            $typeWhere = "AND sa.scholarship_type = '" . addslashes($scholarshipType) . "'";
        } else {
            $typeWhere = "AND sa.scholarship_type IN ('One Family One College Graduate Scholarship', 'STAN C')";
        }

        $munWhere = '';
        if ($municipality) {
            $munWhere = "AND m.name = '" . addslashes($municipality) . "'";
        }

        $sql = "
            SELECT
                sa.application_ref_no,
                sa.scholarship_type,
                sa.assessment_weight,
                m.name AS municipality_name
            FROM scholarship_applications sa
            INNER JOIN applicants a ON a.id = sa.applicant_id
            INNER JOIN municipalities m ON m.id = a.municipality
            WHERE sa.status = 'pending'
            AND sa.assessment_weight IS NOT NULL
            {$typeWhere}
            {$munWhere}
            ORDER BY sa.assessment_weight DESC
            LIMIT 35
        ";

        $connection = $this->getDI()->get('db');
        $result     = $connection->query($sql);
        $result->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        $rows = $result->fetchAll();

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'application_ref_no' => $row['application_ref_no'],
                'municipality'       => $row['municipality_name'] ?? 'N/A',
                'scholarship_type'   => $row['scholarship_type'],
                'assessment_weight'  => $row['assessment_weight'] !== null
                    ? (float) number_format((float) $row['assessment_weight'], 2, '.', '')
                    : null,
            ];
        }

        $total = count($data);
        $paged = array_slice($data, 0, $limit); // already offset by SQL if needed, but we paginate in PHP here

        // Re-run without municipality filter to get all available municipalities
        $sqlMuns = "
            SELECT DISTINCT m.name
            FROM scholarship_applications sa
            INNER JOIN applicants a ON a.id = sa.applicant_id
            INNER JOIN municipalities m ON m.id = a.municipality
            WHERE sa.status = 'pending'
            AND sa.assessment_weight IS NOT NULL
            {$typeWhere}
            ORDER BY m.name ASC
            LIMIT 35
        ";
        $munResult = $connection->query($sqlMuns);
        $munResult->setFetchMode(\Phalcon\Db\Enum::FETCH_ASSOC);
        $munRows = $munResult->fetchAll();
        $availableMunicipalities = array_column($munRows, 'name');

        // Paginate
        $paged = array_slice($data, $offset, $limit);

        return $this->response->setJsonContent([
            'data'                     => $paged,
            'total'                    => $total,
            'page'                     => $page,
            'total_pages'              => $total > 0 ? (int) ceil($total / $limit) : 1,
            'available_municipalities' => $availableMunicipalities,
            'available_scholarships'   => [
                'One Family One College Graduate Scholarship',
                'STAN C'
            ],
        ]);
    }
    ////////////////////////////
    // --- Applicant List --- //
    ////////////////////////////
    // fetch and display all applicants based on selected scholarship 
    public function getApplicantsAction()
    {
        $this->view->disable();

        $applicants = Applicants::find();

        $scholarshipApps = ScholarshipApplications::find([
            'conditions' => 'status = :status:',
            'bind'       => ['status' => 'pending']
        ]);

        // Map scholarship applications by application_ref_no
        $scholarshipMap = [];
        foreach ($scholarshipApps as $sch) {
            $scholarshipMap[$sch->application_ref_no][] = $sch;
        }

        $assessorEvaluations = AssessorEvaluations::find();
        $evaluationMap = [];
        foreach ($assessorEvaluations as $eval) {
            $evaluationMap[$eval->application_ref_no] = $eval;
        }
        $municipalities = Municipalities::find();
        $municipalityMap = [];
        
        foreach ($municipalities as $mun) {
            $municipalityMap[$mun->id] = $mun->name;
        }
        $data = [];
        $baseUrl = 'https://qsas.quezon.gov.ph/qsas-backend/public/';

        foreach ($applicants as $applicant) {
            $fatherName = trim($applicant->father_first . ' ' . ($applicant->father_middle ?? '') . ' ' . $applicant->father_last);
            $motherName = trim($applicant->mother_first . ' ' . ($applicant->mother_middle ?? '') . ' ' . $applicant->mother_last);
            $fullName   = trim($applicant->applicant_first . ' ' . ($applicant->applicant_middle ?? '') . ' ' . $applicant->applicant_last);

            // $addressParts = array_filter([
            //     trim($applicant->house_no ?? ''),
            //     trim($applicant->street ?? ''),
            //     $applicant->purok ? ('Purok ' . trim($applicant->purok)) : null,
            //     trim($applicant->barangay ?? ''),
            //     trim($applicant->municipality ?? '')
            // ]);
            $municipalityName = $municipalityMap[$applicant->municipality] ?? '';
            
            $addressParts = array_filter([
                trim($applicant->house_no ?? ''),
                trim($applicant->street ?? ''),
                $applicant->purok ? ('Purok ' . trim($applicant->purok)) : null,
                trim($applicant->barangay ?? ''),
                $municipalityName
            ]);
            $address = implode(', ', $addressParts);

            if (isset($scholarshipMap[$applicant->application_ref_no])) {
                foreach ($scholarshipMap[$applicant->application_ref_no] as $sch) {
                    $eval = $evaluationMap[$sch->application_ref_no] ?? null;

                    // Personal Assessment from scholarship_applications
                    $personalAssessment = $sch->assessment_weight ?? null;

                    // Recommending Assessment from assessor_evaluations
                    if ($eval) {
                        $recommendingAssessment = ($sch->scholarship_type === 'Priority Courses Scholarship')
                        ? $eval->priority_weight
                        : $eval->assessment_weight;

                    // Round to 2 decimal places
                    $recommendingAssessment = $recommendingAssessment !== null
                        ? floor($recommendingAssessment * 100) / 100
                        : null;
                } else {
                    $recommendingAssessment = null;
                }
                    $data[] = [
                        'application_ref_no'      => $applicant->application_ref_no,
                        'name'                    => $fullName,
                        'picture'                 => $applicant->picture ? '/' . ltrim($applicant->picture, '/') : null,
                        // 'picture'                 => $applicant->picture ? $baseUrl . ltrim($applicant->picture, '/') : null, //for production
                        'grade_pdf'               => $applicant->grade_pdf ? '/' . ltrim($applicant->grade_pdf, '/') : null,
                        // 'grade_pdf'               => $applicant->grade_pdf ? 'https://qsas.quezon.gov.ph/qsas-backend/public/' . ltrim($applicant->grade_pdf, '/') : null,
                        'grades'                  => $applicant->grades ? json_decode($applicant->grades, true) : [],
                        'created_at'              => $sch->applied_at ?? $applicant->created_at,
                        'scholarship_type'        => $sch->scholarship_type,
                        'father_name'             => $fatherName,
                        'mother_name'             => $motherName,
                        'birthdate'               => $applicant->birthdate,
                        'gender'                  => $applicant->gender,
                        'civil_status'            => $applicant->civil_status,
                        'no_of_children'          => $applicant->children,
                        'mobile_number'           => $applicant->contact,
                        'address'                 => $address,
                        'email_address'           => $applicant->email,
                        'personal_assessment'     => $personalAssessment,
                        'recommending_assessment' => $recommendingAssessment,
                        'priority_weight'         => $sch->priority_weight ?? null,
                        'assessment_weight'       => $sch->assessment_weight ?? null,
                        'hometown_location'       => $applicant->hometown_location,
                        'barangay_accessibility'  => $applicant->barangay_accessibility,
                        'hard_to_reach_barangays' => $applicant->hard_to_reach_barangays,
                        'current_academic_status' => $applicant->current_academic_status
                    ];
                }
            } else {
                $data[] = [
                    'application_ref_no'      => $applicant->application_ref_no,
                    'name'                    => $fullName,
                    'picture'                 => $applicant->picture ? '/' . ltrim($applicant->picture, '/') : null,
                    // 'picture'                 => $applicant->picture ? $baseUrl . ltrim($applicant->picture, '/') : null, //for production
                    'grade_pdf'               => $applicant->grade_pdf ? '/' . ltrim($applicant->grade_pdf, '/') : null,
                    // 'grade_pdf'               => $applicant->grade_pdf ? 'https://qsas.quezon.gov.ph/qsas-backend/public/' . ltrim($applicant->grade_pdf, '/') : null,
                    'grades'                  => $applicant->grades ? json_decode($applicant->grades, true) : [],
                    'created_at'              => $applicant->created_at,
                    'scholarship_type'        => $applicant->scholarship_type,
                    'father_name'             => $fatherName,
                    'mother_name'             => $motherName,
                    'birthdate'               => $applicant->birthdate,
                    'gender'                  => $applicant->gender,
                    'civil_status'            => $applicant->civil_status,
                    'no_of_children'          => $applicant->children,
                    'mobile_number'           => $applicant->contact,
                    'address'                 => $address,
                    'email_address'           => $applicant->email,
                    'personal_assessment'     => null,
                    'recommending_assessment' => null,
                    'priority_weight'         => null,
                    'assessment_weight'       => null,
                    'hometown_location'       => $applicant->hometown_location,
                    'barangay_accessibility'  => $applicant->barangay_accessibility,
                    'hard_to_reach_barangays' => $applicant->hard_to_reach_barangays,
                    'current_academic_status' => $applicant->current_academic_status
                ];
            }
        }

        return $this->response->setJsonContent($data);
    }
    public function updateLocationAction()
    {
        $this->view->disable();
        $response = new Response();

        try {
            $body = $this->request->getJsonRawBody(true);

            $applicationRefNo      = $body['application_ref_no'] ?? null;
            $hometownLocation      = $body['hometown_location'] ?? null;
            $barangayAccessibility = $body['barangay_accessibility'] ?? null;
            $hardToReach           = $body['hard_to_reach_barangays'] ?? null;

            if (!$applicationRefNo) {
                return $response->setJsonContent(['success' => false, 'message' => 'Missing application_ref_no.']);
            }

            $applicant = Applicants::findFirst([
                'conditions' => 'application_ref_no = :ref:',
                'bind'       => ['ref' => $applicationRefNo]
            ]);

            if (!$applicant) {
                return $response->setJsonContent(['success' => false, 'message' => 'Applicant not found.']);
            }

            // Update location fields
            $applicant->hometown_location      = $hometownLocation;
            $applicant->barangay_accessibility = $barangayAccessibility;
            $applicant->hard_to_reach_barangays = $hardToReach;

            // Re-fetch points from location_point_system
            $getPoints = function($category, $value) {
                $row = LocationPointSystem::findFirst([
                    'conditions' => 'category = :cat: AND option_value = :val:',
                    'bind'       => ['cat' => $category, 'val' => $value]
                ]);
                return $row ? (int)$row->points : 0;
            };

            $applicant->hometown_pts           = $getPoints('hometown', $hometownLocation);
            $applicant->brgy_accessibility_pts = $getPoints('barangay_accessibility', $barangayAccessibility);
            $applicant->hard_to_reach_brgy_pts = $getPoints('hard_to_reach', $hardToReach);

            if (!$applicant->save()) {
                return $response->setJsonContent(['success' => false, 'message' => 'Failed to save.', 'errors' => $applicant->getMessages()]);
            }

            // Recompute geo_loc_weight for all related scholarship applications
            $hometownScore    = (float)$applicant->hometown_pts * 0.20;
            $brgyAccessScore  = (float)$applicant->brgy_accessibility_pts * 0.30;
            $hardToReachScore = (float)$applicant->hard_to_reach_brgy_pts * 0.30;
            $municipalityScore = (float)$applicant->municipality_pts * 0.20;

            $geoLocWeight = number_format(
                round(($hometownScore + $brgyAccessScore + $hardToReachScore + $municipalityScore) * 0.35, 2),
                2, '.', ''
            );

            $scholarshipApps = ScholarshipApplications::find([
                'conditions' => 'application_ref_no = :ref:',
                'bind'       => ['ref' => $applicationRefNo]
            ]);

            foreach ($scholarshipApps as $app) {
                $app->geo_loc_weight = $geoLocWeight;

                $newPriorityWeight = number_format(
                    round((float)$app->prio_assess_weight + (float)$geoLocWeight + (float)$app->grade_points_weight, 2),
                    2, '.', ''
                );

                $app->priority_weight = $newPriorityWeight;
                $app->updated_at = date('Y-m-d H:i:s');
                $app->save();

                $assessments = AssessorEvaluations::find([
                    'conditions' => 'application_ref_no = :ref:',
                    'bind'       => ['ref' => $applicationRefNo]
                ]);

                foreach ($assessments as $eval) {
                    $eval->priority_weight = $newPriorityWeight;
                    $eval->updated_at = date('Y-m-d H:i:s');
                    $eval->save();
                }
            }

            return $response->setJsonContent(['success' => true, 'message' => 'Location updated successfully.']);

        } catch (\Exception $e) {
            return $response->setJsonContent(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    public function updateGradesAction()
    {
        $this->view->disable();
        $response = new Response();

        try {
            $body = $this->request->getJsonRawBody(true);

            $applicationRefNo = $body['application_ref_no'] ?? null;
            $grades           = $body['grades'] ?? [];

            if (!$applicationRefNo) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => 'Missing application_ref_no.'
                ]);
            }

            $applicant = Applicants::findFirst([
                'conditions' => 'application_ref_no = :ref:',
                'bind'       => ['ref' => $applicationRefNo]
            ]);

            if (!$applicant) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => 'Applicant not found.'
                ]);
            }

            // Compute updated total grade points
            $totalGradePointsEditable = array_reduce($grades, function ($carry, $g) {
                return $carry + (float)($g['numeric_grade'] ?? 0);
            }, 0);

            // Save updated grades to applicant
            $applicant->grades_editable             = json_encode($grades);
            $applicant->total_grade_points_editable = $totalGradePointsEditable;

            if (!$applicant->save()) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => 'Failed to save grades.',
                    'errors'  => $applicant->getMessages()
                ]);
            }

            // Recompute grade_points_weight (30% of updated total)
            $gradePointsWeight = number_format(round($totalGradePointsEditable * 0.30, 2), 2, '.', '');

            // Update all related scholarship applications
            $scholarshipApps = ScholarshipApplications::find([
                'conditions' => 'application_ref_no = :ref:',
                'bind'       => ['ref' => $applicationRefNo]
            ]);

            foreach ($scholarshipApps as $app) {
                $app->grade_points_weight = $gradePointsWeight;

                // Recompute priority_weight with updated grade_points_weight
                $newPriorityWeight = number_format(
                    round(
                        (float)$app->prio_assess_weight +
                        (float)$app->geo_loc_weight +
                        (float)$gradePointsWeight,
                        2
                    ),
                    2, '.', ''
                );

                $app->priority_weight = $newPriorityWeight;
                $app->updated_at = date('Y-m-d H:i:s');
                $app->save();

                // Also update assessor evaluations priority_weight
                $assessments = AssessorEvaluations::find([
                    'conditions' => 'application_ref_no = :ref:',
                    'bind'       => ['ref' => $applicationRefNo]
                ]);

                foreach ($assessments as $eval) {
                    $eval->priority_weight = $newPriorityWeight;
                    $eval->updated_at = date('Y-m-d H:i:s');
                    $eval->save();
                }
            }

            return $response->setJsonContent([
                'success'                      => true,
                'message'                      => 'Grades updated successfully.',
                'total_grade_points_editable'  => $totalGradePointsEditable,
                'grade_points_weight'          => $gradePointsWeight
            ]);

        } catch (\Exception $e) {
            return $response->setJsonContent([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    ///////////////////////////
    // --- User Settings --- //
    ///////////////////////////
    public function createUserAction()
    {
        $this->view->disable(); 
        $response = new Response();

        try {
            $idNo        = trim($this->request->getPost('idNo', 'string'));
            $firstName   = trim($this->request->getPost('first_name', 'string'));
            $middleName  = trim($this->request->getPost('middle_name', 'string'));
            $lastName    = trim($this->request->getPost('last_name', 'string'));
            $designation = trim($this->request->getPost('designation', 'string'));
            $phone       = trim($this->request->getPost('phone', 'string'));
            $password    = trim($this->request->getPost('password', 'string'));
            $permissions = $this->request->getPost('permissions');

            if (!$idNo || !$firstName || !$lastName || !$password) {
                return $response->setJsonContent([
                    'status'  => 'error',
                    'message' => 'ID No, First name, Last name, and Password are required.'
                ]);
            }

            // handle avatar upload
            $avatarPath = null;
            if ($this->request->hasFiles(true)) {
                foreach ($this->request->getUploadedFiles() as $file) {
                    if ($file->getKey() === 'avatar' && $file->getSize() > 0) {
                        $uploadDir = dirname(APP_PATH) . '/public/admin-avatar/'; 
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        $filename = uniqid() . '_' . preg_replace('/\s+/', '_', $file->getName());
                        $file->moveTo($uploadDir . $filename);
                        $avatarPath = 'admin-avatar/' . $filename; // relative path for db
                    }
                }
            }
    

            $user = new Users();
            $user->idNo         = $idNo;
            $user->first_name   = ucwords(strtolower($firstName));
            $user->middle_name  = $middleName ? ucwords(strtolower($middleName)) : null;
            $user->last_name    = ucwords(strtolower($lastName));
            $user->designation  = $designation ? ucwords(strtolower($designation)) : null;
            $user->phone        = $phone;
            $user->password     = password_hash($password, PASSWORD_BCRYPT);
            $user->permissions  = $permissions;
            $user->is_locked    = 0;
            $user->avatar       = $avatarPath; // store path

            if ($user->save()) {
                return $response->setJsonContent([
                    'status'  => 'success',
                    'message' => 'User created successfully.'
                ]);
            } else {
                return $response->setJsonContent([
                    'status'  => 'error',
                    'message' => 'Failed to save user.',
                    'errors'  => $user->getMessages()
                ]);
            }
        } catch (\Exception $e) {
            return $response->setJsonContent([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
    private function isValidPassword(string $password): bool
    {
     return (bool) preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{12,}$/', $password);
    }
    // Update Password --
    public function changePasswordAction()
    {
        $this->view->disable(); 
        $response = new Response();
        $request = $this->request->getJsonRawBody(true);

        $idNo           = $request['idNo'] ?? null;  // current user id
        $oldPassword    = $request['oldPassword'] ?? null;
        $newPassword    = $request['newPassword'] ?? null;

        if (!$idNo || !$oldPassword || !$newPassword) {
            return $response->setJsonContent([
                'status'  => 'error',
                'message' => 'Missing required fields.'
            ]);
        }

        // Find user
        $user = Users::findFirstByIdNo($idNo);
        if (!$user) {
            return $response->setJsonContent([
                'status'  => 'error',
                'message' => 'User not found.'
            ]);
        }

        // Verify old password
        if (!password_verify($oldPassword, $user->password)) {
            return $response->setJsonContent([
                'status'  => 'error',
                'message' => 'Old password is incorrect.'
            ]);
        }

        // Validate new password
        if (!$this->isValidPassword($newPassword)) {
            return $response->setJsonContent([
                'status'  => 'error',
                'message' => 'New password must be at least 12 characters long, with uppercase, lowercase, number, and special character.'
            ]);
        }

        // Update password
        $user->password = password_hash($newPassword, PASSWORD_BCRYPT);

        if ($user->save()) {
            return $response->setJsonContent([
                'status'  => 'success',
                'message' => 'Password updated successfully.'
            ]);
        } else {
            return $response->setJsonContent([
                'status'  => 'error',
                'message' => 'Failed to update password.',
                'errors'  => $user->getMessages()
            ]);
        }
    }
    // Get current user permissions ---
    public function getMyPermissionsAction()
    {
        $this->view->disable();
        $response = new Response();

        // You should extract user idNo from JWT or session.
        // For now we use request (Angular sends it).
        $request = $this->request->getJsonRawBody(true);
        $idNo = $request['idNo'] ?? null;

        if (!$idNo) {
            return $response->setJsonContent([
                'status'  => 'error',
                'message' => 'Missing idNo.'
            ]);
        }

        $user = Users::findFirstByIdNo($idNo);
        if (!$user) {
            return $response->setJsonContent([
                'status'  => 'error',
                'message' => 'User not found.'
            ]);
        }

        return $response->setJsonContent([
            'status'      => 'success',
            'permissions' => $user->permissions ?? []
        ]);
    }
    // Update current user permissions ---
    public function updateMyPermissionsAction()
    {
        $this->view->disable();
        $response = new Response();
        $request = $this->request->getJsonRawBody(true);

        $idNo        = $request['idNo'] ?? null;
        $permissions = $request['permissions'] ?? [];

        if (!$idNo) {
            return $response->setJsonContent([
                'status'  => 'error',
                'message' => 'Missing idNo.'
            ]);
        }

        $user = Users::findFirstByIdNo($idNo);
        if (!$user) {
            return $response->setJsonContent([
                'status'  => 'error',
                'message' => 'User not found.'
            ]);
        }

        $user->permissions = json_encode($permissions);

        if ($user->save()) {
            return $response->setJsonContent([
                'status'  => 'success',
                'message' => 'Permissions updated successfully.',
                'permissions' => $permissions
            ]);
        } else {
            return $response->setJsonContent([
                'status'  => 'error',
                'message' => 'Failed to update permissions.',
                'errors'  => $user->getMessages()
            ]);
        }
    }
    // Fetch all users ---
    public function getAllUsersAction()
    {
        $this->view->disable(); 
        $response = new Response();

        try {
            $users = Users::find();
            $data = [];

            foreach ($users as $user) {
                $data[] = [
                    'id_number'   => $user->idNo,
                    'first_name'  => $user->first_name,
                    'middle_name' => $user->middle_name,
                    'last_name'   => $user->last_name,
                    'designation' => $user->designation,
                    'phone'       => $user->phone,
                    'is_locked'   => $user->is_locked,
                    'permissions' => $user->permissions ?? [],
                                        'avatar'      => $user->avatar ? $user->avatar : null,

                ];
            }

            return $response->setJsonContent([
                'status' => 'success',
                'data'   => $data
            ]);
        } catch (\Exception $e) {
            return $response->setJsonContent([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
    // Update user ---
    public function updateUserAction($idNo)
    {
        $this->view->disable();
        $response = new Response();

        try {
            $user = Users::findFirstByIdNo($idNo);
            if (!$user) {
                return $response->setJsonContent([
                    'status'  => 'error',
                    'message' => 'User not found'
                ]);
            }

            $data = $this->request->getPost(); 
            $files = $this->request->getUploadedFiles(); 

            // Handle avatar upload
            foreach ($files as $file) {
                if ($file->getKey() === 'avatar' && $file->getSize() > 0) {
                    $uploadDir = dirname(APP_PATH) . '/public/admin-avatar/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    // Remove old avatar if exists
                    if (!empty($user->avatar)) {
                        $oldFile = dirname(APP_PATH) . '/public/' . $user->avatar;
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }

                    // Save new avatar
                    $fileName = uniqid() . '_' . $file->getName();
                    $file->moveTo($uploadDir . $fileName);

                    $user->avatar = 'admin-avatar/' . $fileName; // save relative path
                }
            }
            // Update other fields
            $user->first_name  = isset($data['first_name']) ? ucwords(strtolower($data['first_name'])) : $user->first_name;
            $user->middle_name = isset($data['middle_name']) ? ucwords(strtolower($data['middle_name'])) : $user->middle_name;
            $user->last_name   = isset($data['last_name']) ? ucwords(strtolower($data['last_name'])) : $user->last_name;

            $user->designation = $data['designation'] ?? $user->designation;
            $user->phone       = $data['phone'] ?? $user->phone;

            if (!empty($data['password'])) {
                $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            if (!empty($data['permissions'])) {
                $user->permissions = $data['permissions'];
            }

            if ($user->save()) {
                return $response->setJsonContent([
                    'status'  => 'success',
                    'message' => 'User updated successfully',
                    'avatar'  => $user->avatar ? '/' . $user->avatar : null
                ]);
            } else {
                return $response->setJsonContent([
                    'status'  => 'error',
                    'message' => 'Failed to update user',
                    'errors'  => $user->getMessages()
                ]);
            }
        } catch (\Exception $e) {
            return $response->setJsonContent([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
    // Fetch all questions and options ---
    public function getQuestionsAction()
    {
        $this->view->disable();

        $questions = AssessmentQuestions::find([
            'order' => 'order_no ASC'
        ]);

        $result = [];

        foreach ($questions as $q) {

            $options = AssessmentOptions::find([
                'conditions' => 'question_id = :qid:',
                'bind'       => ['qid' => $q->id],
                'order'      => 'order_no ASC'
            ]);

            $optionsArray = [];

            foreach ($options as $opt) {
                $optionsArray[] = [
                    'text'   => $opt->option_text,
                    'points' => (int)($opt->points ?? 0)
                ];
            }

            $result[] = [
                'id'        => $q->question_code,
                'question'  => $q->question,
                'weight'  => $q->weight,
                'options'   => $optionsArray,
                'is_active' => (int)$q->is_active,
                'type' => $q->type,
            ];
        }

        return $this->response
            ->setContentType('application/json')
            ->setJsonContent([
                'success' => true,
                'data'    => $result
            ]);
    }
    // Update Assessment Question ---
    public function updateQuestionAction()
    {
        $this->view->disable();
        $response = new Response();

        try {
            $data = $this->request->getJsonRawBody(true);
            
            $questionId = $data['id'] ?? null;
            $questionText = $data['question'] ?? null;
            $weight = isset($data['weight']) ? (float)$data['weight'] : null;
            $points       = isset($data['points']) ? (int)$data['points'] : null;
            $options = $data['options'] ?? [];
            $type = $data['type'] ?? 'mcq';

            if (!$questionId || !$questionText) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => 'Question ID and text are required'
                ]);
            }

            $question = AssessmentQuestions::findFirst([
                'conditions' => 'question_code = ?1',
                'bind' => [1 => $questionId]
            ]);

            if (!$question) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => 'Question not found'
                ]);
            }

        $question->question   = $questionText;
                if ($weight !== null) {
                    $question->weight = number_format((float)$weight, 3, '.', '');
                }
                if ($points !== null) {
                    $question->points = $points; 
                }
                $question->updated_at = date('Y-m-d H:i:s');

                if (!$question->save()) {
                    return $response->setJsonContent([
                        'success' => false,
                        'message' => 'Failed to update question',
                        'errors'  => $question->getMessages()
                    ]);
                }

                $existingOptions = AssessmentOptions::find([
                    'conditions' => 'question_id = ?1',
                    'bind'       => [1 => $question->id]
                ]);

                foreach ($existingOptions as $opt) {
                    $opt->delete();
                }
                
                if ($type === 'short') {

                    // Always store a single scoring option
                    $opt = $options[0] ?? ['text' => 'SHORT_ANSWER', 'points' => 0];

                    $newOption = new AssessmentOptions();
                    $newOption->question_id = $question->id;
                    $newOption->option_text = 'SHORT_ANSWER';
                    $newOption->points      = (int)($opt['points'] ?? 0);
                    $newOption->order_no    = 1;

                    if (!$newOption->save()) {
                        return $response->setJsonContent([
                            'success' => false,
                            'message' => 'Failed to save short answer points',
                            'errors'  => $newOption->getMessages()
                        ]);
                    }

                } else {

                    foreach ($options as $index => $opt) {
                        if (!isset($opt['text']) || trim($opt['text']) === '') continue;

                        $newOption = new AssessmentOptions();
                        $newOption->question_id = $question->id;
                        $newOption->option_text = trim($opt['text']);
                        $newOption->points      = (int)($opt['points'] ?? 0);
                        $newOption->order_no    = $index + 1;

                        if (!$newOption->save()) {
                            return $response->setJsonContent([
                                'success' => false,
                                'message' => 'Failed to save option',
                                'errors'  => $newOption->getMessages()
                            ]);
                        }
                    }

                }

                return $response->setJsonContent([
                    'success' => true,
                    'message' => 'Question updated successfully'
                ]);

            } catch (\Exception $e) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
    }
    // Set Question Status ---
    public function setQuestionStatusAction()
    {
        $this->view->disable();
        $response = new Response();

        try {
            $data = $this->request->getJsonRawBody(true);

            $questionCode = $data['id'] ?? null;
            $status       = $data['is_active'] ?? null;

            if ($questionCode === null || !in_array($status, [0, 1], true)) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => 'Invalid parameters'
                ]);
            }

            $question = AssessmentQuestions::findFirst([
                'conditions' => 'question_code = ?1',
                'bind'       => [1 => $questionCode]
            ]);

            if (!$question) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => 'Question not found'
                ]);
            }

            $question->is_active  = $status;
            $question->updated_at = date('Y-m-d H:i:s');

            if ($question->save()) {
                return $response->setJsonContent([
                    'success' => true,
                    'message' => $status === 1
                        ? 'Question activated'
                        : 'Question deactivated'
                ]);
            }

            return $response->setJsonContent([
                'success' => false,
                'message' => 'Failed to update status',
                'errors'  => $question->getMessages()
            ]);

        } catch (\Exception $e) {
            return $response->setJsonContent([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    public function createQuestionAction()
    {
        $this->view->disable();
        $response = new Response();

        try {
            $data = $this->request->getJsonRawBody(true);

            $questionCode = trim($data['question_code'] ?? '');
            $questionText = trim($data['question'] ?? '');
            $weight = (float)($data['weight'] ?? 0);
            $question->type = $data['type'] ?? 'mcq';
            $options      = $data['options'] ?? [];

            if (!$questionCode || !$questionText) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => 'Question code and question text are required'
                ]);
            }

            $existing = AssessmentQuestions::findFirst([
                'conditions' => 'question_code = ?1',
                'bind' => [1 => $questionCode]
            ]);

            if ($existing) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => 'Question code already exists'
                ]);
            }

            $lastOrder = AssessmentQuestions::maximum(['column' => 'order_no']) ?? 0;

            $question = new AssessmentQuestions();
            $question->question_code = $questionCode;
            $question->question      = $questionText;
            $question->weight = $weight;
            $question->order_no      = $lastOrder + 1;
            $question->is_active     = 1;
            $question->created_at    = date('Y-m-d H:i:s');

            if (!$question->save()) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => 'Failed to save question',
                    'errors'  => $question->getMessages()
                ]);
            }

            foreach ($options as $index => $opt) {
                $text   = trim($opt['text'] ?? '');
                $points = (int)($opt['points'] ?? 0);

                if (!$text) continue;

                $option = new AssessmentOptions();
                $option->question_id = $question->id;
                $option->option_text = $text;
                $option->points      = $points;
                $option->order_no    = $index + 1;

                if (!$option->save()) {
                    return $response->setJsonContent([
                        'success' => false,
                        'message' => 'Failed to save options',
                        'errors'  => $option->getMessages()
                    ]);
                }
            }

            return $response->setJsonContent([
                'success' => true,
                'message' => 'Question created successfully'
            ]);

        } catch (\Exception $e) {
            return $response->setJsonContent([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    ///////////////////////
    // --- Evaluator --- //
    ///////////////////////
    // Save assessor's assessment answer
    public function saveAssessorEvaluationAction()
    {
        $this->view->disable();
        $response = new Response();

        try {
            $data = $this->request->getJsonRawBody(true);

            $applicationRefNo = $data['application_ref_no'] ?? null;
            $assessorIdNo     = $data['assessor_id_no'] ?? null;
            $answers          = $data['answers'] ?? null;

            if (!$applicationRefNo || !$assessorIdNo || !is_array($answers)) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => 'Missing or invalid required fields'
                ]);
            }

            // --- Fetch the scholarship application for weights ---
            $scholarshipApplication = ScholarshipApplications::findFirst([
                'conditions' => 'application_ref_no = :ref:',
                'bind'       => ['ref' => $applicationRefNo]
            ]);

            if (!$scholarshipApplication) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => 'Invalid scholarship application'
                ]);
            }
            
            // --- Fetch questions ---
            $questionCodes = array_column($answers, 'id');
            $questions = AssessmentQuestions::find([
                'conditions' => 'question_code IN ({codes:array}) AND is_active = 1',
                'bind'       => ['codes' => $questionCodes]
            ]);

            $questionMap = [];
            foreach ($questions as $q) {
                $questionMap[$q->question_code] = [
                    'id'     => $q->id,
                    'weight' => (float) $q->weight
                ];
            }

            if (empty($questionMap)) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => 'No matching active questions found'
                ]);
            }

            // --- Compute max points ---
            $phql = "
                SELECT question_id, MAX(points) AS max_point
                FROM AssessmentOptions
                WHERE question_id IN ({qids:array})
                GROUP BY question_id
            ";
            $result = $this->modelsManager->executeQuery(
                $phql,
                ['qids' => array_column($questionMap, 'id')]
            );

            $maxPointsMap = [];
            foreach ($result as $row) {
                $maxPointsMap[$row->question_id] = (int) $row->max_point;
            }

            $maxScore = array_sum($maxPointsMap);

            $totalScore = 0;
            $autoWeightedScore = 0.0;
            $manualWeightScore = 0.0;

            foreach ($answers as $answer) {
                $qCode = $answer['id'] ?? null;
                $text  = $answer['answer'] ?? null;
                $manualWeight = isset($answer['weight']) ? (float)$answer['weight'] : 0;

                if (!$qCode || !isset($questionMap[$qCode])) {
                    continue;
                }

                $qid    = $questionMap[$qCode]['id'];
                $weight = $questionMap[$qCode]['weight'];

                $option = AssessmentOptions::findFirst([
                    'conditions' => 'question_id = :qid: AND option_text = :text:',
                    'bind'       => ['qid' => $qid, 'text' => $text]
                ]);

                if ($option) {
                    // --- MCQ auto scoring ---
                    $points = (int) $option->points;
                    $totalScore += $points;
                    $autoWeightedScore += ($weight / 100) * $points;
                } else {
                    // --- Short answer manual scoring ---
                    if ($manualWeight > 0) {
                        $manualWeightScore += $manualWeight;
                    }
                }
            }

            $autoWeightedScore  = round($autoWeightedScore, 2);
            $manualWeightScore  = round($manualWeightScore, 2);
            $finalWeightedScore = round($autoWeightedScore + $manualWeightScore, 2);
             // --- Compute priority weight ---
            $priorityWeight = round(
                ($finalWeightedScore * 0.35) +
                (float)$scholarshipApplication->geo_loc_weight +
                (float)$scholarshipApplication->grade_points_weight,
                4
            );

            $now = date('Y-m-d H:i:s');

            // --- Save or update evaluation ---
            $evaluation = AssessorEvaluations::findFirst([
                'conditions' => 'application_ref_no = :ref: AND assessor_id_no = :assessor:',
                'bind'       => ['ref' => $applicationRefNo, 'assessor' => $assessorIdNo]
            ]);

            if (!$evaluation) {
                $evaluation = new AssessorEvaluations();
                $evaluation->application_ref_no = $applicationRefNo;
                $evaluation->assessor_id_no     = $assessorIdNo;
                $evaluation->created_at         = $now;
            }

            $evaluation->answers           = json_encode($answers);
            $evaluation->total_score       = $totalScore;
            $evaluation->max_score         = $maxScore;
            $evaluation->auto_score        = $autoWeightedScore;
            $evaluation->manual_score      = $manualWeightScore;
            $evaluation->assessment_weight = $finalWeightedScore;
            $evaluation->priority_weight   = $priorityWeight;

            $evaluation->status         = 'evaluated';
            $evaluation->updated_at     = $now;
            $evaluation->submitted_at   = $now;

            if (!$evaluation->save()) {
                return $response->setJsonContent([
                    'success' => false,
                    'message' => 'Failed to save assessor evaluation',
                    'errors'  => $evaluation->getMessages()
                ]);
            }

            return $response->setJsonContent([
                'success'            => true,
                'total_score'        => number_format($totalScore, 2, '.', ''),
                'max_score'          => number_format($maxScore, 2, '.', ''),
                'auto_score'         => number_format($autoWeightedScore, 2, '.', ''),
                'manual_score'       => number_format($manualWeightScore, 2, '.', ''),
                'assessment_weight'  => number_format($finalWeightedScore, 2, '.', ''),
                'priority_weight'    => number_format($priorityWeight, 2, '.', '')
            ]);

        } catch (\Exception $e) {
            return $response->setJsonContent([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    // Fetch assessor's answer and used in comparison with the applicant's answer 
    public function getAssessorEvaluationAction($applicationRefNo)
    {
        $evaluation = AssessorEvaluations::findFirst([
            'conditions' => 'application_ref_no = :ref:',
            'bind' => ['ref' => $applicationRefNo],
            'order' => 'created_at DESC'
        ]);

        if (!$evaluation) {
            return $this->response->setJsonContent([
                'success' => false,
                'data' => null
            ]);
        }

        return $this->response->setJsonContent([
            'success' => true,
            'data' => json_decode($evaluation->answers, true)
        ]);
    }


}

