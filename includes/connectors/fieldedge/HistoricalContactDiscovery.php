<?php
/** Read-only, one-pass historical CRM contact comparison; tags are never evidence. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;
require_once __DIR__ . '/OAuthClient.php';

final class HistoricalContactDiscovery {
    private static function page( array $response, string $key ): array {
        $records = $response[$key] ?? null;
        if ( ! is_array($records) || ! array_is_list($records) || count($records) > 3 ||
            ! is_int($response['total'] ?? null) || $response['total'] < count($records) ) {
            throw new \RuntimeException('historical_page_invalid');
        }
        foreach ($records as $row) { if (!is_array($row)) { throw new \RuntimeException('historical_page_invalid'); } }
        return $records;
    }
    public static function run( callable $get, SelectedCaseJournal $journal, array $candidates ): array {
        if (count($candidates) < 1 || count($candidates) > 2) { throw new \RuntimeException('historical_candidates_invalid'); }
        $seen = [];
        foreach ($candidates as $candidate) {
            $id = $candidate['crm_contact_id'] ?? null;
            $email = $candidate['email'] ?? null;
            if (!is_string($id) || !preg_match('/\A[1-9][0-9]{0,9}\z/D',$id) ||
                !is_string($email) || !filter_var($email,FILTER_VALIDATE_EMAIL) ||
                ($candidate['source'] ?? null) !== 'FluentForms' || isset($seen[$id]) ||
                array_diff(array_keys($candidate), ['crm_contact_id','email','source','source_created_at','has_click_id'])) {
                throw new \RuntimeException('historical_candidate_invalid');
            }
            $seen[$id] = true;
        }
        $results = [];
        foreach ($candidates as $candidate) {
            $result = ['crm_contact_id'=>$candidate['crm_contact_id'], 'status'=>'Needs Review',
                'reason_code'=>'not_found_in_bounded_search','provider_customer_found'=>false,
                'work_order_found'=>false,'invoice_read_verified'=>false,'lead_id_verified'=>false,
                'website_inquiry_attributed'=>false,'crm_tags_used'=>false,
                'provider_relationships'=>[]];
            if ($journal->remaining() < 1) { $result['reason_code']='budget_exhausted'; $results[]=$result; break; }
            try {
                $page = $get('customer_search',['email'=>$candidate['email']]);
                $customers = self::page($page,'customers');
                $exact = array_values(array_filter($customers,static fn($c)=>
                    is_string($c['primaryEmail'] ?? null) && strcasecmp(trim($c['primaryEmail']),trim($candidate['email']))===0));
                $result['search_total']=$page['total'];
                $result['search_page_incomplete']=$page['total'] > count($customers);
                if (count($exact)!==1 || $result['search_page_incomplete']) {
                    $result['reason_code']=$exact ? 'customer_ambiguous_or_page_incomplete':'exact_customer_not_on_bounded_page';
                } else {
                    $customerId=$exact[0]['id'] ?? '';
                    TargetedRead::id($customerId);
                    $result['provider_customer_found']=true;
                    $result['provider_relationships']['customerId']=$customerId;
                    if ($journal->remaining()<1) { $result['reason_code']='budget_exhausted_after_customer'; }
                    else {
                        $workPage=$get('customer_work_orders',['customerId'=>$customerId]);
                        $works=self::page($workPage,'data');
                        $result['work_order_count_on_page']=count($works);
                        $result['work_order_total']=$workPage['total'];
                        $result['work_order_page_incomplete']=$workPage['total']>count($works);
                        // Inspect a single documented work-order ID as a sample, never as unique attribution.
                        if ($works && $journal->remaining()>0) {
                            $workIds=[];
                            foreach($works as $work){ TargetedRead::id($work['workOrderId'] ?? null); $workIds[]=$work['workOrderId']; }
                            sort($workIds,SORT_STRING);
                            $detail=$get('work_order',['id'=>$workIds[0]]);
                            if (($detail['workOrderId'] ?? null)!==$workIds[0] ||
                                ($detail['customerId'] ?? null)!==$customerId) { throw new \RuntimeException('historical_work_relation_invalid'); }
                            $result['work_order_found']=true;
                            $result['work_order_sample_only']=count($works)!==1 || $result['work_order_page_incomplete'];
                            $result['provider_relationships']['workOrderId']=$workIds[0];
                            $result['provider_relationships']['customerId']=$customerId;
                            $result['work_order_completed_field']=$detail['completed'] ?? null;
                            $result['work_order_has_completed_date']=!empty($detail['workOrderInformation']['completedDate']);
                            $result['work_order_snapshot_sha256']=hash('sha256',json_encode($detail,JSON_THROW_ON_ERROR));
                            $invoiceId=$detail['invoiceId'] ?? null;
                            if (is_string($invoiceId) && $invoiceId!=='' && $journal->remaining()>0) {
                                TargetedRead::id($invoiceId);
                                $invoice=$get('invoice',['id'=>$invoiceId]);
                                $body=$invoice['data'] ?? $invoice;
                                if (!is_array($body) || !in_array($body['invoiceId'] ?? null,[$invoiceId],true)) { throw new \RuntimeException('historical_invoice_relation_invalid'); }
                                $result['provider_relationships']['invoiceId']=$invoiceId;
                                $result['invoice_read_verified']=true;
                                $result['invoice_fields_present']=array_values(array_intersect(['total','balance','payStatus','invoiceDate'],array_keys($body)));
                                $result['invoice_snapshot_sha256']=hash('sha256',json_encode($body,JSON_THROW_ON_ERROR));
                            }
                            $leadId=$detail['leadId'] ?? null;
                            if(is_string($leadId) && $leadId!=='' && $journal->remaining()>0) {
                                TargetedRead::id($leadId);
                                $lead=$get('lead',['id'=>$leadId]);
                                $body=$lead['data'] ?? null;
                                if(!is_array($body)||($body['leadId'] ?? null)!==$leadId) {throw new \RuntimeException('historical_lead_relation_invalid');}
                                $result['provider_relationships']['leadId']=$leadId;
                                $result['lead_id_verified']=true;
                            }
                            $result['reason_code']='work_order_operational_link_verified_not_attribution';
                        } else {$result['reason_code']=$works?'work_order_detail_budget_exhausted':'no_customer_work_orders_on_bounded_page';}
                    }
                }
            } catch (\Throwable $error) {
                $result['reason_code']='historical_request_or_structure_unconfirmed';
                $result['request_failure_stop']=true;
            }
            $results[]=$result;
            if (!empty($result['request_failure_stop']) || $result['work_order_found']) {break;}
        }
        return $results;
    }
}
