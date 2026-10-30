const assert=require('node:assert/strict');
const fs=require('node:fs'),vm=require('node:vm'),{execFileSync}=require('node:child_process');
const source=fs.readFileSync('assets/js/content-edit.js','utf8');
const colors=JSON.parse(execFileSync(process.env.PHP_BIN||'php',['-r',
    `require 'components/program-page-public.php'; $out=['presets'=>programPageThemePresets(),'styles'=>[]]; foreach(array_merge(array_column(programPageThemePresets(),'accent'),['#FFFFFF','#000000','#ABCDEF','#123456','#FFFF00','#FF0000']) as $hex)$out['styles'][$hex]=programPageThemeStyle($hex); echo json_encode($out);`
],{encoding:'utf8',windowsHide:true}));
const properties={};
const context=vm.createContext({PD:{theme:'green',theme_custom:colors.presets.green.accent,presets:colors.presets},pMeta:{},
    own:(o,k)=>Object.hasOwn(o,k),S:{doc:{documentElement:{style:{setProperty:(k,v)=>{properties[k]=v;}}}}},
    pMedia:{},bindEditing(){},restoreSlideReplacements(){},fetch(){throw Error('Preview must not publish or request data');}});
vm.runInContext(source.slice(source.indexOf('        function pMetaSet('),source.indexOf('        function postItems(')),context);
function expected(hex){return Object.fromEntries(colors.styles[hex].split(';').filter(Boolean).map(v=>{const i=v.indexOf(':');return [v.slice(0,i),v.slice(i+1)];}));}
for(const [preset,value]of Object.entries(colors.presets)){
    context.pMetaSet('theme',preset,context.PD.theme);
    context.pMetaSet('theme_custom',value.accent,context.PD.theme_custom);
    assert.deepEqual(properties,expected(value.accent),'Preset previews match published PHP colors: '+preset);
}
for(const hex of ['#FFFFFF','#000000','#ABCDEF','#123456','#FFFF00','#FF0000']){
    context.pMetaSet('theme','custom',context.PD.theme);context.pMetaSet('theme_custom',hex,context.PD.theme_custom);
    assert.deepEqual(properties,expected(hex),'Custom preview contrast/tint matches publishing: '+hex);
}
// Draft restoration rebinds against the new iframe, whose CSS still has the published color.
const rebound={};context.S.doc={documentElement:{style:{setProperty:(k,v)=>{rebound[k]=v;}}}};
context.pMeta={theme:'blue',theme_custom:colors.presets.blue.accent};
const hook=source.match(/draftRebind:(function\(\)\{bindEditing\(S\.doc\);restoreSlideReplacements\(\);[^\r\n]+),/);
assert(hook,'Entity draft restoration hook exists');vm.runInContext('('+hook[1]+')()',context);
assert.deepEqual(rebound,expected(colors.presets.blue.accent),'Reopened drafts show pending colors');
context.pMetaSet('theme','green',context.PD.theme);context.pMetaSet('theme_custom',context.PD.theme_custom,context.PD.theme_custom);
assert.equal(Object.keys(context.pMeta).length,0,'Returning to original color clears pending metadata');
assert.deepEqual(rebound,expected(colors.presets.green.accent));
console.log('PASS: immediate preset/custom preview, published color parity, draft restoration and reverting without server writes.');
