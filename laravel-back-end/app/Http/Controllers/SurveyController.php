<?php

namespace App\Http\Controllers;


use App\Http\Resources\SurveyResource;
use App\Http\Requests\StoreSurveyRequest;
use App\Http\Requests\StoreSurveyAnswerRequest;
use App\Http\Requests\UpdateSurveyRequest;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionAnswer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class SurveyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $survey = Survey::where('user_id', $user->id)->paginate(5);

        return SurveyResource::collection($survey);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreSurveyRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreSurveyRequest $request)
    {
        $data = $request->validated();
        $survey = Survey::create($data);
        // Create new questions
        foreach ($data['questions'] as $question) {
            // get the id of the newly created survey
            $question['survey_id'] = $survey->id;
            $this->createQuestion($question);
        }

        return new SurveyResource($survey);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Survey  $survey
     * @return \Illuminate\Http\Response
     */
    public function show(Survey $survey, Request $request)
    {
        $user = $request->user();
        if ($user->id !== $survey->user_id) return abort(403, 'Unauthorized action.');

        return new SurveyResource($survey);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateSurveyRequest  $request
     * @param  \App\Models\Survey  $survey
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateSurveyRequest $request, Survey $survey)
    {
        // get validated requests
        $data = $request->validated();
        // update survey in the database
        $survey->update($data);
        // get ids as plain array of existing questions
        $existingIds = $survey->questions()->pluck('id')->toArray();
        // get ids as plain array of new questions
        $newIds = Arr::pluck($data['questions'], 'id');
        // find questions to delete
        $toDelete = array_diff($existingIds, $newIds);
        // find questions to add
        $toAdd = array_diff($newIds, $existingIds);
        // delete questions by $toDelete array
        SurveyQuestion::destroy($toDelete);
        // create new questions
        foreach ($data['questions'] as $question) {
            // check if question id is present in @var $toAdd array
            if (in_array($question['id'], $toAdd)) {
                $question['survey_id'] = $survey->id;
                $this->createQuestion($question);
            }
        }
        // update existing questions
        $questionMap = collect($data['questions'])->keyBy('id');
        foreach ($survey->questions as $question) {
            // check if question id exists in SurveyQuestions table
            if (isset($questionMap[$question->id])) $this->updateQuestion($question, $questionMap[$question->id]);
        }

        return new SurveyResource($survey);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Survey  $survey
     * @return \Illuminate\Http\Response
     */
    public function destroy(Survey $survey, Request $request)
    {
        $user = $request->user();
        if ($user->id !== $survey->user_id) return abort(403, 'Unauthorized action.');

        $survey->delete();
        // if there is an old image, delete it
        if ($survey->image) {
            $absolutePath = public_path($survey->image);
            File::delete($absolutePath);
        }

        return response(''. 204);
    }

     /**
     * Insert a new question in the database
     *
     * @param object variable
     * @return created survey question
     */
    private function createQuestion($data)
    {
        // check if question contains data
        if (is_array($data['data'])) {
            // parse to json format
            $data['data'] = json_encode($data['data']);
        }
        // validate question
        $validator = Validator::make($data, [
            'question' => 'required|string',
            'type' => ['required', Rule::in([
                Survey::TYPE_TEXT,
                Survey::TYPE_TEXTAREA,
                Survey::TYPE_SELECT,
                Survey::TYPE_RADIO,
                Survey::TYPE_CHECKBOX
            ])],
            'description' => 'nullable|string',
            'data' => 'present',
            'survey_id' => 'exists:App\Models\Survey,id'
        ]);

        return SurveyQuestion::create($validator->validated());
    }

    /**
     * Update question in the database
     *
     * @param
     * @return
     */
    private function updateQuestion(SurveyQuestion $question, $data)
    {
        // check if question contains data
        if (is_array($data['data'])) {
            $data['data'] = json_encode($data['data']);
        }
        // validate question
        $validator = Validator::make($data, [
            'id' => 'exists:App\Models\SurveyQuestion,id',
            'question' => 'required|string',
            'type' => ['required', Rule::in([
                Survey::TYPE_TEXT,
                Survey::TYPE_TEXTAREA,
                Survey::TYPE_SELECT,
                Survey::TYPE_RADIO,
                Survey::TYPE_CHECKBOX
            ])],
            'description' => 'nullable|string',
            'data' => 'present'
        ]);

        return $question->update($validator->validated());
    }

    /**
     * Display the specified resource that is accessible by Guest
     *
     * @param  \App\Models\Survey  $survey
     * @return \Illuminate\Http\Response
     */
    public function showForGuest(Survey $survey)
    {
        return new SurveyResource($survey);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreSurveyRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function storeAnswer(StoreSurveyAnswerRequest $request, Survey $survey)
    {
        $validated = $request->validated();
        // create survey answer
        $surveyAnswer = SurveyAnswer::create([
            'survey_id' => $survey->id,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s')
        ]);

        foreach ($validated['answers'] as $questionId => $answer) {
            // returns a boolean
            $question = SurveyQuestion::where([
                'id' => $questionId,
                'survey_id' => $survey->id
            ])->get();
            // if the question belongs to the survey
            if (!$question) return response("Invalid question ID: \"$questionId\"", 400);

            $data = [
                'survey_question_id' => $questionId,
                'survey_answer_id' => $surveyAnswer->id,
                'answer' => is_array($answer) ? json_encode($answer) : $answer
            ];

            SurveyQuestionAnswer::create($data);
        }

        return response("", 201);
    }
}
