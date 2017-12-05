<?php
namespace App\Http\Requests\Auth;

use Laranix\Support\Request\RequestValidator;

class PostRegister extends RequestValidator
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $usertable = config('laranixauth.users.table', 'users');

        return [
            'first_name'    => 'required|max:64',
            'last_name'     => 'required|max:64',
            'email'         => 'required|email|confirmed|max:255|unique:' . $usertable,
            'company'       => 'sometimes|max:64',
            'username'      => 'required|min:3|max:64|alpha_dash|unique:' . $usertable,
            'password'      => 'required|confirmed|min:6',
            'terms'         => 'accepted',
        ];
    }
}
