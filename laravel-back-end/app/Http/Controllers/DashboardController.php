<?php

namespace App\Http\Controllers;

use App\Http\Resources\SurveyAnswerResource;
use App\Http\Resources\SurveyDashboardResource;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use Illuminate\Http\Request;
use App\Http\Resources\SurveyResource;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        // Total Number of Surveys
        $totalSurveys = Survey::query()->where('user_id', $user->id)->count();
        // Latest Survey
        $latestSurveys = Survey::query()->where('user_id', $user->id)->latest('created_at')->first();
        // Total Number of Answers
        $totalAnswers = SurveyAnswer::query()
            ->join('surveys', 'survey_answers.survey_id', '=', 'surveys.id')
            ->where('surveys.user_id', $user->id)
            ->count();
        // Latest 5 Answers
        $latestAnswers = SurveyAnswer::query()
            ->join('surveys', 'survey_answers.survey_id', '=', 'surveys.id')
            ->where('surveys.user_id', $user->id)
            ->orderBy('end_date', 'DESC')
            ->limit(5)
            ->getModels('survey_answers.*');

        return [
            'totalSurveys' => $totalSurveys,
            'latestSurvey' => $latestSurveys ? new SurveyDashboardResource($latestSurveys) : null,
            'totalAnswers' => $totalAnswers,
            'latestAnswers' => SurveyAnswerResource::collection($latestAnswers)
        ];
    }
}
