<?php
namespace App\Http\Controllers\Auth\Email\Verification;

use App\Http\Requests\Auth\Email\Verification\PostVerify;
use App\Http\Requests\Auth\Email\Verification\PostVerifyRefresh;
use Illuminate\Http\Request;
use Laranix\Auth\Email\Verification\Events\RefreshAttempt;
use Laranix\Auth\Email\Verification\Events\VerifyAttempt;
use Laranix\Foundation\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laranix\Auth\User\Token\Token;
use Laranix\Auth\Email\Verification\Manager;
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
     * @param \Illuminate\Http\Request                 $request
     * @param \Laranix\Auth\Email\Verification\Manager $manager
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     * @throws \Laranix\Support\Exception\InvalidInstanceException
     * @throws \Laranix\Support\Exception\InvalidTypeException
     */
    public function show(Request $request, Manager $manager)
    {
        $token = $request->query('token');
        $email = $request->query('email');

        if ($token === null || $email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->getVerifyForm($token, $email);
        }

        event(new VerifyAttempt($email));

        return $this->verify($request, $manager, $token, $email);
    }

    /**
     * Get the verification form
     *
     * @param string|null $token
     * @param string|null $email
     * @return \Illuminate\Contracts\View\View
     * @throws \Laranix\Support\Exception\InvalidInstanceException
     * @throws \Laranix\Support\Exception\InvalidTypeException
     */
    protected function getVerifyForm(string $token = null, string $email = null): View
    {
        $this->prepareForFormResponse(true, new ScriptSettings([
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
     * @param \App\Http\Requests\Auth\Email\Verification\PostVerify $request
     * @param \Laranix\Auth\Email\Verification\Manager                              $manager
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(PostVerify $request, Manager $manager): RedirectResponse
    {
        $email = $request->post('email');

        event(new VerifyAttempt($email));

        return $this->verify(
            $request, $manager, $request->post('token'), $email
        );
    }

    /**
     * Show error message on failed verification
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function result(Request $request): View
    {
        $session    = $request->session();
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
     * @param \Illuminate\Http\Request                 $request
     * @param \Laranix\Auth\Email\Verification\Manager $manager
     * @param string                                   $token
     * @param string                                   $email
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function verify(Request $request, Manager $manager, string $token, string $email): RedirectResponse
    {
        $verify = $manager->processToken($token, $email);

        switch ($verify) {
            case Token::TOKEN_VALID:
                return $this->redirectAfterValidVerification($request);
            default:
            case Token::TOKEN_EXPIRED:
            case Token::TOKEN_INVALID:
                return $this->redirectAfterInvalidVerification($verify);
        }
    }

    /**
     * Get the form to refresh a verification code
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     * @throws \Laranix\Support\Exception\InvalidInstanceException
     * @throws \Laranix\Support\Exception\InvalidTypeException
     */
    public function create(Request $request): View
    {
        $this->prepareForFormResponse(true, new ScriptSettings([
            'key'       => 'verify-email-refresh-form',
            'filename'  => 'forms/verifyemailrefresh.js',
        ]));

        return $this->view->make(
            $this->config->get('laranixauth.verification.views.verify_refresh', 'auth.verify.refresh')
        )->with([
            'verify_refresh_message' => $request->session()->get('verify_refresh_message'),
        ]);
    }

    /**
     * Handle a refresh verification request form being posted
     *
     * @param \App\Http\Requests\Auth\Email\Verification\PostVerifyRefresh $request
     * @param \Laranix\Auth\Email\Verification\Manager                                     $manager
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Laranix\Auth\Email\Verification\EmailVerificationException
     */
    public function store(PostVerifyRefresh $request, Manager $manager): RedirectResponse
    {
        $email = $request->post('email');

        event(new RefreshAttempt($email));

        try {
            $token = $manager->fetchTokenByEmail($email);

            $manager->sendMail($token->user ?? null, $manager->renewToken($token));

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
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectAfterValidVerification(Request $request): RedirectResponse
    {
        if ($request->user() === null) {
            $message = 'Your email has been verified, you may now <a href="' . $this->url->to('/login') . '">login</a>';
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
    protected function redirectAfterInvalidVerification(int $verifyResult): RedirectResponse
    {
        if ($verifyResult === Token::TOKEN_EXPIRED) {
            $message = 'Your token has expired, please <a href="' . $this->url->to('/email/verify/refresh') . '">request a new one</a>';
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
