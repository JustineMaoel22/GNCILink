const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const docker=(args)=>{const r=spawnSync('docker',args,{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout.trim();};
const fixture=(...args)=>docker(['compose','exec','-T','web','php','tests/rbac-fixture.php',...args]);
const delay=ms=>new Promise(r=>setTimeout(r,ms));
let database,container;const sessions=[];
(async()=>{
 database=JSON.parse(fixture('create')).database;container='gnci-facility-approval-'+database.slice(-12);
 docker(['compose','run','--rm','-d','--name',container,'-p','127.0.0.1:8096:8096','-e','GNC_RBAC_TEST_DB='+database,'-e','RBAC_INTERNAL_ORIGIN=http://127.0.0.1:8096','-e','PHP_CLI_SERVER_WORKERS=4','web','php','-S','0.0.0.0:8096','tests/rbac-test-router.php']);
 const base='http://127.0.0.1:8096';
 for(let n=0;n<40;n++){try{if((await fetch(base+'/admin/login.php')).ok)break;}catch{}await delay(250);}
 const role={};for(const name of ['Super Admin','Content Editor','The Mirror']){role[name]=JSON.parse(docker(['exec',container,'php','tests/rbac-fixture.php','session',database,name]));sessions.push(role[name]);}
 const request=(who,url,data)=>fetch(base+url,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:role[who].cookie,...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams({csrf_token:role[who].csrf,...data}):undefined});
 const inspect=()=>JSON.parse(fixture('inspect',database));
 const endpoint='/admin/action/editor-draft-handler.php',scope={page:'programs',route:'/pages/academics/college/program/bael'};
 const snapshot=JSON.stringify({version:1,state:{pRows:{facility:[{item_id:0,draft_key:'private-photo',title:'Pending facility',file:{$draftFile:0},image_url:'blob:http://localhost/private-photo',framing:{x:50,y:50,zoom:2}}]}},preview:{fields:[],media:[],orders:[],icons:[],links:[]},files:[{name:'private.png',type:'image/png',modified:0,data:'iVBORw0KGgo='}],urls:{'blob:http://localhost/private-photo':0}});
 let response=await request('Content Editor',endpoint,{...scope,action:'save',revision:0,snapshot});let result=await response.json();assert(result.success,JSON.stringify(result));const revision=result.revision;
 assert.equal(inspect().submissions.length,0,'Autosaving a facility draft does not submit or publish it');
 response=await request('Content Editor',endpoint,{...scope,action:'load'});result=await response.json();assert.equal(result.draft.snapshot.state.pRows.facility[0].title,'Pending facility');assert.equal(result.draft.snapshot.files[0].name,'private.png');
 response=await request('Super Admin',endpoint,{...scope,action:'load'});result=await response.json();assert.equal(result.draft,null,'Another account cannot see a private draft without a review submission');
 response=await request('Content Editor',endpoint,{...scope,action:'submit_review',revision});result=await response.json();assert(result.success,JSON.stringify(result));const submission=result.submission_id;
 assert.equal(inspect().submissions.at(-1).status,'pending','Explicit submission waits for Super Admin');
 response=await request('Content Editor',endpoint,{...scope,action:'complete_review',review_submission:submission});assert.equal(response.status,403,'Content Editor cannot approve their own changes');
 response=await request('Super Admin',endpoint,{...scope,action:'load',review_submission:submission});result=await response.json();assert(result.success,JSON.stringify(result));assert.equal(result.draft.snapshot.state.pRows.facility[0].framing.zoom,2,'Super Admin review loads uploaded files and framing intact');
 response=await request('Content Editor','/admin/action/program-page-handler.php',{action:'save_program_items',program_id:1,item_type:'facility',items:'[]'});assert.equal(response.status,202,'Forged direct facility publishing is queued for approval');
 response=await request('Content Editor','/admin/action/image-framing-handler.php',{path:'/uploads/programs/private.png',framing:JSON.stringify({x:50,y:50,zoom:2})});assert.equal(response.status,202,'Forged direct photo reposition publishing is queued');
 response=await request('The Mirror',endpoint,{...scope,action:'save',revision:0,snapshot});assert.equal(response.status,403,'Unauthorized role cannot edit program facilities');
 response=await request('Content Editor',endpoint,{...scope,action:'save',revision,csrf_token:'invalid',snapshot});assert.equal(response.status,403,'Draft changes require CSRF');
 console.log('PASS: facility files/framing persist in private drafts; submission occurs only explicitly and waits for Super Admin; own-approval, direct live writes, other-role access, and CSRF bypasses are denied or queued. Isolated test database only.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{for(const s of sessions)try{docker(['exec',container,'php','tests/rbac-fixture.php','destroy-session',database,s.session]);}catch{}if(container&&/^gnci-facility-approval-[a-f0-9]{12}$/.test(container))try{docker(['stop',container]);}catch{}if(database&&/^gnci_rbac_test_[a-f0-9]{12}$/.test(database))try{fixture('drop',database);}catch(error){console.error('Cleanup failed:',error.message);process.exitCode=1;}});
