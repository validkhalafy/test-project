/* Behaviour test for assets/js/media-gallery.js in a DOM.
   Needs jsdom:  npm i --no-save jsdom   then   node tests/js/media-gallery.dom.test.js */
const {JSDOM}=require('jsdom'); const fs=require('fs');
const path=require('path'); const {execFileSync}=require('child_process');
const root=path.resolve(__dirname,'..','..');
const html=execFileSync('php',[path.join(__dirname,'gen-gallery-html.php')],{encoding:'utf8'}); const js=fs.readFileSync(path.join(root,'assets','js','media-gallery.js'),'utf8');
let pass=0,fail=0; const ok=(c,n)=>{c?pass++:fail++;console.log((c?'  ok    ':'  FAIL  ')+n);};
function boot(extra){
  const dom=new JSDOM('<!doctype html><html dir="rtl"><body>'+html+(extra||'')+'</body></html>',{runScripts:'outside-only',pretendToBeVisual:true,url:'https://example.test/'});
  const w=dom.window; w.matchMedia=w.matchMedia||(()=>({matches:false}));
  // jsdom has no layout; give the viewport a size so the scroller math is defined
  Object.defineProperty(w.HTMLElement.prototype,'clientWidth',{configurable:true,get(){return this.classList&&this.classList.contains('aetmg-viewport')?600:0;}});
  Object.defineProperty(w.HTMLElement.prototype,'scrollWidth',{configurable:true,get(){return this.classList&&this.classList.contains('aetmg-viewport')?1800:0;}});
  w.eval(js); return w;
}
const tick=()=>new Promise(r=>setTimeout(r,40));
(async()=>{
let w=boot(); await tick(); const d=w.document;
ok(!!w.ArtieyMediaGallery && d.querySelector('.aetmg').getAttribute('data-ready')==='1','script initialises the gallery and marks it ready');
w.ArtieyMediaGallery.init(); ok(d.querySelectorAll('.aetmg[data-ready]').length===1,'init() is idempotent');
ok(d.querySelectorAll('.aetmg-dots .aetmg-dot').length===3,'dots are built from the scroll width (3 pages)');
ok(d.querySelector('.aetmg-prev').disabled===false,'with loop on, the previous arrow stays enabled at the start (it wraps to the end)');
// open image
const opens=d.querySelectorAll('.aetmg-open[data-index]'); opens[0].click();
let lb=d.querySelector('.aetmg-lb');
ok(lb&&!lb.hidden&&lb.getAttribute('role')==='dialog'&&lb.getAttribute('aria-modal')==='true','clicking a photo opens an accessible modal dialog');
ok(lb.querySelector('.aetmg-lb-stage img').getAttribute('src')==='https://e.test/a.jpg','the full image is shown');
ok(d.documentElement.classList.contains('aetmg-lb-open'),'page scroll is locked while open');
ok(d.activeElement===lb.querySelector('.aetmg-lb-close'),'focus moves into the dialog');
// keyboard: RTL => ArrowLeft is "next"
const key=(k,extra)=>d.dispatchEvent(new w.KeyboardEvent('keydown',Object.assign({key:k,bubbles:true,cancelable:true},extra||{})));
key('ArrowLeft'); ok(lb.querySelector('.aetmg-lb-stage iframe')!==null,'RTL: ArrowLeft goes to the next item (the YouTube video)');
const fr=lb.querySelector('.aetmg-lb-stage iframe');
ok(/^https:\/\/www\.youtube-nocookie\.com\/embed\/dQw4w9WgXcQ\?.*autoplay=1/.test(fr.src),'video loads only now, from the privacy-enhanced host, with autoplay after the click');
ok(fr.getAttribute('referrerpolicy')==='strict-origin-when-cross-origin'&&fr.hasAttribute('allowfullscreen'),'iframe has referrer policy and fullscreen');
key('ArrowLeft'); const vid=lb.querySelector('.aetmg-lb-stage video');
ok(vid&&vid.querySelector('source').getAttribute('src')==='https://example.test/f.mp4'&&vid.hasAttribute('controls')&&vid.hasAttribute('playsinline'),'a self-hosted file plays in a <video> with controls');
key('ArrowRight'); ok(!!lb.querySelector('.aetmg-lb-stage iframe'),'RTL: ArrowRight goes back');
// focus trap
const btns=[...lb.querySelectorAll('button')].filter(b=>b.style.display!=='none'); const last=lb.querySelector('.aetmg-lb-stage iframe'); 
lb.querySelector('.aetmg-lb-close').focus(); key('Tab',{shiftKey:true}); ok(lb.contains(d.activeElement),'Shift+Tab from the first control stays inside the dialog');
// close with Escape: playback removed, focus restored
key('Escape'); ok(lb.hidden===true&&lb.querySelector('.aetmg-lb-stage').children.length===0,'Escape closes and removes the player (stops playback)');
ok(!d.documentElement.classList.contains('aetmg-lb-open')&&d.activeElement===opens[0],'scroll lock released and focus returns to the thumbnail');
// close by backdrop and button
opens[1].click(); lb.querySelector('.aetmg-lb-backdrop').dispatchEvent(new w.MouseEvent('click',{bubbles:true})); ok(lb.hidden,'clicking the backdrop closes');
opens[0].click(); lb.querySelector('.aetmg-lb-close').click(); ok(lb.hidden,'the close button closes');
// security: tampered data cannot create a foreign iframe / javascript: image / non-http poster
const evil=[{t:'video',p:'youtube',e:'https://evil.example/embed/x',a:'x',c:'x',w:'javascript:alert(1)'},{t:'image',s:'javascript:alert(1)',a:'x'},{t:'video',p:'file',f:'javascript:alert(1)',m:'video/mp4',o:'javascript:alert(2)'},{t:'video',p:'youtube',e:'https://www.youtube-nocookie.com.evil.example/embed/dQw4w9WgXcQ'}];
d.querySelector('.aetmg-data').textContent=JSON.stringify(evil);
w.document.querySelector('.aetmg').removeAttribute('data-ready'); w.ArtieyMediaGallery.init();
opens[0].click(); ok(lb.querySelector('.aetmg-lb-stage iframe')===null,'an embed URL outside the allow-list creates no iframe');
let linkOk=true; lb.querySelectorAll('a').forEach(a=>{ if(/^javascript:/i.test(a.href)) linkOk=false; }); ok(linkOk,'no javascript: link is created');
const nxt=()=>lb.querySelector('.aetmg-lb-next').click();
nxt(); ok(lb.querySelector('.aetmg-lb-stage img')===null,'a javascript: image URL creates no <img>');
nxt(); ok(lb.querySelector('.aetmg-lb-stage video')===null,'a javascript: video file creates no <video>');
nxt(); ok(lb.querySelector('.aetmg-lb-stage iframe')===null,'a look-alike host (youtube-nocookie.com.evil.example) is refused');
lb.querySelector('.aetmg-lb-close').click();
// scroller arrows
w=boot(); await tick(); const v=w.document.querySelector('.aetmg-viewport'); let to=[]; v.scrollTo=o=>{to.push(o.left); v.scrollLeft=o.left;};
Object.defineProperty(v,'scrollLeft',{configurable:true,writable:true,value:0});
w.document.querySelector('.aetmg-next').click(); ok(to.length===1&&to[0]<=0,'RTL: the next arrow scrolls toward negative scrollLeft ('+to[0]+')');
v.scrollLeft=-1200; w.document.querySelector('.aetmg-next').click(); ok(to[to.length-1]===0,'with loop on, "next" at the end wraps to the start');
// loop off: arrows disable at the ends
const noloop=html.replace('&quot;loop&quot;:true','&quot;loop&quot;:false'); ok(noloop!==html,'(test setup) loop flag was actually switched off');
const dom2=new JSDOM('<!doctype html><html dir="rtl"><body>'+noloop+'</body></html>',{runScripts:'outside-only',pretendToBeVisual:true,url:'https://example.test/'});
const w2=dom2.window; w2.matchMedia=()=>({matches:false});
Object.defineProperty(w2.HTMLElement.prototype,'clientWidth',{configurable:true,get(){return this.classList&&this.classList.contains('aetmg-viewport')?600:0;}});
Object.defineProperty(w2.HTMLElement.prototype,'scrollWidth',{configurable:true,get(){return this.classList&&this.classList.contains('aetmg-viewport')?1800:0;}});
w2.eval(js); await tick();
ok(w2.document.querySelector('.aetmg-prev').disabled===true&&w2.document.querySelector('.aetmg-next').disabled===false,'with loop off, "previous" is disabled at the start and "next" is enabled');
// reduced motion: autoplay never starts
const auto=html.replace('&quot;autoplay&quot;:0','&quot;autoplay&quot;:2000'); ok(auto!==html,'(test setup) autoplay flag was actually switched on');
const dom3=new JSDOM('<!doctype html><html><body>'+auto+'</body></html>',{runScripts:'outside-only',pretendToBeVisual:true,url:'https://example.test/'});
const w3=dom3.window; w3.matchMedia=()=>({matches:true}); let timers=0; const si=w3.setInterval; w3.setInterval=(...a)=>{timers++;return si.apply(w3,a);};
w3.eval(js); await tick(); ok(timers===0,'autoplay does not start when the visitor prefers reduced motion');
const dom4=new JSDOM('<!doctype html><html><body>'+auto+'</body></html>',{runScripts:'outside-only',pretendToBeVisual:true,url:'https://example.test/'});
const w4=dom4.window; w4.matchMedia=()=>({matches:false}); let timers4=0; const si4=w4.setInterval; w4.setInterval=(...a)=>{timers4++;return si4.apply(w4,a);};
w4.eval(js); await tick(); ok(timers4===1,'autoplay starts when motion is allowed (so the reduced-motion check above is meaningful)');
console.log('\n'+pass+' passed, '+fail+' failed'); process.exit(fail?1:0);
})();
