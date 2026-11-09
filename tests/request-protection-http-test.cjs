// Isolated HTTP and multi-process tests; no database or external API requests.
const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),net=require('node:net');
const {spawn}=require('node:child_process');
const dir=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-http-test-'));
const fixture=path.resolve('tests/request-protection-fixture.php');
function child(args){return spawn('php',args,{windowsHide:true,env:{...process.env,GNCI_LIMIT_TEST_DIR:dir},stdio:['ignore','pipe','pipe']});}
function run(args){return new Promise((resolve,reject)=>{const p=child(args);let out='',err='';p.stdout.on('data',v=>out+=v);p.stderr.on('data',v=>err+=v);p.on('error',reject);p.on('close',code=>code?reject(Error(err)):resolve(out));});}
let server;
(async()=>{
    const results=await Promise.all(Array.from({length:30},()=>run([fixture])));
    assert.equal(results.filter(v=>v==='1').length,7,'atomic quota across concurrent PHP processes');
    const port=await new Promise(resolve=>{const socket=net.createServer();socket.listen(0,'127.0.0.1',()=>{const p=socket.address().port;socket.close(()=>resolve(p));});});
    server=child(['-S',`127.0.0.1:${port}`,fixture]);server.stdout.resume();server.stderr.resume();
    const base=`http://127.0.0.1:${port}`;
    for(let i=0;i<50;i++){try{if((await fetch(base+'/health')).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
    assert.equal((await fetch(base+'/a')).status,200);
    assert.equal((await fetch(base+'/a')).status,200);
    const denied=await fetch(base+'/a?changed=1',{headers:{'X-Forwarded-For':'198.51.100.1'}});
    assert.equal(denied.status,429);assert.ok(Number(denied.headers.get('Retry-After'))>0);
    assert.equal(denied.headers.get('Cache-Control'),'no-store');assert.equal((await denied.json()).success,false);
    assert.equal((await fetch(base+'/b')).status,200,'separate endpoint budgets');
    await fetch(base+'/user?id=1');await fetch(base+'/user?id=1');assert.equal((await fetch(base+'/user?id=1')).status,429);
    assert.equal((await fetch(base+'/user?id=2')).status,200,'separate user budgets');
    const broken=await fetch(base+'/broken');assert.equal(broken.status,503);assert.equal(broken.headers.get('Retry-After'),'60');
    console.log('PASS: 30-process atomic quota; HTTP 429/Retry-After/no-store; spoofing resistance; endpoint/user isolation; storage failure 503.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(server){server.kill();await new Promise(resolve=>server.once('close',resolve));}
    // Exact random temporary directory created above, never the workspace.
    fs.rmSync(dir,{recursive:true,force:true});
});
