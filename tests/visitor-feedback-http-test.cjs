const assert=require('node:assert/strict');
const base=process.env.BASE_URL||'http://localhost:8080';
const consent='gnc_privacy_v1='+encodeURIComponent(JSON.stringify({version:1,at:Math.floor(Date.now()/1000),analytics:false,personalization:true,external:true}));
(async()=>{
 const url=base+'/api/visitor-feedback.php';
 for(const [method,headers,body,expected] of [
  ['GET',{},undefined,405],
  ['POST',{'X-GNC-Feedback':'1'},'{}',403],
  ['POST',{'X-GNC-Feedback':'1',Origin:'https://invalid.example',Cookie:consent},'{}',403],
  ['POST',{'X-GNC-Feedback':'1',Origin:base},'{}',403],
  ['POST',{'X-GNC-Feedback':'1',Origin:base,Cookie:consent},'{invalid',422],
  ['POST',{'X-GNC-Feedback':'1',Origin:base,Cookie:consent},JSON.stringify({action:'untrusted'}),422],
  ['POST',{'X-GNC-Feedback':'1',Origin:base,Cookie:consent},'x'.repeat(4097),413]
 ]){
  const r=await fetch(url,{method,headers:{'Content-Type':'application/json',...headers},body});
  assert.equal(r.status,expected,method+' '+JSON.stringify(headers));
  const result=await r.json();assert.equal(result.ok,false);assert.equal(r.headers.get('cache-control'),'no-store');
 }
 console.log('PASS: real HTTP method, origin, consent, malformed action/JSON, payload size and no-store checks. No Google responses sent.');
})().catch(e=>{console.error(e);process.exitCode=1;});
