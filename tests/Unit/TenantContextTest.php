<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use PHPUnit\Framework\TestCase;

class TenantContextTest extends TestCase
{
    protected TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = new TenantContext();
    }

    public function test_can_set_and_get_tenant(): void
    {
        $tenant = new Tenant(['name' => 'Acme Test Corp', 'slug' => 'acme-test']);
        $tenant->id = 42;

        $this->context->setTenant($tenant);

        $this->assertTrue($this->context->hasTenant());
        $this->assertSame($tenant, $this->context->getTenant());
        $this->assertEquals(42, $this->context->id());
    }

    public function test_can_clear_tenant(): void
    {
        $tenant = new Tenant(['name' => 'Acme Test Corp', 'slug' => 'acme-test']);
        $tenant->id = 99;

        $this->context->setTenant($tenant);
        $this->assertTrue($this->context->hasTenant());

        $this->context->clear();

        $this->assertFalse($this->context->hasTenant());
        $this->assertNull($this->context->getTenant());
        $this->assertNull($this->context->id());
    }
}
