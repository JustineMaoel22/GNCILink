const assert=require('node:assert/strict');
const {rank,canonical}=require('../assets/js/recommendation-engine.js');
const now=Date.now(), day=86400000;
const item=(id,title,category,tags=[],popular=0)=>({id,title,category,tags,popular,url:'/page/'+id,description:'',date:now});
const catalog=[
 item('it','BSIT Information Technology','Programs',['academics','dept:1']),
 item('cs','BSCS Computer Science','Programs',['academics','dept:1']),
 item('nursing','Nursing','Programs',['academics','dept:2']),
 item('dept','College of Computing','Departments',['academics','dept:1']),
 item('admissions','Admissions requirements','Admissions',['academics','admissions']),
 item('announcement','School announcement','Announcements',['updates']),
 item('news','School news','News',['updates']),
 item('event','Upcoming open day','Events',['updates']),
 item('popular','Popular page','Campus',[],200)
];
const h=(visits,last=now)=>({visits,last,seconds:120});
const it=rank(catalog,{it:h(10)},'it',{},now);
assert.equal(it[0].id,'cs');
assert(it.some(i=>i.id==='dept'));
assert(it.some(i=>i.id==='admissions'));
assert(!it.some(i=>i.id==='it'));
const updates=rank(catalog,{announcement:h(10)},'announcement',{},now);
assert(updates.slice(0,2).every(i=>['news','event'].includes(i.id)));
assert.equal(rank(catalog,{},null,{},now)[0].id,'popular');
assert.deepEqual(rank(catalog,{it:h(50,now-31*day)},null,{},now),rank(catalog,{},null,{},now));
const shift=rank(catalog,{it:h(10,now-25*day),announcement:h(12)},'announcement',{},now);
assert(['news','event'].includes(shift[0].id));
const repeats=rank(catalog,{it:h(10)},'it',{cs:now},now);
assert.notEqual(repeats[0].id,'cs');
assert.notEqual(canonical('/news-article.php?id=1'),canonical('/news-article.php?id=2'));
assert.equal(canonical('/pages/academics/college/program.php?utm_source=x&slug=bsit'),canonical('/pages/academics/college/program.php?slug=bsit'));
assert.equal(canonical('/index.php'),'/');
console.log('PASS: IT affinity, announcements, fallback, expiry, changing interests, repetition, and dynamic URL identity');
