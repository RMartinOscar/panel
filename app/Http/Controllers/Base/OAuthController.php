<?php

namespace App\Http\Controllers\Base;

use App\Http\Controllers\Controller;
use App\Services\Users\UserUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    /**
     * OAuthController constructor.
     */
    public function __construct(
        private UserUpdateService $updateService
    ) {
    }

    /**
     * Link a new OAuth
     */
    protected function link(Request $request): RedirectResponse
    {
        $driver = $request->get('driver');

        return Socialite::with($driver)->redirect();
    }

    /**
     * Remove a OAuth link
     */
    protected function unlink(Request $request): Response
    {
        $oauth = $request->user()->oauth;
        unset($oauth[$request->get('driver')]);

        $this->updateService->handle($request->user(), ['oauth' => $oauth]);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
