/* Selected-text display styles; authored content keeps its existing storage format. */
(function(){
    'use strict';
    const rendered=new WeakMap();
    function textNodes(el){const out=[],walker=document.createTreeWalker(el,NodeFilter.SHOW_TEXT);while(walker.nextNode())out.push(walker.currentNode);return out;}
    function offsets(el,range,caret=false){if(!range||(!caret&&range.collapsed)||!el.contains(range.startContainer)||!el.contains(range.endContainer))return null;const prefix=range.cloneRange();prefix.selectNodeContents(el);prefix.setEnd(range.startContainer,range.startOffset);const start=prefix.toString().length;prefix.setEnd(range.endContainer,range.endOffset);return {el,start,end:prefix.toString().length};}
    function selection(el){const s=getSelection();return s?.rangeCount?offsets(el,s.getRangeAt(0)):null;}
    function restore(saved){if(!saved?.el.isConnected)return;const nodes=textNodes(saved.el);let count=0,start=null,end=null;for(const node of nodes){const next=count+node.length;if(!start&&saved.start<=next)start=[node,Math.max(0,saved.start-count)];if(!end&&saved.end<=next)end=[node,Math.max(0,saved.end-count)];count=next;}if(!start||!end)return;const r=document.createRange();r.setStart(...start);r.setEnd(...end);const s=getSelection();s.removeAllRanges();s.addRange(r);}
    function valid(runs,text){return (runs||[]).filter(r=>Number.isInteger(r.start)&&Number.isInteger(r.end)&&r.start>=0&&r.end>r.start&&text.slice(r.start,r.end)===r.text);}
    function edit(runs,text,range,patch){
        runs=valid(runs,text);const boundaries=new Set([range.start,range.end]);runs.forEach(r=>{boundaries.add(r.start);boundaries.add(r.end);});
        const points=Array.from(boundaries).sort((a,b)=>a-b),out=[];
        for(let i=0;i<points.length-1;i++){const start=points[i],end=points[i+1],old=runs.find(r=>r.start<=start&&r.end>=end),style={};
            if(old?.size)style.size=old.size;if(old?.color)style.color=old.color;
            if(start>=range.start&&end<=range.end)for(const [key,value]of Object.entries(patch)){if(value===null)delete style[key];else style[key]=value;}
            if(!Object.keys(style).length)continue;
            const last=out.at(-1);if(last&&last.end===start&&last.size===style.size&&last.color===style.color){last.end=end;last.text=text.slice(last.start,end);}else out.push({start,end,text:text.slice(start,end),...style});
        }return out;
    }
    function render(targets,settings){
        const savedSelection=getSelection()?.rangeCount?getSelection().getRangeAt(0).cloneRange():null;let saved=null;
        for(const [id,el]of targets){const text=el.textContent,runs=valid(settings[id]?.[window.GNCTextLayout.device()]?.runs,text),stamp=JSON.stringify(runs)+':'+text;
            if(rendered.get(el)===stamp&&(!runs.length||el.querySelector('[data-gnc-inline-owner="'+id+'"]')))continue;
            if(!saved&&savedSelection)saved=offsets(el,savedSelection,true);
            el.querySelectorAll('[data-gnc-inline-owner="'+id+'"]').forEach(span=>span.replaceWith(...span.childNodes));el.normalize();
            if(runs.length){let at=0;for(const node of textNodes(el)){const start=at,end=start+node.length;at=end;
                const relevant=runs.filter(r=>r.start<end&&r.end>start);if(!relevant.length)continue;
                const fragment=document.createDocumentFragment();let cursor=start;
                for(const r of relevant){const a=Math.max(start,r.start),b=Math.min(end,r.end);if(a>cursor)fragment.append(document.createTextNode(text.slice(cursor,a)));const span=document.createElement('span');span.dataset.gncInlineOwner=id;
                    if(r.size){span.dataset.gncInlineSize=r.size;span.style.setProperty('font-size','min('+r.size+'px, 20vw)');}
                    if(r.color){span.dataset.gncInlineColor=r.color;span.style.color=r.color;}
                    span.style.overflowWrap='anywhere';span.textContent=text.slice(a,b);fragment.append(span);cursor=b;
                }if(cursor<end)fragment.append(document.createTextNode(text.slice(cursor,end)));node.replaceWith(fragment);
            }}rendered.set(el,stamp);
        }if(saved)restore(saved);
    }
    function collect(el,id){let at=0;const out=[];for(const node of textNodes(el)){const start=at,end=start+node.length;at=end;const span=node.parentElement.closest('[data-gnc-inline-owner="'+id+'"]');if(!span||!node.length)continue;const style={};if(span.dataset.gncInlineSize)style.size=Number(span.dataset.gncInlineSize);if(span.dataset.gncInlineColor)style.color=span.dataset.gncInlineColor;const last=out.at(-1);if(last&&last.end===start&&last.size===style.size&&last.color===style.color){last.end=end;last.text+=node.data;}else out.push({start,end,text:node.data,...style});}return out;}
    window.GNCInlineTextStyle={selection,restore,edit,render,collect};
    function start(){window.GNCTextLayout?.apply();}if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();
