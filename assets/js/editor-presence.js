/* Same-page presence and server-backed stale-write protection. No shared text or files. */
(function () {
    'use strict';
    window.GNCEditorPresence={create:function(options){
        var endpoint='/admin/action/editor-presence-handler.php', token='', route='', generation=0;
        var pending=Promise.resolve(), connecting=Promise.resolve(), timer=0, lastInteraction=Date.now(), lastEdit=0;
        var disposed=false, offline=false, stale=false, users=[], stopped=false, sending=false, failureMessage='';
        var polling=false, failures=0, nextPoll=0;
        var reservation=null, owned=false, canTakeover=false, lost=false, verifiedAt=0;
        var button=document.getElementById('vePresenceButton'), panel=document.getElementById('vePresencePanel');
        var list=document.getElementById('vePresenceList'), warning=document.getElementById('vePresenceWarning');
        var message=document.getElementById('vePresenceMessage'), recovery=document.getElementById('vePresenceRecovery');
        var status=document.getElementById('vePresenceStatus'), count=document.getElementById('vePresenceCount');
        var nativeFetch=window.fetch.bind(window);
        var start=document.createElement('button'),takeover=document.createElement('button');
        start.type=takeover.type='button';start.className=takeover.className='btn btn-outline-secondary ve-btn';
        button.parentNode.insertBefore(start,button);button.parentNode.insertBefore(takeover,button);
        takeover.textContent='Take over editing';takeover.hidden=true;
        function editable(){return owned&&!offline;}
        function access(){if(options.access)options.access(editable());}
        function activity(){return !options.preview()&&(Date.now()-lastEdit<15000||options.dirty())?'editing':'viewing';}
        function initials(name){return name.trim().split(/\s+/).slice(0,2).map(function(word){return Array.from(word)[0]||'';}).join('').toUpperCase();}
        function paint(){
            start.textContent=owned?'Finish editing':'Start editing';
            start.disabled=offline||options.busy()||(!owned&&!!reservation);
            takeover.hidden=owned||!reservation||!canTakeover;takeover.disabled=offline||options.busy();
            count.textContent=offline?'—':String(users.length);
            button.title=offline?'Active users unavailable':'Active users on this page: '+users.length;
            button.setAttribute('aria-label',button.title);button.classList.toggle('is-offline',offline);
            status.textContent=offline?(failureMessage||'Connection unavailable. Saving waits for conflict protection.'):'Updates automatically · only this page';
            list.replaceChildren();
            users.forEach(function(user){
                var row=document.createElement('li'),avatar=document.createElement('span'),body=document.createElement('span');
                avatar.className='ve-presence-avatar';avatar.textContent=initials(user.name);avatar.setAttribute('aria-hidden','true');
                if(typeof user.avatar==='string'&&/^data:image\/jpeg;base64,[A-Za-z0-9+/]+=*$/.test(user.avatar)){
                    var photo=document.createElement('img');photo.alt='';photo.width=34;photo.height=34;
                    photo.addEventListener('error',function(){photo.remove();});
                    photo.src=user.avatar;avatar.appendChild(photo);
                }
                var name=document.createElement('strong'),state=document.createElement('small');
                name.textContent=user.name+(user.self?' (you)':'');state.textContent=user.activity==='editing'?'Editing':'Viewing';
                state.className=user.activity==='editing'?'is-editing':'';body.append(name,state);row.append(avatar,body);list.appendChild(row);
            });
            var otherEditors=users.filter(function(user){return !user.self&&user.activity==='editing';});
            warning.hidden=!(stale||reservation&&!owned||lost||offline);
            warning.classList.toggle('is-conflict',stale);
            message.textContent=stale?'Newer content was saved elsewhere. Save a recovery draft to keep your pending work, then reopen and reconcile it with the current page before publishing.':
                offline?'Active users are unavailable. Your pending work is kept; reconnect before saving.':
                reservation&&!owned?'Currently being edited by '+reservation.name+'.':
                lost?'Editing access expired, was released, or was taken over. Your pending changes are kept. Save a recovery draft or start editing again.':'';
            recovery.hidden=!(stale||lost)||!options.dirty();recovery.disabled=offline||options.busy();
        }
        async function call(action,extra,peer){
            var response=await nativeFetch(endpoint,{method:'POST',credentials:'same-origin',redirect:'manual',signal:AbortSignal.timeout(12000),
                body:new URLSearchParams(Object.assign({action:action,csrf_token:options.csrf,token:peer===undefined?token:peer},extra||{}))});
            var data=await response.json().catch(function(){return {};});
            if (!response.ok||!data.success) {
                var error=Error(data.error||'Your session ended or active users are unavailable.');
                error.status=response.status;error.retryAfter=Number(response.headers.get('Retry-After'))||0;throw error;
            }
            return data;
        }
        function accept(data){users=data.users||users;stale=!!data.stale;reservation=data.reservation||null;var wasOwned=owned;owned=!!(reservation&&reservation.owned);if(wasOwned&&!owned)lost=true;if(owned)lost=false;verifiedAt=Date.now();canTakeover=!!data.can_takeover;offline=false;failureMessage='';failures=0;nextPoll=0;stopped=false;paint();access();}
        async function acquire(take){await connecting;accept(await call(take?'takeover':'acquire',take?{confirmed:'1'}:{}));return editable();}
        async function release(){if(token){await call('release');owned=false;reservation=null;paint();access();}}
        start.addEventListener('click',async function(){try{if(owned)await release();else await acquire(false);}catch(error){failureMessage=error.message;options.notify(error.message,'error');poll();}});
        takeover.addEventListener('click',async function(){if(await window.AdminDialogs.confirm('Take over editing from '+reservation.name+'? Their draft will be preserved and their editing access will stop.')){try{await acquire(true);}catch(error){options.notify(error.message,'error');}}});
        function beacon(action,peer){if(!peer)return;var body=new URLSearchParams({action:action,csrf_token:options.csrf,token:peer});navigator.sendBeacon(endpoint,body);}
        function schedule(){clearTimeout(timer);if(!disposed&&!stopped)timer=setTimeout(poll,Math.max(5000,nextPoll-Date.now()));}
        async function poll(){
            if(disposed||stopped||polling)return;
            if(Date.now()<nextPoll){schedule();return;}
            if(Date.now()-lastInteraction>300000&&!options.busy()){
                // Stop advertising an abandoned tab without forgetting its concurrency baseline.
                if(owned&&Date.now()-verifiedAt>45000){owned=false;reservation=null;lost=true;paint();access();}
                schedule();return;
            }
            if(!token||sending){schedule();return;}
            var current=generation;
            polling=true;
            try{var data=await call('ping',{activity:activity()});if(current===generation){failures=0;nextPoll=0;accept(data);}}
            catch(error){if(current===generation){offline=true;access();failureMessage=error.message;failures++;nextPoll=Date.now()+Math.max(5000*Math.pow(2,Math.min(failures,6)),error.retryAfter*1000||0);if([0,302,401,403].indexOf(error.status)>=0||failures>=5)stopped=true;paint();}}
            finally{polling=false;schedule();}
        }
        function open(url){
            var next=String(url||'/');
            var current=++generation,old=token;route=next;token='';users=[];stale=false;offline=false;stopped=false;failures=0;nextPoll=0;
            owned=false;reservation=null;lost=false;access();
            clearTimeout(timer);beacon('leave',old);status.textContent='Connecting…';
            connecting=(async function(){
                try{
                    var data=await call('join',{page:options.page,route:next},'');
                    if(current!==generation){beacon('leave',data.token);return;}
                    token=data.token;accept(data);
                }catch(error){if(current===generation){offline=true;failureMessage=error.message;paint();}}
                finally{schedule();}
            })();
            return connecting;
        }
        async function check(){
            await connecting;
            if(!token||offline){
                if(token){try{accept(await call('check',{activity:activity()}));return;}catch(error){offline=true;paint();throw error;}}
                else throw Error('Reconnect to the editor before saving. Your pending work is kept here.');
            }
            try{accept(await call('check',{activity:activity()}));}
            catch(error){if(error.status===409)stale=true;else offline=true;paint();throw error;}
        }
        function request(input,init){
            var work=pending.then(async function(){
                await connecting;
                var body=init.body,action=body&&body.get?body.get('action'):'';
                var draft=String(input).indexOf('editor-draft-handler.php')>=0;
                var read=/^(get|list|load|fetch_one|upload_environment|categories|get_content)$/.test(action||'');
                var needsCheck=!read&&!(draft&&['delete','save_recovery','record_publication'].indexOf(action)>=0);
                if(needsCheck)await check();
                var headers=new Headers(init.headers||{});
                if(token){headers.set('X-GNCI-Editor-Peer',token);if(options.publishing())headers.set('X-GNCI-Editor-Batch','1');}
                sending=true;
                try{
                    var response=await window.fetch(input,Object.assign({},init,{headers:headers}));
                    if(response.status===409){stale=true;paint();}
                    return response;
                }finally{sending=false;}
            });
            pending=work.catch(function(){});return work;
        }
        function interact(event){
            lastInteraction=Date.now();
            if(event.type==='input')lastEdit=Date.now();
        }
        function bind(doc){
            ['pointerdown','keydown','input'].forEach(function(type){doc.addEventListener(type,interact,{passive:true});});
            if(doc!==document)doc.addEventListener('pointerdown',function(){panel.hidden=true;button.setAttribute('aria-expanded','false');});
        }
        bind(document);
        recovery.addEventListener('click',function(){if(options.recover)options.recover();});
        button.addEventListener('click',function(){panel.hidden=!panel.hidden;button.setAttribute('aria-expanded',String(!panel.hidden));if(!panel.hidden){poll();panel.focus();}});
        document.addEventListener('click',function(event){if(!event.target.closest('#vePresence')){panel.hidden=true;button.setAttribute('aria-expanded','false');}});
        document.addEventListener('keydown',function(event){if(event.key==='Escape'&&!panel.hidden){panel.hidden=true;button.setAttribute('aria-expanded','false');button.focus();}});
        window.addEventListener('online',function(){lastInteraction=Date.now();stopped=false;nextPoll=0;poll();});
        window.addEventListener('offline',function(){offline=true;paint();access();});
        window.addEventListener('pagehide',function(){disposed=true;clearTimeout(timer);beacon('leave',token);});
        window.addEventListener('pageshow',function(event){if(event.persisted){disposed=false;offline=true;paint();}});
        return {open:open,bind:bind,fetch:request,check:check,
            sync:poll,
            refresh:function(){if(options.dirty())lastEdit=Date.now();recovery.disabled=offline||options.busy();start.disabled=offline||options.busy()||(!owned&&!!reservation);takeover.disabled=offline||options.busy();},
            release:release,acquire:acquire,editable:editable,
            resumeDraft:async function(extra){accept(await call('resume_draft',extra));},
            reconcileDraft:async function(extra){var data=await call('reconcile_draft',extra);accept(data);return data.draft_revision;},
            token:function(){return token;}
        };
    }};
})();
