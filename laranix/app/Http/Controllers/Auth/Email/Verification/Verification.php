<?php
namespace App\Http\Controllers\Auth\Email\Verification;

use Laranix\Auth\Email\Verification\Events\RefreshAttempt;
use Laranix\Auth\Email\Verification\Events\VerifyAttempt;
use Laranix\Foundation\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laranix\Auth\User\Token\Token;
use Laranix\Auth\Email\Verification\Manager as VerificationManager;
use Laranix\Support\Exception\NullValueException;
use Laranix\Auth\Email\Verification\EmailVerificationException;
use Laranix\Themer\Scripts\Settings as ScriptSettings;
use Illuminate\Contracts\View\View;

class Verification extends Controller
{
    /**
     * If the verifier is logged in
     *
     * @var bool
     */
    protected $is_guest = false;

    /**
     * Verify the users email, or show the form if the token or email (in query string) is not provided
     *
     * @param \Laranix\Auth\Email\Verification\Manager $verificationManager
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function getVerify(VerificationManager $verificationManager)
    {
        $token = $this->getQueryData('token');
        $email = $this->getQueryData('email');

        if ($token === null || $email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->getManualVerificationForm($token, $email);
        }

        event(new VerifyAttempt($email));

        return $this->verify($verificationManager, $token, $email);
    }

    /**
     * Get the verification form
     *
     * @param string|null $token
     * @param string|null $email
     * @return \Illuminate\Contracts\View\View
     */
    protected function getManualVerificationForm(string $token = null, string $email = null) : View
    {
        $this->prepareForFormResponse(new ScriptSettings([
            'key'       => 'verify-email-form',
            'filename'  => 'forms/verifyemail.js',
        ]));

        return $this->view->make($this->config->get('laranixauth.verification.views.verify_form', 'auth.verify.verify'))
            ->with([
                'token' => old('token', $token),
                'email' => old('email', $email),
            ]);
    }

    /**
     * Verify a manually submitted verification form
     *
     * @param \Laranix\Auth\Email\Verification\Manager $verificationManager
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postVerify(VerificationManager $verificationManager) : RedirectResponse
    {
        $email = $this->getPostData('email');

        event(new VerifyAttempt($email));

        $this->validate([
            'token'     => 'required|regex:/^[A-Fa-f0-9]{64}$/',
            'email'     => 'required|email|max:255',
        ]);

        return $this->verify($verificationManager, $this->getPostData('token'), $email);
    }

    /**
     * Show error message on failed verification
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function getVerifyResult() : View
    {
        $session    = $this->getSessionData();
        $header     = $session->get('verification_notice_header');
        $message    = $session->get('verification_notice_message');
        $is_error   = $session->get('verification_notice_is_error');

        if ($header === null || $message === null || $is_error === null) {
            abort(403);
        }

        $session->keep('verification_notice_header', 'verification_notice_message', 'verification_notice_is_error');

        return $this->renderStatePage([
            'page_title'    => $header,
            'header'        => $header,
            'message'       => $message,
        ], $is_error);
    }

    /**
     * Verify a users email change
     *
     * @param \Laranix\Auth\Email\Verification\Manager $verificationManager
     * @param string                             $token
     * @param string                             $email
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function verify(VerificationManager $verificationManager, string $token, string $email) : RedirectResponse
    {
        $verify = $verificationManager->processToken($token, $email);

        switch ($verify) {
            case Token::TOKEN_VALID:
                return $this->redirectAfterValidVerification();
            default:
            case Token::TOKEN_EXPIRED:
            case Token::TOKEN_INVALID:
                return $this->redirectAfterInvalidVerification($verify);
        }
    }

    /**
     * Get the form to refresh a verification code
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function getVerificationRefreshForm() : View
    {
        $this->prepareForFormResponse(new ScriptSettings([
            'key'       => 'verify-email-refresh-form',
            'filename'  => 'forms/verifyemailrefresh.js',
        ]));

        return $this->view->make($this->config->get('laranixauth.verification.views.verify_refresh', 'auth.verify.refresh'))
                          ->with([
                                'verify_refresh_message' => $this->getSessionData('verify_refresh_message'),
                            ]);
    }

    /**
     * Handle a refresh verification request form being posted
     *
     * @param \Laranix\Auth\Email\Verification\Manager    $verificationManager
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Laranix\Auth\Email\Verification\EmailVerificationException
     */
    public function postVerificationRefreshForm(VerificationManager $verificationManager) : RedirectResponse
    {
        $email = $this->getPostData('email');

        event(new RefreshAttempt($email));

        $this->validate([
            'email'     => 'required|email|max:255',
        ]);

        try {
            $token = $verificationManager->fetchTokenByEmail($email);

            $verificationManager->sendMail($token->user ?? null, $verificationManager->renewToken($token));

        } catch (\Exception $e) {
            // Null value exception means the user doesn't exist
            // We don't want the user to know that
            if (!($e instanceof NullValueException)) {
                throw new EmailVerificationException("Failed to refresh email verification: {$e->getMessage()}", 0, $e);
            }
        }

        return redirect($this->url->to('email/verify/refresh'))
            ->with('verify_refresh_message',
                   'If the email is registered in our system, you will receive an email with a new verification code shortly');
    }

    /**
     * Redirect a user after a valid verification
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectAfterValidVerification() : RedirectResponse
    {
        if ($this->request->user() === null) {
            $message = 'Your email has been verified, you may now <a href="' . $this->url->to('login') . '">login</a>';
        } else {
            $message = 'Your email has been updated';
        }

        return redirect($this->url->to('email/verify/result'))
            ->with([
                // TODO Localise
                'verification_notice_header'   => 'Email Verified',
                'verification_notice_message'  => $message,
                'verification_notice_is_error' => false,
            ]);
    }

    /**
     * Redirect a user after an invalid verification
     *
     * @param int $verifyResult
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectAfterInvalidVerification(int $verifyResult) : RedirectResponse
    {
        if ($verifyResult === Token::TOKEN_EXPIRED) {
            $message = 'Your token has expired, please <a href="' . $this->url->to('email/verify/refresh') . '">request a new one</a>';
        } else {
            $message = 'The provided information does not match our records';
        }

        return redirect($this->url->to('email/verify/result'))
            ->with([
                'verification_notice_header'   => 'Email Verification Error',
                'verification_notice_message'  => $message,
                'verification_notice_is_error' => true,
            ]);
    }
}
