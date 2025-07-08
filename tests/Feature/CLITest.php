<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\DataLoaderService;
use Mockery;

class CLITest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock the DataLoaderService to return preset data
        $mockDataLoader = Mockery::mock(DataLoaderService::class);

        // Mock students.json data
        $mockDataLoader->shouldReceive('load')
            ->with('students.json')
            ->andReturn([
                ["id" => "student1", "firstName" => "Tony", "lastName" => "Stark", "yearLevel" => 6],
                ["id" => "student2", "firstName" => "Steve", "lastName" => "Rogers", "yearLevel" => 6],
                ["id" => "student3", "firstName" => "Peter", "lastName" => "Parker", "yearLevel" => 6],
            ]);

        // Mock assessments.json data (simple stub)
        $mockDataLoader->shouldReceive('load')
            ->with('assessments.json')
            ->andReturn([
                ['id' => 'assessment1', 'name' => 'Assessment 1']
            ]);

        // Mock questions.json data (simple stub)
        $mockDataLoader->shouldReceive('load')
            ->with('questions.json')
            ->andReturn([
                ['id' => 'numeracy1', 'text' => 'Question 1?'],
                ['id' => 'numeracy2', 'text' => 'Question 2?'],
                ['id' => 'numeracy3', 'text' => 'Question 3?'],
                ['id' => 'numeracy4', 'text' => 'Question 4?'],
                ['id' => 'numeracy5', 'text' => 'Question 5?'],
                ['id' => 'numeracy6', 'text' => 'Question 6?'],
                ['id' => 'numeracy7', 'text' => 'Question 7?'],
                ['id' => 'numeracy8', 'text' => 'Question 8?'],
                ['id' => 'numeracy9', 'text' => 'Question 9?'],
                ['id' => 'numeracy10', 'text' => 'Question 10?'],
                ['id' => 'numeracy11', 'text' => 'Question 11?'],
                ['id' => 'numeracy12', 'text' => 'Question 12?'],
                ['id' => 'numeracy13', 'text' => 'Question 13?'],
                ['id' => 'numeracy14', 'text' => 'Question 14?'],
                ['id' => 'numeracy15', 'text' => 'Question 15?'],
                ['id' => 'numeracy16', 'text' => 'Question 16?'],
            ]);

        // Mock student-responses.json data (only a few full examples included, extend as needed)
        $mockDataLoader->shouldReceive('load')
            ->with('student-responses.json')
            ->andReturn([
                [
                    "id" => "studentReponse1",
                    "assessmentId" => "assessment1",
                    "assigned" => "14/12/2019 10:31:00",
                    "started" => "16/12/2019 10:00:00",
                    "completed" => "16/12/2019 10:46:00",
                    "student" => ["id" => "student1", "yearLevel" => 3],
                    "responses" => [
                        ["questionId" => "numeracy1", "response" => "option3"],
                        ["questionId" => "numeracy2", "response" => "option4"],
                        ["questionId" => "numeracy3", "response" => "option2"],
                        ["questionId" => "numeracy4", "response" => "option1"],
                        ["questionId" => "numeracy5", "response" => "option1"],
                        ["questionId" => "numeracy6", "response" => "option1"],
                    ],
                    "results" => ["rawScore" => 6]
                ],
                [
                    "id" => "studentReponse4",
                    "assessmentId" => "assessment1",
                    "assigned" => "14/12/2019 10:31:00",
                    "started" => "16/12/2019 10:00:00",
                    "completed" => "16/12/2019 10:46:00",
                    "student" => ["id" => "student2", "yearLevel" => 3],
                    "responses" => [
                        ["questionId" => "numeracy1", "response" => "option1"],
                        ["questionId" => "numeracy2", "response" => "option4"],
                        ["questionId" => "numeracy3", "response" => "option2"],
                        ["questionId" => "numeracy4", "response" => "option2"],
                        ["questionId" => "numeracy5", "response" => "option3"],
                        ["questionId" => "numeracy6", "response" => "option3"],
                    ],
                    "results" => ["rawScore" => 8]
                ],
                [
                    "id" => "studentReponse7",
                    "assessmentId" => "assessment1",
                    "assigned" => "14/12/2019 10:31:00",
                    "started" => "16/12/2019 10:00:00",
                    "completed" => "16/12/2019 10:46:00",
                    "student" => ["id" => "student3", "yearLevel" => 3],
                    "responses" => [
                        ["questionId" => "numeracy1", "response" => "option3"],
                        ["questionId" => "numeracy2", "response" => "option4"],
                        ["questionId" => "numeracy3", "response" => "option1"],
                        ["questionId" => "numeracy4", "response" => "option1"],
                        ["questionId" => "numeracy5", "response" => "option1"],
                        ["questionId" => "numeracy6", "response" => "option1"],
                    ],
                    "results" => ["rawScore" => 4]
                ],
                // add more student responses here as needed...
            ]);

        // Bind the mock instance to the app container
        $this->app->instance(DataLoaderService::class, $mockDataLoader);
    }

    public function test_command_fails_with_invalid_report_type()
    {
        $this->artisan('report:generate')
            ->expectsQuestion('Enter Student ID', 'student1')
            ->expectsQuestion('Report to generate (1 for Diagnostic, 2 for Progress, 3 for Feedback)', '9')
            ->expectsOutput('Invalid report type selected.')
            ->assertExitCode(1);
    }

    public function test_command_fails_with_nonexistent_student_id()
    {
        $this->artisan('report:generate')
            ->expectsQuestion('Enter Student ID', 'nonexistent_student')
            ->expectsQuestion('Report to generate (1 for Diagnostic, 2 for Progress, 3 for Feedback)', '1')
            ->expectsOutput("Student with ID 'nonexistent_student' not found.")
            ->assertExitCode(1);
    }

    public function test_command_runs_successfully_with_valid_inputs()
    {
        $this->artisan('report:generate')
            ->expectsQuestion('Enter Student ID', 'student1')
            ->expectsQuestion('Report to generate (1 for Diagnostic, 2 for Progress, 3 for Feedback)', '1')
            ->assertExitCode(0);
    }

    public function test_command_fails_with_empty_student_id()
    {
        $this->artisan('report:generate')
            ->expectsQuestion('Enter Student ID', '')
            ->expectsQuestion('Report to generate (1 for Diagnostic, 2 for Progress, 3 for Feedback)', '1')
            ->expectsOutput("Student with ID '' not found.")
            ->assertExitCode(1);
    }

    public function test_command_fails_with_empty_report_type()
    {
        $this->artisan('report:generate')
            ->expectsQuestion('Enter Student ID', 'student1')
            ->expectsQuestion('Report to generate (1 for Diagnostic, 2 for Progress, 3 for Feedback)', '')
            ->expectsOutput('Invalid report type selected.')
            ->assertExitCode(1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
