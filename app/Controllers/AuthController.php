<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\User;
use App\Requests\LoginRequest;
use Tempest\Auth\Authentication\Authenticator;
use Tempest\Cryptography\Password\PasswordHasher;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Responses\Redirect;
use Tempest\Router\Get;
use Tempest\Router\Post;
use Tempest\View\View;

use function Tempest\View\view;

final readonly class AuthController
{
    public function __construct(
        private Authenticator $authenticator,
        private PasswordHasher $passwordHasher,
    ) {
    }

    #[Get('/login')]
    public function show(Request $request): View|Redirect
    {
        if ($this->authenticator->current() !== null) {
            return new Redirect('/');
        }

        return view('views/login.view.php', error: $request->get('error') !== null);
    }

    #[Post('/login')]
    public function login(LoginRequest $request): Redirect
    {
        $user = User::select()
            ->where('username = ?', $request->username)
            ->first();

        if ($user === null || ! $this->passwordHasher->verify($request->password, $user->password ?? '')) {
            return new Redirect('/login?error=1');
        }

        $this->authenticator->authenticate($user);

        return new Redirect('/');
    }

    #[Post('/logout')]
    public function logout(): Redirect
    {
        $this->authenticator->deauthenticate();

        return new Redirect('/login');
    }
}
