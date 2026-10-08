(() => {
  'use strict';
  const element=document.getElementById('mhl-ui-catalog');
  const config=element?JSON.parse(element.textContent):(globalThis.MHLUIConfig||{language:'cs',messages:{}});
  const messages=config.messages;
  const keys=Object.keys(messages).sort((a,b)=>b.length-a.length);
  const escapePattern=value=>value.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
  const pattern=keys.length?new RegExp('(?<![\\p{L}\\p{N}_])(?:'+keys.map(escapePattern).join('|')+')(?![\\p{L}\\p{N}_])','gu'):null;
  // Translate source literals only. Dynamic template values keep their original bytes and escaping.
  const text=source=>pattern?source.replace(pattern,key=>messages[key]):source;
  const html=(parts,...values)=>parts.reduce((out,part,index)=>out+text(part)+(index<values.length?values[index]:''),'');
  const number=(value,digits=0)=>new Intl.NumberFormat(config.language==='en'?'en-US':'cs-CZ',{minimumFractionDigits:digits,maximumFractionDigits:digits}).format(value);
  globalThis.MHLUI=Object.freeze({text,html,number,language:config.language});
})();
