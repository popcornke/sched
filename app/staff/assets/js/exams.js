/* STAFF examination timetable: read-only data view and print controls. */
'use strict';
const root='../modules/04-exam-timetable-generator/';
const $=s=>document.querySelector(s);
const period=$('#period'),program=$('#program'),report=$('#report'),status=$('#status');
const val=v=>String(v??'').trim();
const tones=['tone-blue','tone-green','tone-yellow','tone-purple','tone-peach','tone-teal'];
const node=(tag,cls,txt)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(txt!==undefined)e.textContent=val(txt);return e;};
function fill(el,data,value,label){el.replaceChildren(new Option('Select…',''));for(const item of data)el.add(new Option(label(item),value(item)));}
async function request(url){const r=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});let d;try{d=await r.json();}catch{throw Error('Server returned an invalid response.');}if(!r.ok||!d.success)throw Error(d.message||'Unable to load examinations.');return d;}
function category(a){const c=val(a.section_code).toUpperCase();const t=val(a.section_type).toUpperCase();for(const x of ['IM','NA','IS'])if(new RegExp('(?:^|[^A-Z])'+x+'(?:$|[^A-Z0-9])').test(c+' '+t))return x;return t||'OTHER';}
function color(cat){if(cat==='IM')return 'tone-blue';if(cat==='NA')return 'tone-green';if(cat==='IS')return 'tone-yellow';const n=[...cat].reduce((a,c)=>a+c.charCodeAt(0),0);return tones[n%tones.length];}
function naturalSort(a,b){return a.localeCompare(b,undefined,{numeric:true,sensitivity:'base'});}
function yearNumber(a){const n=Number(a.year_level);return Number.isInteger(n)&&n>=1&&n<=4?n:99;}
function displayDate(s){if(!s)return 'Date not specified';const d=new Date(s+'T12:00:00');return Number.isNaN(d.getTime())?s:d.toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'});}
function slotKey(a){return val(a.start_time)+'|'+val(a.end_time);}
function slotLabel(key){const [a,b]=key.split('|');return `${a}–${b}`;}
function sortSlots(a,b){return a.localeCompare(b);}
function shortCode(a){return val(a.subject_code)||'—';}
function uniqueKeys(items){return [...new Set(items.map(slotKey))].sort(sortSlots);}
function ordinal(n){const v=['1ST','2ND','3RD'];return v[n-1]||`${n}TH`;}
function cellHitsText(hits){
  const codes=[...new Set(hits.map(a=>shortCode(a)).filter(Boolean))].join(' / ')||'—';
  const proctors=[...new Set(hits.map(a=>val(a.proctor_name)).filter(Boolean))].join(', ');
  return {codes,proctors};
}
function makeCompactSheet(d,rows,band,bandIndex,pageIndex,totalPages,allSlots){
 const sheet=node('article','compact-sheet');
 const meta=node('div','sheet-meta');
 const top=node('div','shift-box');
 top.append(node('strong','',`${ordinal(bandIndex+1)} SHIFT`),node('small','',band.length?`${band[0].split('|')[0]} – ${band[band.length-1].split('|')[1]}`:'No slots'));
 meta.append(top,node('div','meta-date',`EXAM DATES: ${(d.exam_dates||[]).map(displayDate).join(' · ')}`));sheet.append(meta);
 const scroll=node('div','matrix-scroll');const table=node('table','compact-matrix');
 const colg=node('colgroup');for(const name of ['cluster','roomcol','sectioncol']){const c=node('col',name);colg.append(c);}for(let i=0;i<9;i++)colg.append(node('col'));table.append(colg);
 const thead=node('thead');const g=node('tr','top-head');
 for(const n of ['CLUSTER','ROOM','COURSE / SECTION']){const th=node('th','lefthead',n);th.rowSpan=2;g.append(th);} 
 for(let day=1;day<=3;day++){const th=node('th','day-head day-'+day,`DAY ${day}`);th.colSpan=3;g.append(th);}thead.append(g);
 const tr=node('tr','times');for(let day=1;day<=3;day++){for(let i=0;i<3;i++)tr.append(node('th','',band[i]?slotLabel(band[i]):'—'));}thead.append(tr);table.append(thead);
 const tbody=node('tbody');
 for(let rowIndex=0; rowIndex<rows.length; rowIndex++){
  const entry=rows[rowIndex];
  const r=node('tr');
  if(entry.majorOnly){
    r.append(node('th','major-unclustered','MAJOR'));
  } else if(entry.clusterStart){
    const th=node('th','clustername',`CLUSTER ${entry.clusterNumber}`);
    th.rowSpan=entry.clusterSpan;
    r.append(th);
  }
  r.append(node('td','roomname',entry.room));
  const info=node('td','sectioncell');
  info.append(node('span','sectioncode',entry.majorOnly?'4TH YEAR MAJOR':entry.section));
  r.append(info);
  for(let day=1;day<=3;day++){
    for(let i=0;i<3;i++){
      const cell=node('td','subject-slot'); const wanted=band[i];
      const hits=wanted?entry.items.filter(a=>Number(a.exam_day)===day&&slotKey(a)===wanted&&!isStandaloneDay3Major(a)):[];
      const majors=wanted&&day===3?(entry.majorItems||[]).filter(a=>slotKey(a)===wanted):[];
      if(!wanted){cell.classList.add('unused'); cell.textContent='';}
      else if(!hits.length){cell.classList.add('empty'); cell.textContent='—';}
      else{
        const cat=category(hits[0]); cell.classList.add(color(cat));
        const text=cellHitsText(hits);
        cell.append(node('span','code',text.codes));
        if(text.proctors) cell.append(node('span','proctor',text.proctors));
        cell.title=hits.map(a=>[a.subject_title, a.section_code, a.proctor_name].filter(Boolean).join(' · ')).join('\n');
      }
      if(majors.length){
        cell.classList.remove('empty','unused');
        if(!hits.length)cell.textContent='';
        for(const a of majors){
          const item=node('div','embedded-major '+color(category(a)));
          item.append(node('span','major-section',val(a.section_code)||'—'),node('span','major-subject',shortCode(a)),node('span','major-proctor',val(a.proctor_name)||'—'));
          item.title=[a.section_code,a.subject_code,a.proctor_name].filter(Boolean).join(' · ');
          cell.append(item);
        }
      }
      r.append(cell);
    }
  }
  tbody.append(r);
 }
 table.append(tbody); scroll.append(table); sheet.append(scroll);
 sheet.append(node('div','page-meta',`Page ${pageIndex+1} of ${totalPages} · ${bandIndex+1} of ${Math.ceil(allSlots.length/3)} time groups · Saved exams (read-only)`));
 return sheet;
}
// Standalone 4th-year MAJOR subjects are embedded in Day 3 cells by SAVED room/time.
// The ITE4/CAP101/PRAC101 trio remains in the regular section rows.
const clusteredFourthYear=new Set(['ITE4','CAP101','PRAC101']);
function isStandaloneDay3Major(a){
 return yearNumber(a)===4 && val(a.section_type).toUpperCase()==='MAJOR'
   && Number(a.exam_day)===3 && !clusteredFourthYear.has(val(a.subject_code).toUpperCase());
}
function render(d){
 $('#heading').textContent=`${val(d.program)} · Examination Timetable`;
 $('#subtitle').textContent=`A.Y. ${d.period.academic_year} · Semester ${d.period.semester} · ${d.returned_exams} saved exam entries`;
 const sheets=$('#sheets'),legend=$('#legend'); sheets.replaceChildren(); legend.replaceChildren();
 const assignments=Array.isArray(d.assignments)?d.assignments:[];
 const categories=[...new Set(assignments.map(category))].sort(naturalSort);
 for(const cat of categories){const item=node('span'); item.append(node('i','swatch '+color(cat)),node('span','',cat==='OTHER'?'Other':cat)); legend.append(item);} 
 const keyMap=new Map();
 for(const a of assignments){
   const room=val(a.room_name)||'Unassigned';
   const section=val(a.section_code)||'Unknown section';
   const key=JSON.stringify([room,section]);
   if(!keyMap.has(key)) keyMap.set(key,{room,section,year:yearNumber(a),items:[]});
   else keyMap.get(key).year=Math.min(keyMap.get(key).year,yearNumber(a));
   keyMap.get(key).items.push(a);
 }
 // Always display 1st year, 2nd year, 3rd year, then 4th year.
 // Within each year, keep sections and rooms in natural alphanumeric order.
 const rows=[...keyMap.values()].sort((a,b)=>a.year-b.year||naturalSort(a.section,b.section)||naturalSort(a.room,b.room));
 const slots=uniqueKeys(assignments); if(!slots.length||!rows.length) throw Error('No saved examination entries to display.');
 const bands=[]; for(let i=0;i<slots.length;i+=3) bands.push(slots.slice(i,i+3));
 const majorDay3=assignments.filter(isStandaloneDay3Major);
 const pages=[];
 const maxRows=24; // Multiple of three keeps each displayed cluster together.
 for(let bi=0; bi<bands.length; bi++){
   const shiftSlots=new Set(bands[bi]);
   const activeRows=rows
     .map(entry=>({...entry,items:entry.items.filter(a=>shiftSlots.has(slotKey(a))&&!isStandaloneDay3Major(a)),majorItems:[]}))
     .filter(entry=>entry.items.length>0);
   // Use the original saved room and time; attach to a row of the SAME room.
   const majors=majorDay3.filter(a=>shiftSlots.has(slotKey(a)));
   const extraRooms=new Map();
   for(const a of majors){
     const room=val(a.room_name)||'Unassigned';
     const matching=activeRows.find(row=>row.room===room);
     if(matching){matching.majorItems.push(a);continue;}
     if(!extraRooms.has(room))extraRooms.set(room,{room,section:'',year:4,items:[],majorItems:[],majorOnly:true});
     extraRooms.get(room).majorItems.push(a);
   }
   // Assign a new cluster whenever the year level changes, even if the
   // previous cluster has fewer than 3 sections. Only regular rows count.
   let clusterNumber=0;
   const clusters=[];
   for(const entry of activeRows){
     const previous=clusters[clusters.length-1];
     if(!previous || previous.year!==entry.year || previous.rows.length===3){
       clusters.push({year:entry.year,rows:[]});
     }
     clusters[clusters.length-1].rows.push(entry);
   }
   for(const cluster of clusters){
     clusterNumber++;
     cluster.rows.forEach((entry,index)=>{
       entry.clusterNumber=clusterNumber;
       entry.clusterStart=index===0;
       entry.clusterSpan=cluster.rows.length;
     });
   }
   // Keep cluster rowspans intact across print pages.
   let chunk=[];
   for(const cluster of clusters){
     if(chunk.length && chunk.length+cluster.rows.length>maxRows){
       pages.push({band:bands[bi],bandIndex:bi,rows:chunk});
       chunk=[];
     }
     chunk.push(...cluster.rows);
   }
   if(chunk.length) pages.push({band:bands[bi],bandIndex:bi,rows:chunk});
   // A room without a normal section in this shift stays in the SAME shift matrix,
   // but is not assigned an artificial CLUSTER number.
   const extras=[...extraRooms.values()].sort((a,b)=>naturalSort(a.room,b.room));
   if(extras.length){
     const last=pages[pages.length-1];
     if(last && last.bandIndex===bi && last.rows.length+extras.length<=maxRows)last.rows.push(...extras);
     else for(let i=0;i<extras.length;i+=maxRows)
       pages.push({band:bands[bi],bandIndex:bi,rows:extras.slice(i,i+maxRows)});
   }
 }
 const total=pages.length;
 for(let page=0;page<total;page++){
   const p=pages[page];
   sheets.append(makeCompactSheet(d,p.rows,p.band,p.bandIndex,page,total,slots));
 }
 $('#group-note').textContent='Each cluster contains up to 3 sections from the same year level only. Standalone 4th-year Day 3 MAJOR exams remain unclustered in their saved shift and room.';
 report.hidden=false;
}
async function catalog(){try{const d=await request(root+'exam-preview.php');fill(period,d.periods,x=>x.academic_period_id,x=>`${x.academic_year} · Semester ${x.semester}`);fill(program,d.programs,x=>x.program_code,x=>`${x.program_code} · ${x.program_name}`);status.textContent='Choose the academic period and program.';}catch(e){status.textContent=e.message;}}
async function load(){report.hidden=true;if(!period.value||!program.value){status.textContent='Please choose an academic period and a program.';return;}const b=$('#load');b.disabled=true;status.textContent='Loading saved examination matrix…';try{const d=await request(root+'exam-saved.php?period_id='+encodeURIComponent(period.value)+'&program='+encodeURIComponent(program.value));if(!d.has_saved_exams){status.textContent='No saved ACTIVE DEMO exams found for the selection.';return;}render(d);status.textContent='Saved examination matrix loaded (read-only).';}catch(e){status.textContent=e.message;}finally{b.disabled=false;}}
$('#load').addEventListener('click',load);$('#print').addEventListener('click',()=>window.print());catalog();
const navToggle=$('#sidebarToggle');
navToggle.addEventListener('click',()=>{
 const workspace=document.querySelector('.workspace');
 workspace.classList.toggle('nav-closed');
 navToggle.setAttribute('aria-expanded',String(!workspace.classList.contains('nav-closed')));
});
if(window.matchMedia('(max-width:760px)').matches){document.querySelector('.workspace').classList.add('nav-closed');navToggle.setAttribute('aria-expanded','false');}
