<?php

namespace App\Exceptions;

use RuntimeException;

class TenantNotResolvedException extends RuntimeException
{
    /**
     * @param  class-string  $modelClass
     */
    public static function forModel(string $modelClass): self
    {
        return new self(
            "A tenant must be resolved before querying [{$modelClass}]. "
            .'Resolve the tenant first, or opt out explicitly with withoutGlobalScope(TenantScope::class).'
        );
    }
}
