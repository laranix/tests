<?php
namespace App\Http\Requests\Auth\Password\Reset;

use Laranix\Support\Request\RequestValidator;

class PostReset extends RequestValidator
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'token'     => 'required|regex:/^[A-Fa-f0-9]{64}$/',
            'email'     => 'required|email|max:255',
            'password'  => 'required|confirmed|min:6'
        ];
    }

    /**
     * Custom messages
     *
     * @return array
     */
    public function messages()
    {
        return [
            'token.regex'   => 'Invalid token',
        ];
    }
}
