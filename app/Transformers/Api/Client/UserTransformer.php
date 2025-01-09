<?php

namespace App\Transformers\Api\Client;

use Illuminate\Support\Str;
use App\Models\User;

class UserTransformer extends BaseClientTransformer
{
    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return User::RESOURCE_NAME;
    }

    /**
     * Transforms a User model into a representation that can be shown to regular
     * users of the API.
     */
    public function transform(User $user): array
    {
        return [
            'uuid' => $user->uuid,
            'username' => $user->username,
            'email' => $user->email,
            'language' => $user->language,
            'image' => 'https://gravatar.com/avatar/' . md5(Str::lower($user->email)), // deprecated
            'admin' => $user->isRootAdmin(), // deprecated, use "root_admin"
            'root_admin' => $user->isRootAdmin(),
            '2fa_enabled' => (bool) $user->use_totp,
            'created_at' => $this->formatTimestamp($user->created_at),
            'updated_at' => $this->formatTimestamp($user->updated_at),
        ];
    }
}
