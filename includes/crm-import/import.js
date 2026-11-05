/* Ordinary authenticated administrator UI; batches advance without manual approvals. */
(() => {
  'use strict';
  const root = document.getElementById('seab-crm-import'); if (!root) return;
  const initial = JSON.parse(document.getElementById('seab-import-initial').textContent);
  const upload = document.getElementById('seab-import-upload'), mappings = document.getElementById('seab-import-mappings');
  const review = document.getElementById('seab-import-review'), message = document.getElementById('seab-import-message');
  const begin = document.getElementById('seab-import-begin'), resume = document.getElementById('seab-import-resume');
  let state = initial.state, busy = false;
  const option = (select, value, label) => { const o = document.createElement('option'); o.value = value; o.textContent = label; select.append(o); };
  option(mappings.elements.form, '', 'Choose a destination form');
  initial.choices.forms.forEach(f => option(mappings.elements.form, f.id, f.title));
  for (const [name, slug, label, types] of [['service_field','service_type','Service',['text','select-one']],['outcome_field','customer_rating','Outcome reason',['select-one']]]) {
    const select = mappings.elements[name]; const fields = initial.choices.fields.filter(f => types.includes(f.type));
    if (!fields.some(f => f.slug === slug)) option(select, slug, `Create missing ${label} field (${slug})`);
    fields.forEach(f => option(select, f.slug, `${f.label} (${f.slug})`));
    if (Array.from(select.options).some(o => o.value === slug)) select.value = slug;
  }
  function selectors(saved) { const select = mappings.elements.form_service; select.replaceChildren(); option(select, '', 'Choose the actual Service input');
    const f = initial.choices.forms.find(f => String(f.id) === mappings.elements.form.value);
    if (f) Object.entries(f.selectors).forEach(([key,label]) => option(select,key,`${label} (${key})`));
    if(saved)select.value=saved;
  }
  mappings.elements.form.addEventListener('change', () => selectors());
  function render() {
    const recovery=document.querySelector('#seab-import-recovery p');if(recovery&&state.recovery)recovery.textContent=state.recovery.message;
    message.textContent = `${state.message || 'Upload a configuration ZIP to begin.'}${state.total ? ` Progress: ${state.cursor}/${state.total}.` : ''}`;
    document.getElementById('seab-import-details').textContent = JSON.stringify({status:state.status, revision:state.revision, upload_sha256:state.upload_sha256, package_revision:state.package_revision, vendor_saves:state.writes},null,2);
    upload.hidden = ['running','uncertain'].includes(state.status);
    mappings.hidden = !['uploaded','review','complete'].includes(state.status);
    begin.hidden = state.status !== 'review'; resume.hidden = !['running','uncertain'].includes(state.status);
    review.replaceChildren();
    if(state.status === 'review'){
      const h=document.createElement('h3');h.textContent='Review changes';review.append(h);const table=document.createElement('table');table.className='widefat striped';
      for(const a of state.actions){const tr=document.createElement('tr');for(const text of [a.title,a.kind,a.decision]){const td=document.createElement('td');td.textContent=text;tr.append(td);}
        if(a.decision==='conflict'){const td=document.createElement('td'), s=document.createElement('select'), p=document.createElement('p'), link=document.createElement('a');p.textContent=`Destination: ${a.current_title}. Changed fields: ${a.changed_fields.join(', ')}. `;link.href=nativeURL(a);link.textContent='Inspect native object';p.append(link);td.append(p);s.dataset.key=a.key;option(s,'','Choose');option(s,'keep','Keep destination edit');option(s,'replace','Replace with package');td.append(s);tr.append(td);}table.append(tr);
      }review.append(table);
      if(state.actions.some(a=>a.decision==='conflict')){begin.hidden=true;const b=document.createElement('button');b.className='button';b.textContent='Review conflict choices';b.onclick=async()=>{const choices={...(state.choices||{})};review.querySelectorAll('select').forEach(s=>choices[s.dataset.key]=s.value);await execute('preview',{mappings:JSON.stringify(readMappings()),choices:JSON.stringify(choices)});};review.append(b);}
    }
    const summary=document.getElementById('seab-import-summary');summary.replaceChildren();
    if(state.status==='complete'){const p=document.createElement('p');p.textContent=`Import complete: ${state.total-1} native definitions; ${state.writes} saves including Ads setup. Automation, enrollment, Sheets and exports remain disabled.`;summary.append(p);
      const ul=document.createElement('ul');for(const a of state.actions){if(a.kind==='settings')continue;const li=document.createElement('li'),link=document.createElement('a');link.textContent=a.title;link.href=nativeURL(a);li.append(link);ul.append(li);}const content=document.createElement('details'), title=document.createElement('summary');title.textContent='View imported CRM content';content.append(title,ul);const next=document.createElement('a');next.className='button button-primary';next.href='admin.php?page=siteengine-ads-bridge&tab=setup&section=form';next.textContent='Continue setup';summary.append(next,content);
    }
    root.querySelectorAll('button').forEach(b=>b.disabled=busy);
  }
  function nativeURL(a){
    const base='admin.php?page=fluentcrm-admin#/';
    if(a.kind==='template')return base+'email/templates/'+encodeURIComponent(a.id);
    if(a.kind==='automation'||a.kind==='step')return base+'funnels';
    if(a.kind.startsWith('email_sequence'))return base+'email/sequences';
    if(['campaign','sequence_email'].includes(a.kind))return base+'email/campaigns';
    return base+({field:'settings/custom_fields',list:'contact-groups/lists',tag:'contact-groups/tags'}[a.kind]||'');
  }
  const readMappings=()=>Object.fromEntries(new FormData(mappings).entries());
  async function request(operation, extra){const body=extra instanceof FormData?extra:new FormData();if(!(extra instanceof FormData))Object.entries(extra||{}).forEach(([k,v])=>body.set(k,v));body.set('action','seab_crm_import');body.set('operation',operation);body.set('_ajax_nonce',root.dataset.nonce);
    const response=await fetch(ajaxurl,{method:'POST',body,credentials:'same-origin',signal:AbortSignal.timeout(25000)});const data=await response.json();if(!data.success){if(data.data.state)state=data.data.state;throw new Error(data.data.message||'Import request failed.');}state=data.data;
  }
  async function execute(operation, extra){if(busy)return;busy=true;render();try{await request(operation,extra);if(operation==='begin'||operation==='continue'){let batches=0;while(state.status==='running'){if(++batches>64)throw new Error('Batch ceiling reached. Progress retained.');await request('continue');render();}}}catch(e){state.message=e.message+' Upload, mappings and progress are retained. Inspect before resuming.';}finally{busy=false;render();}}
  upload.addEventListener('submit',e=>{e.preventDefault();execute('upload',new FormData(upload));});
  mappings.addEventListener('submit',e=>{e.preventDefault();execute('preview',{mappings:JSON.stringify(readMappings()),choices:'{}'});});
  begin.onclick=()=>execute('begin',{plan_hash:state.plan_hash});resume.onclick=()=>execute('continue');
  if(state.mappings){for(const [k,v] of Object.entries(state.mappings))if(mappings.elements[k])mappings.elements[k].value=v;selectors(state.mappings.form_service);}
  render();
})();
