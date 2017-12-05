<?php
namespace App\Http\Requests\Auth;

use Laranix\Support\Request\RequestValidator;

class PostLogin extends RequestValidator
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email'     => 'required|email|max:255',
            'password'  => 'required|min:6',
        ];
    }
}
