// Browser fixture for the real text controller and editor toolbar, without authentication or writes.
const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawnSync}=require('node:child_process');
const root=path.resolve(__dirname,'..');
const catalog=spawnSync('php',[],{input:"<?php require 'includes/text-layout.php'; echo json_encode(textLayoutFonts());",cwd:root,encoding:'utf8',windowsHide:true});
assert.equal(catalog.status,0,catalog.stderr);
const fonts=JSON.parse(catalog.stdout);
const controller=fs.readFileSync(path.join(root,'assets/js/text-layout.js'),'utf8');
const editor=fs.readFileSync(path.join(root,'assets/js/content-edit.js'),'utf8');
const toolbar=editor.slice(editor.indexOf('    function buildItem('),editor.indexOf('    var lastPos ='));
const folder=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-google-fonts-'));
try {
    const fixture=path.join(folder,'fonts.html');
    fs.writeFileSync(fixture,`<!doctype html><style>body{font:16px Arial;margin:20px}section{width:90%;min-height:300px}h1{font-family:Georgia;font-size:32px}#toolbar{padding:12px;background:#eee}button,select{margin:4px}</style>
    <section><h1 data-gnc-text="aaaaaaaaaaaaaaaaaaaaaaaa">Editable heading</h1><p>Neighboring content</p><img data-gnc-text="bbbbbbbbbbbbbbbbbbbbbbbb" data-gnc-layout-media width="40" height="40"><div id="rich" data-gnc-text="cccccccccccccccccccccccc"><h2 style="font-family:Georgia">Rich heading</h2><span class="material-symbols-outlined" style="font-family:'Material Symbols Outlined'">info</span></div></section><div id="toolbar"></div>
    <script>window.GNC_TEXT_FONTS=${JSON.stringify(fonts)};</script><script>${controller}</script><script>
    const checks={},target=document.querySelector('h1'),staged={};
    const api=window.GNCTextLayout;
    const layout=api.create({state:()=>({busy:false,preview:false}),stage:(id,device,value,base)=>{
        staged[id]=staged[id]||{};staged[id][device]={value,base};api.setChanges(staged);
    }});
    const h=(tag,props={},children=[])=>{const el=document.createElement(tag);Object.entries(props).forEach(([key,value])=>{
        if(key==='on')Object.entries(value).forEach(([name,fn])=>el.addEventListener(name,fn));
        else if(key==='text')el.textContent=value;
        else if(value===true)el.setAttribute(key,'');
        else if(value!==false&&value!=null)el.setAttribute(key,value);
    });children.filter(child=>child!=null).forEach(child=>el.append(child));return el;};
    const render=new Function('h','icon','S','textLayout','tb','richTextTools','placeTb',${JSON.stringify(toolbar+';return renderToolbar;')})(h,()=>document.createElement('span'),{sel:{el:target},adapter:{toolbar:()=>[]},win:window,doc:document},layout,document.getElementById('toolbar'),()=>[],()=>{});
    render();
    const select=()=>document.querySelector('#toolbar select');
    const choose=value=>{select().value=value;select().dispatchEvent(new Event('change'));};
    checks.catalog=select().options.length===9&&select().options[0].text==='Default font';
    checks.noInitialRequest=!document.querySelector('[data-gnc-google-font]');
    const original=target.style.cssText;
    choose('inter');
    checks.applies=target.style.fontFamily.includes('Inter')&&layout.fontFamily()==='inter';
    checks.normalFlow=target.style.position===''&&!target.closest('[data-text-layout-slot]');
    const link=document.querySelector('[data-gnc-google-font=inter]'),url=new URL(link.href);
    checks.googleCss=url.origin==='https://fonts.googleapis.com'&&url.pathname==='/css2'&&url.searchParams.get('display')==='swap'&&url.searchParams.get('family').startsWith('Inter:ital,wght@');
    render();choose('inter');checks.noDuplicateRequest=document.querySelectorAll('[data-gnc-google-font=inter]').length===1;
    const draft=JSON.parse(JSON.stringify(staged));api.setChanges({});checks.unpublishedReset=target.style.cssText===original;
    api.setChanges(draft);checks.draftRestored=target.style.fontFamily.includes('Inter');
    Object.defineProperty(window,'innerWidth',{value:390,configurable:true});api.apply();
    checks.mobileDefault=target.style.fontFamily==='';
    layout.changeFontFamily('lora');checks.mobileFont=target.style.fontFamily.includes('Lora');
    Object.defineProperty(window,'innerWidth',{value:1440,configurable:true});api.apply();render();
    checks.desktopPreserved=target.style.fontFamily.includes('Inter');
    layout.changeFontSize(2);checks.sizePreservesFont=target.style.fontFamily.includes('Inter')&&Number.isFinite(api.snapshot()['aaaaaaaaaaaaaaaaaaaaaaaa'].desktop.fontSize);
    layout.toggle();document.querySelector('.text-layout-move').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true}));
    checks.movePreservesFont=api.snapshot()['aaaaaaaaaaaaaaaaaaaaaaaa'].desktop.fontFamily==='inter';
    choose('');checks.defaultKeepsSize=!target.style.fontFamily&&!!target.style.fontSize;
    choose('poppins');const snapshot=JSON.stringify(api.snapshot());layout.changeFontFamily('bad; color:red');checks.invalidRejected=JSON.stringify(api.snapshot())===snapshot;
    api.acceptChanges(staged);api.setChanges({});checks.published=target.style.fontFamily.includes('Poppins');
    layout.select(document.querySelector('img'));layout.changeFontFamily('lora');checks.mediaExcluded=JSON.stringify(api.snapshot())===snapshot;
    layout.select(document.getElementById('rich'));layout.changeFontFamily('rubik');
    checks.richHeading=getComputedStyle(document.querySelector('#rich h2')).fontFamily.includes('Rubik');
    checks.iconPreserved=getComputedStyle(document.querySelector('#rich span')).fontFamily.includes('Material Symbols Outlined');
    layout.changeFontFamily('');checks.richDefault=getComputedStyle(document.querySelector('#rich h2')).fontFamily==='Georgia';
    document.body.appendChild(Object.assign(document.createElement('pre'),{id:'test-result',textContent:JSON.stringify(checks)}));
    </script>`);
    const run=spawnSync(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',[
        '--headless=new','--disable-gpu','--no-first-run','--user-data-dir='+path.join(folder,'profile'),
        '--window-size=1440,900','--virtual-time-budget=1500','--dump-dom','file:///'+fixture.replaceAll('\\','/')
    ],{encoding:'utf8',windowsHide:true,maxBuffer:4000000});
    if(run.error)throw run.error;
    const result=run.stdout.match(/<pre id="test-result">([^<]+)<\/pre>/);assert.ok(result,'Browser did not return results: '+run.stderr);
    for(const [name,value] of Object.entries(JSON.parse(result[1])))assert.equal(value,true,name);
    console.log('PASS: real font toolbar, on-demand Google CSS, normal flow, draft/publish state, responsive fonts, resize/move preservation, default reset and invalid/media guards.');
}finally{
    if(path.dirname(folder)===os.tmpdir()&&path.basename(folder).startsWith('gnci-google-fonts-'))fs.rmSync(folder,{recursive:true,force:true});
}
