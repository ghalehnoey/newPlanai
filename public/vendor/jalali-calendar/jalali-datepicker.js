/* JalaliCalendar kit 1.0.0. Derived from HR's shared picker, no HR dependencies. */
((global) => {
  'use strict';
  const owners = new WeakMap();
  let counter = 0;
  const months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
  const weekdays = ['شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه'];
  const fa = new Intl.NumberFormat('fa-IR', {useGrouping:false});

  class JalaliDatePicker {
    static version = '1.0.0';
    static normalize(value) {
      return String(value ?? '').replace(/[۰-۹]/g, c => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(c)))
        .replace(/[٠-٩]/g, c => String('٠١٢٣٤٥٦٧٨٩'.indexOf(c))).trim();
    }
    constructor(options = {}) {
      this.options = {selector:'input[data-jalali-date],input[data-shamsi-date]', minYear:1200,
        maxYear:1500, timeout:12000, observe:true, root:document, ...options};
      if (!Number.isInteger(this.options.minYear) || !Number.isInteger(this.options.maxYear) ||
          this.options.minYear < 1000 || this.options.maxYear > 9377 || this.options.minYear > this.options.maxYear)
        throw new TypeError('Invalid year range');
      if (!options.endpoint && typeof options.loadMonth !== 'function')
        throw new TypeError('Provide endpoint or async loadMonth(year, month, {signal})');
      if (options.endpoint && new URL(options.endpoint, location.href).origin !== location.origin)
        throw new TypeError('Calendar endpoint must be same-origin; use loadMonth for another transport');
      this.root = this.options.root;
      this.cache = new Map(); this.inputs = new Map(); this.listeners = [];
      this.sequence = 0; this.focusDay = 1; this.target = null; this.trigger = null; this.destroyed = false;
      this.today = JalaliDatePicker.normalize(options.today || '');
      this.dialog = document.createElement('dialog');
      this.dialog.className = 'hr-jalali-picker'; this.dialog.dir = 'rtl';
      this.dialog.innerHTML = `<header class="hr-jalali-head"><div><small>انتخاب تاریخ شمسی</small><strong data-title>تقویم شمسی</strong></div><button type="button" data-action="close" aria-label="بستن تقویم">×</button></header>
        <div class="hr-jalali-navigation"><button type="button" data-action="previous" aria-label="ماه قبل">‹</button><label>ماه<select data-month></select></label><label>سال<select data-year></select></label><button type="button" data-action="next" aria-label="ماه بعد">›</button></div>
        <div class="hr-jalali-week" aria-hidden="true"><span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span></div>
        <div class="hr-jalali-days" data-days role="grid" aria-label="روزهای ماه شمسی"></div><p class="hr-jalali-status" data-status role="status" aria-live="polite"></p><p class="hr-jalali-error" data-error role="alert" hidden></p>
        <footer class="hr-jalali-footer"><button type="button" data-action="today" data-jalali-action="today">امروز</button><button type="button" data-action="clear">پاک‌کردن</button><span data-selected></span></footer><small data-help>شروع هفته شنبه است. حرکت با کلیدهای جهت، PageUp / PageDown برای ماه و Escape برای بستن. قرمز فقط جمعه است، نه همهٔ تعطیلات رسمی.</small>`;
      const id = `jalali-picker-${++counter}`;
      this.dialog.id = id;
      const find = key => this.dialog.querySelector(`[data-${key}]`);
      for (const name of ['title','month','year','days','status','error','selected','help']) this[name] = find(name);
      this.title.id = id+'-title'; this.help.id = id+'-help';
      this.dialog.setAttribute('aria-labelledby',this.title.id); this.dialog.setAttribute('aria-describedby',this.help.id);
      for (let y=this.options.minYear;y<=this.options.maxYear;y++) this.year.add(new Option(fa.format(y),String(y)));
      months.forEach((name,i) => this.month.add(new Option(name,String(i+1))));
      document.body.append(this.dialog);
      this.listen(this.root,'click', event => {
        const button = event.target.closest?.('[data-jalali-open]');
        const input = button?.closest('.hr-date-control')?.querySelector(this.options.selector);
        if (input && owners.get(input)===this) {event.preventDefault();this.open(input,button);}
      });
      this.listen(this.root,'keydown', event => {
        if (owners.get(event.target)===this && ((event.altKey&&event.key==='ArrowDown')||event.key==='F4')) {
          event.preventDefault(); this.open(event.target);
        }
      });
      this.listen(this.root,'input', event => {if (owners.get(event.target)===this) event.target.setCustomValidity('');});
      this.listen(this.root,'focusout', event => {if (owners.get(event.target)===this) this.validate(event.target);});
      this.listen(this.dialog,'click', event => {
        if (event.target===this.dialog) {
          const box=this.dialog.getBoundingClientRect();
          if(event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom)this.close();
          return;
        }
        const action = event.target.closest('[data-action]')?.dataset.action;
        if (action) {
          event.preventDefault();
          if (action==='close') this.close(); else if (action==='previous') this.step(-1);
          else if (action==='next') this.step(1); else if (action==='today') this.choose(this.today);
          else if (action==='clear') this.choose('');
        } else {
          const button = event.target.closest('button[data-value]');
          if (button&&!button.disabled) this.choose(button.dataset.value);
        }
      });
      this.listen(this.dialog,'cancel', event => {event.preventDefault();this.close();});
      this.listen(this.dialog,'close', () => {this.trigger?.setAttribute('aria-expanded','false');});
      this.listen(this.year,'change', () => this.render()); this.listen(this.month,'change', () => this.render());
      this.listen(this.days,'keydown', event => this.keyboard(event));
      this.mount(this.root);
      if (this.options.observe) {
        this.observer = new MutationObserver(records => {
          for (const record of records) for (const node of record.addedNodes) if (node.nodeType===1) this.mount(node);
          for (const input of this.inputs.keys()) if (!input.isConnected) this.unmount(input);
        });
        this.observer.observe(this.root,{childList:true,subtree:true});
      }
    }
    listen(node, name, fn) {node.addEventListener(name,fn);this.listeners.push([node,name,fn]);}
    parts(value) {
      const match = JalaliDatePicker.normalize(value).match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
      if (!match) return null;
      const p = match.slice(1).map(Number);
      return p[0]>=this.options.minYear && p[0]<=this.options.maxYear && p[1]>=1 && p[1]<=12 && p[2]>=1 && p[2]<=31 ? p : null;
    }
    canonical(p) {return `${p[0]}/${String(p[1]).padStart(2,'0')}/${String(p[2]).padStart(2,'0')}`;}
    allowed(value, input=this.target) {
      if (!value) return true;
      const parts = this.parts(value); if (!parts) return false;
      const canonical = this.canonical(parts);
      for (const [key,sign] of [['jalaliMin',-1],['jalaliMax',1]]) {
        const bound = input?.dataset[key]; if (!bound) continue;
        const p = this.parts(bound); if (!p) return false;
        if (sign===-1 && canonical<this.canonical(p) || sign===1 && canonical>this.canonical(p)) return false;
      }
      return true;
    }
    mount(root=this.root) {
      if (this.destroyed) return;
      const inputs = [...(root.matches?.(this.options.selector)?[root]:[]),...(root.querySelectorAll?.(this.options.selector)||[])];
      for (const input of inputs) {
        if (input.tagName!=='INPUT' || owners.has(input)) continue;
        if (!['text','search','tel'].includes(input.type)) throw new TypeError('Use a text input, not type=date');
        const attributes = new Map(['placeholder','inputmode','dir','autocomplete','maxlength','data-jalali-date'].map(k=>[k,input.getAttribute(k)]));
        owners.set(input,this); input.dataset.jalaliDate='';input.placeholder='۱۴۰۵/۰۱/۰۱';
        input.inputMode='numeric';input.dir='ltr';input.autocomplete='off';input.maxLength=10;
        const wrapper=document.createElement('span');wrapper.className='hr-date-control';input.before(wrapper);wrapper.append(input);
        const button=document.createElement('button');button.type='button';button.className='hr-date-open';button.dataset.jalaliOpen='';
        button.textContent='▦';button.disabled=input.disabled||input.readOnly;
        for (const [key,value] of Object.entries({'aria-label':'باز کردن تقویم شمسی','aria-haspopup':'dialog','aria-expanded':'false','aria-controls':this.dialog.id})) button.setAttribute(key,value);
        wrapper.append(button);this.inputs.set(input,{wrapper,button,attributes});
      }
    }
    async fetchMonth(year,month,signal) {
      year=Number(year);month=Number(month);
      const key=year+'/'+month, cached=this.cache.get(key);
      if (cached&&Date.now()-cached.at<60000) return cached.data;
      let data;
      if (this.options.loadMonth) data=await this.options.loadMonth(year,month,{signal});
      else {
        const url=new URL(this.options.endpoint,location.href);url.searchParams.set('year',year);url.searchParams.set('month',month);
        const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal});
        if (!response.ok||response.redirected||!response.headers.get('content-type')?.includes('application/json')) throw new Error('calendar-unavailable');
        data=await response.json();
      }
      if (data.year!==year||data.month!==month||!Number.isInteger(data.offset)||data.offset<0||data.offset>6||
          !Array.isArray(data.days)||data.days.length<29||data.days.length>31||!/^\d{4}\/\d{2}\/\d{2}$/.test(data.today)) throw new Error('calendar-invalid');
      for (let i=0;i<data.days.length;i++) {
        const item=data.days[i], weekday=(data.offset+i)%7;
        if (item.day!==i+1||item.value!==this.canonical([year,month,i+1])||item.weekday!==weekday||item.is_friday!==(weekday===6)) throw new Error('calendar-invalid-day');
      }
      this.cache.set(key,{at:Date.now(),data});if(this.cache.size>36)this.cache.delete(this.cache.keys().next().value);
      return data;
    }
    async validate(input) {
      if (this.destroyed||input.disabled||input.readOnly) return false;
      if (!input.value) {input.setCustomValidity('');return !input.required;}
      const p=this.parts(input.value);
      if (!p) {input.setCustomValidity('تاریخ شمسی معتبر با قالب ۱۴۰۵/۰۱/۰۱ وارد کنید.');return false;}
      const value=this.canonical(p);input.value=value;
      const controller=new AbortController(), timer=setTimeout(()=>controller.abort(),this.options.timeout);
      try {
        const data=await this.fetchMonth(p[0],p[1],controller.signal);
        const valid=data.days.some(day=>day.value===value)&&this.allowed(value,input);
        if (input.value===value&&!this.destroyed) input.setCustomValidity(valid?'':'روز نامعتبر یا خارج از بازهٔ مجاز است.');
        return valid;
      } catch {
        // No fabricated conversion: the host MUST always validate on the server.
        return null;
      } finally {clearTimeout(timer);}
    }
    async open(input,button) {
      if (this.destroyed||owners.get(input)!==this||input.disabled||input.readOnly) return;
      if (this.trigger) this.trigger.setAttribute('aria-expanded','false');
      this.target=input;this.trigger=button||this.inputs.get(input)?.button;
      const p=this.parts(input.value)||this.parts(this.today)||[this.options.minYear,1,1];
      this.year.value=String(p[0]);this.month.value=String(p[1]);this.focusDay=p[2];
      this.selected.textContent=this.parts(input.value)?this.canonical(this.parts(input.value)):'';
      if (typeof this.dialog.showModal!=='function') {input.focus();return;}
      if(!this.dialog.open)this.dialog.showModal();this.trigger?.setAttribute('aria-expanded','true');
      await this.render();
    }
    async render() {
      const sequence=++this.sequence;this.controller?.abort();const controller=new AbortController();this.controller=controller;
      const timer=setTimeout(()=>controller.abort(),this.options.timeout);
      this.error.hidden=true;this.status.textContent='در حال بارگذاری تقویم…';this.days.setAttribute('aria-busy','true');this.days.replaceChildren();
      try {
        const data=await this.fetchMonth(this.year.value,this.month.value,controller.signal);
        if(sequence!==this.sequence||!this.dialog.open||this.destroyed)return;
        this.today=data.today;this.title.textContent=months[data.month-1]+' '+fa.format(data.year);
        let row;
        for(let i=0;i<Math.ceil((data.offset+data.days.length)/7)*7;i++) {
          if(i%7===0){row=document.createElement('div');row.className='hr-jalali-row';row.setAttribute('role','row');this.days.append(row);}
          const item=data.days[i-data.offset];
          if(!item){const blank=document.createElement('span');blank.setAttribute('role','gridcell');blank.setAttribute('aria-hidden','true');row.append(blank);continue;}
          const button=document.createElement('button');button.type='button';button.className='hr-jalali-day';button.setAttribute('role','gridcell');
          button.textContent=fa.format(item.day);button.dataset.day=item.day;button.dataset.value=item.value;button.dataset.friday=item.is_friday;button.tabIndex=-1;
          button.disabled=!this.allowed(item.value);button.setAttribute('aria-label',weekdays[item.weekday]+' '+item.value);
          button.setAttribute('aria-selected',String(this.parts(this.target.value)&&this.canonical(this.parts(this.target.value))===item.value));
          if(item.value===this.today)button.setAttribute('aria-current','date');row.append(button);
        }
        this.focusGrid(this.focusDay);
        this.dialog.querySelector('[data-action=previous]').disabled=data.year===this.options.minYear&&data.month===1;
        this.dialog.querySelector('[data-action=next]').disabled=data.year===this.options.maxYear&&data.month===12;
        this.dialog.querySelector('[data-action=today]').disabled=!this.allowed(this.today);
        this.status.textContent='تاریخ را انتخاب کنید؛ هفته از شنبه شروع می‌شود.';
      } catch {
        if(sequence===this.sequence&&!this.destroyed){this.error.textContent='تقویم بارگذاری نشد؛ اتصال یا نشست ورود را بررسی کنید. ورود دستی تاریخ ممکن است و باید در سرور اعتبارسنجی شود.';this.error.hidden=false;this.status.textContent='';}
      } finally {clearTimeout(timer);if(sequence===this.sequence)this.days.setAttribute('aria-busy','false');}
    }
    focusGrid(day) {
      const buttons=[...this.days.querySelectorAll('button:not(:disabled)')];
      const clamped=buttons.length?Math.max(Number(buttons[0].dataset.day),Math.min(day,Number(buttons.at(-1).dataset.day))):day;
      const button=buttons.find(b=>Number(b.dataset.day)===clamped)||buttons[0];
      buttons.forEach(b=>b.tabIndex=b===button?0:-1);button?.focus();
    }
    step(delta) {
      let y=Number(this.year.value),m=Number(this.month.value)+delta;
      if(m<1){m=12;y--;}if(m>12){m=1;y++;}
      if(y<this.options.minYear||y>this.options.maxYear)return;
      this.year.value=y;this.month.value=m;this.render();
    }
    keyboard(event) {
      const button=event.target.closest('button[data-day]');if(!button)return;
      const day=Number(button.dataset.day), keys={ArrowLeft:day+1,ArrowRight:day-1,ArrowUp:day-7,ArrowDown:day+7};
      let next=keys[event.key];
      if(event.key==='Home')next=Number(button.parentElement.querySelector('button:not(:disabled)')?.dataset.day)||day;
      else if(event.key==='End')next=Number([...button.parentElement.querySelectorAll('button:not(:disabled)')].at(-1)?.dataset.day)||day;
      else if(event.key==='PageUp'||event.key==='PageDown'){event.preventDefault();this.focusDay=day;this.step(event.key==='PageUp'?-1:1);return;}
      if(next===undefined)return;event.preventDefault();
      if(this.days.querySelector(`button[data-day="${next}"]:not(:disabled)`)){this.focusDay=next;this.focusGrid(next);}
      else if(next<1){this.focusDay=31;this.step(-1);}else if(next>this.days.querySelectorAll('button[data-day]').length){this.focusDay=1;this.step(1);}
    }
    choose(value) {
      if(!this.target||this.target.disabled||this.target.readOnly||!this.target.isConnected||!this.allowed(value))return;
      const input=this.target;input.value=value;input.setCustomValidity('');
      input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));
      input.dispatchEvent(new CustomEvent('jalali:change',{bubbles:true,detail:{value}}));this.close();
    }
    close() {
      ++this.sequence;this.controller?.abort();if(this.dialog.open)this.dialog.close();
      this.trigger?.setAttribute('aria-expanded','false');if(this.target?.isConnected)this.target.focus();
    }
    unmount(input) {
      const record=this.inputs.get(input);if(!record)return;
      const {wrapper,attributes}=record;owners.delete(input);input.setCustomValidity('');
      if(input.parentElement===wrapper)wrapper.replaceWith(input);
      for(const [key,value] of attributes)value===null?input.removeAttribute(key):input.setAttribute(key,value);
      this.inputs.delete(input);
    }
    destroy() {
      if(this.destroyed)return;this.close();this.destroyed=true;this.observer?.disconnect();
      this.listeners.forEach(([node,name,fn])=>node.removeEventListener(name,fn));this.listeners=[];
      for(const input of this.inputs.keys())this.unmount(input);
      this.inputs.clear();this.cache.clear();this.dialog.remove();
    }
  }
  global.JalaliDatePicker=JalaliDatePicker;
})(globalThis);
