(() => {
 'use strict';
 const config = window.BCP_ACAD_NOTIFICATIONS;
 const bell = document.getElementById('acadBell');
 const count = document.getElementById('acadBellCount');
 const panel = document.getElementById('acadNotificationPanel');
 const items = document.getElementById('acadNotificationItems');
 const summary = document.getElementById('acadNotificationSummary');
 const markAll = document.getElementById('acadMarkAll');
 if (!config || !bell || !count || !panel || !items || !summary || !markAll) return;
 let entries = [];
 let unread = 0;
 const api = async (action, data) => {
   const options = data ? {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action,csrf_token:config.csrf,...data})} : {credentials:'same-origin'};
   const response = await fetch(data ? config.api : config.api+'?action=list&limit=30', options);
   const payload = await response.json();
   if(!response.ok || !payload.success) throw new Error(payload.message || 'Notification request failed');
   return payload;
 };
 const validUrl = url => {
   if(typeof url !== 'string') return null;
   try { const parsed = new URL(url, location.origin); return parsed.origin === location.origin && parsed.pathname.startsWith(location.pathname.split('/app/')[0]+'/app/') ? parsed.href : null; } catch(_) {return null;}
 };
 function render() {
   count.hidden = unread < 1;
   count.textContent = unread > 99 ? '99+' : String(unread);
   summary.textContent = unread ? `${unread} unread notification${unread===1?'':'s'}` : 'You are all caught up';
   bell.setAttribute('aria-label', unread ? `${unread} unread notifications` : 'Open notifications');
   items.replaceChildren();
   if (!entries.length) {const empty=document.createElement('div');empty.className='acad-notification-empty';empty.textContent='No notifications yet';items.append(empty);return;}
   for (const item of entries) {
     const btn=document.createElement('button');btn.type='button';btn.className='acad-notification-item'+(item.read?'':' unread');
     const title=document.createElement('strong');title.textContent=item.title||'Notification';
     const msg=document.createElement('span');msg.textContent=item.message||'';
     const when=document.createElement('time');when.textContent=item.createdAt ? new Date(item.createdAt).toLocaleString() : '';
     btn.append(title,msg,when);
     btn.addEventListener('click',async()=>{
       const dest=validUrl(item.url);
       if(!item.read){try{await api('read',{notification_id:item.id});}catch(e){console.warn(e);} }
       if(dest)location.assign(dest);else {await load();close();}
     });items.append(btn);
   }
 }
 async function load(){try{const data=await api('list');entries=Array.isArray(data.notifications)?data.notifications:[];unread=Number(data.unread_count)||0;render();}catch(e){summary.textContent='Notifications unavailable';console.warn(e);}}
 function close(){panel.hidden=true;bell.setAttribute('aria-expanded','false');}
 bell.addEventListener('click',()=>{const open=panel.hidden;panel.hidden=!open;bell.setAttribute('aria-expanded',String(open));if(open)load();});
 markAll.addEventListener('click',async()=>{try{await api('read_all',{});await load();}catch(e){console.warn(e);}});
 document.addEventListener('click',e=>{if(!panel.hidden && !panel.contains(e.target) && !bell.contains(e.target))close();});
 document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)load();});
 load();setInterval(()=>{if(!document.hidden)load();},30000);
})();
