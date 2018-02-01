<?php
namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\PostLogin;
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
     * @throws \Laranix\Support\Exception\InvalidInstanceException
     * @throws \Laranix\Support\Exception\InvalidTypeException
     */
    public function show(): View
    {
        $this->prepareForFormResponse(true, new ScriptSettings([
            'key'       => 'login-form',
            'filename'  => 'forms/login.js',
        ]));

        return $this->view->make($this->config->get('laranixauth.login.views.login_form', 'auth.login'));
    }

    /**
     * Login to app
     *
     * @param \App\Http\Requests\Auth\PostLogin $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    public function doLogin(PostLogin $request)
    {
        return $this->login($request);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param mixed|User               $user
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Laranix\Support\Exception\NullValueException
     * @throws \Laranix\Support\Exception\ArgumentOutOfRangeException
     */
    protected function authenticated(Request $request, $user): RedirectResponse
    {
        if ($user->account_status !== User::USER_ACTIVE) {
            return $this->accountRestricted($request, $user);
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
     * @param \Illuminate\Http\Request                        $request
     * @param \Illuminate\Contracts\Auth\Authenticatable|User $user
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Laranix\Support\Exception\ArgumentOutOfRangeException
     */
    protected function accountRestricted(Request $request, Authenticatable $user): RedirectResponse
    {
        $this->logoutUser($request,false);

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
    protected function getAccountRestrictedMessage(int $accountStatus): string
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
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function doLogout(Request $request): RedirectResponse
    {
        return $this->logoutUser($request);
    }

    /**
     * Logout user
     *
     * @param \Illuminate\Http\Request $request
     * @param bool                     $redirect
     * @return \Illuminate\Http\RedirectResponse|null
     */
    protected function logoutUser(Request $request, bool $redirect = true): ?RedirectResponse
    {
        $guard      = $this->guard();
        /** @var User $user */
        $user       = $guard->user();

        $guard->logout();
        $request->session()->invalidate();

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
     * Override
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function validateLogin(Request $request)
    {
        //
    }
}
