<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReportService;
use App\Services\DataLoaderService;

class CreateReport extends Command
{
    protected $signature = 'report:generate
                        {studentId? : The ID of the student}
                        {reportType? : The report type (1=diagnostic, 2=progress, 3=feedback)}';

    protected $description = 'Generate an assessment report (diagnostic, progress, or feedback) for a given student.';

    protected ReportService $reportService;
    protected DataLoaderService $dataLoader;

    public function __construct(ReportService $reportService, DataLoaderService $dataLoader)
    {
        parent::__construct();
        $this->reportService = $reportService;
        $this->dataLoader = $dataLoader;
    }

    public function handle()
    {
        $this->info("Please enter the following");

        $studentId = $this->argument('studentId') ?: $this->ask('Enter Student ID');

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

        $students = $this->dataLoader->load('students.json');
        $assessments = $this->dataLoader->load('assessments.json');
        $questions = $this->dataLoader->load('questions.json');
        $responses = $this->dataLoader->load('student-responses.json');

        if (!$students || !$assessments || !$questions || !$responses) {
            $this->error('One or more data files failed to load.');
            return 1;
        }

        $student = collect($students)->firstWhere('id', $studentId);
        if (!$student) {
            $this->error("Student with ID '{$studentId}' not found.");
            return 1;
        }

        $studentResponses = collect($responses)->filter(function ($response) use ($studentId) {
            return $response['student']['id'] === $studentId && !empty($response['completed']);
        })->values();

        if ($studentResponses->isEmpty()) {
            $this->info("No completed assessments found for student {$studentId}.");
            return 0;
        }

        match ($reportType) {
            'diagnostic' => $this->reportService->generateDiagnosticReport($this, $student, $studentResponses, $assessments, $questions),
            'progress' => $this->reportService->generateProgressReport($this, $student, $studentResponses, $assessments),
            'feedback' => $this->reportService->generateFeedbackReport($this, $student, $studentResponses, $assessments, $questions),
        };

        return 0;
    }
}
