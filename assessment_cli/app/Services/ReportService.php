<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Console\Command;

class ReportService
{
    public function generateDiagnosticReport(Command $cmd, $student, $responses, $assessments, $questions)
    {
        $latest = $responses->sortByDesc('completed')->first();
        $assessment = collect($assessments)->firstWhere('id', $latest['assessmentId']);
        $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));

        $completedDate = Carbon::createFromFormat('d/m/Y H:i:s', $latest['completed'])->format('jS F Y h:i A');
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

            $strandStats[$strand] ??= ['correct' => 0, 'total' => 0];
            $strandStats[$strand]['total']++;

            if ($studentAnswer === $correctAnswer) {
                $correctCount++;
                $strandStats[$strand]['correct']++;
            }
        }

        $cmd->info("{$studentName} recently completed {$assessment['name']} assessment on {$completedDate}");
        $cmd->info("He got {$correctCount} questions right out of {$totalQuestions}. Details by strand given below:\n");

        foreach ($strandStats as $strand => $stats) {
            $cmd->info("{$strand}: {$stats['correct']} out of {$stats['total']} correct");
        }
    }

    public function generateProgressReport(Command $cmd, $student, $responses, $assessments)
    {
        $studentName = trim((isset($student['firstName']) ? $student['firstName'] : '') . ' ' . (isset($student['lastName']) ? $student['lastName'] : ''));
        if ($studentName === '') {
            $studentName = isset($student['id']) ? $student['id'] : 'Unknown Student';
        }

        $assessmentIds = $responses->pluck('assessmentId')->unique();
        $assessmentName = 'Assessment';

        foreach ($assessmentIds as $id) {
            $assessment = collect($assessments)->firstWhere('id', $id);
            if ($assessment && isset($assessment['name'])) {
                $assessmentName = $assessment['name'];
                break;
            }
        }

        // Sort by assigned date
        $sorted = $responses->sortBy(function ($resp) {
            if (empty($resp['assigned'])) {
                return 0;
            }

            $dt = \DateTime::createFromFormat('d/m/Y H:i:s', $resp['assigned']);
            return $dt ? $dt->getTimestamp() : 0;
        });

        $count = $sorted->count();

        $cmd->info("{$studentName} has completed {$assessmentName} assessment {$count} times in total. Date and raw score given below:\n");

        foreach ($sorted as $resp) {
            $date = 'Unknown date';
            if (!empty($resp['assigned'])) {
                $dt = \DateTime::createFromFormat('d/m/Y H:i:s', $resp['assigned']);
                $date = $dt ? $dt->format('jS F Y') : $date;
            }

            $score = isset($resp['results']['rawScore']) ? $resp['results']['rawScore'] : 0;
            $total = count(isset($resp['responses']) ? $resp['responses'] : []) ?: 16;

            $cmd->info("Date: {$date}, Raw Score: {$score} out of {$total}");
        }

        if ($count >= 2) {
            $firstScore = isset($sorted->first()['results']['rawScore']) ? $sorted->first()['results']['rawScore'] : 0;
            $lastScore = isset($sorted->last()['results']['rawScore']) ? $sorted->last()['results']['rawScore'] : 0;
            $improvement = $lastScore - $firstScore;

            $cmd->info("\n{$studentName} got {$improvement} more correct in the recent completed assessment than the oldest");
        }
    }

    public function generateFeedbackReport(Command $cmd, $student, $responses, $assessments, $questions)
    {
        // Get student name fallback to id if names missing
        $studentName = trim((isset($student['firstName']) ? $student['firstName'] : '') . ' ' . (isset($student['lastName']) ? $student['lastName'] : ''));
        if ($studentName === '') {
            $studentName = isset($student['id']) ? $student['id'] : 'Unknown Student';
        }

        // Sort responses by completed date
        $sortedResponses = collect($responses)->sortBy(function ($resp) {
            if (isset($resp['completed'])) {
                $dt = \DateTime::createFromFormat('d/m/Y H:i:s', $resp['completed']);
                return $dt ? $dt->getTimestamp() : 0;
            }
            return 0;
        });

        $latest = $sortedResponses->last();

        if (!$latest) {
            $cmd->warn("No completed assessments available.");
            return;
        }

        // Format completion date
        $completed = 'Unknown';
        if (isset($latest['completed'])) {
            $dt = \DateTime::createFromFormat('d/m/Y H:i:s', $latest['completed']);
            if ($dt) {
                $completed = $dt->format('jS F Y h:i A');
            }
        }

        // Find assessment name
        $assessment = collect($assessments)->firstWhere('id', isset($latest['assessmentId']) ? $latest['assessmentId'] : null);
        $assessmentName = $assessment && isset($assessment['name']) ? $assessment['name'] : 'Assessment';

        $rawScore = isset($latest['results']['rawScore']) ? $latest['results']['rawScore'] : 0;
        $total = isset($latest['responses']) ? count($latest['responses']) : 0;

        $cmd->info("{$studentName} recently completed {$assessmentName} assessment on {$completed}");
        $cmd->info("He got {$rawScore} questions right out of {$total}. Feedback for wrong answers are given below:\n");

        foreach ($latest['responses'] as $resp) {
            $questionId = isset($resp['questionId']) ? $resp['questionId'] : null;
            if (!$questionId) {
                continue;
            }

            $q = collect($questions)->firstWhere('id', $questionId);
            if (!$q) {
                continue;
            }

            $correctAnswerId = isset($q['config']['key']) ? $q['config']['key'] : null;
            $userAnswerId = isset($resp['response']) ? $resp['response'] : null;

            // Skip if user answer is correct
            if ($userAnswerId === $correctAnswerId) {
                continue;
            }

            $options = isset($q['config']['options']) ? collect($q['config']['options']) : collect();

            $userAnswer = $options->firstWhere('id', $userAnswerId);
            $correctAnswer = $options->firstWhere('id', $correctAnswerId);

            $yourAnswerText = $userAnswer ? "{$userAnswer['label']} with value {$userAnswer['value']}" : 'Unknown';
            $correctAnswerText = $correctAnswer ? "{$correctAnswer['label']} with value {$correctAnswer['value']}" : 'Unknown';

            $hint = isset($q['config']['hint']) ? $q['config']['hint'] : 'No hint available';

            $cmd->line("Question: {$q['stem']}");
            $cmd->line("Your answer: {$yourAnswerText}");
            $cmd->line("Right answer: {$correctAnswerText}");
            $cmd->line("Hint: {$hint}\n");
        }
    }

}
