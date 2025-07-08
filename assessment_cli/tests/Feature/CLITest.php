<?php

namespace Tests\Feature;

use Tests\TestCase;

class CLITest extends TestCase
{
    public function test_command_fails_with_invalid_report_type()
    {
        $this->artisan('report:generate')
            ->expectsQuestion('Enter Student ID', 'student1') // assuming this ID exists in students.json
            ->expectsQuestion('Report to generate (1 for Diagnostic, 2 for Progress, 3 for Feedback)', '9') // invalid type
            ->expectsOutput('Invalid report type selected.')
            ->assertExitCode(1);
    }

    public function test_command_fails_with_nonexistent_student_id()
    {
        $this->artisan('report:generate')
            ->expectsQuestion('Enter Student ID', 'nonexistent_student') // an ID not in the real JSON
            ->expectsQuestion('Report to generate (1 for Diagnostic, 2 for Progress, 3 for Feedback)', '1')
            ->expectsOutput("Student with ID 'nonexistent_student' not found.")
            ->assertExitCode(1);
    }

    public function test_command_runs_successfully_with_valid_inputs()
    {
        $this->artisan('report:generate')
            ->expectsQuestion('Enter Student ID', 'student1') // valid student ID from students.json
            ->expectsQuestion('Report to generate (1 for Diagnostic, 2 for Progress, 3 for Feedback)', '1') // valid report type
            ->assertExitCode(0);
    }

    public function test_command_fails_with_empty_student_id()
    {
        $this->artisan('report:generate')
            ->expectsQuestion('Enter Student ID', '') // empty student ID
            ->expectsQuestion('Report to generate (1 for Diagnostic, 2 for Progress, 3 for Feedback)', '1') // valid report type
            ->expectsOutput("Student with ID '' not found.")
            ->assertExitCode(1);
    }

    public function test_command_fails_with_empty_report_type()
    {
        $this->artisan('report:generate')
            ->expectsQuestion('Enter Student ID', 'student1') // valid student ID
            ->expectsQuestion('Report to generate (1 for Diagnostic, 2 for Progress, 3 for Feedback)', '') // empty report type
            ->expectsOutput('Invalid report type selected.')
            ->assertExitCode(1);
    }
}
