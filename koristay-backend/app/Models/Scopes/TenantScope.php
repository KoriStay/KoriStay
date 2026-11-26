<?php

namespace App\Models\Scopes;

use App\Exceptions\TenantNotResolvedException;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Fails closed: without a resolved tenant the query is refused instead of
     * returning every tenant's rows.
     *
     * @throws TenantNotResolvedException
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            throw TenantNotResolvedException::forModel($model::class);
        }

        $builder->where($model->getTable().'.tenant_id', $tenantId);
    }
}
