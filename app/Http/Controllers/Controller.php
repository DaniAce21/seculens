<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Controlador base de la aplicacion.
 *
 * Importa AuthorizesRequests porque las policies se invocan mediante
 * $this->authorize() en los controllers. Es una adicion sobre el
 * esqueleto de Laravel y por eso se documenta aqui: sin este trait,
 * cada controller que use $this->authorize() fallaria en tiempo de
 * ejecucion con un error poco descriptivo.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
