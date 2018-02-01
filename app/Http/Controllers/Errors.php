<?php
namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Laranix\Foundation\Controllers\Controller;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Errors extends Controller
{
    /**
     * Return true for page outputting
     *
     * @return bool
     */
    protected function shouldAutoPrepareForResponse(): bool
    {
        return true;
    }

    /**
     * @param \Exception $exception
     * @return \Illuminate\Contracts\View\View|null
     */
    public function renderClientError(\Exception $exception): ?View
    {
        $class = get_class($exception);

        switch ($class) {
            case NotFoundHttpException::class:
                return $this->notFound($exception);
            case AccessDeniedHttpException::class:
                return $this->unauthorized($exception);
            default:
                return null;
        }
    }

    /**
     * Render 404
     *
     * @param \Exception $exception
     * @return \Illuminate\Contracts\View\View
     */
    public function notFound(\Exception $exception): View
    {
        return $this->view->make('errors.404', [
            'exception' => $exception
        ]);
    }

    /**
     * Render 403
     *
     * @param \Exception $exception
     * @return \Illuminate\Contracts\View\View
     */
    public function unauthorized(\Exception $exception): View
    {
        return $this->view->make('errors.403', [
            'exception' => $exception
        ]);
    }
}
