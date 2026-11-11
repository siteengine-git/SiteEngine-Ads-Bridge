<?php
/** In-memory exact-contract double only; NOT WordPress persistence or a ledger. */
namespace SiteEngine\AdsBridge\Tests\FieldEdge;
final class SyntheticProspectiveApi {
	public array $rows = array(); public array $calls = array(); public bool $loseObserveReply = false; public bool $failObserve = false; public bool $badRead = false; public bool $badPermitRead = false;
	public function prepare( string $json ): array {
		$this->calls[] = 'prepare'; $i = json_decode( $json, true );
		$id = hash( 'sha256', $i['provider'] . $i['tenant_id'] . $i['inquiry_id'] );
		if ( isset( $this->rows[$id] ) ) {
			return array( 'contract_version' => '1', 'status' => $this->rows[$id]['intent_json'] === $json ? 'replayed' : 'conflict', 'intent_id' => $id, 'body_sha256' => $i['request']['body_sha256'] );
		}
		$this->rows[$id] = array( 'contract_version' => '1', 'status' => 'prepared', 'intent_id' => $id, 'body_sha256' => $i['request']['body_sha256'], 'intent_json' => $json, 'claim' => null, 'observation' => null, 'mapping' => null );
		return array( 'contract_version' => '1', 'status' => 'prepared', 'intent_id' => $id, 'body_sha256' => $i['request']['body_sha256'] );
	}
	public function claim( string $id, string $sha ): array {
		$this->calls[] = 'claim'; $r = &$this->rows[$id];
		if ( $r['status'] !== 'prepared' || $r['claim'] || $r['body_sha256'] !== $sha ) { return array( 'status' => 'held' ); }
		$r['status'] = 'claimed'; $r['claim'] = array( 'permit' => hash( 'sha256', 'synthetic-permit:' . $id ), 'body_sha256' => $sha, 'dispatch_mode' => 'synthetic_transport_only' );
		return array( 'contract_version' => '1', 'status' => 'claimed', 'intent_id' => $id, 'body_sha256' => $sha ) + $r['claim'];
	}
	public function observe( string $id, string $permit, string $json ): array {
		$this->calls[] = 'observe';
		if ( $this->failObserve ) { throw new \RuntimeException( 'storage_unavailable' ); }
		$r = &$this->rows[$id]; $o = json_decode( $json, true );
		if ( $permit !== $r['claim']['permit'] || $o['request_body_sha256'] !== $r['body_sha256'] ) { return array( 'status' => 'held' ); }
		if ( $r['observation'] && $r['observation'] !== $o ) { return array( 'status' => 'conflict' ); }
		$r['observation'] = $o; $r['status'] = $o['classification'] === 'success' ? 'succeeded' : $o['classification'];
		if ( $r['status'] === 'succeeded' ) {
			$i = json_decode( $r['intent_json'], true );
			$r['mapping'] = array( 'provider' => 'fieldedge', 'tenant_id' => $i['tenant_id'], 'lead_id' => $o['lead_id'], 'inquiry_id' => $i['inquiry_id'], 'source' => $i['source'] );
		}
		if ( $this->loseObserveReply ) { throw new \RuntimeException( 'lost_reply' ); }
		return $r;
	}
	public function read( string $id ): array {
		$this->calls[] = 'read'; $r = $this->rows[$id] ?? array( 'status' => 'not_found' );
		if ( $this->badRead ) { $r['intent_json'] .= ' '; }
		if ( $this->badPermitRead && $r['claim'] ) { $r['claim']['permit'] = str_repeat( 'a', 64 ); }
		return $r;
	}
}
