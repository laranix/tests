<?php
namespace App\Http\Controllers;

use Laranix\Foundation\Controllers\Controller;

class Home extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function getHome()
    {
        return $this->view->make('home');
    }
}
