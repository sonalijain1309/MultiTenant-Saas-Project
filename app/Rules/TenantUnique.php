<?php

namespace App\Rules;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantUnique implements ValidationRule
{
    /**
     * @param string $table Target database table
     * @param string|null $column Target column name (defaults to attribute name)
     * @param mixed|null $ignoreId Record ID to ignore (for update requests)
     * @param string $idColumn Column name for ignore ID (defaults to 'id')
     * @param int|null $tenantId Explicit tenant ID (defaults to active TenantContext)
     */
    public function __construct(
        protected string $table,
        protected ?string $column = null,
        protected mixed $ignoreId = null,
        protected string $idColumn = 'id',
        protected ?int $tenantId = null
    ) {
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tenantId = $this->tenantId ?? app(TenantContext::class)->id();

        if ($tenantId === null) {
            $fail("Validation cannot verify uniqueness: no active tenant context.");
            return;
        }

        $column = $this->column ?? $attribute;

        $query = DB::table($this->table)
            ->where('tenant_id', $tenantId)
            ->where($column, $value);

        if ($this->ignoreId !== null) {
            $query->where($this->idColumn, '!=', $this->ignoreId);
        }

        // Exclude soft-deleted rows if the table contains deleted_at column
        if (Schema::hasColumn($this->table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($query->exists()) {
            $humanColumn = ucwords(str_replace('_', ' ', $column));
            $fail("The {$humanColumn} has already been taken for this tenant.");
        }
    }
}
