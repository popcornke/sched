(() => { 'use strict';
 const form=document.getElementById('saAuditFilters'), results=document.getElementById('saAuditResults'), hint=document.getElementById('saAuditHint'), count=document.getElementById('saAuditTotal');
 const overlay=document.getElementById('saAuditOverlay'), body=document.getElementById('saAuditDetails'), dialog=overlay?.querySelector('.sa-audit-dialog');
 if(!form||!results||!overlay||!body||!dialog)return;
 let pending=null, timer=null, lastFocus=null, serial=0;
 const readQuery=()=>{const params=new URLSearchParams(new FormData(form));for(const [k,v] of params){if(!String(v).trim())params.delete(k);}return params;};
 async function refresh(url,history=true){const requestId=++serial;pending?.abort();pending=new AbortController();hint.classList.add('is-loading');hint.textContent='Updating audit events…';try{
 const res=await fetch(url,{credentials:'same-origin',signal:pending.signal,headers:{'X-Requested-With':'XMLHttpRequest'}});if(!res.ok)throw Error('HTTP '+res.status);
 const doc=new DOMParser().parseFromString(await res.text(),'text/html');const next=doc.getElementById('saAuditResults');const nextCount=doc.getElementById('saAuditTotal');if(!next)throw Error('Missing results');if(requestId!==serial)return;
 results.innerHTML=next.innerHTML;if(nextCount)count.textContent=nextCount.textContent;if(history)window.history.replaceState(null,'',url);
 hint.textContent='Filters update automatically. Clear the search and select All to see every event.';
 }catch(error){if(error.name==='AbortError')return;hint.textContent='Unable to update results. Try again.';console.warn('Audit filter error:',error);}finally{if(requestId===serial)hint.classList.remove('is-loading');}}
 function update(){const params=readQuery();params.delete('page');const query=params.toString();refresh('audit-logs.php'+(query?'?'+query:''));}
 form.addEventListener('submit',e=>{e.preventDefault();clearTimeout(timer);update();});
 form.querySelector('[name=q]')?.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(update,320);});
 form.querySelectorAll('select,input[type=date]').forEach(el=>el.addEventListener('change',()=>{clearTimeout(timer);update();}));
 results.addEventListener('click',e=>{const link=e.target.closest('.sa-audit-pages a');if(link){e.preventDefault();refresh(link.href);return;}const btn=e.target.closest('[data-audit]');if(!btn)return;let details;try{details=JSON.parse(btn.dataset.audit);}catch{return;}lastFocus=btn;body.replaceChildren();for(const [key,value] of Object.entries(details)){const dt=document.createElement('dt');dt.textContent=key;const dd=document.createElement('dd');dd.textContent=String(value);body.append(dt,dd);}overlay.hidden=false;document.body.style.overflow='hidden';dialog.focus();});
 function close(){overlay.hidden=true;document.body.style.overflow='';lastFocus?.focus();}
 document.getElementById('saAuditClose')?.addEventListener('click',close);document.getElementById('saAuditCloseX')?.addEventListener('click',close);
 overlay.addEventListener('click',e=>{if(e.target===overlay)close();});document.addEventListener('keydown',e=>{if(overlay.hidden)return;if(e.key==='Escape')close();if(e.key==='Tab'){const focusable=[...dialog.querySelectorAll('button:not([disabled])')];const first=focusable[0],last=focusable[focusable.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}});
 })();
