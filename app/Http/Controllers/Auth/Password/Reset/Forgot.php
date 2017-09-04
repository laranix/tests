<?php
namespace App\Http\Controllers\Auth\Password\Reset;

use Laranix\Auth\Password\Reset\Events\ForgotAttempt;
use Laranix\Foundation\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laranix\Auth\Password\Reset\PasswordResetException;
use Laranix\Auth\Password\Reset\Manager;
use Laranix\Auth\User\Repository as UserRepository;
use Laranix\Support\Exception\NullValueException;
use Laranix\Themer\Scripts\Settings as ScriptSettings;
use Illuminate\Contracts\View\View;

class Forgot extends Controller
{
    /**
     * Show forgot password form
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function getPasswordForgotForm() : View
    {
        $this->prepareForFormResponse(new ScriptSettings([
            'key'       => 'pass-forgot-form',
            'filename'  => 'forms/passforgot.js',
        ]));

        return $this->view->make($this->config->get('laranixauth.password.views.request_form', 'auth.password.forgot'))
            ->with([
                'forgot_password_message' => $this->getSessionData()->get('forgot_password_message')
            ]);
    }

    /**
     * Send a reset link to email
     *
     * @param \Laranix\Auth\Password\Reset\Manager    $resetManager
     * @param \Laranix\Auth\User\Repository     $userRepository
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Laranix\Auth\Password\Reset\PasswordResetException
     */
    public function postPasswordForgotForm(Manager $resetManager, UserRepository $userRepository) : RedirectResponse
    {
        $email = $this->getPostData('email');

        event(new ForgotAttempt($email));

        $this->validate([
            'email' => 'required|email|max:255',
        ]);

        try {
            $user = $userRepository->getByEmail($email);

            $resetManager->sendMail($user, $resetManager->createToken($user, $email));
        }  catch (\Exception $e) {

            // Null value exception means the user doesn't exist
            // We don't want the user to know that
            if (!($e instanceof NullValueException)) {
                throw new PasswordResetException("Failed to begin password reset process: {$e->getMessage()}", 0, $e);
            }

            // Log otherwise
        }

        // TODO Localise
        return redirect($this->url->to('password/forgot'))
            ->with('forgot_password_message',
                   'If the email is registered in our system, you will receive an email with instructions to reset your password shortly');
    }
}
