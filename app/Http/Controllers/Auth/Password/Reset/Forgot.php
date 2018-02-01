<?php
namespace App\Http\Controllers\Auth\Password\Reset;

use App\Http\Requests\Auth\Password\Reset\PostForgot;
use Illuminate\Http\Request;
use Laranix\Auth\Password\Reset\Events\ForgotAttempt;
use Laranix\Foundation\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laranix\Auth\Password\Reset\PasswordResetException;
use Laranix\Auth\Password\Reset\Manager;
use Laranix\Auth\User\Repository;
use Laranix\Support\Exception\NullValueException;
use Laranix\Themer\Scripts\Settings as ScriptSettings;
use Illuminate\Contracts\View\View;

class Forgot extends Controller
{
    /**
     * Show forgot password form
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     * @throws \Laranix\Support\Exception\InvalidInstanceException
     * @throws \Laranix\Support\Exception\InvalidTypeException
     */
    public function create(Request $request): View
    {
        $this->prepareForFormResponse(true, new ScriptSettings([
            'key'       => 'pass-forgot-form',
            'filename'  => 'forms/passforgot.js',
        ]));

        return $this->view->make(
            $this->config->get('laranixauth.password.views.request_form', 'auth.password.forgot')
        )->with([
            'forgot_password_message' => $request->session()->get('forgot_password_message')
        ]);
    }

    /**
     * Send a reset link to email
     *
     * @param \App\Http\Requests\Auth\Password\Reset\PostForgot $request
     * @param \Laranix\Auth\Password\Reset\Manager                              $resetManager
     * @param \Laranix\Auth\User\Repository                                     $userRepository
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Laranix\Auth\Password\Reset\PasswordResetException
     */
    public function store(PostForgot $request, Manager $resetManager, Repository $userRepository): RedirectResponse
    {
        $email = $request->post('email');

        event(new ForgotAttempt($email));

        try {
            $user = $userRepository->getByEmail($email);

            $resetManager->sendMail($user, $resetManager->createToken($user, $email));
        }  catch (\Exception $e) {

            // Null value exception means the user doesn't exist
            // We don't want the user to know that
            if (!($e instanceof NullValueException)) {
                throw new PasswordResetException("Failed to begin password reset process: {$e->getMessage()}", 0, $e);
            }

            // TODO Log otherwise
        }

        // TODO Localise
        return redirect(
            $this->url->to('password/forgot')
        )->with(
            'forgot_password_message',
            'If the email is registered in our system, you will receive an email with instructions to reset your password shortly'
        );
    }
}
