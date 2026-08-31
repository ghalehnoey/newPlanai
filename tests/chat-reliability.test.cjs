async function runChatReliabilityTests(source) {
    function environment() {
        const elements = new Map(), timers = new Map(), pending = [], storage = new Map();
        let timerId = 0;
        class Element {
            constructor(id = '') {
                this.id = id; this.value = ''; this.style = {}; this.dataset = {}; this.children = [];
                this.textContent = ''; this.disabled = false; this.hidden = false; this._html = '';
                const classes = new Set();
                this.classList = {add: (...xs) => xs.forEach(x=>classes.add(x)), remove: (...xs)=>xs.forEach(x=>classes.delete(x)), contains:x=>classes.has(x), toggle:(x,on)=>on?classes.add(x):classes.delete(x)};
            }
            set innerHTML(value) {
                this._html = value;
                if (this.id === 'chatBox') for (const key of elements.keys()) if(key.startsWith('ai-typing')) elements.delete(key);
            }
            get innerHTML() { return this._html; }
            addEventListener() {}
            setAttribute(key, value) { this[key] = value; }
            focus() {}
            append(...items) { this.children.push(...items); }
            prepend(item) { this.children.unshift(item); }
            replaceChildren(...items) { this.children = items; }
            remove() { elements.delete(this.id); }
            insertAdjacentHTML(_, html) {
                this._html += html;
                for(const match of html.matchAll(/id="([^"]+)"/g)) elements.set(match[1], new Element(match[1]));
            }
            querySelector(selector) { return this.children.find(x => selector === '.text-sm' && x.className?.includes('text-sm')) || null; }
        }
        const ids = ['chatBox','question','sendBtn','fileInput','fileInputBtn','fileUploadContainer','filePreviewContainer','fileNameDisplay','removeFileBtn','chatMain','selectedModelButton','selectedModelName','selectedModelIcon','modelMenu','modelMenuBackdrop','chatStatus','stopChatBtn','sidebar','sidebarBackdrop','conversationList'];
        ids.forEach(id => elements.set(id, new Element(id)));
        const document = {
            getElementById:id=>elements.get(id)||null,
            querySelector:selector=>selector.includes('meta[')?{content:selector.includes('chat-user-id')?'test-user':'test-csrf'}:null,
            querySelectorAll:()=>[], createElement:()=>new Element(), addEventListener(){}
        };
        class Controller { constructor(){this.signal={aborted:false};} abort(){this.signal.aborted=true;} }
        class Data { constructor(){this.fields={};} append(k,v){this.fields[k]=v;} }
        const fetch = (url,options={})=>new Promise((resolve,reject)=>pending.push({url,options,resolve,reject}));
        const window = {addEventListener(){}};
        const api = new Function('document','window','navigator','sessionStorage','fetch','AbortController','FormData','TextDecoder','URL','setTimeout','clearTimeout','marked','console', source + '\nreturn {sendMessage,newConversation,loadConversation,httpChatError,createChatEventParser,stopChatResponse, setState(id,text){currentModel="test-model";currentModelSupportsFiles=true;currentConversationId=id;questionInput.value=text;}, setText(text){questionInput.value=text;}, state(){return {id:currentConversationId,sending:isSendingMessage,text:questionInput.value,retry:retryIntent,active:activeRequest,files:selectedFiles};}};')(
            document,window,{onLine:true},
            {getItem:k=>storage.get(k)||null,setItem:(k,v)=>storage.set(k,v),removeItem:k=>storage.delete(k)},
            fetch,Controller,Data,class {decode(x){return x||'';}},{createObjectURL:()=> 'blob:test'},
            (fn,delay)=>{const id=++timerId;timers.set(id,{fn,delay});return id;},id=>timers.delete(id),
            {parse:x=>x},{log(){},error(){}}
        );
        const respond = (events) => {
            const chunks = events.map(([event,data])=>'event: '+event+'\ndata: '+JSON.stringify(data)+'\n\n');
            let index=0;
            return {ok:true,headers:{get:key=>key==='X-Chat-Protocol'?'2':'text/event-stream'},body:{getReader:()=>({read:async()=>index<chunks.length?{value:chunks[index++],done:false}:{done:true}})}};
        };
        return {api,pending,respond,elements,storage,timers};
    }
    const results=[];
    const check=(value,message)=>{if(!value)throw Error(message);};
    async function test(name,fn){await fn();results.push(name);}

    await test('new chat cancels pending send and ignores stale response without unlocking newer send',async()=>{
        const e=environment();e.api.setState('7','old question');
        const old=e.api.sendMessage();
        e.api.newConversation();
        check(!e.api.state().sending,'new chat remained locked');
        e.api.setText('new question');const fresh=e.api.sendMessage();
        e.pending[0].resolve(e.respond([['meta',{conversation_id:'7',user_message_id:10}],['delta',{text:'old answer'}],['done',{}]]));
        await old;
        check(e.api.state().sending,'old finally unlocked new send');
        check(e.api.state().id===null,'old response replaced conversation id');
        e.pending[1].resolve(e.respond([['meta',{conversation_id:'8',user_message_id:11,title:'new question'}],['delta',{text:'new answer'}],['done',{}]]));
        await fresh;
        check(e.api.state().id==='8'&&!e.api.state().sending,'new send did not complete');
    });
    await test('late conversation load cannot overwrite new chat',async()=>{
        const e=environment();const old=e.api.loadConversation('7');
        e.api.newConversation();
        e.pending[0].resolve({ok:true,json:async()=>({messages:[]})});
        await old;
        check(e.api.state().id===null,'late load changed id');
        check(e.elements.get('chatBox').innerHTML.includes('یک مکالمه جدید'),'late load replaced new view');
    });
    await test('419 restores draft and displays login action without replaying POST',async()=>{
        const e=environment();e.api.setState(null,'saved question');const p=e.api.sendMessage();
        e.pending[0].resolve({ok:false,status:419,json:async()=>({message:'raw internal response'})});
        await p;
        check(e.api.state().text==='saved question','draft lost');
        check(e.elements.get('chatStatus').children[1].href==='/login','login action missing');
        check(e.pending.length===1&&!e.api.state().sending,'unexpected replay/locked send');
    });
    await test('known failed stream allows retry of same message id without a duplicate upload',async()=>{
        const e=environment();e.api.setState(null,'question');const p=e.api.sendMessage();
        e.pending[0].resolve(e.respond([['meta',{conversation_id:'8',user_message_id:11,title:'question'}],['error',{code:'ai_unavailable',message:'safe error',retry_message_id:11,request_id:'synthetic'}]]));
        await p;
        check(e.api.state().text==='question'&&e.api.state().retry.id===11,'failed message not retryable');
        const retry=e.api.sendMessage();
        check(e.pending[1].options.body.fields.retry_message_id===11,'retry did not reuse message id');
        e.pending[1].resolve(e.respond([['meta',{conversation_id:'8',user_message_id:11}],['delta',{text:'answer'}],['done',{}]]));
        await retry;
        check(!e.api.state().sending,'retry locked input');
    });
    await test('incomplete network stream is not shown as completed and restores draft',async()=>{
        const e=environment();e.api.setState('8','question');const p=e.api.sendMessage();
        e.pending[0].resolve(e.respond([['meta',{conversation_id:'8',user_message_id:12}],['delta',{text:'partial'}]]));
        await p;
        check(e.api.state().text==='question'&&!e.api.state().sending,'incomplete response lost draft');
        check(e.api.state().retry===null,'ambiguous network failure automatically retryable');
        check(e.elements.get('chatStatus').dataset.kind==='error','incomplete stream marked success');
    });
    await test('HTTP quota, validation, size, and busy errors have distinct safe messages',async()=>{
        const e=environment();
        check(e.api.httpChatError(403,{code:'quota_exceeded',error:'<script>bad</script>'}).action.href==='/plans','quota action missing');
        check(!e.api.httpChatError(403,{code:'quota_exceeded',error:'<script>bad</script>'}).message.includes('<script>'),'raw quota HTML surfaced');
        check(e.api.httpChatError(422,{errors:{files:['file validation']}}).message==='file validation','validation lost');
        check(e.api.httpChatError(413).message!==e.api.httpChatError(429).message,'error categories merged');
    });
    await test('SSE parser preserves fragmented Persian JSON and multiple events',async()=>{
        const e=environment(),received=[];
        const parser=e.api.createChatEventParser((event,data)=>received.push([event,data]));
        const raw='event: delta\ndata: {"text":"سلام\\nدنیا"}\n\nevent: done\ndata: {"message_id":1}\n\n';
        for(let i=0;i<raw.length;i+=3)parser.push(raw.slice(i,i+3));
        check(received.length===2&&received[0][1].text==='سلام\nدنیا','SSE framing corrupt');
    });
    await test('UI timeout aborts request while preserving the draft',async()=>{
        const e=environment();e.api.setState('8','question');const p=e.api.sendMessage();
        const timer=[...e.timers.values()].find(t=>t.delay===180000);
        timer.fn();check(e.pending[0].options.signal.aborted,'timeout did not abort');
        const error=new Error('AbortError');error.name='AbortError';e.pending[0].reject(error);
        await p;check(e.api.state().text==='question'&&!e.api.state().sending,'timeout lost draft/lock');
    });
    await test('automatic fallback names the actual answering model',async()=>{
        const e=environment();e.api.setState('8','question');const p=e.api.sendMessage();
        e.pending[0].resolve(e.respond([['meta',{conversation_id:'8',user_message_id:12}],['notice',{code:'model_fallback',name:'Fallback Test'}],['delta',{text:'answer'}],['done',{}]]));
        await p;
        check(e.elements.get('chatStatus').children[0].textContent.includes('Fallback Test'),'fallback model not disclosed');
    });
    return results;
}
if (typeof module !== 'undefined') module.exports = {runChatReliabilityTests};
