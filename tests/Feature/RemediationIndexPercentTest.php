<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class RemediationIndexPercentTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    public function test_each_item_carries_concept_id_and_its_mastery_percent(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $pecahan = $this->concept($course, 'Pecahan');
        $desimal = $this->concept($course, 'Desimal');
        $quiz = $this->quiz($course);
        $p = [];
        foreach ([1, 2, 3, 4] as $position) {
            $p[$position] = $this->question($quiz, $pecahan, $position);
        }
        $d1 = $this->question($quiz, $desimal, 5);
        $d2 = $this->question($quiz, $desimal, 6);

        $this->remedialLesson($course, $pecahan, 'Remedial Pecahan', 1);
        $this->remedialLesson($course, $desimal, 'Remedial Desimal', 1);

        $this->submitViaService($student, $quiz, [
            [$p[1], true], [$p[2], true], [$p[3], false], [$p[4], false],
            [$d1, false], [$d2, false],
        ]);

        $this->signIn($student);

        $response = $this->getJson("/api/courses/{$course->id}/remediation")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.concept', 'Pecahan')
            ->assertJsonPath('data.1.concept', 'Desimal');

        $this->assertEquals($pecahan->id, $response->json('data.0.concept_id'));
        $this->assertEquals(50, $response->json('data.0.mastery_percent'));
        $this->assertEquals($desimal->id, $response->json('data.1.concept_id'));
        $this->assertEquals(0, $response->json('data.1.mastery_percent'));
    }
}
