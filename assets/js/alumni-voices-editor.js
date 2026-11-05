/* Structured alumni cards; private snapshots and publishing belong to the CMS. */
(function(){
    'use strict';
    window.GNCAlumniVoicesEditor=function(ctx){
        let data=null,baseline='',files={};
        const previews=new WeakMap();
        function fileFrames(){
            const frames={},api=window.GNCImageFraming;
            if(api)for(const [id,file]of Object.entries(files)){const frame=api.fileSettings(file);if(frame)frames[id]={...frame};}
            return frames;
        }
        function photoPreview(file){if(!previews.has(file))previews.set(file,ctx.preview(file));return previews.get(file);}
        const dirty=()=>data&&(JSON.stringify(data.items)!==baseline||Object.keys(files).length)?1:0;
        function entryState(card,item,index){
            const original=JSON.parse(baseline||'[]');
            card.querySelector('[data-alumni-voice-state]').textContent=files[item.id]||JSON.stringify(original[index])!==JSON.stringify(item)?'Unpublished changes':'Published';
        }
        function render(){
            const doc=ctx.doc(),grid=doc&&doc.querySelector('[data-alumni-voices-grid]'),template=doc&&doc.getElementById('alumni-voice-template');
            if(!data||!grid||!template)return;
            const add=grid.querySelector('[data-alumni-voice-add]');
            grid.replaceChildren();
            data.items.forEach((item,index)=>{
                const card=template.content.firstElementChild.cloneNode(true);card.dataset.alumniVoice=item.id;card.dataset.gncLayoutScope=item.id;
                const image=card.querySelector('img'),source=files[item.id]?photoPreview(files[item.id]):item.image;
                if(source)image.src=source;image.hidden=!source;image.alt=item.alt;
                card.classList.toggle('is-empty-photo',!source);
                card.querySelector('[data-alumni-voice-number]').textContent='Entry '+(index+1)+(index===0?' · Featured':'');
                entryState(card,item,index);
                card.querySelector('[data-alumni-voice-position]').disabled=!source||!window.GNCImageFraming;
                card.querySelector('[data-alumni-voice-photo]').textContent=source?'Replace photo':'Add photo';
                for(const [selector,key,label,max,multi]of [['blockquote','quote','Quote',2000,true],['h3','name','Name',150],['footer p','batch','Batch / class year',100],['footer em','description','Description / role',300],['[data-alumni-voice-alt]','alt','Photo description',300]]){
                    const field=card.querySelector(selector);field.textContent=item[key];
                    for(const [attr,value]of Object.entries({'data-ve-field':'alumni-voice-text','data-ve-key':item.id+'_'+key,'data-ve-label':label,'data-ve-max':max,'data-ve-ph':label,'aria-label':label}))field.setAttribute(attr,value);
                    if(multi)field.setAttribute('data-ve-multiline','');
                    field.setAttribute('role','textbox');
                    field.setAttribute('aria-multiline',multi?'true':'false');
                    ctx.makeField(field,(node,value)=>{item[key]=value;if(key==='alt')image.alt=value;entryState(card,item,index);});
                }
                card.querySelector('[data-alumni-voice-feature]').disabled=index===0;
                card.querySelector('[data-alumni-voice-feature]').textContent=index===0?'★ Featured':'☆ Set as featured';
                card.querySelector('[data-alumni-voice-up]').disabled=index===0;
                card.querySelector('[data-alumni-voice-down]').disabled=index===data.items.length-1;
                for(const [selector,icon,label]of [
                    ['photo','image',source?'Replace photo':'Add photo'],
                    ['position','arrows-move','Reposition photo'],
                    ['feature','star',index===0?'Featured':'Set as featured'],
                    ['up','arrow-up','Move up'],['down','arrow-down','Move down'],['delete','trash','Remove entry']
                ]){
                    const button=card.querySelector('[data-alumni-voice-'+selector+']');
                    const glyph=doc.createElement('i'),text=doc.createElement('span');
                    glyph.className='bi bi-'+icon;glyph.setAttribute('aria-hidden','true');
                    text.className='alumni-voice-action-label';text.textContent=label;
                    button.replaceChildren(glyph,text);button.title=label;button.setAttribute('aria-label',label);
                }
                card.querySelector('.alumni-voice-alt summary').setAttribute('aria-label','Edit photo description');
                grid.append(card);
            });
            if(add)grid.append(add);
            if(add)add.disabled=!data.ready||data.items.length>=100;
            const empty=doc.querySelector('[data-alumni-voices-empty]');if(empty)empty.hidden=!!data.items.length;
            if(doc.defaultView.GNCTextLayout)doc.defaultView.GNCTextLayout.apply();
        }
        function add(){
            if(!data||!data.ready)return;
            if(data.items.length>=100){ctx.notify('Use up to 100 alumni voices.','error');return;}
            const item={id:crypto.randomUUID(),image:'',name:'',quote:'',batch:'',description:'',alt:''};
            data.items.push(item);ctx.close();ctx.select(null);render();ctx.refresh();
            const card=ctx.doc().querySelector('[data-alumni-voice="'+item.id+'"]');
            card.scrollIntoView({block:'center',behavior:'smooth'});card.querySelector('h3').focus({preventScroll:true});
        }
        function photo(item){
            ctx.pickFile('image/jpeg,image/png,image/webp,image/gif',file=>{
                if(!/^image\/(jpeg|png|webp|gif)$/.test(file.type)||file.size>5*1024*1024||!file.size){ctx.notify('Choose a JPG, PNG, WEBP, or GIF up to 5 MB.','error');return;}
                files[item.id]=file;ctx.select(null);render();ctx.refresh();
            },false,null,true,true);
        }
        function pick(target){
            if(target.closest('[data-alumni-voice-add]')){add();return {kind:'none'};}
            const card=target.closest('[data-alumni-voice]');if(!card||!data)return null;
            const index=data.items.findIndex(item=>item.id===card.dataset.alumniVoice),item=data.items[index];
            if(!item||target.closest('button:disabled'))return {kind:'none'};
            const photoDetails=target.closest('.alumni-voice-alt summary');
            if(photoDetails){photoDetails.parentElement.open=!photoDetails.parentElement.open;card.classList.toggle('is-description-open',photoDetails.parentElement.open);return {kind:'none'};}
            const field=target.closest('[data-ve-field="alumni-voice-text"]');
            if(field)return {kind:'field',el:field,toolbarEl:card,prevent:false};
            if(target.closest('[data-alumni-voice-position]')){if(window.GNCImageFraming)window.GNCImageFraming.edit(card.querySelector('img'));return {kind:'none'};}
            if(target.closest('[data-alumni-voice-photo]')){photo(item);return {kind:'none'};}
            if(target.closest('[data-alumni-voice-delete]')){
                ctx.confirm('Remove this alumni voice? It will be removed from the website when you publish.').then(ok=>{if(!ok)return;data.items.splice(index,1);delete files[item.id];ctx.select(null);render();ctx.refresh();});return {kind:'none'};
            }
            let next=target.closest('[data-alumni-voice-feature]')?0:target.closest('[data-alumni-voice-up]')?index-1:target.closest('[data-alumni-voice-down]')?index+1:null;
            if(next!==null&&next>=0&&next<data.items.length){const action=target.closest('button');data.items.splice(index,1);data.items.splice(next,0,item);ctx.select(null);render();ctx.refresh();const moved=ctx.doc().querySelector('[data-alumni-voice="'+item.id+'"]');const control=moved.querySelector(action.hasAttribute('data-alumni-voice-up')?'[data-alumni-voice-up]':action.hasAttribute('data-alumni-voice-down')?'[data-alumni-voice-down]':'[data-alumni-voice-feature]');(control.disabled?moved.querySelector('h3'):control).focus();}
            return next!==null?{kind:'none'}:{kind:'alumni-voice',el:card,id:item.id};
        }
        return {
            mount(doc){const source=doc.getElementById('alumniVoicesData');data=source?JSON.parse(source.textContent):null;baseline=data?JSON.stringify(data.items):'';files={};render();},
            state(value){
                if(!value)return {data,baseline,files,frames:fileFrames()};
                data=value.data;baseline=value.baseline;files=value.files||{};
                const api=window.GNCImageFraming;
                if(api)for(const [id,frame]of Object.entries(value.frames||{})){if(files[id])api.setFileSettings(files[id],frame);}
            },
            fileFramingStaged(target,file){if(Object.values(files).includes(file))ctx.refresh();},
            render,pick,dirty,
            toolbar(sel){
                const card=sel.el&&sel.el.closest('[data-alumni-voice]');
                if(!card||!data)return null;
                const index=data.items.findIndex(item=>item.id===card.dataset.alumniVoice),item=data.items[index];
                if(!item)return null;
                const action=key=>()=>{
                    pick(card.querySelector('[data-alumni-voice-'+key+']'));
                    const current=ctx.doc().querySelector('[data-alumni-voice="'+item.id+'"]');
                    if(current)ctx.select({kind:'alumni-voice',el:current,id:item.id});
                };
                return [
                    {type:'chip',text:'Entry '+(index+1)+(index===0?' · Featured':'')},
                    {type:'chip',text:card.querySelector('[data-alumni-voice-state]').textContent},
                    {icon:'image',label:item.image||files[item.id]?'Replace photo':'Add photo',run:()=>photo(item)},
                    {icon:'arrows-move',title:'Reposition photo',disabled:card.querySelector('[data-alumni-voice-position]').disabled,run:action('position')},
                    {icon:'card-text',title:'Photo description',run:()=>{
                        const details=card.querySelector('.alumni-voice-alt');details.open=!details.open;
                        card.classList.toggle('is-description-open',details.open);
                        if(details.open)details.querySelector('[data-alumni-voice-alt]').focus();
                    }},
                    {icon:'star',title:index===0?'Featured':'Set as featured',disabled:index===0,run:action('feature')},
                    {icon:'arrow-up',title:'Move up',disabled:index===0,run:action('up')},
                    {icon:'arrow-down',title:'Move down',disabled:index===data.items.length-1,run:action('down')},
                    {type:'sep'},
                    {icon:'trash',title:'Remove entry',danger:true,run:action('delete')}
                ];
            },
            async publish(){
                if(!dirty())return;
                const uploads={};for(const id of Object.keys(files))uploads['voice_'+id]=files[id];
                const result=await ctx.send('/admin/action/alumni-voices-handler.php',{items:JSON.stringify(data.items),revision:data.revision},uploads);
                if(!result.ok)throw Error(result.error||'Could not publish alumni voices.');
                // The upload now has a permanent URL. Keep both editor instances in
                // sync with the framing saved by the upload handler before repainting.
                const frames=fileFrames(),doc=ctx.doc();
                for(const item of result.items){if(frames[item.id])for(const win of [window,doc&&doc.defaultView]){
                    if(win&&win.GNCImageFraming)win.GNCImageFraming.set(item.image,frames[item.id]);
                }}
                data.items=result.items;data.revision=result.revision;files={};baseline=JSON.stringify(data.items);render();ctx.notify('Alumni voices published.','ok');
            }
        };
    };
})();
