const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),crypto=require('node:crypto');
const source=fs.readFileSync('integrations/visitor-feedback/Code.gs','utf8');
const secret='test-only-secret-'.repeat(4),visitor='a'.repeat(64);
let rows=[['visitor_hash','state','response_id','created_at']],saves=0,failWrite=false,lockAvailable=true,held=false,acquired=0;
const questions=Array.from({length:6},(_,i)=>({getType:()=>18,getId:()=>101+i,getTitle:()=>`Question ${i+1}`,asRatingItem:()=>({getRatingScaleLevel:()=>5,createResponse:value=>value})}));
const range=(row,col,nr=1,nc=1)=>({getValues:()=>Array.from({length:nr},(_,i)=>Array.from({length:nc},(_,j)=>rows[row+i-1]?.[col+j-1]??'')),setValues:values=>values.forEach((r,i)=>r.forEach((v,j)=>{rows[row+i-1]??=[];rows[row+i-1][col+j-1]=v;})),createTextFinder:needle=>({matchEntireCell(){return this;},findNext:()=>{const n=rows.findIndex((r,i)=>i>0&&r[0]===needle);return n<0?null:{getRow:()=>n+1};}})});
const sheet={getRange:range,getLastRow:()=>rows.length};
const properties={FEEDBACK_SCRIPT_SECRET:secret,FORM_ID:'test-form',RECEIPTS_SHEET_ID:'test-sheet'};
const context=vm.createContext({Date,JSON,Number,String,Array,Math,Object,Error,
 PropertiesService:{getScriptProperties:()=>({getProperty:key=>properties[key]})},
 Utilities:{computeHmacSha256Signature:(body,key)=>[...crypto.createHmac('sha256',key).update(body).digest()]},
 LockService:{getScriptLock:()=>({tryLock:()=>{acquired++;held=lockAvailable;return held;},hasLock:()=>held,releaseLock:()=>{held=false;}})},
 SpreadsheetApp:{openById:()=>({getSheetByName:()=>sheet}),flush(){}},
 FormApp:{ItemType:{RATING:18,SCALE:5,SECTION_HEADER:6,IMAGE:11,VIDEO:12},openById:()=>({getItems:()=>questions,isAcceptingResponses:()=>true,createResponse:()=>{const response={withItemResponse:()=>response,submit:()=>{if(failWrite)throw Error('Unknown write outcome');saves++;return {getId:()=>`receipt-${saves}`};}};return response;}})},
 ContentService:{MimeType:{JSON:'json'},createTextOutput:text=>({setMimeType:()=>text})}
});
vm.runInContext(source,context);
function request(data={},signingSecret=secret){const payload=JSON.stringify({action:'status',visitor,at:Math.floor(Date.now()/1000),...data});return JSON.parse(context.doPost({postData:{contents:JSON.stringify({payload,signature:crypto.createHmac('sha256',signingSecret).update(payload).digest('hex')})}}));}
const answers=Object.fromEntries(questions.map(q=>[q.getId(),3]));
assert.equal(request().questions.length,6);assert.equal(saves,0);
assert.equal(request({},'wrong-secret').ok,false);assert.equal(request({at:1}).ok,false);
for(const invalid of [{},[],{...answers,101:0},{...answers,101:6},{...answers,101:'5'},Object.fromEntries(Object.entries(answers).slice(1)),{...answers,107:3}])assert.equal(request({action:'submit',answers:invalid}).ok,false);
assert.equal(rows.length,1);assert.equal(saves,0,'Invalid input must not reserve or save anything');
assert.equal(request({action:'submit',answers}).submitted,true);assert.equal(saves,1);
assert.equal(request({action:'submit',answers}).submitted,true);assert.equal(saves,1,'Retry returns the same receipt without a second response');
assert.equal(request().receipt,'receipt-1');assert.equal(held,false,'Lock is released');
lockAvailable=false;assert.equal(request({visitor:'b'.repeat(64)}).ok,false);lockAvailable=true;
failWrite=true;assert.equal(request({visitor:'b'.repeat(64),action:'submit',answers}).ok,false);
failWrite=false;assert.equal(request({visitor:'b'.repeat(64),action:'submit',answers}).pending,true);assert.equal(saves,1,'Uncertain writes are not repeated');
const before=acquired;assert.equal(request({},'forged').ok,false);assert.equal(acquired,before,'Unauthorized calls never acquire a lock or read Forms');
console.log('PASS: Apps Script authentication, timestamp, all-six validation, confirmed receipts, idempotent retries, lock contention and uncertain-write duplicate protection (Google services mocked).');
