<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Listado de usuarios (solo lectura).
 *
 * La aplicacion funciona en modo single-user (ver AutoLogin): no hay alta,
 * edicion ni login de usuarios. Se eliminaron los metodos create/store/
 * show/edit/update, que no tenian rutas ni vistas y no podian usarse.
 *
 * La password nunca se muestra en la vista ni se devuelve en JSON.
 */
class UserController extends Controller
{
    /**
     * Listado de usuarios. Restringido a ADMIN por UserPolicy.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
        ]);
    }
}
