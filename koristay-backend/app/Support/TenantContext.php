<?php

namespace App\Support;

use App\Models\Tenant;

class TenantContext {
    protected ?Tenant $tenant = null;

    public function setTenant(Tenant $tenant): void {
        $this->tenant = $tenant;

        if($tenant) 
        {
            setPermissionsTeamId($tenant->id);
        }
    }

    public function get(): ?Tenant {
        return $this->tenant;
    }

    public function id(): ?int {
        return $this->tenant?->id;
    }
}