const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawnSync}=require('node:child_process');
(async()=>{
    const dirs=fs.readdirSync(os.tmpdir()).filter(n=>n.startsWith('alumni-voices-')).map(n=>path.join(os.tmpdir(),n)).sort((a,b)=>fs.statSync(b).birthtimeMs-fs.statSync(a).birthtimeMs);
    for(const dir of dirs.slice(1)){
        let s;
        try{
            const port=fs.readFileSync(path.join(dir,'DevToolsActivePort'),'utf8').split('\n')[0];
            const tabs=await(await fetch('http://127.0.0.1:'+port+'/json/list',{signal:AbortSignal.timeout(2000)})).json();
            s=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>s.addEventListener('open',r,{once:true}));
            let id=0;const pending=new Map();s.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
            const send=(method,params={})=>new Promise((resolve,reject)=>{const n=++id,t=setTimeout(()=>{pending.delete(n);reject(Error('timeout'));},5000);pending.set(n,p=>{clearTimeout(t);p.error?reject(Error('command failed')):resolve(p.result);});s.send(JSON.stringify({id:n,method,params}));});
            const result=await send('Runtime.evaluate',{expression:"(async()=>{if(!window.VE)return;let route=new URL(VE.state.url,location.origin);if(!/^\\d+$/.test(route.searchParams.get('voices_test')||''))return;['ce_preview','_ve','reload'].forEach(k=>route.searchParams.delete(k));let fields={page:'alumni',route:route.pathname+route.search,csrf_token:VE_CONFIG.csrf};let post=async more=>(await fetch('/admin/action/editor-draft-handler.php',{method:'POST',body:new URLSearchParams({...fields,...more})})).json();let loaded=await post({action:'load',revision:'0'});if(loaded.draft)await post({action:'delete',revision:loaded.draft.revision});return true})()",awaitPromise:true,returnByValue:true,timeout:4000});
            const cookies=await send('Network.getCookies',{urls:['http://localhost:8080']});
            const session=cookies.cookies.find(c=>c.name==='PHPSESSID');
            if(session)spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php','destroy',session.value],{windowsHide:true,stdio:'ignore'});
            await send('Browser.close');console.log('Closed an older test browser and removed its isolated draft.');
        }catch(error){console.log('Older test browser already closed or unavailable.');}finally{s?.close();}
    }
})().catch(error=>{console.error(error.message);process.exitCode=1;});
