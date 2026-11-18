<?php
namespace SiteEngine\AdsBridge\Tests\FieldEdge;
final class ProjectionFixture {
	public static function input(): array {
		return array( 'synthetic' => true, 'provider' => 'fieldedge', 'tenant_id' => 'synthetic-climatrol-projection-1',
			'customer' => array( 'id' => 'synthetic-customer-1', 'email' => 'fe-projection-customer-1@example.invalid', 'name' => 'Synthetic FE Customer', 'phone' => '2025550100', 'source_updated_at' => '2026-11-11T12:00:00Z' ),
			'job' => array( 'id' => 'synthetic-job-1', 'customer_id' => 'synthetic-customer-1', 'status' => 'scheduled', 'service_category' => 'HVAC maintenance', 'scheduled_at' => '2026-11-12T14:00:00Z', 'completed_at' => null, 'source_updated_at' => '2026-11-11T12:00:00Z' ) );
	}
}
