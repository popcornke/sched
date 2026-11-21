(()=>{'use strict';
 const form=document.getElementById('saReportFilters'), results=document.getElementById('saReportResults'), status=document.getElementById('saReportStatus');
 const csv=document.getElementById('saReportCsv'),pdf=document.getElementById('saReportPdf');
 if(!form||!results||!status||!csv||!pdf)return;
 let currentController=null,version=0,timer;
 const run=async()=>{
  const from=form.elements.namedItem('from').value,to=form.elements.namedItem('to').value;
  if(from&&to&&from>to){status.textContent='From date must be on or before To date.';csv.setAttribute('aria-disabled','true');pdf.setAttribute('aria-disabled','true');return;}
  const params=new URLSearchParams(new FormData(form));const current=new URL(location.href);current.search=params.toString();history.replaceState(null,'',current.href);
  csv.href='report-export.php?'+params.toString()+'&format=csv';pdf.href='report-export.php?'+params.toString()+'&format=pdf';
  csv.setAttribute('aria-disabled','true');pdf.setAttribute('aria-disabled','true');
  if(currentController)currentController.abort();currentController=new AbortController();const key=++version;
  status.textContent='Updating preview…';status.classList.add('is-loading');results.setAttribute('aria-busy','true');
  try{const response=await fetch('reports.php?'+params.toString()+'&partial=1',{credentials:'same-origin',signal:currentController.signal,headers:{'X-Requested-With':'XMLHttpRequest'}});if(!response.ok)throw new Error('Request failed: '+response.status);const html=await response.text();if(key!==version)return;results.innerHTML=html;status.textContent='Preview is up to date.';csv.removeAttribute('aria-disabled');pdf.removeAttribute('aria-disabled');}
  catch(err){if(err.name==='AbortError')return;if(key!==version)return;status.textContent='Unable to refresh preview. Try changing a filter again.';console.warn('Report preview',err);}
  finally{if(key===version){status.classList.remove('is-loading');results.removeAttribute('aria-busy');}}
 };
 const schedule=()=>{clearTimeout(timer);timer=setTimeout(run,260);};
 form.addEventListener('change',schedule);form.addEventListener('input',e=>{if(e.target.type==='date')schedule();});
 form.addEventListener('submit',e=>{e.preventDefault();clearTimeout(timer);run();});
 for(const link of [csv,pdf])link.addEventListener('click',e=>{if(link.getAttribute('aria-disabled')==='true')e.preventDefault();});
})();
