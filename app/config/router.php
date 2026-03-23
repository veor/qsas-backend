<?php
$router = $di->getRouter();

// --- DEVELOPMENT SSL ---
$baseUri='/qsas/qsas-backend';

/* --- PRODUCTION --- */
// $baseUri='https://qsas.quezon.gov.ph/';

 // submit applicant details
/* --- PRODUCTION --- */
// $router->add($baseUri . 'qsas-backend/applicant/submit', [
// --- DEVELOPMENT SSL ---
$router->addPost('/submit', [
    'controller' => 'applicant',
    'action'     => 'submit',
]);

/* --- PRODUCTION --- */
// $router->addGet($baseUri . 'qsas-backend/applicant/fetchDistrictAndMunicipality', [
// --- DEVELOPMENT SSL ---
$router->addGet('/districts-municipalities', [
    'controller' => 'applicant',
    'action'     => 'fetchDistrictAndMunicipality',
]);

// check if applicant exists
/* --- PRODUCTION --- */
// $router->addPost($baseUri . 'qsas-backend/applicant/checkDuplicate', [
// --- DEVELOPMENT SSL ---
$router->addPost('/checkDuplicate', [ 
    'controller' => 'applicant',
    'action'     => 'checkDuplicate',
]);

// verify secret answer if correct
/* --- PRODUCTION --- */
// $router->addPost($baseUri . 'qsas-backend/applicant/verifySecret', [ 
// --- DEVELOPMENT SSL ---
$router->addPost('/verifySecret', [ 
    'controller' => 'applicant',
    'action'     => 'verifySecret',
]);

// update applicant 
/* --- PRODUCTION --- */
// $router->addPost($baseUri . 'qsas-backend/applicant/update', [ 
// --- DEVELOPMENT SSL ---
$router->addPost('/update', [ 
    'controller' => 'applicant',
    'action'     => 'update',
]);

 //fetch all applicants
/* --- PRODUCTION --- */
// $router->addGet($baseUri . 'qsas-backend/admin/getApplicants', [ 
// --- DEVELOPMENT SSL ---
$router->addGet('/admin/applicants', [
    'controller' => 'admin',
    'action'     => 'getApplicants',
]);

//fetch and count all scholarship
/* --- PRODUCTION --- */
// $router->addGet($baseUri . 'qsas-backend/admin/getScholarshipCounts', [ 
// --- DEVELOPMENT SSL ---
$router->addGet('/admin/scholarship-counts', [ 
    'controller' => 'admin',
    'action'     => 'getScholarshipCounts',
]);

// create new admin/user
/* --- PRODUCTION --- */
// $router->addPost($baseUri . 'qsas-backend/admin/createUser', [ 
// --- DEVELOPMENT SSL ---
$router->addPost('/admin/create-user', [ 
    'controller' => 'admin',
    'action'     => 'createUser',
]);

// change admin/user password
/* --- PRODUCTION --- */
// $router->addPost($baseUri . 'qsas-backend/admin/changePassword', [ 
// --- DEVELOPMENT SSL ---
$router->addPost('/admin/change-password', [ 
    'controller' => 'admin',
    'action'     => 'changePassword',
]);

// fetch admin/user perms
/* --- PRODUCTION --- */
// $router->addPost($baseUri . 'qsas-backend/admin/getMyPermissions', [
// --- DEVELOPMENT SSL ---
$router->addPost('/admin/get-my-permissions', [ 
    'controller' => 'admin',
    'action'     => 'getMyPermissions',
]);

// update admin/user perms
/* --- PRODUCTION --- */
// $router->addPost($baseUri . 'qsas-backend/admin/updateMyPermissions', [ 
// --- DEVELOPMENT SSL ---
$router->addPost('/admin/update-my-permissions', [ 
    'controller' => 'admin',
    'action'     => 'updateMyPermissions',
]);

// fetch all admin/users
/* --- PRODUCTION --- */
// $router->addGet($baseUri . 'qsas-backend/admin/getAllUsers', [ 
// --- DEVELOPMENT SSL ---
$router->addGet('/admin/users', [ 
    'controller' => 'admin',
    'action'     => 'getAllUsers',
]);

// update admin/user info
/* --- PRODUCTION --- */
// $router->addPost($baseUri . 'qsas-backend/admin/updateUser/{idNo}', [
// --- DEVELOPMENT SSL ---
$router->addPost('/admin/users/{idNo}', [ 
    'controller' => 'admin',
    'action'     => 'updateUser',
]); 

// save applicant assessment answers
/* --- PRODUCTION --- */
// $router->addPost($baseUri . 'qsas-backend/assessment/saveAssessmentAnswers', [
// --- DEVELOPMENT SSL ---
$router->addPost('/assessment/save', [ 
    'controller' => 'assessment',
    'action'     => 'saveAssessmentAnswers'
]);

// fecth applicant assessment answers
/* --- PRODUCTION --- */
// $router->addGet($baseUri .  'qsas-backend/getAssessmentAnswers/{applicationRefNo}', [
// --- DEVELOPMENT SSL ---
$router->addGet('/assessment/get/{applicationRefNo}', [ 
    'controller' => 'applicant',
    'action'     => 'getAssessmentAnswers'
]);

// fecth applicant assessment answers
/* --- PRODUCTION --- */
// $router->addGet($baseUri . 'qsas-backend/admin/getAssessorEvaluation/{applicationRefNo}', [ 
// --- DEVELOPMENT SSL ---
$router->addGet('/admin/assessment/get/{applicationRefNo}', [ 
    'controller' => 'admin',
    'action'     => 'getAssessorEvaluation'
]);


// Save assessor evaluation
/* --- PRODUCTION --- */
// $router->addPost($baseUri . 'qsas-backend/admin/saveAssessorEvaluation', [
// --- DEVELOPMENT SSL ---
$router->addPost('/admin/assessment/save', [
    'controller' => 'admin',
    'action'     => 'saveAssessorEvaluation'
]);

// Get assessor's own evaluation for an application
/* --- PRODUCTION --- */
// $router->addGet($baseUri . 'qsas-backend/admin/getAssessorEvaluation/{applicationRefNo}', [
// --- DEVELOPMENT SSL ---
$router->addGet('/admin/assessment/get/{applicationRefNo}', [
    'controller' => 'admin',
    'action'     => 'getAssessorEvaluation'
]);

// Get all evaluations for an application (for comparison)
/* --- PRODUCTION --- */
// $router->addGet($baseUri . 'qsas-backend/admin/getAllEvaluationsForApplication/{applicationRefNo}', [
// --- DEVELOPMENT SSL ---
$router->addGet('/admin/assessment/all/{applicationRefNo}', [
    'controller' => 'admin',
    'action'     => 'getAllEvaluationsForApplication'
]);

/* --- PRODUCTION --- */
// $router->addPost($baseUri . 'qsas-backend/admin/updateGrades', [
// --- DEVELOPMENT SSL ---
$router->addPost('/admin/update-grades', [
    'controller' => 'admin',
    'action'     => 'updateGrades'
]);

// --- PRODUCTION ---
// $router->addPost($baseUri . '/qsas-backend/admin/updateLocation', [
// --- DEVELOPMENT SSL ---
$router->addPost('/admin/update-location', [
    'controller' => 'admin',
    'action'     => 'updateLocation'
]);

// $router->addGet('/admin/getTopByCourseForPriorityCourses', [
$router->addGet('/admin/top-by-course', [
    'controller' => 'admin',
    'action'     => 'getTopByCourseForPriorityCourses'
]);

// $router->addGet('/admin/getTopByMunicipality', [
$router->addGet('/admin/top-by-municipality', [
    'controller' => 'admin',
    'action'     => 'getTopByMunicipality'
]);


$router->handle($_SERVER['REQUEST_URI']);