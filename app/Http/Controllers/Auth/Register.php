<?php
namespace App\Http\Controllers\Auth;

use Laranix\Foundation\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\RedirectResponse;
use Laranix\Auth\Group\Repository as GroupRepository;
use Laranix\Auth\User\Groups\{UserGroupAdder, AddsUserToGroup};
use Laranix\Auth\User\{CreatesUsers, UserCreator};
use Laranix\Auth\Email\Verification\Manager as VerificationManager;
use Laranix\Themer\Scripts\Settings as ScriptSettings;

class Register extends Controller implements UserCreator, UserGroupAdder
{
    use CreatesUsers, AddsUserToGroup;

    /**
     * Show registration form
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function getRegister() : View
    {
        $this->prepareForFormResponse(new ScriptSettings([
            'key'       => 'register-form',
            'filename'  => 'forms/register.js',
        ]));

        return $this->view->make($this->config->get('laranixauth.users.views.register_form', 'auth.register.form'));
    }

    /**
     * Register a user
     *
     * @param \Laranix\Auth\Email\Verification\Manager   $verificationManager
     * @param \Laranix\Auth\Group\Repository       $group
     * @param \Illuminate\Database\DatabaseManager $db
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function postRegister(VerificationManager $verificationManager, GroupRepository $group, DatabaseManager $db) : RedirectResponse
    {
        $this->validateRegistration();

        $db->connection()->beginTransaction();

        try {
            $user = $this->createUser($this->getPostData()->all());

            /** @var \Laranix\Auth\User\Token\MailSettings $token */
            $token = $verificationManager->sendMail($user, $verificationManager->createToken($user));

            $groupinfo = $group->getByName($this->config->get('laranixauth.groups.default_group', 'User'));

            if ($groupinfo !== null) {
                $this->addUserToGroup([
                    'user'    => $user->id,
                    'group'   => $groupinfo->id,
                    'primary' => true,
                ]);
            }

            $db->connection()->commit();

            event(new Registered($user));

            return redirect($this->url->to('register/success'))
                    ->with([
                        'registered_username'   => $user->username,
                        'registered_email'      => $user->email,
                        'token_expiry'          => $token->expiry,
                        'token_valid_for'       => $token->humanExpiry,
                    ]);

        } catch (\Exception $e) {
            $db->connection()->rollBack();

            throw $e;

            // TODO redirect/log
        }
    }

    /**
     * Validate registration request
     */
    protected function validateRegistration()
    {
        $usertable = $this->config->get('laranixauth.users.table', 'users');

        // On email/username, make sure you update the column name here if it differs
        $this->validate([
            'first_name'    => 'required|max:64',
            'last_name'     => 'required|max:64',
            'email'         => 'required|email|confirmed|max:255|unique:' . $usertable,
            'company'       => 'sometimes|max:64',
            'username'      => 'required|min:3|max:64|alpha_dash|unique:' . $usertable,
            'password'      => 'required|confirmed|min:6',
            'terms'         => 'accepted',
        ]);
    }

    /**
     * Show registration success
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Contracts\View\View
     */
    public function getRegisterSuccess()
    {
        $session                = $this->getSessionData();

        $registered_username    = $session->get('registered_username');
        $registered_email       = $session->get('registered_email');
        $token_expiry           = $session->get('token_expiry');
        $token_valid_for        = $session->get('token_valid_for');

        $data = compact('registered_username', 'registered_email', 'token_expiry', 'token_valid_for');

        // Any values are null then redirect
        if (in_array(null, $data, true)) {
            return redirect($this->url->to('register'));
        }

        $session->keep('registered_username', 'registered_email', 'verify_token_expiry', 'token_valid_for');

        return $this->view->make($this->config->get('laranixauth.users.views.register_success', 'auth.register.success'), $data);
    }
}
