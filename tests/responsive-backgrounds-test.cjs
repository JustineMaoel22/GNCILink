const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const rules={textContent:'',dataset:{}},nodes=new Map(),listeners={};
function node(id,tag='DIV',image='url("/assets/images/test.jpg")'){
    const props=new Map();const n={id,tagName:tag,className:tag==='IMG'?'hero-image':'banner',classList:[tag==='IMG'?'hero-image':'banner'],isConnected:true,
        style:{getPropertyValue:k=>props.get(k)?.[0]||'',getPropertyPriority:k=>props.get(k)?.[1]||'',setProperty:(k,v,p='')=>props.set(k,[v,p]),removeProperty:k=>props.delete(k)},
        dataset:{},getAttribute:k=>k==='src'?'/assets/images/test.jpg':null,
        hasAttribute:k=>k==='data-responsive-background-active'&&n.dataset.responsiveBackgroundActive==='1',
        closest:()=>null,matches:()=>false,contains:o=>o===n,clientWidth:1200,clientHeight:400,
        css:{backgroundImage:image,backgroundPosition:'50% 50%',backgroundSize:'cover',objectPosition:'50% 50%',objectFit:'cover'}};
    n.ownerDocument=doc;nodes.set('#'+id,n);return n;
}
const doc={head:{append(){}},createElement:()=>rules,readyState:'loading',querySelector:s=>nodes.get(s),querySelectorAll:()=>[],addEventListener:(k,fn)=>{listeners[k]=fn;},dispatchEvent(){}};
const win={GNC_RESPONSIVE_BACKGROUNDS:{},addEventListener(){}};
const sandbox={window:win,document:doc,location:{origin:'https://school.test'},innerWidth:1366,URL,getComputedStyle:n=>n.css,requestAnimationFrame:()=>{},CustomEvent:class{},Image:class{},console};
vm.runInNewContext(fs.readFileSync('assets/js/responsive-backgrounds.js','utf8'),sandbox);
const api=win.GNCResponsiveBackgrounds,banner=node('banner'),other=node('other'),img=node('hero','IMG');
const t=api.target(banner),u=api.target(other),photo=api.target(img);
const setting=(path,x)=>({path,kind:'background',fit:'cover',x,y:25,zoom:1});
api.stage(t,setting('/uploads/mobile.jpg',12),null,'mobile');
assert.equal(api.value(t.selector,'desktop'),null);assert.equal(api.value(t.selector,'tablet'),null);
assert.equal(api.value(u.selector,'mobile'),null,'Identical legacy paths are scoped to their own section');
api.setDevice('mobile');assert.equal(banner.style.getPropertyValue('background-position'),'12% 25%');
api.stage(t,setting('/uploads/desktop.jpg',81),null,'desktop');
api.setDevice('desktop');assert.equal(banner.style.getPropertyValue('background-position'),'81% 25%');
api.setDevice('mobile');assert.equal(banner.style.getPropertyValue('background-position'),'12% 25%');
api.setDevice('tablet');assert.equal(banner.style.getPropertyValue('background-position'),'','Unassigned tablet restores original positioning');
api.accept({'#banner':{mobile:setting('/uploads/mobile.jpg',12),desktop:setting('/uploads/desktop.jpg',81)}});
api.stage(t,null,null,'mobile');api.setDevice('mobile');assert.equal(api.value(t.selector,'mobile'),null);assert.equal(banner.style.getPropertyValue('background-position'),'');
assert.equal(api.value(t.selector,'desktop').x,81,'Removing mobile preserves desktop');
api.reset();assert.equal(api.value(t.selector,'mobile').x,12,'Discard restores published override');
api.accept({});assert.equal(banner.style.getPropertyValue('background-position'),'','Clearing changes restores original inline styles');
api.stage(photo,{...setting('/uploads/portrait.jpg',33),kind:'image',zoom:2},null,'mobile');
api.setDevice('mobile');assert.equal(img.style.getPropertyValue('transform'),'scale(2)');
api.setDevice('desktop');assert.equal(img.style.getPropertyValue('transform'),'');assert(!img.hasAttribute('data-responsive-background-active'));
api.setDevice(null);assert(rules.textContent.includes('(max-width:767px)'));assert(rules.textContent.includes('(min-width:768px) and (max-width:1024px)'));assert(rules.textContent.includes('(min-width:1025px)'));
assert(rules.textContent.includes('content:url("/uploads/portrait.jpg")'),'Image-backed banners use device-specific source');
api.reset();assert(!rules.textContent.includes('portrait.jpg'));
console.log('PASS: independent devices and sections, fallback/removal, discard, image cropping, and public media queries.');
