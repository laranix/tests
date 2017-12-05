<?php
namespace App\Http\Requests\Auth\Password\Reset;

use Laranix\Support\Request\RequestValidator;

class PostForgot extends RequestValidator
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email' => 'required|email|max:255',
        ];
    }
}
