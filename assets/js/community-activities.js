(function(){
    'use strict';
    const source=document.getElementById('communityPageData');if(!source)return;
    const data=JSON.parse(source.textContent),list=document.querySelector('[data-community-list]'),feature=document.querySelector('[data-community-feature]');
    let content=data.content,active=content.activities[0]?.id||null;
    const node=(tag,cls,text)=>{const el=document.createElement(tag);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;};
    function select(id){
        const row=content.activities.find(row=>row.id===id);if(!row)return;active=id;
        list.querySelectorAll('[data-community-id]').forEach(el=>el.classList.toggle('is-active',el.dataset.communityId===id));
    }
    function paintImage(){
        const src=typeof content.showcase_image==='string'?content.showcase_image:(content.activities.find(row=>row.image)?.image??data.showcase_image??'');
        const image=feature.querySelector('[data-community-image]');image.hidden=!src;image.alt=content.texts.hero_title;
        if(src)image.src=src;else image.removeAttribute('src');
        feature.querySelector('[data-community-no-image]').hidden=!!src;
        feature.querySelector('[data-community-caption]').textContent=content.texts.hero_title;
    }
    function textField(tag,cls,row,key,label,max){
        const el=node(tag,cls,row[key]);
        if(data.preview){el.setAttribute('data-ve-field','community-activity-'+key);el.setAttribute('data-ve-key',row.id);el.setAttribute('data-ve-label',label);el.setAttribute('data-ve-max',max);if(key==='description')el.setAttribute('data-ve-multiline','1');}
        return el;
    }
    function render(next){
        content=next;const markers={};
        list.querySelectorAll('[data-gnc-text]').forEach(el=>markers[el.getAttribute('data-ve-field')+':'+el.getAttribute('data-ve-key')]=el.getAttribute('data-gnc-text'));
        list.replaceChildren();
        content.activities.forEach((row,index)=>{
            const li=node('li','community-activity-item');li.dataset.communityId=row.id;if(data.preview)li.setAttribute('data-ve-community-card',row.id);
            const number=node('span','community-activity-number',String(index+1).padStart(2,'0'));number.setAttribute('aria-hidden','true');li.appendChild(number);
            const copy=node('div','community-activity-copy');
            copy.appendChild(textField('h4','',row,'title','Activity title',255));copy.appendChild(textField('span','community-activity-category',row,'category','Category',100));
            if(data.preview)copy.appendChild(textField('p','community-activity-description',row,'description','Activity description',12000));
            li.appendChild(copy);list.appendChild(li);
        });
        list.querySelectorAll('[data-ve-field]').forEach(el=>{const key=el.getAttribute('data-ve-field')+':'+el.getAttribute('data-ve-key');if(markers[key])el.setAttribute('data-gnc-text',markers[key]);});
        document.querySelector('[data-community-empty]').hidden=!!content.activities.length;
        document.querySelector('[data-community-showcase]').hidden=!content.activities.length;
        if(!content.activities.some(row=>row.id===active))active=content.activities[0]?.id||null;
        if(active)select(active);
        paintImage();
    }
    window.GNCCommunityActivities={render,select,active:()=>active};if(active)select(active);paintImage();
})();
