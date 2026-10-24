(function(){
 const recentTab=document.getElementById('acadRecentChoice'),reportsTab=document.getElementById('acadReportsChoice');
 const recentPanel=document.getElementById('acadRecentPanel'),reportsPanel=document.getElementById('acadReportsPanel'),frame=document.getElementById('acadReportsFrame');
 function select(which){
  const reports=which==='reports';
  recentTab.classList.toggle('active',!reports);reportsTab.classList.toggle('active',reports);
  recentTab.setAttribute('aria-selected',String(!reports));reportsTab.setAttribute('aria-selected',String(reports));
  recentTab.tabIndex=reports?-1:0;reportsTab.tabIndex=reports?0:-1;
  recentPanel.hidden=reports;reportsPanel.hidden=!reports;
  if(reports&&!frame.getAttribute('src'))frame.setAttribute('src',frame.dataset.src);
 }
 recentTab.addEventListener('click',()=>select('recent'));
 reportsTab.addEventListener('click',()=>select('reports'));
 [recentTab,reportsTab].forEach((tab)=>tab.addEventListener('keydown',(e)=>{
  if(e.key==='ArrowRight'||e.key==='ArrowLeft'||e.key==='Home'||e.key==='End'){
   e.preventDefault();const next=tab===recentTab?reportsTab:recentTab;next.focus();select(next===reportsTab?'reports':'recent');
  }
 }));
 select('recent');
})();
