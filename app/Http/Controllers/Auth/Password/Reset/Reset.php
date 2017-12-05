<?php
namespace App\Http\Controllers\Auth\Password\Reset;

use App\Http\Requests\Auth\Password\Reset\PostReset;
use Laranix\Auth\Password\Reset\Events\VerifyAttempt;
use Laranix\Foundation\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Laranix\Auth\Password\{Hasher as PasswordHasher, HashesPasswords};
use Laranix\Auth\Password\Reset\Manager as PasswordResetManager;
use Laranix\Auth\User\Token\Token;
use Laranix\Themer\Scripts\Settings as ScriptSettings;

class Reset extends Controller implements PasswordHasher
{
    use HashesPasswords;

    /**
     * Get the reset form
     *
     * If no token supplied, redirect to the request form
     *
     * @return \Illuminate\Contracts\View\View
     * @throws \Laranix\Support\Exception\InvalidInstanceException
     */
    public function getPasswordResetForm() : View
    {
        $this->prepareForFormResponse(true, new ScriptSettings([
            'key'       => 'pass-reset-form',
            'filename'  => 'forms/passreset.js',
        ]));

        return $this->view->make($this->config->get('laranixauth.password.views.reset_form', 'auth.password.reset'))
            ->with([
                'token' => old('token', $this->getQueryData('token')),
                'email' => old('email', $this->getQueryData('email')),
            ]);
    }

    /**
     * Reset a users password
     *
     * @param \App\Http\Requests\Auth\Password\Reset\PostReset $postReset
     * @param \Laranix\Auth\Password\Reset\Manager                             $resetManager
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postPasswordResetForm(PostReset $postReset, PasswordResetManager $resetManager): RedirectResponse
    {
        $email = $this->getPostData('email');

        event(new VerifyAttempt($email));

        return $this->reset($resetManager, $this->getPostData('token'), $email, $this->getPostData('password'));
    }

    /**
     * Verify a users password reset
     *
     * @param \Laranix\Auth\Password\Reset\Manager $resetManager
     * @param string                               $token
     * @param string                               $email
     * @param string                               $newPassword
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function reset(
        PasswordResetManager $resetManager,
        string $token,
        string $email,
        string $newPassword
    ): RedirectResponse {
        $verify = $resetManager->processToken($token, $email, $newPassword);

        switch ($verify) {
            case Token::TOKEN_VALID:
                return $this->redirectAfterValidReset();
            default:
            case Token::TOKEN_EXPIRED:
            case Token::TOKEN_INVALID:
                return $this->redirectAfterInvalidReset($verify);
        }
    }

    /**
     * Show error message on failed reset
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function getPasswordResetError() : View
    {
        $session = $this->getSessionData();
        $message = $session->get('password_reset_error_message');

        if ($message === null) {
            abort(403);
        }

        $session->keep('password_reset_error_message');
        $header = 'Password Reset Error';

        return $this->renderStatePage([
            'page_title'    => $header,
            'header'        => $header,
            'message'       => $message,
        ], true);
    }

    /**
     * Redirect a user after a valid reset
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectAfterValidReset() : RedirectResponse
    {
        // TODO Localise
        return redirect($this->url->to('login'))
            ->with([
                'login_notice'          => true,
                'login_notice_header'   => 'Password Reset Successfully',
                'login_notice_message'  => 'Your password has been reset, you may now login using your new credentials',
                'login_notice_is_error' => false,
            ]);
    }

    /**
     * Redirect a user after an invalid reset
     *
     * @param int $resetResult
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectAfterInvalidReset(int $resetResult) : RedirectResponse
    {
        // TODO Localise
        if ($resetResult === Token::TOKEN_EXPIRED) {
            $message = 'Your token has expired, please <a href="' . $this->url->to('password/forgot') . '">request a new one</a>';
        } else {
            $message = 'The provided information does not match our records.';
        }

        return redirect($this->url->to('password/reset/error'))
            ->with([
                'password_reset_error_message'   => $message,
            ]);
    }


}
