(function (root) {
    'use strict';
    var serial = 0;
    function parse(value) {
        var raw = String(value || '').trim(), extension = '', ext = raw.match(/\s*(?:loc(?:al)?\.?|ext(?:ension)?\.?|#)\s*([0-9]{1,6})\s*$/i);
        if (!raw) return {type:'mobile',digits:'',extension:''};
        if (ext) { extension = ext[1]; raw = raw.slice(0, -ext[0].length).trim(); }
        if (!/^\+?[0-9()\s-]+$/.test(raw)) return null;
        var digits = raw.replace(/[^0-9]/g, '');
        if (raw[0] === '+' && !digits.startsWith('63')) return null;
        if (digits.length === 12 && digits.startsWith('63')) digits = digits.slice(2);
        else if (digits.length === 11 && digits.startsWith('09')) digits = digits.slice(1);
        else if (digits.length === 7 && raw[0] !== '+') digits = '045' + digits;
        if (digits.length !== 10) return null;
        var type = digits.startsWith('045') && raw[0] !== '+' ? 'landline' : 'mobile';
        if (type === 'mobile' && (extension || digits[0] === '0')) return null;
        return {type:type,digits:type === 'landline' ? digits.slice(3) : digits,extension:extension};
    }
    function format(digits, type) {
        return (type === 'landline' ? [digits.slice(0,3),digits.slice(3,7)] : [digits.slice(0,3),digits.slice(3,6),digits.slice(6,10)]).filter(Boolean).join('-');
    }
    function national(raw, type) {
        var digits = raw.replace(/[^0-9]/g,'');
        if (type === 'mobile') {
            if ((raw.trim()[0] === '+' || digits.length > 10) && digits.startsWith('63')) digits = digits.slice(2);
            if (digits.startsWith('09')) digits = digits.slice(1);
        } else if (digits.length > 7 && digits.startsWith('045')) digits = digits.slice(3);
        return digits.slice(0,type === 'landline' ? 7 : 10);
    }
    function bind(source, options) {
        if (source.__contact) return source.__contact;
        options = options || {};
        var doc = source.ownerDocument, extensionOnly = options.extensionOnly || source.hasAttribute('data-contact-extension');
        var wrapper = doc.createElement('div'); wrapper.className = 'contact-control';
        var types = doc.createElement('div'); types.className = 'contact-type'; types.setAttribute('role','group'); types.setAttribute('aria-label','Contact number type');
        var main = doc.createElement('div'); main.className = 'contact-main';
        var prefix = doc.createElement('span'); prefix.className = 'contact-prefix'; prefix.setAttribute('aria-hidden','true');
        var input = doc.createElement('input'); input.type = 'text'; input.inputMode = 'numeric'; input.className = 'form-control'; input.autocomplete = 'off';
        input.setAttribute('aria-label', extensionOnly ? 'Local number' : 'Contact number');
        var label = doc.createElement('label'); label.className = 'contact-extension'; label.textContent = 'Local Number / Extension';
        var extension = doc.createElement('input'); extension.type='text'; extension.inputMode='numeric'; extension.className='form-control'; extension.maxLength=6; extension.placeholder='e.g. 123'; label.appendChild(extension);
        var error = doc.createElement('p'); error.className='contact-error'; error.id='contact-error-'+(++serial); error.setAttribute('aria-live','polite');
        input.setAttribute('aria-describedby',error.id); extension.setAttribute('aria-describedby',error.id);
        var legacy = doc.createElement('p'); legacy.className='contact-legacy';
        main.appendChild(prefix); main.appendChild(input);
        wrapper.appendChild(types); wrapper.appendChild(main); wrapper.appendChild(label); wrapper.appendChild(legacy); wrapper.appendChild(error);
        source.type = 'hidden'; source.after(wrapper);
        var type='mobile', buffers={}, original='', last='', untouched=true, buttons={};
        function render() {
            types.hidden=extensionOnly; label.hidden=extensionOnly || type !== 'landline'; prefix.hidden=extensionOnly;
            prefix.textContent=type === 'landline' ? '045-' : '+63-';
            input.placeholder=extensionOnly ? '123' : type === 'landline' ? '000-0000' : '000-000-0000';
            input.maxLength=extensionOnly ? 6 : type === 'landline' ? 8 : 12;
            Object.keys(buttons).forEach(function (key) { buttons[key].setAttribute('aria-pressed',String(type===key)); });
            input.value=extensionOnly ? buffers[type].digits : format(buffers[type].digits,type); extension.value=buffers[type].extension;
        }
        function validate(show) {
            var digits=buffers[type].digits, ext=buffers[type].extension, message='';
            if ((options.required || source.required) && !digits && !ext && (!untouched || original==='')) message=extensionOnly?'Enter a local number.':'Enter a contact number.';
            if (!untouched && (digits || ext)) {
                if (extensionOnly && !/^[0-9]{1,6}$/.test(digits)) message='Enter a local number containing 1–6 digits.';
                else if (!extensionOnly && digits.length !== (type==='landline'?7:10)) message=type==='landline'?'Enter all 7 digits after 045.':'Enter all 10 digits after +63.';
                else if (!extensionOnly && type==='mobile' && digits[0]==='0') message='Enter the mobile number after +63, without a leading 0.';
                else if (type==='landline' && ext && !/^[0-9]{1,6}$/.test(ext)) message='Use up to 6 digits for the extension.';
            }
            input.setCustomValidity(message); input.setAttribute('aria-invalid',String(!!message));
            error.textContent=show ? message : ''; error.hidden=!error.textContent;
            return !message;
        }
        function emit() {
            untouched=false; legacy.hidden=true;
            var state=buffers[type], value=extensionOnly ? state.digits : state.digits ? (type==='landline'?'045-':'+63-')+format(state.digits,type) : '';
            if (!extensionOnly && type==='landline' && state.extension) value+=' loc. '+state.extension;
            source.value=value; last=value; validate(true);
            source.dispatchEvent(new Event('input',{bubbles:true})); source.dispatchEvent(new Event('change',{bubbles:true}));
        }
        ['mobile','landline'].forEach(function (key) {
            var button=doc.createElement('button'); button.type='button'; button.textContent=key==='mobile'?'Mobile Number':'Landline Number'; buttons[key]=button;
            button.onclick=function () { if(type===key)return; type=key;render();emit();input.focus(); }; types.appendChild(button);
        });
        function update(raw, position) {
            var before=raw.slice(0,position).replace(/[^0-9]/g,'').length;
            var rawDigits=raw.replace(/[^0-9]/g,''), digits=extensionOnly ? rawDigits.slice(0,6) : national(raw,type);
            var removed=Math.max(0,rawDigits.length-(extensionOnly ? rawDigits.length : national(raw,type).length));
            // Prefix removal affects the caret; discarded excess digits at the end do not.
            if (!extensionOnly && ((type==='mobile' && rawDigits.startsWith('09')) || (type==='landline' && rawDigits.startsWith('045')) || (raw.trim()[0]==='+' && rawDigits.startsWith('63')))) before=Math.max(0,before-removed);
            buffers[type].digits=digits; render(); emit();
            var target=Math.min(before,digits.length), caret=0, seen=0;
            while(caret<input.value.length && seen<target) { if(/[0-9]/.test(input.value[caret]))seen++;caret++; }
            input.setSelectionRange(caret,caret);
        }
        input.addEventListener('input',function () {update(input.value,input.selectionStart);});
        input.addEventListener('paste',function (event) {
            event.preventDefault(); var pasted=event.clipboardData.getData('text'), start=input.selectionStart, end=input.selectionEnd;
            var full=parse(pasted);
            var explicitType=full && (full.type==='mobile' || pasted.replace(/[^0-9]/g,'').slice(0,10).startsWith('045') && pasted.replace(/[^0-9]/g,'').length>=10);
            if(full && !extensionOnly && (full.type===type || explicitType)) { type=full.type; buffers[type]={digits:full.digits,extension:full.extension};render();emit();input.setSelectionRange(input.value.length,input.value.length); }
            else { var raw=input.value.slice(0,start)+pasted+input.value.slice(end);update(raw,start+pasted.length); }
        });
        input.addEventListener('beforeinput',function (event) {
            if(input.selectionStart!==input.selectionEnd)return;
            var position=input.selectionStart, backward=event.inputType==='deleteContentBackward', forward=event.inputType==='deleteContentForward';
            if(backward && input.value[position-1]==='-') {event.preventDefault();update(input.value.slice(0,position-2)+input.value.slice(position),position-2);}
            else if(forward && input.value[position]==='-') {event.preventDefault();update(input.value.slice(0,position)+input.value.slice(position+2),position);}
        });
        extension.addEventListener('input',function () {extension.value=extension.value.replace(/[^0-9]/g,'').slice(0,6);buffers[type].extension=extension.value;emit();});
        function refresh() {
            original=source.value; last=source.value; untouched=true; buffers={mobile:{digits:'',extension:''},landline:{digits:'',extension:''}};
            var parsed=extensionOnly ? (/^[0-9]{0,6}$/.test(original)?{type:'mobile',digits:original,extension:''}:null) : parse(original);
            type=parsed ? parsed.type : 'landline'; if(parsed)buffers[type]={digits:parsed.digits,extension:parsed.extension};
            legacy.textContent=parsed?'':'Saved contact: '+original+'. Enter a new number to replace it.';legacy.hidden=!!parsed;
            render();validate(false);
        }
        source.__contact={validate:function () {var ok=validate(true);if(!ok)input.reportValidity();return ok;},refresh:refresh,input:input,extension:extension,wrapper:wrapper};
        refresh();source.dispatchEvent(new CustomEvent('contact-control-ready',{bubbles:true}));return source.__contact;
    }
    var api={parse:parse,bind:bind,refresh:function (source) {if(source.__contact)source.__contact.refresh();},valid:function (scope) {return Array.from(scope.querySelectorAll('input')).filter(function (field) {return field.__contact;}).every(function (field) {return field.__contact.validate();});}};
    root.GNCContact=api;
    function scan() {document.querySelectorAll('[data-contact-number],[data-contact-extension],#adm-office-phone,.dean-phone-input').forEach(function (input) {bind(input);});}
    if(root.document) {
        scan();new MutationObserver(function (changes) {if(changes.some(function (change) {return change.addedNodes.length;}))scan();}).observe(document.documentElement,{childList:true,subtree:true});
        document.addEventListener('submit',function (event) {if(!api.valid(event.target)){event.preventDefault();event.stopImmediatePropagation();}},true);
        document.addEventListener('shown.bs.modal',function (event) {event.target.querySelectorAll('input').forEach(function (field) {if(field.__contact)field.__contact.refresh();});});
    }
})(typeof window !== 'undefined' ? window : globalThis);
