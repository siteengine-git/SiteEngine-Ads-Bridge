<?php
/** Existing DPAPI launcher alone supplies credentials through redirected stdin. */
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
require_once __DIR__.'/../../includes/connectors/fieldedge/ApprovedLeadWorker.php';
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$bytes=stream_get_contents(STDIN,196609);
$r=\SiteEngine\AdsBridge\Connectors\FieldEdge\ApprovedLeadWorker::run($bytes);unset($bytes);
echo \SiteEngine\AdsBridge\Connectors\FieldEdge\ApprovedLeadWorker::encode($r);
exit($r['status']==='observations_ready'?0:2);
