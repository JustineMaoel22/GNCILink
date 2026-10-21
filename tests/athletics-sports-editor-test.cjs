const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
class Node {
    constructor(tag,attrs={}){this.tag=tag;this.attrs={...attrs};this.children=[];this.parentNode=null;this.textContent='';this.handlers={};this.style={setProperty(k,v){this[k]=v;}};this.classes=new Set((attrs.class||'').split(' '));this.classList={add:n=>this.classes.add(n),remove:n=>this.classes.delete(n),contains:n=>this.classes.has(n),toggle:(n,on)=>on?this.classes.add(n):this.classes.delete(n)};}
    get className(){return [...this.classes].join(' ');}set className(value){this.classes=new Set(value.split(' '));}
    get attributes(){return Object.entries(this.attrs).map(([name,value])=>({name,value}));}
    setAttribute(k,v){this.attrs[k]=String(v);}getAttribute(k){return this.attrs[k]??null;}removeAttribute(k){delete this.attrs[k];}
    matches(s){return s.split(',').some(part=>{part=part.trim();if(part==='*')return true;if(part.startsWith('.'))return part.slice(1).split('.').every(n=>this.classes.has(n));const m=part.match(/^\[([^=\]]+)(?:="([^"]*)")?\]$/);return m?Object.hasOwn(this.attrs,m[1])&&(m[2]===undefined||this.attrs[m[1]]===m[2]):this.tag===part;});}
    closest(s){for(let n=this;n;n=n.parentNode)if(n.matches(s))return n;return null;}
    querySelectorAll(s){return this.children.flatMap(n=>[...(n.matches(s)?[n]:[]),...n.querySelectorAll(s)]);}querySelector(s){return this.querySelectorAll(s)[0]||null;}
    insertBefore(n,ref){n.remove();const i=ref?this.children.indexOf(ref):this.children.length;assert(i>=0);this.children.splice(i,0,n);n.parentNode=this;return n;}appendChild(n){return this.insertBefore(n,null);}
    remove(){if(this.parentNode){this.parentNode.children.splice(this.parentNode.children.indexOf(this),1);this.parentNode=null;}}
    cloneNode(deep){const n=new Node(this.tag,this.attrs);n.classes=new Set(this.classes);n.textContent=this.textContent;if(deep)this.children.forEach(c=>n.appendChild(c.cloneNode(true)));return n;}
    getBoundingClientRect(){return {width:300};}focus(){this.focused=true;}scrollIntoView(){}addEventListener(name,fn){this.handlers[name]=fn;}emit(name,extra={}){this.handlers[name]?.({target:this,preventDefault(){},...extra});}showModal(){this.open=true;}close(){this.open=false;this.emit('close');}
    get src(){return this.attrs.src;}set src(v){this.attrs.src=v;}
    get alt(){return this.attrs.alt;}set alt(v){this.attrs.alt=v;}
}
function h(tag,attrs,kids=[]){const n=new Node(tag,attrs);if(attrs.text)n.textContent=attrs.text;kids.forEach(c=>n.appendChild(c));return n;}
const doc=new Node('document'),viewport=doc.appendChild(new Node('div')),track=viewport.appendChild(new Node('div',{class:'athletics-sports-track'})),group=track.appendChild(new Node('div',{class:'athletics-sports-group','data-sports-original':''}));viewport.clientWidth=1500;
const original={texts:{hero_title:'Athletics',hero_description:'Description',programs_title:'Sports',programs_description:'Programs'},sports:[{id:'basketball',name:'Basketball',type:'',image:'/photo.png'}]};
doc.getElementById=id=>id==='veSportsData'?{textContent:JSON.stringify({content:original,revision:'first'})}:null;
const title=doc.appendChild(new Node('h1',{'data-ve-field':'sports-text','data-ve-key':'hero_title'}));
const banner=doc.appendChild(new Node('img',{'data-ve-sports-background-area':'hero',src:'/banner.png'}));
const backdrop=doc.appendChild(new Node('div',{'data-ve-sports-background-area':'showcase'}));
const controls=doc.appendChild(new Node('div',{'data-ve-sports-speed-controls':''})),speedRange=controls.appendChild(new Node('input',{'data-ve-sports-speed':''})),slower=controls.appendChild(new Node('button',{'data-ve-sports-slower':''})),faster=controls.appendChild(new Node('button',{'data-ve-sports-faster':''})),speedOutput=controls.appendChild(new Node('output',{'data-ve-sports-speed-value':''}));
const motionRoot=doc.appendChild(new Node('main',{'data-ve-sports-preview':''}));motionRoot.appendChild(viewport);
const motionButton=controls.appendChild(new Node('button',{'data-ve-sports-motion':''}));
const teamsGrid=doc.appendChild(new Node('div',{'data-ve-teams-grid':''}));
doc.createElement=tag=>new Node(tag);
const facilityRoot=doc.appendChild(new Node('div',{class:'athletics-facilities-carousel'})),facilityStage=facilityRoot.appendChild(new Node('div',{class:'athletics-facilities-stage'})),facilityControls=facilityRoot.appendChild(new Node('div',{class:'athletics-facilities-controls'})),facilityDots=facilityControls.appendChild(new Node('div',{class:'athletics-facilities-dots'}));
const card=h('article',{'data-ve-sport':'basketball',class:'athletics-sport'},[h('img',{'data-ve-sport-image':'',src:'/photo.png'}),h('strong',{'data-ve-field':'sport-name'}),h('span',{'data-ve-field':'sport-type'})]);group.appendChild(card);
let posted,uploads,pending,fail=true,reloads=0,refreshes=0,cacheCalls=0,pickerTarget,preserveLogo;const navigations=[];
const slideshow=require('../assets/js/athletics-sports.js');
const context=vm.createContext({h,URL,loadFrame:url=>navigations.push(url),window:{location:{origin:'http://localhost:8080'},GNCImageFraming:{cacheUpload:async file=>{cacheCalls++;return file;}}},$$:(s,root)=>root.querySelectorAll(s),S:{doc,win:{location:{href:'http://localhost:8080/pages/student%20life/athletics-sports.php?ce_preview=1'},GNCSportsSlideshow:{refresh:()=>{refreshes++;slideshow.refresh(doc);}}},preview:false,busy:false},MB:1024*1024,
    makeField:(el,fn)=>{el.__ve={onChange:fn};},fieldOf:el=>el.closest('[data-ve-field]'),select(){},closePop(){},renderToolbar(){},chrome(){},toast(){},plural:(n,w)=>`${n} ${w}`,
    pickFile:(accept,fn,personPhoto,target,preserve)=>{pickerTarget=target;preserveLogo=preserve;fn({name:'sport.png',size:100});},previewUrl:()=> 'blob:new',confirmPublish:(message,fn)=>fn(),confirmPop:(title,message,label,fn)=>fn(),
    run:(label,fn)=>pending=fn(),send:async(url,fields,files)=>{posted=fields;uploads=files;return fail?{ok:false,error:'Retry'}:{ok:true};},reloadEditor:()=>reloads++});
const source=fs.readFileSync('assets/js/content-edit.js','utf8');vm.runInContext(source.slice(source.indexOf('    function SportsAdapter()'),source.indexOf('    function PaymentGuideAdapter()')),context);
context.S.win.GNCSportsSlideshow.refreshFacilities=(doc,index)=>slideshow.refreshFacilities(doc,index);
(async()=>{
    const adapter=context.SportsAdapter();adapter.mount(doc);assert.equal(adapter.dirtyCount(),0);
    context.S.adapter=adapter;const pageLink=new Node('a',{href:'?highlights_page=2&ce_preview=1#competition-highlights'});assert(adapter.onLink(pageLink));assert.equal(navigations.length,1,'Admin can browse highlights pages');
    assert(motionRoot.classes.has('sports-motion-preview'),'Slideshow plays inside the admin editor');assert.equal(motionButton.textContent,'Edit cards');
    motionButton.emit('click');assert(!motionRoot.classes.has('sports-motion-preview'));assert.equal(motionButton.textContent,'Play slideshow');assert.equal(adapter.dirtyCount(),0,'Playback is not a content change');
    faster.emit('click');assert.equal(speedOutput.textContent,'1.25×');assert(adapter.dirtyCount()>0);slower.emit('click');assert.equal(adapter.dirtyCount(),0,'Restoring saved speed clears dirty state');
    assert(motionRoot.classes.has('sports-motion-preview'),'Changing speed starts motion immediately inside admin');
    speedRange.value='0.25';speedRange.emit('input');assert(slower.disabled);assert.equal(track.style['--sports-scroll-duration'],'152s','Slower speed increases duration');
    adapter.onLink(pageLink);assert.equal(navigations.length,1,'Changing news pages cannot discard pending edits');
    speedRange.value='3';speedRange.emit('input');assert(faster.disabled);assert.equal(adapter.pick(speedRange).prevent,false,'Range input keeps its native behavior');
    speedRange.value='1.5';speedRange.emit('input');assert.equal(speedOutput.textContent,'1.5×');
    adapter.runSecondary();assert.equal(doc.querySelectorAll('[data-ve-sport]').length,2,'Add appends an editable sport');assert(adapter.dirtyCount()>0);
    assert(!motionRoot.classes.has('sports-motion-preview'),'Adding a sport pauses the slideshow for editing');
    const added=doc.querySelectorAll('[data-ve-sport]')[1],name=added.querySelector('[data-ve-field="sport-name"]');name.__ve.onChange(name,'Badminton');
    assert(added.classes.has('athletics-sport--placeholder'),'New sports use the green gradient');assert.equal(added.querySelector('img').getAttribute('src'),null,'New sports do not reuse another sport photo');
    assert.equal(doc.querySelectorAll('[data-ve-sport]').length,2,'Marquee duplicates never become editable records');
    assert(track.querySelector('.is-copy'));assert(group.querySelector('.is-duplicate'),'Short lists fill a full viewport');
    assert.equal(track.children.length,2);assert.equal(track.children[0].children.length,track.children[1].children.length,'Equal groups create a seamless half-width loop');
    const filled=group.children.length;slideshow.refresh(doc);assert.equal(group.children.length,filled,'Rebuilding does not accumulate duplicate slides');
    const copy=track.querySelector('.is-copy');assert.equal(copy.getAttribute('aria-hidden'),'true');assert.equal(copy.querySelectorAll('[data-ve-field]').length,0,'Duplicates have no editable fields');
    const toolbar=adapter.toolbar({el:added});toolbar.find(item=>item.label==='Change photo').run();assert.equal(pickerTarget,added,'Upload uses the card rather than its selected heading');assert(adapter.actions().primary.disabled,'Publish waits for image preparation');await new Promise(setImmediate);assert.equal(added.querySelector('img').src,'blob:new');
    assert(!added.classes.has('athletics-sport--placeholder'));assert.equal(added.querySelector('img').getAttribute('hidden'),null,'Uploaded photos replace the gradient');
    adapter.pick(new Node('button',{'data-ve-sports-background':'hero'}));assert.equal(pickerTarget,banner);await new Promise(setImmediate);assert.equal(banner.src,'blob:new','Banner replacement previews immediately');
    adapter.toolbar({kind:'sports-background',el:backdrop,key:'showcase'}).find(item=>item.label==='Change background image').run();await new Promise(setImmediate);assert.equal(backdrop.style.backgroundImage,'url("blob:new")','Slideshow background replacement previews immediately');assert.equal(cacheCalls,3,'All staged sports images have stable upload copies');
    adapter.runPrimary();await pending;assert.equal(JSON.parse(posted.content).speed,1.5);assert.equal(JSON.parse(posted.content).sports[1].name,'Badminton');assert.equal(Object.keys(uploads).length,3);assert(uploads.background_hero);assert(uploads.background_showcase);assert(adapter.dirtyCount()>0,'Failed publish stays pending');assert.equal(reloads,0);
    fail=false;adapter.runPrimary();await pending;assert.equal(adapter.dirtyCount(),0);assert.equal(reloads,1);
    adapter.pick(new Node('button',{'data-ve-team-add':''}));await new Promise(setImmediate);
    assert.equal(teamsGrid.children.length,1);assert.equal(preserveLogo,true,'Team artwork bypasses the photo crop dialog');
    const team=teamsGrid.children[0],teamName=team.querySelector('[data-ve-field="team-name"]');teamName.__ve.onChange(teamName,'Green Mamba');assert.equal(team.querySelector('img').getAttribute('alt'),'Green Mamba');
    adapter.runPrimary();await pending;assert.equal(JSON.parse(posted.content).teams[0].name,'Green Mamba');assert(Object.keys(uploads).some(key=>key.startsWith('team_')));assert.equal(adapter.dirtyCount(),0);
    adapter.toolbar({el:team}).find(item=>item.label==='Remove logo').run();assert.equal(teamsGrid.children.length,0);assert(adapter.dirtyCount()>0);
    adapter.runPrimary();await pending;assert.equal(adapter.dirtyCount(),0);
    for(let i=0;i<5;i++){adapter.pick(new Node('button',{'data-ve-facility-add':''}));await new Promise(setImmediate);}
    assert.equal(facilityStage.children.length,5);assert.equal(preserveLogo,undefined,'Facility photos use the crop tool');assert.equal(pickerTarget,facilityStage.children[4]);
    slideshow.refreshFacilities(doc,2);assert.equal(facilityDots.children.length,5);assert.equal(facilityStage.children[2].getAttribute('data-pos'),'0');assert.equal(facilityStage.children[1].getAttribute('data-pos'),'-1');assert.equal(facilityStage.children[3].getAttribute('data-pos'),'1');await new Promise(resolve=>setTimeout(resolve,450));assert.equal(facilityStage.children[0].getAttribute('data-pos'),'hide-l');assert.equal(facilityStage.children[4].getAttribute('data-pos'),'hide-r','Carousel matches Programs: one center and two faded neighbours');
    slideshow.refreshFacilities(doc,-1);assert.equal(facilityRoot.__activeFacilityIndex,4,'Previous wraps to the final facility');slideshow.refreshFacilities(doc,5);assert.equal(facilityRoot.__activeFacilityIndex,0,'Next wraps to the first facility');
    const facility=facilityStage.children[0],facilityName=facility.querySelector('[data-ve-field="facility-name"]');facilityName.__ve.onChange(facilityName,'Training Gym');
    adapter.toolbar({el:facility}).find(item=>item.title==='Move facility later').run();assert.equal(facilityStage.children[1],facility);
    fail=true;adapter.runPrimary();await pending;assert.equal(JSON.parse(posted.content).facilities.length,5);assert.equal(Object.keys(uploads).filter(key=>key.startsWith('facility_')).length,5);assert(adapter.dirtyCount()>0,'Failed facility publish remains pending');fail=false;adapter.runPrimary();await pending;assert.equal(adapter.dirtyCount(),0);
    adapter.toolbar({el:facility}).find(item=>item.label==='Remove facility photo').run();assert.equal(facilityStage.children.length,4);assert.equal(facilityDots.children.length,4);adapter.runPrimary();await pending;assert.equal(adapter.dirtyCount(),0);
    context.window.GNCImageFraming.cacheUpload=async()=>{throw new Error('Choose sport.png again.');};
    adapter.toolbar({el:added}).find(item=>item.label==='Change photo').run();await new Promise(setImmediate);
    assert.equal(adapter.dirtyCount(),0,'An unreadable replacement does not discard or replace the previously saved image');assert.equal(added.querySelector('img').src,'blob:new');
    adapter.toolbar({el:added}).find(item=>item.title==='Move earlier').run();assert.equal(group.children[0],added);assert(adapter.dirtyCount()>0);
    adapter.toolbar({el:added}).find(item=>item.label==='Remove sport').run();assert.equal(doc.querySelectorAll('[data-ve-sport]').length,1);
    context.S.preview=true;adapter.runSecondary();assert.equal(doc.querySelectorAll('[data-ve-sport]').length,1,'Preview cannot add');faster.emit('click');assert.equal(speedOutput.textContent,'1.5×','Preview does not change the saved speed');
    let autoTick=null;const windowEvents={};doc.body=doc;doc.documentElement=new Node('html');doc.hidden=false;doc.activeElement=facilityStage;
    doc.defaultView={setInterval(fn,ms){assert.equal(ms,5000);autoTick=fn;return 1;},clearInterval(){autoTick=null;},matchMedia:()=>({matches:false}),addEventListener:(name,fn)=>windowEvents[name]=fn};
    slideshow.mount(doc);assert.equal(autoTick,null,'Autoplay stays paused while editing facilities');
    doc.documentElement.classList.add('ve-previewing');doc.emit('visibilitychange');assert(autoTick);const before=facilityRoot.__activeFacilityIndex;autoTick();assert.equal(facilityRoot.__activeFacilityIndex,(before+1)%4,'Preview rotates every five seconds like Programs');
    facilityRoot.emit('mouseenter');assert.equal(autoTick,null);facilityRoot.emit('mouseleave');assert(autoTick,'Hover pauses and resumes autoplay');
    const beforeSwipe=facilityRoot.__activeFacilityIndex;facilityStage.emit('pointerdown',{clientX:100});facilityStage.emit('pointermove',{clientX:20});assert.equal(facilityRoot.__activeFacilityIndex,(beforeSwipe+1)%4,'Swipe advances facilities');facilityStage.emit('pointerup');
    facilityStage.emit('pointerdown',{clientX:100});facilityStage.emit('pointerup');facilityRoot.emit('click',{target:facilityStage.children[facilityRoot.__activeFacilityIndex]});
    const modal=doc.querySelector('.sports-facility-lightbox');assert(modal?.open,'The center facility opens a full-size photo');assert.equal(autoTick,null);modal.close();assert(!doc.querySelector('.sports-facility-lightbox'));assert(autoTick,'Closing the photo restores autoplay');windowEvents.pagehide();assert.equal(autoTick,null);
    console.log('PASS: inline sports editing, new slides, images, seamless inert duplicates, failed publish/retry, reordering, removal and Preview.');
})().catch(error=>{console.error(error);process.exitCode=1;});
