<?php

namespace App\Actions;

use App\Models\RequestType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class CreateRequestType
{
    private const int MAX_SLUG_INSERT_ATTEMPTS = 5;

    /** @param array{name: string, description?: ?string, is_active?: bool} $data */
    public function handle(Workspace $workspace, User $creator, array $data): RequestType
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_SLUG_INSERT_ATTEMPTS; $attempt++) {
            $requestType = new RequestType($data);
            $requestType->forceFill(['slug' => $this->uniqueSlug($workspace, $data['name'])]);
            $requestType->workspace()->associate($workspace);
            $requestType->creator()->associate($creator);

            try {
                $requestType->save();

                return $requestType;
            } catch (UniqueConstraintViolationException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException;
    }

    private function uniqueSlug(Workspace $workspace, string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'request-type';
        $slug = $baseSlug;
        $suffix = 2;

        while ($workspace->requestTypes()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
