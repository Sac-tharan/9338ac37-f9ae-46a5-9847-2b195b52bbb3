<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateReport extends Command
{
    protected $signature = 'report:generate
                        {studentId? : The ID of the student}
                        {reportType? : The report type (diagnostic, progress, feedback)}';

    protected $description = 'Generate an assessment report (diagnostic, progress, or feedback) for a given student.';

    protected function loadJsonFile(string $filename): array
    {
        // Assuming JSON files are stored in the 'storage/app/data' directory
        $path = storage_path("app/data/{$filename}");

        if (!file_exists($path)) {
            $this->error("File not found: {$filename}");
            return [];
        }

        $jsonContent = file_get_contents($path);

        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON in file: {$filename}");
            return [];
        }

        return $data;
    }

    public function generateDiagnosticReport($student, $studentResponses, $assessments, $questions)
    {
        $latest = $studentResponses->sortByDesc('completed')->first();
        $assessment = collect($assessments)->firstWhere('id', $latest['assessmentId']);
        $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));

        $completedDate = \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', $latest['completed'])->format('jS F Y h:i A');
        $totalQuestions = count($latest['responses']);
        $correctCount = 0;

        $strandStats = [];

        foreach ($latest['responses'] as $response) {
            $question = collect($questions)->firstWhere('id', $response['questionId']);
            if (!$question) {
                continue;
            }

            $strand = $question['strand'] ?? 'Unknown';
            $correctAnswer = $question['config']['key'] ?? null;
            $studentAnswer = $response['response'] ?? null;

            if (!isset($strandStats[$strand])) {
                $strandStats[$strand] = ['correct' => 0, 'total' => 0];
            }
            $strandStats[$strand]['total']++;

            if ($studentAnswer === $correctAnswer) {
                $correctCount++;
                $strandStats[$strand]['correct']++;
            }
        }

        $this->info("{$studentName} recently completed {$assessment['name']} assessment on {$completedDate}");
        $this->info("He got {$correctCount} questions right out of {$totalQuestions}. Details by strand given below:\n");

        foreach ($strandStats as $strand => $stats) {
            $this->info("{$strand}: {$stats['correct']} out of {$stats['total']} correct");
        }
    }

    protected function generateProgressReport($student, $studentResponses, $assessments)
    {
        $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: $student['id'];

        // Get assessment name
        $assessmentIds = $studentResponses->pluck('assessmentId')->unique();
        $assessmentName = 'Assessment';
        foreach ($assessmentIds as $assessmentId) {
            $assessment = collect($assessments)->firstWhere('id', $assessmentId);
            if ($assessment) {
                $assessmentName = $assessment['name'];
                break;
            }
        }

        // Sort responses by completed date using correct format
        $sortedResponses = $studentResponses->sortBy(function ($resp) {
            $completedDate = $resp['assigned'] ?? '';
            $dt = \DateTime::createFromFormat('d/m/Y H:i:s', $completedDate);
            return $dt ? $dt->getTimestamp() : 0;
        });

        $totalAttempts = $sortedResponses->count();
        $this->info("{$studentName} has completed {$assessmentName} assessment {$totalAttempts} times in total. Date and raw score given below:\n");

        foreach ($sortedResponses as $resp) {
            $completedDate = $resp['assigned'] ?? null;
            $dateFormatted = 'Unknown date';

            if ($completedDate) {
                $dt = \DateTime::createFromFormat('d/m/Y H:i:s', $completedDate);
                if ($dt) {
                    $dateFormatted = $dt->format('jS F Y');
                }
            }

            $rawScore = $resp['results']['rawScore'] ?? 0;
            $totalQuestions = count($resp['responses'] ?? []) ?: 16;

            $this->info("Date: {$dateFormatted}, Raw Score: {$rawScore} out of {$totalQuestions}");
        }

        if ($totalAttempts >= 2) {
            $oldestScore = $sortedResponses->first()['results']['rawScore'] ?? 0;
            $latestScore = $sortedResponses->last()['results']['rawScore'] ?? 0;
            $improvement = $latestScore - $oldestScore;

            $this->info("\n{$studentName} got {$improvement} more correct in the recent completed assessment than the oldest");
        }
    }

    protected function generateFeedbackReport($student, $studentResponses, $assessments, $questions)
{
    $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: $student['id'];

    // Get the latest completed response (sort by completed date)
    $latest = collect($studentResponses)->sortBy(function ($resp) {
        $date = $resp['completed'] ?? null;
        $dt = \DateTime::createFromFormat('d/m/Y H:i:s', $date);
        return $dt ? $dt->getTimestamp() : 0;
    })->last();

    if (!$latest) {
        $this->warn("No completed assessments available.");
        return;
    }

    $completedRaw = $latest['completed'] ?? null;
    $completed = 'Unknown date and time';
    if ($completedRaw) {
        $dt = \DateTime::createFromFormat('d/m/Y H:i:s', $completedRaw);
        if ($dt) {
            $completed = $dt->format('jS F Y h:i A');
        }
    }

    // Get assessment name
    $assessment = collect($assessments)->firstWhere('id', $latest['assessmentId']);
    $assessmentName = $assessment['name'] ?? 'Assessment';

    $rawScore = $latest['results']['rawScore'] ?? 0;
    $totalQuestions = count($latest['responses']);

    $this->info("{$studentName} recently completed {$assessmentName} assessment on {$completed}");
    $this->info("He got {$rawScore} questions right out of {$totalQuestions}. Feedback for wrong answers given below:\n");

    // Check each response
    foreach ($latest['responses'] as $response) {
        $questionId = $response['questionId'];
        $selectedOptionId = $response['response'];

        $question = collect($questions)->firstWhere('id', $questionId);
        if (!$question) continue;

        $correctOptionId = $question['config']['key'];
        if ($selectedOptionId === $correctOptionId) {
            continue; // Skip correct answers
        }

        $options = collect($question['config']['options']);
        $userOption = $options->firstWhere('id', $selectedOptionId);
        $rightOption = $options->firstWhere('id', $correctOptionId);

        $yourAnswer = $userOption ? "{$userOption['label']} with value {$userOption['value']}" : 'Unknown';
        $correctAnswer = $rightOption ? "{$rightOption['label']} with value {$rightOption['value']}" : 'Unknown';
        $hint = $question['config']['hint'] ?? 'No hint available';

        $this->line("Question: {$question['stem']}");
        $this->line("Your answer: {$yourAnswer}");
        $this->line("Right answer: {$correctAnswer}");
        $this->line("Hint: {$hint}\n");
    }
}






    public function handle()
    {
        $this->info("Please enter the following");

        $studentId = $this->argument('studentId') ?: $this->ask('Student ID');

        // Show numeric prompt and map input to type
        $reportNumber = $this->argument('reportType');
        if (!in_array($reportNumber, ['1', '2', '3'])) {
            $reportNumber = $this->ask('Report to generate (1 for Diagnostic, 2 for Progress, 3 for Feedback)');
        }

        $reportTypesMap = [
            '1' => 'diagnostic',
            '2' => 'progress',
            '3' => 'feedback',
        ];

        $reportType = $reportTypesMap[$reportNumber] ?? null;

        if (!$reportType) {
            $this->error('Invalid report type selected.');
            return 1;
        }

        // Load all data files...
        $students = $this->loadJsonFile('students.json');
        $assessments = $this->loadJsonFile('assessments.json');
        $questions = $this->loadJsonFile('questions.json');
        $responses = $this->loadJsonFile('student-responses.json');

        if (!$students || !$assessments || !$questions || !$responses) {
            $this->error('One or more data files failed to load.');
            return 1;
        }

        // Find student
        $student = collect($students)->firstWhere('id', $studentId);
        if (!$student) {
            $this->error("Student with ID '{$studentId}' not found.");
            return 1;
        }

        // Filter completed responses (adjust keys according to your JSON)
        $studentResponses = collect($responses)->filter(function ($response) use ($studentId) {
            return $response['student']['id'] === $studentId && !empty($response['completed']);
        })->values();

        if ($studentResponses->isEmpty()) {
            $this->info("No completed assessments found for student {$studentId}.");
            return 0;
        }

        // Call report generation method according to $reportType
        switch ($reportType) {
            case 'diagnostic':
                $this->generateDiagnosticReport($student, $studentResponses, $assessments, $questions);
                break;

            case 'progress':
                $this->generateProgressReport($student, $studentResponses, $assessments);
                break;

            case 'feedback':
                $this->generateFeedbackReport($student, $studentResponses, $assessments, $questions);
                break;
        }

        return 0;
    }

}
