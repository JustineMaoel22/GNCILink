const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const editorSource=fs.readFileSync(path.join(__dirname,'../assets/js/content-edit.js'),'utf8');
const tuitionAdapter=editorSource.slice(editorSource.indexOf('    function TuitionAdapter()'),editorSource.indexOf('    function FeedAdapter()'));
const tuitionPage=fs.readFileSync(path.join(__dirname,'../pages/admission/tuition-fees.php'),'utf8');
// Include editor-only markup above the iframe to catch layers intercepting PDF input.
const editorStageMarkup=tuitionPage.match(/<div class="fee-document-stage">([\s\S]*?)<iframe/)[1].replace(/<\?[\s\S]*?\?>/g,'');
function pdfFixture(label) {
    const stream='BT /F1 18 Tf 30 350 Td ('+label+') Tj ET';
    const objects=['<</Type/Catalog/Pages 2 0 R>>','<</Type/Pages/Count 1/Kids[3 0 R]>>',
        '<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 420]/Resources<</Font<</F1 4 0 R>>>>/Contents 5 0 R>>',
        '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>','<</Length '+stream.length+'>>\nstream\n'+stream+'\nendstream'];
    let pdf='%PDF-1.4\n';const offsets=[0];
    objects.forEach((object,index)=>{offsets.push(Buffer.byteLength(pdf));pdf+=(index+1)+' 0 obj\n'+object+'\nendobj\n';});
    const xref=Buffer.byteLength(pdf);
    pdf+='xref\n0 6\n0000000000 65535 f \n'+offsets.slice(1).map(offset=>String(offset).padStart(10,'0')+' 00000 n \n').join('');
    return pdf+'trailer\n<</Size 6/Root 1 0 R>>\nstartxref\n'+xref+'\n%%EOF';
}
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-tuition-test-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket;
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome did not start');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,result=>result.error?reject(Error(JSON.stringify(result.error))):resolve(result.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base=process.env.BASE_URL||'http://localhost:8080';
    await send('Page.enable');await send('Network.enable');
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*youtube.com*']});
    const page = base + '/pages/admission/tuition-fees.php';
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`
        if(new URLSearchParams(location.search).has('test-document')) {
            const observer=new MutationObserver(()=>{
                const data=document.getElementById('feeDocumentsData');
                if(!data || !data.textContent.trim())return;
                const categories=JSON.parse(data.textContent);
                categories.college.documents=[
                    {title:'College Fee Schedule Test',url:'/__tuition-test.pdf',size:'12 KB'},
                    {title:'Second College PDF',url:'/__tuition-test.pdf?second=1',size:'14 KB'},
                    {title:'Third College PDF',url:'/__tuition-test.pdf?third=1',size:'16 KB'}
                ];
                observer.disconnect();data.textContent=JSON.stringify(categories);
            });observer.observe(document,{childList:true,subtree:true});
        }
    `});
    await send('Fetch.enable',{patterns:[{urlPattern:'*/__tuition-test.pdf*'}]});
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.method==='Fetch.requestPaused'){
        send('Fetch.fulfillRequest',{requestId:packet.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'application/pdf'}],body:Buffer.from(pdfFixture(packet.params.request.url.includes('second')?'Second fee schedule':packet.params.request.url.includes('third')?'Third fee schedule':'First fee schedule')).toString('base64')});
    }});
    async function navigate(url){await send('Page.navigate',{url});await until(()=>evaluate(`location.href===${JSON.stringify(url)}&&document.readyState==='complete'`),'Tuition page did not load');}
    for(const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        await navigate(page);
        assert.equal(await evaluate('document.querySelectorAll("[data-fee-category]").length'),5);
        assert.equal(await evaluate('document.getElementById("feeDownload").hidden'),true,'Empty category has no download');
        await evaluate('document.querySelector("[data-fee-category=graduate-school]").click()');
        assert.equal(await evaluate('document.getElementById("feeEmptyCategory").textContent'),'Graduate School');
        assert.equal(await evaluate('new URLSearchParams(location.search).get("category")'),'graduate-school');
        await evaluate('document.getElementById("feeSearch").value="basic";document.getElementById("feeSearch").dispatchEvent(new Event("input"))');
        assert.equal(await evaluate('Array.from(document.querySelectorAll("[data-fee-category]")).filter(e=>!e.hidden).length'),1);
        await evaluate('document.getElementById("feeSearch").value="no-such-document";document.getElementById("feeSearch").dispatchEvent(new Event("input"))');
        assert.equal(await evaluate('document.getElementById("feeSearchStatus").hidden'),false);
        assert.equal(await evaluate('document.querySelector(".tuition-documents").getBoundingClientRect().width<=innerWidth'),true);
        console.log('PASS '+width+'px: category switching, search, empty state and responsive width');
        await navigate(page+'?test-document=1');
        assert.equal(await evaluate('document.getElementById("feeDocumentTitle").textContent'),'College Fee Schedule Test');
        assert.equal(await evaluate('document.getElementById("feePdfFrame").hidden'),false);
        assert.equal(await evaluate('document.getElementById("feeDownload").getAttribute("href")'),'/__tuition-test.pdf');
        assert.equal(await evaluate('document.getElementById("feeDocumentSelector").hidden'),false);
        assert.equal(await evaluate('document.getElementById("feeDocumentSelect").options.length'),3);
        for (const [index,title,url] of [[1,'Second College PDF','/__tuition-test.pdf?second=1'],[2,'Third College PDF','/__tuition-test.pdf?third=1']]) {
            await evaluate(`(()=>{const select=document.getElementById('feeDocumentSelect');select.value=${JSON.stringify(String(index))};select.dispatchEvent(new Event('change',{bubbles:true}));})()`);
            assert.equal(await evaluate('document.getElementById("feeDocumentTitle").textContent'),title);
            assert.equal(await evaluate('document.getElementById("feePdfFrame").getAttribute("src")'),url+'#view=FitH');
            assert.equal(await evaluate('document.getElementById("feeDownload").getAttribute("href")'),url);
            assert.equal(await evaluate('document.getElementById("feeOpenPdf").getAttribute("href")'),url);
            assert.equal(await evaluate('new URLSearchParams(location.search).get("document")'),String(index));
        }
        const thirdUrl=await evaluate('location.href');
        await navigate(thirdUrl);
        assert.equal(await evaluate('document.getElementById("feeDocumentTitle").textContent'),'Third College PDF','Reload retains the selected PDF');
        await evaluate('document.querySelectorAll(".fee-document-choice")[1].click()');
        assert.equal(await evaluate('document.getElementById("feeDocumentSelect").value'),'1','Sidebar and selector stay synchronized');
        await evaluate('history.back()');
        await until(()=>evaluate('document.getElementById("feeDocumentSelect").value==="2"'),'Back did not restore the third PDF');
        console.log('PASS '+width+'px: all three PDFs selectable, matching viewer/download/open links, reload and Back');
        await evaluate('document.querySelector("[data-fee-category=other-fees]").click()');
        assert.equal(await evaluate('document.getElementById("feePdfFrame").hasAttribute("src")'),false,'Old PDF is cleared for an empty category');
        assert.equal(await evaluate('document.getElementById("feeDownload").hasAttribute("href")'),false);
        assert.equal(await evaluate('document.getElementById("feeDocumentSelector").hidden'),true);
        console.log('PASS '+width+'px: PDF display, download URL, stale document cleanup');
        if(width===1440) {
            const editorChecks=await evaluate(`(()=>{
                document.getElementById('feePdfFrame').insertAdjacentHTML('beforebegin',${JSON.stringify(editorStageMarkup)});
                const api=window.GNCTuitionFees;api.categories.college.documents=[];api.show('college');
                let data=document.getElementById('veTuitionData');
                if(!data){data=document.createElement('script');data.id='veTuitionData';data.type='application/json';document.body.append(data);}
                data.textContent=JSON.stringify({fields:{},icons:{}});
                const pop=document.createElement('div');document.body.append(pop);
                const h=(tag,props={},children=[])=>{
                    const node=document.createElement(tag);
                    Object.entries(props).forEach(([key,value])=>{
                        if(key==='on')Object.entries(value).forEach(([name,fn])=>node.addEventListener(name,fn));
                        else if(key==='text')node.textContent=value;
                        else if(key==='class')node.className=value;
                        else if(key==='value')node.value=value;
                        else node.setAttribute(key,value);
                    });children.forEach(child=>node.append(child));return node;
                };
                let file;
                const create=new Function('S','$$','makeField','previewUrl','chrome','btnSecondary','h','openPop','closePop','pickFile','MB','toast',${JSON.stringify(tuitionAdapter+';return TuitionAdapter();')});
                const adapter=create({doc:document},()=>[],()=>{},blob=>URL.createObjectURL(blob),()=>{},document.getElementById('feeDownload'),h,box=>pop.replaceChildren(box),()=>pop.replaceChildren(),(_accept,callback)=>callback(file),1024*1024,message=>{throw Error(message);});
                adapter.mount(document);adapter.runSecondary();
                const click=text=>Array.from(pop.querySelectorAll('button')).find(button=>button.textContent===text).click();
                file=new File([${JSON.stringify(pdfFixture('First uploaded PDF'))}],'First upload.pdf',{type:'application/pdf'});click('Add PDF');
                const first=api.categories.college.documents[0].url;
                file=new File([${JSON.stringify(pdfFixture('Second uploaded PDF'))}],'Second upload.pdf',{type:'application/pdf'});click('Add PDF');
                const second=api.categories.college.documents[1].url;
                const selectedNew=api.selection().index===1&&document.getElementById('feePdfFrame').getAttribute('src')===second+'#view=FitH';
                click('View');
                const firstView=document.getElementById('feePdfFrame').getAttribute('src')===first+'#view=FitH';
                adapter.runSecondary();pop.querySelectorAll('button')[3].click();
                const secondView=document.getElementById('feePdfFrame').getAttribute('src')===second+'#view=FitH';
                pop.remove();
                const frame=document.getElementById('feePdfFrame');frame.scrollIntoView({behavior:'instant',block:'start'});
                const rect=frame.getBoundingClientRect();
                const viewerReceivesPointer=[100,250,400].every(offset=>document.elementFromPoint(rect.left+rect.width/2,rect.top+offset)===frame);
                return {selectedNew,firstView,secondView,viewerReceivesPointer,distinct:first!==second,changes:adapter.dirtyCount()===2};
            })()`);
            for(const [name,value] of Object.entries(editorChecks))assert.equal(value,true,'Editor adapter: '+name);
            console.log('PASS: editor adapter stages two PDFs, View switches both ways, and the PDF receives pointer input without a blocking layer.');
        }
        if(width===1440) {
            await evaluate('document.querySelector(".tuition-documents").scrollIntoView({behavior:"instant",block:"start"})');await delay(2000);
            const screenshot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(os.tmpdir(),'gnci-tuition-layout.png'),Buffer.from(screenshot.data,'base64'));
        }
    }
    console.log('No database changes or uploaded fixtures.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-tuition-test-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
