<?php

namespace App\Services\Tenancy;

use App\Models\Company;
use Closure;
use LogicException;

final class CompanyContext
{
    private ?Company $company = null;

    public function set(Company $company): void
    {
        $this->company = $company;
    }

    public function clear(): void
    {
        $this->company = null;
    }

    public function company(): Company
    {
        return $this->company ?? throw new LogicException('Active company is required.');
    }

    public function id(): int
    {
        return $this->company()->id;
    }

    public function run(Company $company, Closure $callback): mixed
    {
        $previous = $this->company;
        try {
            $this->set($company);

            return $callback();
        } finally {
            $this->company = $previous;
        }
    }
}
