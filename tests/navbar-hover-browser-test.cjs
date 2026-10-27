// Exercise the shared hover handlers and actual CSS without a database.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const root = path.resolve(__dirname, '..');
const navbar = fs.readFileSync(path.join(root, 'components/navbar.php'), 'utf8');
const script = navbar.slice(navbar.indexOf('<!-- Nested Academics')).match(/<script>([\s\S]*?)<\/script>/)[1];
const css = ['bootstrap.min.css', 'navbar-style.css'].map(file => fs.readFileSync(path.join(root, 'assets/css', file), 'utf8').replace(/^@import[^;]*;/, '')).join('\n');
const item = (id, children) => `<li id="${id}" class="gnc-dropdown-submenu"><div class="gnc-submenu-row"><a href="/example">Department</a><button class="gnc-submenu-toggle" aria-expanded="false">Open</button></div><ul class="gnc-dropdown-menu gnc-nested-menu">${children}</ul></li>`;
const folder = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-hover-nav-'));
try {
    const html = `<!doctype html><style>${css}</style><nav id="mainNavbar" class="gnc-navbar"><ul><li id="root" class="nav-item dropdown"><a>Academics</a><ul class="gnc-dropdown-menu">${item('parent', item('child', '<li><a href="/program">Program</a></li>'))}${item('sibling', '<li>Other program</li>')}</ul></li></ul></nav>
    <script>window.matchMedia=()=>({matches:true});</script><script>${script}</script><script>
    (async()=>{
        const checks={},root=document.getElementById('root'),parent=document.getElementById('parent'),child=document.getElementById('child'),sibling=document.getElementById('sibling');
        const enter=el=>el.dispatchEvent(new MouseEvent('mouseenter'));
        const leave=el=>el.dispatchEvent(new MouseEvent('mouseleave'));
        const wait=ms=>new Promise(resolve=>setTimeout(resolve,ms));
        const open=el=>el.classList.contains('show');
        const visible=el=>getComputedStyle(el.querySelector(':scope > .gnc-dropdown-menu')).pointerEvents==='auto';
        enter(root);leave(root);await wait(150);
        checks.rootClosesQuickly=!open(root)&&!visible(root);
        enter(root);enter(parent);enter(child);await wait(300);
        checks.nestedOpen=open(parent)&&open(child)&&visible(parent)&&visible(child);
        checks.expanded=child.querySelector('button').getAttribute('aria-expanded')==='true';
        leave(child);leave(parent);leave(root);await wait(150);
        checks.gapGrace=open(root)&&open(parent)&&open(child)&&visible(child);
        enter(root);enter(parent);enter(child);await wait(450);
        checks.reentryCancelsClose=open(root)&&open(parent)&&open(child);
        leave(child);await wait(650);
        checks.childClosesIndependently=!open(child)&&open(parent)&&!visible(child);
        enter(sibling);await wait(300);
        checks.siblingSwitch=!open(parent)&&open(sibling)&&!visible(parent);
        leave(sibling);leave(root);await wait(650);
        checks.leaveCloses=!open(root)&&!open(sibling)&&!visible(root);
        enter(root);enter(parent);leave(parent);leave(root);window.dispatchEvent(new Event('resize'));await wait(450);
        checks.resizeResets=!open(root)&&!open(parent);
        document.documentElement.classList.add('gnc-editor-preview');enter(root);enter(parent);
        checks.previewGuard=!open(root)&&!open(parent);
        document.body.appendChild(Object.assign(document.createElement('pre'),{id:'test-result',textContent:JSON.stringify(checks)}));
    })();</script>`;
    const fixture = path.join(folder, 'navbar.html');
    fs.writeFileSync(fixture, html);
    const run = spawnSync(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
        '--headless=new', '--disable-gpu', '--no-first-run', '--user-data-dir='+path.join(folder, 'profile'),
        '--window-size=1440,900', '--virtual-time-budget=6000', '--dump-dom', 'file:///'+fixture.replaceAll('\\', '/')
    ], {encoding:'utf8', windowsHide:true, maxBuffer:4000000});
    if (run.error) throw run.error;
    const match = run.stdout.match(/<pre id="test-result">([^<]+)<\/pre>/);
    assert.ok(match, 'Chrome did not return results: '+run.stderr);
    for (const [name, value] of Object.entries(JSON.parse(match[1]))) assert.equal(value, true, name);
    console.log('PASS: Quick root closing, nested hover opening, gap grace, reentry, independent closing, sibling switching, exit, resize and editor guard.');
} finally {
    if (path.dirname(folder) === os.tmpdir() && path.basename(folder).startsWith('gnci-hover-nav-')) fs.rmSync(folder, {recursive:true, force:true});
}
