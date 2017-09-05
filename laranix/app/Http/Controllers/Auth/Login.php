<?php
namespace App\Http\Controllers\Auth;

use Laranix\Auth\Events\Login\Restricted;
use Laranix\Foundation\Controllers\Controller;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laranix\Auth\User\User;
use Laranix\Support\Exception\ArgumentOutOfRangeException;
use Laranix\Themer\Scripts\Settings as ScriptSettings;
use Illuminate\Contracts\View\View;

class Login extends Controller
{
    use AuthenticatesUsers;

    /**
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Get login form
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function getLogin() : View
    {
        $this->prepareForFormResponse(new ScriptSettings([
            'key'       => 'login-form',
            'filename'  => 'forms/login.js',
        ]));

        return $this->view->make($this->config->get('laranixauth.login.views.login_form', 'auth.login'));
    }

    /**
     * Login to app
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    public function postLogin()
    {
        return $this->login($this->request);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param mixed|User               $user
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function authenticated(Request $request, $user) : RedirectResponse
    {
        if ($user->account_status !== User::USER_ACTIVE) {
            return $this->accountRestricted($user);
        }

        $user->updateLastLogin()->save();

        return redirect()
                ->intended($this->url->to($this->redirectPath()))
                ->with([
                    // TODO Localise
                    'login_notice'          => true,
                    'login_notice_header'   => "Welcome back, {$user->username}!",
                    'login_notice_message'  => 'You have been logged in successfully',
                    'login_notice_is_error' => false,
                ]);
    }

    /**
     * @param \Illuminate\Contracts\Auth\Authenticatable|User $user
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function accountRestricted(Authenticatable $user) : RedirectResponse
    {
        $this->logoutUser(false);

        $message = $this->getAccountRestrictedMessage($user->account_status);

        event(new Restricted($user, $message));

        return redirect($this->url->to('login'))
            ->withErrors([
                'login_account_status' => $message,
            ])
            ->withInput();
    }

    /**
     * Get account status message
     *
     * @param int $accountStatus
     * @return string
     * @throws \Laranix\Support\Exception\ArgumentOutOfRangeException
     */
    protected function getAccountRestrictedMessage(int $accountStatus) : string
    {
        switch ($accountStatus) {
            case User::USER_UNVERIFIED:
                return 'Your account is unverified';
            case User::USER_SUSPENDED:
                return 'Your account is suspended';
            case User::USER_BANNED:
                return 'Your account is banned';
            default:
                throw new ArgumentOutOfRangeException("Account status unrecognised ({$accountStatus})");
        }
    }

    /**
     * Logout of app
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postLogout() : RedirectResponse
    {
        return $this->logoutUser();
    }

    /**
     * Logout user
     *
     * @param bool                     $redirect
     * @return \Illuminate\Http\RedirectResponse|null
     */
    protected function logoutUser(bool $redirect = true) : ?RedirectResponse
    {
        $guard      = $this->guard();
        /** @var User $user */
        $user       = $guard->user();
        $session    = $this->getSessionData();

        $guard->logout();
        $session->invalidate();

        if (!$redirect) {
            return null;
        }

        return redirect($this->url->to('login'))
            ->with([
                // TODO Localise phrases
                'login_notice'          => true,
                'login_notice_header'   => "See you soon, {$user->username}",
                'login_notice_message'  => 'You have been logged out',
                'login_notice_is_error' => false,
            ]);
    }


    /**
     * Validate the user login request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function validateLogin(Request $request)
    {
        $this->validate([
            $this->username()   => 'required|email|max:255',
            'password'          => 'required|min:6',
        ]);
    }
}
