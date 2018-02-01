<?php
namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\PostRegister;
use Illuminate\Http\Request;
use Laranix\Foundation\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Database\DatabaseManager as DbMan;
use Illuminate\Http\RedirectResponse;
use Laranix\Auth\Group\Repository;
use Laranix\Auth\User\Groups\{UserGroupAdder, AddsUserToGroup};
use Laranix\Auth\User\{CreatesUsers, UserCreator};
use Laranix\Auth\Email\Verification\Manager;
use Laranix\Themer\Scripts\Settings as ScriptSettings;

class Register extends Controller implements UserCreator, UserGroupAdder
{
    use CreatesUsers, AddsUserToGroup;

    /**
     * Show registration form
     *
     * @return \Illuminate\Contracts\View\View
     * @throws \Laranix\Support\Exception\InvalidInstanceException
     * @throws \Laranix\Support\Exception\InvalidTypeException
     */
    public function create(): View
    {
        $this->prepareForFormResponse(true, new ScriptSettings([
            'key'       => 'register-form',
            'filename'  => 'forms/register.js',
        ]));

        return $this->view->make(
            $this->config->get('laranixauth.user.views.register_form', 'auth.register.form')
        );
    }

    /**
     * Register a user
     *
     * @param \App\Http\Requests\Auth\PostRegister $request
     * @param \Laranix\Auth\Email\Verification\Manager             $manager
     * @param \Laranix\Auth\Group\Repository                       $group
     * @param \Illuminate\Database\DatabaseManager                 $db
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function store(PostRegister $request, Manager $manager, Repository $group, DbMan $db): RedirectResponse
    {
        $db->connection()->beginTransaction();

        try {
            $user = $this->createUser($request->post());

            /** @var \Laranix\Auth\User\Token\MailSettings $token */
            $token = $manager->sendMail($user, $manager->createToken($user));

            $groupinfo = $group->getByName($this->config->get('laranixauth.group.default_group', 'User'));

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
     * Show registration success
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Contracts\View\View
     */
    public function success(Request $request)
    {
        $session                = $request->session();
        $registered_username    = $session->get('registered_username');
        $registered_email       = $session->get('registered_email');
        $token_expiry           = $session->get('token_expiry');
        $token_valid_for        = $session->get('token_valid_for');

        $data = compact('registered_username', 'registered_email', 'token_expiry', 'token_valid_for');

        // Any values are null then redirect
        if (in_array(null, $data, true)) {
            return redirect($this->url->to('/register'));
        }

        $session->keep('registered_username', 'registered_email', 'verify_token_expiry', 'token_valid_for');

        return $this->view->make(
            $this->config->get('laranixauth.user.views.register_success', 'auth.register.success'),
            $data
        );
    }
}
