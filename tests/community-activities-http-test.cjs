const assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
const base=process.env.BASE_URL||'http://localhost:8080',endpoint=base+'/admin/action/community-activities-handler.php';
function php(file,...args){const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php',file,...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;}
let session,initial,current,owned=false;const images=new Set(),prefix='activity-http-'+Date.now().toString(36);
async function live(){const html=await fetch(base+'/pages/student%20life/community-activity.php').then(r=>r.text());return {html,...JSON.parse(/id="communityPageData">([^<]+)<\/script>/.exec(html)[1])};}
async function save(content,revision,file,key){const body=new FormData();body.set('csrf_token',session.csrf);body.set('content',JSON.stringify(content));body.set('revision',revision);if(file)body.set(key,file,'fixture.png');const r=await fetch(endpoint,{method:'POST',headers:{Cookie:session.name+'='+session.value},body});return {status:r.status,body:await r.json()};}
(async()=>{
    session=JSON.parse(php('tests/admin-design-session.php','create'));
    const editor=await fetch(base+'/admin/content-edit.php?page=community-activities',{headers:{Cookie:session.name+'='+session.value}}).then(r=>r.text());session.csrf=JSON.parse(/window.VE_CONFIG = ([^\n]+);/.exec(editor)[1]).csrf;
    assert.equal((await fetch(endpoint,{method:'POST'})).status,403);
    assert.equal((await fetch(endpoint,{method:'POST',headers:{Cookie:session.name+'='+session.value},body:new URLSearchParams({csrf_token:'invalid'})})).status,403);
    initial=await live();current=initial;
    if(initial.content.activities.length){console.log('SKIP real write lifecycle: authored activities exist. Auth/CSRF passed; content untouched.');return;}
    owned=true;const content=structuredClone(initial.content);
    content.activities=[{id:prefix+'-a',title:'Upload fixture <markup>',description:'Stored description <text>',category:'New category',image:'https://untrusted.invalid/a.jpg'}];
    let response=await save(content,current.revision,new Blob(['not an image'],{type:'image/png'}),'image_'+prefix+'-a');assert.equal(response.status,422);assert.equal((await live()).revision,initial.revision,'Invalid bytes preserve published page');
    const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j2ioAAAAASUVORK5CYII=','base64');
    response=await save(content,current.revision,new Blob([png],{type:'image/png'}),'image_'+prefix+'-a');assert.equal(response.status,200,JSON.stringify(response.body));current=await live();
    const first=current.content.activities[0].image;images.add(first);assert.match(first,/^\/uploads\/community-activities\/[a-f0-9]{32}\.png$/);assert.equal((await fetch(base+first)).status,200);assert.ok(current.html.includes('Upload fixture &lt;markup&gt;'),'Title is escaped');
    response=await save(content,current.revision,new Blob([png],{type:'image/png'}),'image_'+prefix+'-a');assert.equal(response.status,200);current=await live();images.add(current.content.activities[0].image);assert.notEqual(current.content.activities[0].image,first,'Replacement uses a new safe path');
    const edit=structuredClone(current.content);edit.activities[0].category='Updated category';edit.activities.push({id:prefix+'-b',title:'Additional fixture',description:'Second stored description',category:'Additional category',image:''});edit.activities.reverse();
    response=await save(edit,current.revision);assert.equal(response.status,200);current=await live();assert.equal(current.content.activities[0].id,prefix+'-b');assert.equal(current.content.activities[1].category,'Updated category');
    assert.equal((await save(edit,initial.revision)).status,422,'Stale publication rejected');
    edit.activities.pop();assert.equal((await save(edit,current.revision)).status,200);current=await live();assert.equal(current.content.activities.length,1);
    assert.equal((await save(initial.content,current.revision)).status,200);current=await live();assert.equal(current.revision,initial.revision,'Original page restored');owned=false;
    console.log('PASS: real create/edit/reorder/delete, PNG upload/replacement/serving, categories, escaped public content, invalid bytes, stale revision, auth/CSRF and original empty-state restoration.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(owned&&initial)try{const latest=await live();if(latest.content.activities.every(r=>r.id.startsWith(prefix))&&JSON.stringify(latest.content.texts)===JSON.stringify(initial.content.texts)&&latest.content.hero_image===initial.content.hero_image){if((await save(initial.content,latest.revision)).status!==200)throw Error('Fixture restore failed');}else console.error('Concurrent edits detected; automatic restore skipped to preserve them.');}catch(e){console.error(e);process.exitCode=1;}
    if(images.size)try{php('tests/community-activities-fixture.php','cleanup',...images);}catch(e){console.error(e);process.exitCode=1;}
    if(session)php('tests/admin-design-session.php','destroy',session.value);
});
