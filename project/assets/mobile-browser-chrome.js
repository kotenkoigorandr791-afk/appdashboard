(function(){
'use strict';
if(window.__mobileBrowserChromeInit)return;
window.__mobileBrowserChromeInit=1;
var MOBILE_MQ=window.matchMedia('(max-width:768px),(pointer:coarse)');
function isTouchMobile(){return MOBILE_MQ.matches&&('ontouchstart'in window||navigator.maxTouchPoints>0);}
function applyViewport(){
var vv=window.visualViewport;
if(!vv)return;
var root=document.documentElement;
var isTopLevel;
try{isTopLevel=window.top===window.self;}catch(e){isTopLevel=false;}
root.classList.toggle('mobile-browser-chrome-top-level',isTopLevel);
root.classList.toggle('mobile-browser-chrome-embedded',!isTopLevel);
root.classList.add('mobile-browser-chrome-active');
root.style.setProperty('--visual-viewport-height',vv.height+'px');
root.style.setProperty('--visual-viewport-offset-top',(vv.offsetTop||0)+'px');
root.style.setProperty('--visual-viewport-offset-left',(vv.offsetLeft||0)+'px');
root.style.setProperty('--visual-viewport-width',vv.width+'px');
document.querySelectorAll('.mobile-vv-fill,[data-mobile-vv-fill]').forEach(function(el){
var topInset=0;
var leftInset=0;
var content=el.closest('#content.has-app-rail');
if(content){
var rail=content.querySelector('#app-icon-rail');
var railTop=content.classList.contains('rail-top-on-mobile')&&window.matchMedia('(max-width:768px)').matches;
if(rail){
if(railTop)topInset=rail.getBoundingClientRect().height||parseFloat(getComputedStyle(content).getPropertyValue('--rail-h'))||0;
else leftInset=rail.getBoundingClientRect().width||parseFloat(getComputedStyle(content).getPropertyValue('--rail-w'))||0;
}
}
el.style.setProperty('top',((vv.offsetTop||0)+topInset)+'px','important');
el.style.setProperty('left',((vv.offsetLeft||0)+leftInset)+'px','important');
el.style.setProperty('width',Math.max(0,vv.width-leftInset)+'px','important');
el.style.setProperty('height',Math.max(0,vv.height-topInset)+'px','important');
el.style.setProperty('max-height',Math.max(0,vv.height-topInset)+'px','important');
});
}
function boot(){
if(!isTouchMobile())return;
applyViewport();
var vv=window.visualViewport;
if(vv){
vv.addEventListener('resize',applyViewport);
vv.addEventListener('scroll',applyViewport);
}
window.addEventListener('resize',applyViewport);
window.addEventListener('orientationchange',function(){setTimeout(applyViewport,120);});
if(typeof MOBILE_MQ.addEventListener==='function')MOBILE_MQ.addEventListener('change',applyViewport);
else if(typeof MOBILE_MQ.addListener==='function')MOBILE_MQ.addListener(applyViewport);
}
window.MobileBrowserChrome={
boot:boot,
inject:function(doc){
if(!doc||doc.getElementById('mobile-browser-chrome-asset'))return;
var head=doc.head||doc.getElementsByTagName('head')[0];
if(!head)return;
var v='4';
var link=doc.createElement('link');
link.id='mobile-browser-chrome-asset';
link.rel='stylesheet';
link.href='/project/assets/mobile-browser-chrome.css?v='+v;
head.appendChild(link);
var script=doc.createElement('script');
script.src='/project/assets/mobile-browser-chrome.js?v='+v;
head.appendChild(script);
}
};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);
else boot();
})();
