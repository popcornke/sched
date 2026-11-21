(function () {
  'use strict';
  const modal = document.getElementById('officeExamHistoryModal');
  const frame = document.getElementById('officeExamHistoryFrame');
  const printButton = document.getElementById('officePrintExamHistory');
  if (!modal || !frame) return;
  let previousFocus = null;
  function closeModal() {
    if (modal.hidden) return;
    modal.hidden = true;
    document.body.classList.remove('office-modal-open');
    frame.removeAttribute('src');
    if (previousFocus) previousFocus.focus();
  }
  function openModal(id, trigger) {
    if (!/^\d+$/.test(String(id))) return;
    previousFocus = trigger || document.activeElement;
    modal.hidden = false;
    document.body.classList.add('office-modal-open');
    frame.src = 'exam-requests.php?id=' + encodeURIComponent(id) + '&history_embed=1';
    modal.querySelector('.office-modal-close').focus();
  }
  document.querySelectorAll('[data-exam-history-view]').forEach(link => {
    link.addEventListener('click', event => {
      event.preventDefault();
      openModal(link.dataset.examHistoryView, link);
    });
  });
  modal.querySelectorAll('[data-close-exam-history]').forEach(el => el.addEventListener('click', closeModal));
  document.addEventListener('keydown', event => {
    if (modal.hidden) return;
    if (event.key === 'Escape') closeModal();
    if (event.key === 'Tab') {
      const focusable = [...modal.querySelectorAll('button:not([disabled])')];
      const first = focusable[0], last = focusable[focusable.length-1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  printButton?.addEventListener('click', () => {
    try { frame.contentWindow.focus(); frame.contentWindow.print(); }
    catch (err) { window.aoNotice?.('Printing unavailable', 'Unable to open print preview. Please try again.'); }
  });
})();
