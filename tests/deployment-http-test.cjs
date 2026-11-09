const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http');
const base=new URL(process.env.GNCI_TEST_URL||'http://localhost:8080');
function request(route,host){return new Promise((resolve,reject)=>{
    const req=http.get({hostname:base.hostname,port:base.port,path:route,headers:{Host:host}},res=>{
        // Never print or collect potentially sensitive response bodies.
        res.resume();res.on('end',()=>resolve({status:res.statusCode,headers:res.headers}));
    });req.on('error',reject);req.setTimeout(10000,()=>req.destroy(Error('timeout')));
});}
(async()=>{
    let checks=0;
    for(const host of ['localhost:8080','gncipampanga.com','staging.invalid']){
        for(const route of ['/.env','/.git/config','/config/config.php','/cache/activity-log-check.cjs','/tests/browser-routes-test.php','/database/migrate-personnel.php','/scripts/backup-local-normalization.py','/docs/api-request-protection.md','/docker-compose.yml','/uploads.ini']){
            assert.equal((await request(route,host)).status,403,host+route);checks++;
        }
    }
    const name='deployment-probe-'+require('node:crypto').randomBytes(8).toString('hex');
    const probes=[name+'.php',name+'.php.jpg',name+'.html',name+'.txt'];
    try{
        for(const file of probes)fs.writeFileSync(path.join('uploads',file),'deployment probe');
        for(const file of probes.slice(0,3)){assert.equal((await request('/uploads/'+file,base.host)).status,403,file);checks++;}
        assert.equal((await request('/uploads/'+probes[3],base.host)).status,200);checks++;
    }finally{for(const file of probes){const target=path.join('uploads',file);if(fs.existsSync(target))fs.unlinkSync(target);}}
    for(const route of ['/admin/login','/api/calendar-day.php?date=2026-11-09','/assets/js/admin-dialogs.js']){
        const response=await request(route,base.host);assert.equal(response.status,200,route);
        assert.equal(response.headers['x-content-type-options'],'nosniff');checks++;
    }
    console.log(`PASS: ${checks} host-independent private-path, upload execution and public-route checks.`);
})().catch(error=>{console.error(error);process.exitCode=1;});
