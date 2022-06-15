<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSurveyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Survey variable model from main controller function
        $survey = $this->route('survey');
        // Check if auth user is the owner of the survey
        if ($this->user()->id !== $survey->user_id) {
            return false;
        }
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title' => 'required|string|max:1000',
            'image' => 'nullable|string',
            'user_id' => 'exists:users,id',
            'status' => 'required|boolean',
            'description' => 'nullable|string',
            'expire_date' => 'nullable|date|after:tomorrow',
            'questions' => 'array'
        ];
    }

    /**
     * Add condition after validation rules.
     *
     * @return array
     */
    public function validated()
    {
        $validated = $this->validator->validated();

        // Check if image was given and save on local file system
        if (isset($this->image)) {
            $relativePath = $this->saveImage($this->image);
            $validated['image'] = $relativePath;
            // if there is an old image, delete it
            $survey = $this->route('survey');
            if ($survey->image) {
                $absolutePath = public_path($survey->image);
                File::delete($absolutePath);
            }
        }

        return $validated;
    }

    /**
     * Validate image.
     *
     * @return string
     */
    private function saveImage($image)
    {
        // Check if image is valid base64 string
        if (preg_match('/^data:image\/(\w+);base64,/', $image, $type)) {
            // Take out the base64 encoded text without mime type
            $image = substr($image, strpos($image, ',') + 1);
            // Get file extension
            $type = strtolower($type[1]); //jpg, png, gif
            // Check if file is an image
            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                throw new \Exception('invalid image type');
            }
            $image = str_replace('', '+', $image);
            $image = base64_decode($image);

            if ($image === false) {
                throw new \Exception('base64_decode failed');
            }
        } else {
            throw new \Exception('did not match data URL with image data');
        }

        $directory = 'images/';
        $filename = Str::random() . '.' . $type;
        $absolutePath = public_path($directory);
        $relativePath = $directory . $filename;

        if (!File::exists($absolutePath)) {
            File::makeDirectory($absolutePath, 0755, true);
        }
        file_put_contents($relativePath, $image);

        return $relativePath;
    }
}
