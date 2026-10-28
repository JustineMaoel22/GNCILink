const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('assets/js/recommendations.js','utf8');
let prompted=false,due=false,blocked=false,opened=0,scheduled=0;
const context=vm.createContext({
 window:{GNCVisitorFeedback:{hasPrompted:()=>prompted,due:()=>due},scrollX:0,scrollY:100,scrollTo(){}},
 document:{hidden:false,querySelector:()=>blocked?{}:null,activeElement:null,documentElement:{classList:{add(){}}}},
 section:{hidden:true,showModal(){opened++;}},selected:[{}],hasOpened:false,openTimer:null,dueAt:0,
 previousFocus:null,pageScroll:null,observed:false,markShown(){},Date,
 clearTimeout(){},setTimeout(fn,delay){assert.equal(delay,1000);scheduled++;return scheduled;}
});
vm.runInContext(source.slice(source.indexOf('    function openWhenReady()'),source.indexOf('    function closeSuggestions()')),context);
prompted=true;context.openWhenReady();assert.equal(opened,0);assert.equal(scheduled,0,'Suggestions stay quiet after feedback is offered');
prompted=false;due=true;context.openWhenReady();assert.equal(opened,0);assert.equal(scheduled,1,'Due feedback gets priority');
due=false;blocked=true;context.openWhenReady();assert.equal(opened,0);assert.equal(scheduled,2,'Existing dialogs still block suggestions');
blocked=false;context.openWhenReady();assert.equal(opened,1,'Suggestions still open when no feedback is due');
console.log('PASS: feedback priority, same-page suggestion suppression, other-dialog deferral, and normal recommendation opening.');
