<?php
declare(strict_types=1);

class AssessmentController extends \Phalcon\Mvc\Controller
{
// --- User Settings ---
    // Fetch all questions for assessment form ---
    public function getQuestionsAction()
    {
        $questions = AssessmentQuestions::find([
            'conditions' => 'is_active = 1',
            'order' => 'order_no ASC'
        ]);

        $result = [];

        foreach ($questions as $q) {
            $optionsArray = [];

            if ($q->type !== 'short') {
                $options = AssessmentOptions::find([
                    'conditions' => 'question_id = :qid:',
                    'bind' => ['qid' => $q->id],
                    'order' => 'order_no ASC'
                ]);

                foreach ($options as $opt) {
                    $optionsArray[] = $opt->option_text;
                }
            }

            $result[] = [
                'id'       => $q->question_code,
                'question' => $q->question,
                'type'     => $q->type,     
                'options'  => $optionsArray
            ];
        }

        return $this->response
                    ->setContentType('application/json')
                    ->setJsonContent(['success' => true, 'data' => $result]);
    }

// --- Save applicant assessment answers ---
    public function saveAssessmentAnswersAction()
    {
        $request = $this->request;

        if (!$request->isPost()) {
            return $this->response
                ->setJsonContent(['error' => 'Invalid request'])
                ->setStatusCode(400);
        }

        $data = $request->getJsonRawBody(true);
        $applicationRefNo = $data['application_ref_no'] ?? null;
        $answers = $data['answers'] ?? null;

        if (!$applicationRefNo || !is_array($answers)) {
            return $this->response
                ->setJsonContent(['error' => 'Missing or invalid data'])
                ->setStatusCode(400);
        }

        // Fetch questions (ID only)
        $questionCodes = array_column($answers, 'id');
        $questions = AssessmentQuestions::find([
            'conditions' => 'question_code IN ({codes:array})',
            'bind' => ['codes' => $questionCodes]
        ]);

        $questionMap = [];
        foreach ($questions as $q) {
            $questionMap[$q->question_code] = $q->id;
        }

        if (empty($questionMap)) {
            return $this->response
                ->setJsonContent(['error' => 'No matching questions found'])
                ->setStatusCode(400);
        }

        // Compute max score
        $phql = "
            SELECT question_id, MAX(points) AS max_point
            FROM AssessmentOptions
            WHERE question_id IN ({qids:array})
            GROUP BY question_id
        ";

        $result = $this->modelsManager->executeQuery(
            $phql,
            ['qids' => array_values($questionMap)]
        );

        $maxPointsMap = [];
        foreach ($result as $row) {
            $maxPointsMap[$row->question_id] = (int)$row->max_point;
        }

        $maxScore = array_sum($maxPointsMap);

        // Compute total score (no weighted grade yet)
        $totalScore = 0;
        foreach ($answers as $answer) {
            $qCode = $answer['id'];
            $text = $answer['answer'] ?? null;
            if (!$text || !isset($questionMap[$qCode])) continue;

            $qid = $questionMap[$qCode];
            $option = AssessmentOptions::findFirst([
                'conditions' => 'question_id = :qid: AND option_text = :text:',
                'bind' => ['qid' => $qid, 'text' => $text]
            ]);

            if ($option) {
                $totalScore += (int)$option->points;
            }
        }

        $now = date('Y-m-d H:i:s');

        // Save or update record
        $record = AssessmentAnswers::findFirstByApplicationRefNo($applicationRefNo);
        if (!$record) {
            $record = new AssessmentAnswers();
            $record->application_ref_no = $applicationRefNo;
            $record->created_at = $now;
        }

        $record->answers = json_encode($answers);
        $record->total_score = $totalScore;
        $record->max_score = $maxScore;
        $record->status = 'submitted';
        $record->updated_at = $now;
        $record->submitted_at = $now;
        $record->save();

        return $this->response->setJsonContent([
            'success' => true,
            'total_score' => $totalScore,
            'max_score' => $maxScore
        ]);
    }



}

