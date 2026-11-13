 'use strict';
const $ = selector => document.querySelector(selector);
const days=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
const el=(tag,cls='',content)=>{const n=document.createElement(tag);if(cls)n.className=cls;if(content!==undefined)n.textContent=String(content);return n;};
const period=$('#period'),department=$('#department'),status=$('#status'),report=$('#report');
const minutes=t=>{const p=String(t).split(':');return Number(p[0])*60+Number(p[1]);};
const clock=n=>{const h=Math.floor(n/60),m=n%60;return `${h%12||12}:${String(m).padStart(2,'0')}${h>=12?'PM':'AM'}`;};
const displayRange=(a,b)=>`${clock(a)} – ${clock(b)}`;
async function request(query){const response=await fetch(`room-schedules-data.php?${query}`,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});let data;try{data=await response.json();}catch{throw Error('Invalid server response.');}if(!response.ok||!data.success)throw Error(data.message||'Unable to load schedules.');return data;}
function showStatus(message,error=false){status.textContent=message;status.style.color=error?'#b43232':'#526780';}
function fill(select,items,getValue,getText){select.replaceChildren(new Option('Select…',''));for(const item of items)select.add(new Option(getText(item),getValue(item)));}
async function initialize(){try{const data=await request('mode=options');fill(period,data.periods,p=>p.academic_period_id,p=>`${p.academic_year} · Semester ${p.semester}`);fill(department,data.departments,p=>p.program_id,p=>`${p.program_code} — ${p.program_name} (${p.room_count} rooms)`);if(data.periods.length)period.value=String(data.periods[0].academic_period_id);if(!data.periods.length)showStatus('There are no ACTIVE class schedules yet.');else if(!data.departments.length)showStatus('No departments have registered rooms.');else showStatus('Choose a department to see the timetables for all of its rooms.');}catch(e){showStatus(e.message,true);}}
function makeMeetingCell(meetings,span){const cell=el('td','meeting');cell.colSpan=span;if(meetings.length>1)cell.classList.add('collision');if(meetings.length>1)cell.append(el('div','collision-message','⚠ Overlapping class records'));
 for(const m of meetings){const block=el('div','schedule-block');block.append(el('div','schedule-section',`${m.program_code} ${m.section_code}`),el('div','schedule-subject',`${m.subject_code} (${m.subject_title})`));cell.append(block);}return cell;}
// One column for each *complete* start-to-end class interval in this room.
// This avoids creating extra columns at every :30 boundary.
function buildTimetable(meetings){
 const table=el('table','room-matrix compact-matrix direct-range-matrix');
 table.setAttribute('aria-label','Weekly room timetable grouped by actual class start and end times');
 const rangeMap=new Map();
 for(const m of meetings){
  const start=minutes(m.start_time),end=minutes(m.end_time);
  if(!(end>start))continue;
  rangeMap.set(`${start}-${end}`,{start,end,key:`${start}-${end}`});
 }
 const ranges=[...rangeMap.values()].sort((a,b)=>a.start-b.start||a.end-b.end);
 if(!ranges.length)return null;
 table.style.setProperty('--slots',String(ranges.length));
 const cols=el('colgroup'),dayCol=el('col');dayCol.className='day-width';cols.append(dayCol);
 for(const range of ranges)cols.append(el('col'));
 table.append(cols);
 const thead=el('thead'),head=el('tr');head.append(el('th','day-col','DAY / TIME'));
 for(const range of ranges){const th=el('th','time-heading',displayRange(range.start,range.end));th.scope='col';head.append(th);}
 thead.append(head);table.append(thead);
 const tbody=el('tbody');
 for(const day of days){
  const row=el('tr'),label=el('th','day-col',day);label.scope='row';row.append(label);
  const daily=meetings.filter(m=>m.day_of_week===day);
  for(const range of ranges){
   const matching=daily.filter(m=>minutes(m.start_time)===range.start&&minutes(m.end_time)===range.end);
   if(matching.length){
    const cell=makeMeetingCell(matching,1);
    for(const block of cell.querySelectorAll('.schedule-block'))block.append(el('div','schedule-time',displayRange(range.start,range.end)));
    row.append(cell);
   }else row.append(el('td','empty','—'));
  }
  tbody.append(row);
 }
 table.append(tbody);
 return table;
}
function buildRoomSchedule(meetings){
 const matrix=buildTimetable(meetings);
 if(!matrix)return el('p','empty-room','No ACTIVE face-to-face classes scheduled for this room.');
 const scroller=el('div','room-table-scroll');scroller.append(matrix);return scroller;
}

function render(data){report.hidden=false;$('#heading').textContent=`${data.department.program_code} · Department Room Schedules`;$('#subtitle').textContent=`${data.department.program_name} · ${data.period.academic_year} · Semester ${data.period.semester}`;
 const valid=data.meetings.filter(m=>days.includes(m.day_of_week)&&minutes(m.end_time)>minutes(m.start_time));const byRoom=new Map();for(const m of valid){const key=String(m.room_id);if(!byRoom.has(key))byRoom.set(key,[]);byRoom.get(key).push(m);}
 const eligibleRooms=data.rooms.filter(r=>String(r.status).trim().toUpperCase()==='AVAILABLE'&&(byRoom.get(String(r.room_id))||[]).length>0);
 $('#summary').textContent=`${eligibleRooms.length} available rooms with schedules · ${valid.length} scheduled face-to-face meetings · Monday to Saturday`;
 const container=$('#roomReports');container.replaceChildren();
 if(!eligibleRooms.length){container.append(el('p','empty-department','No available rooms with ACTIVE face-to-face schedules for this department and period.'));return;}
 for(let i=0;i<eligibleRooms.length;i++){const room=eligibleRooms[i];const meetings=byRoom.get(String(room.room_id))||[];
  // A pair is a print sheet. On screen, display:contents keeps the original room-card layout.
  if(i%2===0){const sheet=el('div','room-print-sheet');container.append(sheet);}
  const item=el('section','department-room');const hdr=el('div','department-room-heading');const left=el('div');left.append(el('h3','',room.room_name),el('p','',`${room.building||'Building not specified'} · ${meetings.length} class meeting${meetings.length===1?'':'s'}`));hdr.append(left,el('span',`room-status ${String(room.status).toLowerCase()}`,room.status));item.append(hdr);
 item.append(buildRoomSchedule(meetings));container.lastElementChild.append(item);}
}
$('#load').addEventListener('click',async()=>{if(!period.value||!department.value){showStatus('Choose an academic period and department.',true);return;}const button=$('#load');button.disabled=true;report.hidden=true;showStatus('Loading department room schedules…');try{const data=await request(`mode=schedule&period_id=${encodeURIComponent(period.value)}&program_id=${encodeURIComponent(department.value)}`);render(data);showStatus('Department timetables loaded from ACTIVE saved class schedules.');}catch(e){showStatus(e.message,true);}finally{button.disabled=false;}});
$('#print').addEventListener('click',()=>window.print());
const toggle=$('#sidebarToggle'),workspace=$('.workspace');toggle.addEventListener('click',()=>{workspace.classList.toggle('nav-closed');toggle.setAttribute('aria-expanded',String(!workspace.classList.contains('nav-closed')));});if(window.matchMedia('(max-width:760px)').matches){workspace.classList.add('nav-closed');toggle.setAttribute('aria-expanded','false');}
initialize();
