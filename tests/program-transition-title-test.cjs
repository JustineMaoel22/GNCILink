const assert=require('node:assert/strict');
const fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('assets/js/page-entrance.js','utf8');
function simulate(openingTitle){
    const labels=[],listeners={},span={set textContent(value){labels.push(value);}},overlay={dataset:{openingTitle},querySelector:()=>span,addEventListener(){},remove(){}};
    const window={matchMedia:()=>({matches:false,addEventListener(){},removeEventListener(){}}),addEventListener(){}};window.top=window.self=window;
    vm.runInNewContext(source,{window,document:{title:'Bachelor of Science in Medical Laboratory Science',readyState:'loading',documentElement:{classList:{add(){},contains(){return false;}}},getElementById:()=>overlay,querySelector:()=>({textContent:'MEDICAL LABORATORY SCIENCE'}),addEventListener:(type,fn)=>{listeners[type]=fn;}},location:{pathname:'/pages/academics/college/program/bsmls',search:''},URLSearchParams,setTimeout(){}});
    listeners.DOMContentLoaded?.();return labels;
}
assert.deepEqual(simulate('MEDICAL LABORATORY SCIENCE'),['MEDICAL LABORATORY SCIENCE'],'Program transition uses the final heading immediately and never swaps it');
assert.deepEqual(simulate(undefined),['Bachelor of Science in Medical Laboratory Science','MEDICAL LABORATORY SCIENCE'],'Other pages retain the existing fallback behavior');
console.log('PASS: program transition title stays unchanged through DOMContentLoaded; other page transitions retain their behavior.');
