
'use strict';
(function(){
  function initTicker(){
    document.querySelectorAll('.vip-jobs-ticker-track').forEach(function(track){
      if(track.dataset.molTickerReady==='1') return;
      var content=track.querySelector('.vip-jobs-ticker-content');
      if(!content) return;
      track.querySelectorAll('.vip-jobs-ticker-content[data-clone="1"]').forEach(function(n){n.remove();});
      var clone=content.cloneNode(true); clone.dataset.clone='1'; clone.setAttribute('aria-hidden','true');
      clone.querySelectorAll('a').forEach(function(a){a.tabIndex=-1;});
      track.appendChild(clone); track.dataset.molTickerReady='1';
    });
  }
  function enhanceChat(){
    document.querySelectorAll('[onclick*="MOLChatOpen"], [data-mol-chat-open]').forEach(function(el){
      if(el.dataset.chatEnhanced==='1') return;
      el.dataset.chatEnhanced='1'; el.setAttribute('aria-label', el.getAttribute('aria-label')||'Ouvrir le chat');
      el.setAttribute('title',el.getAttribute('title')||'Ouvrir la messagerie instantanée');
    });
  }
  function init(){initTicker();enhanceChat();}
  document.addEventListener('DOMContentLoaded',init,{once:true});
  document.addEventListener('turbo:load',init);
})();
