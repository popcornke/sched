(function(){
 const modal=document.getElementById('officeDocumentModal'),frame=document.getElementById('officeDocumentFrame'),print=document.getElementById('officePrintDocument');
 if(!modal)return;
 function close(){modal.hidden=true;document.body.classList.remove('office-modal-open');frame.removeAttribute('src');}
 function open(id){modal.hidden=false;document.body.classList.add('office-modal-open');frame.src='document-view.php?id='+encodeURIComponent(id)+'&embedded=1';}
 document.querySelectorAll('[data-document-view]').forEach(link=>link.addEventListener('click',function(e){e.preventDefault();open(this.dataset.documentView);}));
 modal.querySelectorAll('[data-close-modal]').forEach(el=>el.addEventListener('click',close));
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!modal.hidden)close();});
 print.addEventListener('click',()=>{try{frame.contentWindow.focus();frame.contentWindow.print();}catch(e){window.aoNotice?.('Printing unavailable', 'Unable to open print preview. Use View to open the document in a separate tab.');}});
 const url=new URL(location.href),id=url.searchParams.get('open');if(id&&/^\d+$/.test(id))open(id);
 document.getElementById('officePrintDocumentCsv')?.addEventListener('click',()=>{
  if(modal.hidden || !frame.getAttribute('src'))return;
  const url=new URL(frame.getAttribute('src'),location.href);
  url.searchParams.set('export','csv');
  const link=document.createElement('a');link.href=url.href;link.download='';document.body.appendChild(link);link.click();link.remove();
 });
})();
