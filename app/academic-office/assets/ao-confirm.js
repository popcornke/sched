(() => {
  'use strict';
  // Shared confirmation UI. No browser alert/confirm/prompt calls.
  const state = {form:null, submitter:null, previousFocus:null};
  const wrapper = document.createElement('div');
  wrapper.className = 'ao-confirm-overlay';
  wrapper.hidden = true;
  wrapper.innerHTML = `<div class="ao-confirm-backdrop" data-ao-cancel></div>
    <section class="ao-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="aoConfirmTitle" aria-describedby="aoConfirmMessage" tabindex="-1">
      <button type="button" class="ao-confirm-close" data-ao-cancel aria-label="Close dialog">×</button>
      <div class="ao-confirm-mark" aria-hidden="true">?</div>
      <div class="ao-confirm-copy"><h2 id="aoConfirmTitle"></h2><p id="aoConfirmMessage"></p><p id="aoConfirmError" class="ao-confirm-error" role="alert" hidden></p></div>
      <div class="ao-confirm-footer"><button type="button" class="ao-confirm-cancel" data-ao-cancel>Cancel</button><button type="button" class="ao-confirm-accept">Confirm</button></div>
    </section>`;
  document.body.append(wrapper);
  const dialog = wrapper.querySelector('.ao-confirm-dialog');
  const title = wrapper.querySelector('#aoConfirmTitle');
  const message = wrapper.querySelector('#aoConfirmMessage');
  const error = wrapper.querySelector('#aoConfirmError');
  const accept = wrapper.querySelector('.ao-confirm-accept');
  const cancel = wrapper.querySelector('.ao-confirm-cancel');
  const mark = wrapper.querySelector('.ao-confirm-mark');
  let noticeOnly = false;
  const labels = {
    APPROVED: ['Approve this schedule?', 'The system will validate and save the approved timetable. This action cannot be undone from this dialog.', 'Approve & Save', false],
    REJECTED: ['Reject this schedule?', 'The Admin must correct or regenerate the timetable. Please include a reason for rejection.', 'Reject Schedule', true],
    RETURNED: ['Return this schedule?', 'The Admin must generate and save a new timetable before resubmitting.', 'Return to Admin', true],
    archive: ['Archive this record?', 'Move the record to Archived History. You can restore it later.', 'Archive', false],
    restore: ['Restore this record?', 'Move this record back to Current History.', 'Restore', false],
    retrieve: ['Retrieve this record?', 'Recover the record from Deleted History.', 'Retrieve', false],
    delete: ['Delete this history record?', 'Move this record to Deleted History. You can retrieve it within 15 days.', 'Move to Deleted', true],
    permanent_delete: ['Permanently delete from history?', 'The record will no longer appear in Academic Office history. Saved schedules and audit evidence remain.', 'Delete Permanently', true],
    save: ['Save this report?', 'Save an approval report snapshot for the selected date range.', 'Save Report', false]
  };
  function close() {
    wrapper.hidden = true;
    document.body.classList.remove('ao-confirm-open');
    const target = state.previousFocus;
    state.form = null; state.submitter = null;
    if (target?.isConnected) target.focus();
  }
  function showError(text) {error.textContent = text; error.hidden = false;}
  function open(heading, detail, buttonLabel, danger, form, submitter, info=false) {
    state.form=form; state.submitter=submitter; state.previousFocus=document.activeElement;
    noticeOnly=info; error.hidden=true;error.textContent='';
    title.textContent=heading;message.textContent=detail;accept.textContent=info?'OK':buttonLabel;
    mark.textContent=info?'i':(danger?'!':'✓');
    wrapper.classList.toggle('ao-confirm-danger',Boolean(danger));
    wrapper.classList.toggle('ao-confirm-info',Boolean(info));
    cancel.hidden=info;
    wrapper.hidden=false; document.body.classList.add('ao-confirm-open');
    accept.focus();
  }
  window.aoNotice = (heading, detail) => open(heading,detail,'OK',false,null,null,true);
  document.addEventListener('submit', (event) => {
    const form=event.target;
    if (!(form instanceof HTMLFormElement) || !form.closest('body.acad-shell, body.acad-report-embedded')) return;
    if (form.dataset.aoConfirmed==='1') {delete form.dataset.aoConfirmed;return;}
    const btn=event.submitter || (form.querySelectorAll('button[name="decision"]').length === 1 ? form.querySelector('button[name="decision"]') : null);
    const decision=btn?.value || form.querySelector('input[name="decision"]')?.value || form.querySelector('input[name="action"]')?.value || form.querySelector('input[name="operation"]')?.value;
    const isHistory=form.hasAttribute('data-history-confirm');
    const isDecision=btn?.name==='decision';
    const isReport=form.classList.contains('report-form') && decision==='save';
    if (!isHistory && !isDecision && !isReport) return;
    event.preventDefault();
    const remarks=form.querySelector('[name="remarks"]');
    const rejection=decision==='REJECTED'||decision==='RETURNED';
    const key=isHistory ? form.querySelector('[name="action"]')?.value : decision;
    const preset=labels[key] || ['Confirm this action?', 'Review your changes before continuing.', 'Continue', false];
    const titleText=isDecision && key==='APPROVED' && form.action.includes('exam-action.php')?'Approve school-wide exams?':preset[0];
    const detail=isDecision && key==='APPROVED' && form.action.includes('exam-action.php')?'All programs will be revalidated and saved together only if every check passes.':preset[1];
    open(titleText, detail, preset[2], preset[3], form, btn);
    if (rejection && !remarks?.value.trim()) showError('Enter a rejection reason in the remarks field before continuing.');
  }, true);
  accept.addEventListener('click', () => {
    if (noticeOnly) {close();return;}
    const form=state.form, btn=state.submitter;
    if (!form) {close();return;}
    const decision=btn?.value;
    const remarks=form.querySelector('[name="remarks"]');
    if ((decision==='REJECTED'||decision==='RETURNED') && !remarks?.value.trim()) {showError('A rejection reason is required.');return;}
    wrapper.hidden=true;document.body.classList.remove('ao-confirm-open');
    form.dataset.aoConfirmed='1';
    if (btn && typeof form.requestSubmit==='function') form.requestSubmit(btn);
    else if (typeof form.requestSubmit==='function') form.requestSubmit();
    else {delete form.dataset.aoConfirmed;form.submit();}
    // If client validation prevents submission, allow another attempt.
    setTimeout(()=>{delete form.dataset.aoConfirmed;},0);
  });
  wrapper.querySelectorAll('[data-ao-cancel]').forEach(el=>el.addEventListener('click',close));
  document.addEventListener('keydown',ev=>{
    if(wrapper.hidden)return;
    if(ev.key==='Escape'){ev.preventDefault();close();}
    if(ev.key==='Tab'){
      const buttons=[...dialog.querySelectorAll('button:not([hidden])')];
      const first=buttons[0],last=buttons[buttons.length-1];
      if(ev.shiftKey&&document.activeElement===first){ev.preventDefault();last.focus();}
      else if(!ev.shiftKey&&document.activeElement===last){ev.preventDefault();first.focus();}
    }
  });
})();
