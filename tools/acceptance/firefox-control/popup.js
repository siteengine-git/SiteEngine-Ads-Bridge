'use strict';
let current=null;
for(const key of ['allocation','release','context','journal','manifest']){
 const label=document.createElement('label');label.textContent=key+' JSON: ';const f=document.createElement('input');f.type='file';f.id=key;label.append(f);document.querySelector('#inputs').append(label,document.createElement('br'));
 if(key!=='manifest'){const p=document.createElement('input');p.id=key+'-pin';p.placeholder='Independent '+key+' SHA-256';p.size=64;document.querySelector('#inputs').append(p,document.createElement('br'));}
}
async function act(op){try{
 let m={op};if(op==='configure'){m.raw={};m.pins={};for(const key of ['allocation','release','context','journal','manifest']){const f=document.getElementById(key).files[0];AlmaFirefoxCore.need(f&&f.size<=1048576,'bounded private '+key+' file');m.raw[key]=await f.text();if(key!=='manifest')m.pins[key]=document.getElementById(key+'-pin').value.trim();}}
 if(op==='refresh'){const f=document.getElementById('journal').files[0];AlmaFirefoxCore.need(f&&f.size<=1048576,'bounded current journal');m.journal=await f.text();m.pin=document.getElementById('journal-pin').value.trim();}
 current=await browser.runtime.sendMessage(m);document.querySelector('#status').textContent=JSON.stringify(current,null,2);
 }catch{current=null;document.querySelector('#status').textContent='FAIL: input/control operation; retain controls, do not capture or retry';}}
for(const key of ['configure','refresh','probe','summary','stop'])document.getElementById(key).onclick=()=>act(key);
document.querySelector('#save').onclick=()=>{if(!current)return;const url=URL.createObjectURL(new Blob([JSON.stringify(current,null,2)],{type:'application/json'}));const a=document.createElement('a');a.href=url;a.download='firefox-control-evidence.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);};
