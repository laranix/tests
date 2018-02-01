<?php
namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Laranix\Foundation\Controllers\Controller;

class Home extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function show(): View
    {
        return $this->view->make('home');
    }
}
