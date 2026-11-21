function printReport(){
 const content=document.getElementById('report-document'); if(!content)return;
 const printable=content.cloneNode(true);
 printable.querySelectorAll('img').forEach(img=>{img.src=new URL(img.getAttribute('src'),location.href).href;});
 const wnd=window.open('','_blank','width=1200,height=820');
 if(!wnd){window.aoNotice?.('PDF export unavailable','Allow pop-ups, then use Export PDF again.');return;}
 const styles=`<style>
 @page{size:A4 landscape;margin:11mm}
 *{box-sizing:border-box}body{font-family:Arial,Helvetica,sans-serif;color:#17243b;background:#fff;margin:0;font-size:11px}
 .report-doc-heading{text-align:center;padding:9px 0 13px;border-bottom:2px solid #1e3971;margin-bottom:12px}
 .report-doc-heading .eyebrow{color:#203e88;font-size:11px;font-weight:700;letter-spacing:.09em}
 .report-doc-heading h2{font-size:18px;margin:5px 0}.report-doc-meta{display:flex;justify-content:center;flex-wrap:wrap;gap:7px 14px;color:#465b78;font-size:10px}
 .report-pills{display:flex;gap:12px;margin:12px 0}.report-pill{border:1px solid #cfd8e6;padding:8px 12px;flex:1}.report-pill small{display:block;color:#5f6f86}.report-pill b{font-size:15px;color:#182e54}
 .report-document-caption,.report-current-note{font-size:10px;color:#53647a;margin:10px 0}.report-export-summary{margin:10px 0}.report-export-summary h3{font-size:11px;color:#203e88;margin:4px 0}.report-export-summary table{width:100%;border-collapse:collapse;font-size:9px}.report-export-summary th,.report-export-summary td{border:1px solid #b7c3d4;padding:5px 7px;text-align:left}.report-export-summary th{background:#edf2f9}
 .acad-document-list{display:block}.acad-document{break-before:page;break-inside:avoid-page;padding:6px 0}
 .acad-document:first-child{break-before:auto}.acad-document-school{text-align:center;border-bottom:1.5px solid #1e3971;padding:8px 0 12px;display:grid;justify-items:center;gap:4px}
 .acad-document-school img{width:40px;height:46px;object-fit:contain}.acad-document-school strong{font-size:12px}.acad-document-school b{font-size:12px}
 .acad-document-details{display:grid;grid-template-columns:repeat(2,1fr);gap:6px;padding:12px 0;font-size:10px}
 .acad-table-scroll{overflow:visible}.acad-document-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:9px}
 .acad-document-table th,.acad-document-table td{border:1px solid #8996a9;padding:5px 4px;vertical-align:top;overflow-wrap:anywhere}
 .acad-document-table th{background:#ecf1f8;text-align:center;font-weight:bold}.acad-document-table thead{display:table-header-group}
 .acad-document-table tr{break-inside:avoid}.acad-document-table th:nth-child(3){width:22%}
 </style>`;
 wnd.document.open();wnd.document.write('<!doctype html><html><head><meta charset="utf-8"><title>BCP Academic Schedule Report</title>'+styles+'</head><body>'+printable.innerHTML+'</body></html>');wnd.document.close();
 const images=[...wnd.document.images];Promise.all(images.map(img=>img.complete?Promise.resolve():new Promise(resolve=>{img.onload=resolve;img.onerror=resolve;}))).then(()=>{wnd.focus();wnd.print();});
}
