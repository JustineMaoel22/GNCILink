// Database-independent coverage of the shared navbar and Bootstrap's real data API.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const root = path.resolve(__dirname, '..');
function files(dir) {
    return fs.readdirSync(dir, {withFileTypes:true}).flatMap(entry => entry.isDirectory()
        ? files(path.join(dir,entry.name)) : entry.name.endsWith('.php') ? [path.join(dir,entry.name)] : []);
}
const navbar = fs.readFileSync(path.join(root,'components/navbar.php'),'utf8');
const imports = source => source.match(/<script\b[^>]*src="[^"]*bootstrap[^">]*\.js"[^>]*>\s*<\/script>/g) || [];
assert.equal(imports(navbar).length,1,'Shared navbar loads Bootstrap exactly once');
assert.ok(imports(navbar)[0].includes('/assets/js/bootstrap.bundle.min.js'),'Use the local bundle including Popper');
const pages = [...files(path.join(root,'pages')), ...files(path.join(root,'components')),path.join(root,'index.php')]
    .filter(file => file !== path.join(root,'components/navbar.php') && /(?:include|require)[^\n]*['"/]navbar\.php['"]/.test(fs.readFileSync(file,'utf8')));
for (const file of pages) assert.equal(imports(fs.readFileSync(file,'utf8')).length,0,'Duplicate Bootstrap import: '+path.relative(root,file));
console.log('PASS: '+pages.length+' navbar consumers use one shared local Bootstrap bundle.');
const markup = navbar.slice(navbar.indexOf('<nav id="mainNavbar"')).replace(/<\?(?:php|=)[\s\S]*?\?>/g,'');
const css = ['bootstrap.min.css','navbar-style.css'].map(file=>fs.readFileSync(path.join(root,'assets/css',file),'utf8').replace(/^@import[^;]*;/,'')).join('\n');
const bootstrap = fs.readFileSync(path.join(root,'assets/js/bootstrap.bundle.min.js'),'utf8');
const folder = fs.mkdtempSync(path.join(os.tmpdir(),'gnci-mobile-nav-'));
try {
    const html = '<!doctype html><meta name="viewport" content="width=device-width, initial-scale=1"><style>'+css+'</style>'+markup.replace('<script src="/assets/js/bootstrap.bundle.min.js"></script>','<script>'+bootstrap+'</script>')+`<script>
    const checks={};
    const button=document.querySelector('.navbar-toggler'),menu=document.getElementById('mobileNav');
    const pause=()=>new Promise(resolve=>setTimeout(resolve,450));
    (async()=>{
        button.click();await pause();
        checks.opens=menu.classList.contains('show');
        checks.singleBackdrop=document.querySelectorAll('.offcanvas-backdrop').length===1;
        const toggle=menu.querySelector('[data-bs-toggle="collapse"]');
        toggle.click();await pause();
        const panel=document.querySelector(toggle.getAttribute('data-bs-target'));
        checks.accordion=panel.classList.contains('show')&&toggle.getAttribute('aria-expanded')==='true';
        for(let i=0;i<40;i++)panel.appendChild(Object.assign(document.createElement('p'),{textContent:'Menu item '+i}));
        const body=menu.querySelector('.offcanvas-body');body.scrollTop=100;
        checks.scrolls=body.scrollTop>0;
        menu.querySelector('[data-bs-dismiss="offcanvas"]').click();await pause();
        checks.closes=!menu.classList.contains('show')&&!document.querySelector('.offcanvas-backdrop');
        button.click();await pause();
        menu.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));await pause();
        checks.escape=!menu.classList.contains('show');
        document.body.appendChild(Object.assign(document.createElement('pre'),{id:'test-result',textContent:JSON.stringify(checks)}));
    })();</script>`;
    const fixture=path.join(folder,'navbar.html');fs.writeFileSync(fixture,html);
    for(const width of [390,768]) {
        const run=spawnSync(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',[
            '--headless=new','--disable-gpu','--no-first-run','--user-data-dir='+path.join(folder,'profile-'+width),
            '--window-size='+width+',740','--virtual-time-budget=5000','--dump-dom','file:///'+fixture.replaceAll('\\','/')
        ],{encoding:'utf8',windowsHide:true,maxBuffer:4000000});
        if(run.error)throw run.error;
        const match=run.stdout.match(/<pre id="test-result">([^<]+)<\/pre>/);
        assert.ok(match,'Chrome did not return results: '+run.stderr);
        const checks=JSON.parse(match[1]);
        for(const [name,value] of Object.entries(checks))assert.equal(value,true,width+'px: '+name);
        console.log('PASS: '+width+'px menu opens, expands, scrolls, closes and dismisses with Escape.');
    }
} finally {
    if(path.dirname(folder)===os.tmpdir()&&path.basename(folder).startsWith('gnci-mobile-nav-'))fs.rmSync(folder,{recursive:true,force:true});
}
