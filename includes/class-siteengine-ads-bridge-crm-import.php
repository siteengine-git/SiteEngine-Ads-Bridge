<?php
/** Destination configuration importer; no separate CRM plugin/application required. */
if(!defined('ABSPATH'))exit;
foreach(['common','session','sequences','dependencies','format','native','engine','preview','admin'] as $seab_import_file)require_once __DIR__.'/crm-import/'.$seab_import_file.'.php';
unset($seab_import_file);
