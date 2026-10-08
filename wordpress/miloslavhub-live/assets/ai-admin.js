{
const tr=globalThis.MHLUI?.text||(v=>v),ui=globalThis.MHLUI?.html||((p,...v)=>p.reduce((o,s,i)=>o+s+(i<v.length?v[i]:''),''));
(() => {
  'use strict';
  const panel = document.querySelector('#mhl-ai-panel');
  if (!panel) return;
  const el = name => panel.querySelector(ui`[data-ai-${name}]`);
  const title = document.querySelector('#title');
  const options = () => [...document.querySelectorAll('input[name="mhl_options[]"]')];
  const current = () => ({title: title.value, options: options().map(input => input.value)});
  const signature = value => JSON.stringify(value);
  const display = value => value.title + '\n\n' + value.options.map((text, i) => ui`${String.fromCharCode(65 + i)}: ${text}`).join('\n');
  let enabled = panel.dataset.enabled === '1', busy = false, source = null, suggestion = null, controller = null;
  let requestId = 0;
  const status = message => { el('status').textContent = message; };
  const update = () => {
    el('controls').disabled = !enabled || busy;
    el('toggle').disabled = busy;
    el('toggle').textContent = enabled ? tr('Vypnout AI pro můj účet') : tr('Zapnout AI pro můj účet');
    el('apply').disabled = !enabled || busy;
    el('language-label').hidden = el('operation').value !== 'translate';
  };
  const clear = () => {
    source = null; suggestion = null;
    el('source-box').hidden = true; el('result-box').hidden = true;
  };
  const post = async (path, body, signal) => {
    const endpoint = new URL(panel.dataset.endpoint + path, window.location.href);
    if (globalThis.MHLUI?.language === 'en') endpoint.searchParams.set('ui_lang', 'en');
    const response = await fetch(endpoint.href, {
      method: 'POST', credentials: 'same-origin', signal,
      headers: {'Content-Type':'application/json', 'X-WP-Nonce':panel.dataset.nonce}, body: JSON.stringify(body)
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || tr('Akci se nepodařilo dokončit.'));
    return data;
  };
  el('preview').addEventListener('click', () => {
    clear();
    if (!title || !title.value.trim()) return status(tr('Nejprve vyplňte otázku.'));
    source = {content:current(), operation:el('operation').value, language:el('language').value};
    el('source').textContent = display(source.content);
    el('source-box').hidden = false;
    status(tr('Zkontrolujte obsah. Odešle se až dalším tlačítkem.'));
  });
  el('send').addEventListener('click', async () => {
    if (!source || busy || !enabled) return;
    if (signature(current()) !== signature(source.content)) { clear(); return status(tr('Otázka se změnila. Znovu zkontrolujte obsah k odeslání.')); }
    const pending = source, id = ++requestId;
    busy = true; update(); controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 20000);
    status(tr('Připravuji návrh…'));
    try {
      const data = await post('suggest', {question_id:Number(panel.dataset.questionId), operation:pending.operation, language:pending.language, ...pending.content}, controller.signal);
      if (id !== requestId) return;
      if (signature(current()) !== signature(pending.content)) { clear(); return status(tr('Během čekání jste otázku upravili. Návrh byl zahozen, vaše úpravy zůstaly zachované.')); }
      const value = data.suggestion;
      if (!value || typeof value.title !== 'string' || !Array.isArray(value.options) || value.options.length !== pending.content.options.length || !value.options.every(x => typeof x === 'string')) throw new Error(tr('Návrh nemá platný formát.'));
      suggestion = value;
      el('result').textContent = display(suggestion);
      el('result-box').hidden = false; el('source-box').hidden = true;
      status(tr('Zkontrolujte návrh. V editoru ani v uložené otázce se zatím nic nezměnilo.'));
    } catch (error) {
      if (id === requestId) status(error.name === 'AbortError' ? tr('Čekání vypršelo. Text zůstal zachován; před novým pokusem chvíli vyčkejte.') : error.message);
    } finally { clearTimeout(timer); busy = false; update(); }
  });
  el('apply').addEventListener('click', () => {
    if (!enabled || !source || !suggestion || busy) return;
    if (signature(current()) !== signature(source.content)) { clear(); return status(tr('Otázka se změnila. Návrh byl zahozen, vaše úpravy zůstaly zachované.')); }
    title.value = suggestion.title;
    options().forEach((input, i) => { input.value = suggestion.options[i]; input.dispatchEvent(new Event('input', {bubbles:true})); });
    title.dispatchEvent(new Event('input', {bubbles:true}));
    clear(); status(tr('Návrh je v editoru. Můžete jej upravit a potom otázku uložit běžným tlačítkem.'));
  });
  el('discard').addEventListener('click', () => { clear(); status(tr('Návrh byl zahozen.')); });
  el('operation').addEventListener('change', () => { clear(); update(); });
  el('language').addEventListener('change', clear);
  el('toggle').addEventListener('click', async () => {
    if (busy) return;
    busy = true; update();
    try {
      const data = await post('preference', {enabled:!enabled});
      enabled = data.enabled; clear(); status(enabled ? tr('AI je pro váš účet zapnutá.') : tr('AI je pro váš účet vypnutá.'));
    } catch (error) { status(error.message); }
    finally { busy = false; update(); }
  });
  update();
})();

}
