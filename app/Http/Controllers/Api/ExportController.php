<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Services\ExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /** API-51 */
    public function attempts(Quiz $quiz, ExportService $export): StreamedResponse
    {
        [$header, $rows] = $export->attemptsTable($quiz);

        return $export->download($quiz->title, 'percobaan', $header, $rows);
    }

    /** API-52 */
    public function mastery(Quiz $quiz, ExportService $export): StreamedResponse
    {
        [$header, $rows] = $export->masteryTable($quiz);

        return $export->download($quiz->title, 'penguasaan', $header, $rows);
    }
}
