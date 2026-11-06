/* Same-page presence and server-backed stale-write protection. No shared text or files. */
(function () {
    'use strict';
    window.GNCEditorPresence={create:function(options){
        var endpoint='/admin/action/editor-presence-handler.php', token='', route='', generation=0;
        var pending=Promise.resolve(), connecting=Promise.resolve(), timer=0, lastInteraction=Date.now(), lastEdit=0;
        var disposed=false, offline=false, stale=false, users=[], stopped=false, sending=false, failureMessage='';
        var button=document.getElementById('vePresenceButton'), panel=document.getElementById('vePresencePanel');
        var list=document.getElementById('vePresenceList'), warning=document.getElementById('vePresenceWarning');
        var message=document.getElementById('vePresenceMessage'), recovery=document.getElementById('vePresenceRecovery');
        var status=document.getElementById('vePresenceStatus'), count=document.getElementById('vePresenceCount');
        var nativeFetch=window.fetch.bind(window);
        function activity(){return !options.preview()&&(Date.now()-lastEdit<15000||options.dirty())?'editing':'viewing';}
        function initials(name){return name.trim().split(/\s+/).slice(0,2).map(function(word){return Array.from(word)[0]||'';}).join('').toUpperCase();}
        function paint(){
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
            warning.hidden=!(stale||otherEditors.length||offline);
            warning.classList.toggle('is-conflict',stale);
            message.textContent=stale?'Newer content was saved elsewhere. Save a recovery draft to keep your pending work, then reopen and reconcile it with the current page before publishing.':
                offline?'Active users are unavailable. Your pending work is kept; reconnect before saving.':
                otherEditors.map(function(user){return user.name;}).join(', ')+(otherEditors.length===1?' is editing this page.':' are editing this page.')+' Coordinate changes before publishing.';
            recovery.hidden=!stale||!options.dirty();recovery.disabled=offline||options.busy();
        }
        async function call(action,extra,peer){
            var response=await nativeFetch(endpoint,{method:'POST',credentials:'same-origin',redirect:'manual',signal:AbortSignal.timeout(12000),
                body:new URLSearchParams(Object.assign({action:action,csrf_token:options.csrf,token:peer===undefined?token:peer},extra||{}))});
            var data=await response.json().catch(function(){return {};});
            if (!response.ok||!data.success) {
                var error=Error(data.error||'Your session ended or active users are unavailable.');
                error.status=response.status;throw error;
            }
            return data;
        }
        function accept(data){users=data.users||users;stale=!!data.stale;offline=false;failureMessage='';paint();}
        function beacon(action,peer){if(!peer)return;var body=new URLSearchParams({action:action,csrf_token:options.csrf,token:peer});navigator.sendBeacon(endpoint,body);}
        function schedule(){clearTimeout(timer);if(!disposed&&!stopped)timer=setTimeout(poll,5000);}
        async function poll(){
            if(disposed||stopped)return;
            if(Date.now()-lastInteraction>300000&&!options.busy()){
                // Stop advertising an abandoned tab without forgetting its concurrency baseline.
                schedule();return;
            }
            if(!token||sending){schedule();return;}
            var current=generation;
            try{var data=await call('ping',{activity:activity()});if(current===generation)accept(data);}
            catch(error){if(current===generation){offline=true;failureMessage=error.message;if([0,302,401,403].indexOf(error.status)>=0)stopped=true;paint();}}
            finally{schedule();}
        }
        function open(url){
            var next=String(url||'/');
            var current=++generation,old=token;route=next;token='';users=[];stale=false;offline=false;stopped=false;
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
                if(token){try{accept(await call('check',{activity:activity()}));}catch(error){offline=true;paint();throw error;}}
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
        window.addEventListener('online',function(){lastInteraction=Date.now();poll();});
        window.addEventListener('offline',function(){offline=true;paint();});
        window.addEventListener('pagehide',function(){disposed=true;clearTimeout(timer);beacon('leave',token);});
        window.addEventListener('pageshow',function(event){if(event.persisted){disposed=false;offline=true;paint();}});
        return {open:open,bind:bind,fetch:request,check:check,
            sync:poll,
            refresh:function(){if(options.dirty())lastEdit=Date.now();recovery.disabled=offline||options.busy();},
            release:function(){if(token)return call('release').catch(function(){});},
            token:function(){return token;}
        };
    }};
})();
