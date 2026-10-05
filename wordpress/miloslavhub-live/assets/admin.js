(() => {
  'use strict';
  function toggleQuestionType() {
    const selected = document.querySelector('input[name="mhl_correct_index"]:checked');
    if (!selected) return;
    const quiz = selected.value !== '-1';
    document.querySelectorAll('.mhl-quiz-only').forEach(el => { el.style.display = quiz ? '' : 'none'; });
    document.querySelectorAll('.mhl-poll-only').forEach(el => { el.style.display = quiz ? 'none' : ''; });
    const type = document.getElementById('mhl-inferred-type');
    if (type) type.textContent = quiz ? 'Kvíz – se správnou odpovědí a body' : 'Anketa – bez správné odpovědi';
  }
  document.querySelectorAll('input[name="mhl_correct_index"]').forEach(el => el.addEventListener('change', toggleQuestionType));
  toggleQuestionType();

  document.querySelectorAll('.mhl-qr[data-qr]').forEach((box) => {
    const url = box.dataset.qr || ''; if (!url) return;
    const size = Number(box.dataset.size || 220);
    const img = document.createElement('img'); img.width = size; img.height = size; img.alt = 'QR kód pro hlasování'; img.loading = 'lazy';
    img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=' + (size*2) + 'x' + (size*2) + '&margin=12&data=' + encodeURIComponent(url); box.appendChild(img);
  });
  document.querySelectorAll('.mhl-copy[data-copy]').forEach((button) => button.addEventListener('click', async () => {
    const value = button.dataset.copy || '';
    try { await navigator.clipboard.writeText(value); const old=button.textContent; button.textContent='Zkopírováno'; setTimeout(()=>button.textContent=old,1200); }
    catch (_) { window.prompt('Zkopírujte adresu:', value); }
  }));


  function initQuestionPicker() {
    const picker = document.querySelector('[data-mhl-question-picker]');
    if (!picker) return;
    const selectedList = picker.querySelector('[data-mhl-selected-list]');
    const availableList = picker.querySelector('[data-mhl-available-list]');
    if (!selectedList || !availableList) return;

    let dragging = null;
    let dragSource = null;

    function items(list) {
      return Array.from(list.querySelectorAll('.mhl-question-item'));
    }
    function selectedItems() { return items(selectedList); }
    function availableItems() { return items(availableList); }

    function refresh() {
      selectedItems().forEach((item, index) => {
        item.classList.add('is-selected');
        const toggle = item.querySelector('.mhl-question-toggle');
        const order = item.querySelector('.mhl-order');
        const badge = item.querySelector('.mhl-order-badge');
        const handle = item.querySelector('.mhl-drag-handle');
        if (toggle) toggle.checked = true;
        if (order) order.value = String(index + 1);
        if (badge) { badge.textContent = String(index + 1); badge.removeAttribute('aria-hidden'); }
        if (handle) {
          handle.setAttribute('draggable', 'true');
          handle.removeAttribute('aria-hidden');
          handle.setAttribute('tabindex', '0');
          handle.setAttribute('aria-label', 'Přetáhnout otázku nebo změnit pořadí');
        }
      });
      availableItems().forEach((item) => {
        item.classList.remove('is-selected');
        const toggle = item.querySelector('.mhl-question-toggle');
        const order = item.querySelector('.mhl-order');
        const badge = item.querySelector('.mhl-order-badge');
        const handle = item.querySelector('.mhl-drag-handle');
        if (toggle) toggle.checked = false;
        if (order) order.value = '';
        if (badge) { badge.textContent = ''; badge.setAttribute('aria-hidden', 'true'); }
        if (handle) {
          handle.setAttribute('draggable', 'true');
          handle.removeAttribute('aria-hidden');
          handle.setAttribute('tabindex', '0');
          handle.setAttribute('aria-label', 'Přidat otázku přetažením');
        }
      });
      const selectedEmpty = selectedList.querySelector('.mhl-selected-empty');
      const availableEmpty = availableList.querySelector('.mhl-available-empty');
      if (selectedEmpty) selectedEmpty.hidden = selectedItems().length > 0;
      if (availableEmpty) availableEmpty.hidden = availableItems().length > 0;
    }

    function sortAvailable() {
      const empty = availableList.querySelector('.mhl-available-empty');
      availableItems()
        .sort((a, b) => (a.dataset.title || '').localeCompare(b.dataset.title || '', 'cs'))
        .forEach((item) => availableList.insertBefore(item, empty || null));
    }

    function addToSelected(item, before = null) {
      const empty = selectedList.querySelector('.mhl-selected-empty');
      selectedList.insertBefore(item, before || empty || null);
      refresh();
    }

    function returnToAvailable(item) {
      const empty = availableList.querySelector('.mhl-available-empty');
      availableList.insertBefore(item, empty || null);
      sortAvailable();
      refresh();
    }

    function moveUp(item) {
      const prev = item.previousElementSibling;
      if (prev && prev.classList.contains('mhl-question-item')) selectedList.insertBefore(item, prev);
      refresh();
    }

    function moveDown(item) {
      const next = item.nextElementSibling;
      if (next && next.classList.contains('mhl-question-item')) selectedList.insertBefore(next, item);
      refresh();
    }

    function selectedDropBefore(clientY, excludeItem) {
      const siblings = selectedItems().filter((item) => item !== excludeItem);
      for (const item of siblings) {
        const rect = item.getBoundingClientRect();
        if (clientY < rect.top + rect.height / 2) return item;
      }
      return null;
    }

    function clearDropTargets() {
      selectedList.classList.remove('is-drop-target');
      availableList.classList.remove('is-drop-target');
    }

    picker.addEventListener('change', (event) => {
      const toggle = event.target.closest('.mhl-question-toggle');
      if (!toggle) return;
      const item = toggle.closest('.mhl-question-item');
      if (!item) return;
      if (toggle.checked) addToSelected(item);
      else returnToAvailable(item);
    });

    picker.addEventListener('click', (event) => {
      const item = event.target.closest('.mhl-question-item');
      if (!item) return;
      if (event.target.closest('.mhl-add-question')) {
        event.preventDefault();
        addToSelected(item);
        return;
      }
      if (event.target.closest('.mhl-remove-question')) {
        event.preventDefault();
        returnToAvailable(item);
        return;
      }
      if (!item.classList.contains('is-selected')) return;
      if (event.target.closest('.mhl-move-up')) { event.preventDefault(); moveUp(item); }
      if (event.target.closest('.mhl-move-down')) { event.preventDefault(); moveDown(item); }
    });

    picker.addEventListener('keydown', (event) => {
      const handle = event.target.closest('.mhl-drag-handle');
      if (!handle) return;
      const item = handle.closest('.mhl-question-item');
      if (!item) return;
      if ((event.altKey || event.ctrlKey) && event.key === 'ArrowUp' && item.classList.contains('is-selected')) {
        event.preventDefault(); moveUp(item);
      }
      if ((event.altKey || event.ctrlKey) && event.key === 'ArrowDown' && item.classList.contains('is-selected')) {
        event.preventDefault(); moveDown(item);
      }
      if ((event.altKey || event.ctrlKey) && event.key === 'ArrowRight' && !item.classList.contains('is-selected')) {
        event.preventDefault(); addToSelected(item);
      }
      if ((event.altKey || event.ctrlKey) && event.key === 'ArrowLeft' && item.classList.contains('is-selected')) {
        event.preventDefault(); returnToAvailable(item);
      }
    });

    picker.addEventListener('dragstart', (event) => {
      const handle = event.target.closest('.mhl-drag-handle');
      const item = handle ? handle.closest('.mhl-question-item') : null;
      if (!item) { event.preventDefault(); return; }
      dragging = item;
      dragSource = item.parentElement;
      item.classList.add('is-dragging');
      picker.classList.add('is-dragging-question');
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', item.dataset.questionId || '');
      }
    });

    [selectedList, availableList].forEach((list) => {
      list.addEventListener('dragenter', (event) => {
        if (!dragging) return;
        event.preventDefault();
        clearDropTargets();
        list.classList.add('is-drop-target');
      });
      list.addEventListener('dragover', (event) => {
        if (!dragging) return;
        event.preventDefault();
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
      });
    });

    selectedList.addEventListener('drop', (event) => {
      if (!dragging) return;
      event.preventDefault();
      const before = selectedDropBefore(event.clientY, dragging);
      addToSelected(dragging, before);
      clearDropTargets();
    });

    availableList.addEventListener('drop', (event) => {
      if (!dragging) return;
      event.preventDefault();
      returnToAvailable(dragging);
      clearDropTargets();
    });

    picker.addEventListener('dragend', (event) => {
      if (dragging) {
        dragging.classList.remove('is-dragging');
        if (event.dataTransfer && event.dataTransfer.dropEffect === 'none' && dragSource && dragging.parentElement !== dragSource) {
          const empty = dragSource.querySelector('.mhl-selected-empty, .mhl-available-empty');
          dragSource.insertBefore(dragging, empty || null);
          if (dragSource === availableList) sortAvailable();
        }
      }
      dragging = null;
      dragSource = null;
      picker.classList.remove('is-dragging-question');
      clearDropTargets();
      refresh();
    });

    refresh();
  }

  function initTeachers() {
    const wrap=document.querySelector('[data-mhl-teachers]');
    if(!wrap)return;
    const list=wrap.querySelector('[data-mhl-teachers-list]');
    const tpl=wrap.querySelector('[data-mhl-teacher-template]');
    const add=wrap.querySelector('[data-mhl-add-teacher]');
    if(!list||!tpl||!add)return;
    function renumber(){
      [...list.querySelectorAll('[data-mhl-teacher-row]')].forEach((row,i)=>{
        row.querySelectorAll('[data-field]').forEach((el)=>{if(el.dataset.field)el.name=`mhl_teachers[${i}][${el.dataset.field}]`;});
        row.querySelectorAll('input[name^="mhl_teachers["]').forEach((el)=>{
          const m=el.name.match(/\]\[([^\]]+)\]$/); if(m)el.name=`mhl_teachers[${i}][${m[1]}]`;
        });
      });
    }
    add.addEventListener('click',()=>{
      list.appendChild(tpl.content.cloneNode(true));renumber();
      const rows=list.querySelectorAll('[data-mhl-teacher-row]');rows[rows.length-1]?.querySelector('input[type="text"]')?.focus();
    });
    list.addEventListener('click',(e)=>{
      const btn=e.target.closest('.mhl-remove-teacher');if(!btn)return;e.preventDefault();
      btn.closest('[data-mhl-teacher-row]')?.remove();renumber();
    });
    renumber();
  }
  initTeachers();

  initQuestionPicker();
})();
