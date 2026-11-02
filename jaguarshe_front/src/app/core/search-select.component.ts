import { Component, ElementRef, HostListener, Input, ViewChild, forwardRef, inject } from '@angular/core';
import { ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';

export interface SelectOption { value:number|string; label:string; }
let nextSelectId=0;

@Component({
 selector:'hse-search-select',standalone:true,
 providers:[{provide:NG_VALUE_ACCESSOR,useExisting:forwardRef(()=>SearchSelectComponent),multi:true}],
 template: `
 <button #trigger type="button" class="select-trigger" [class.placeholder]="!selected" [disabled]="disabled"
  [attr.aria-label]="label+': '+(selected?.label||placeholder)" aria-haspopup="listbox" [attr.aria-expanded]="open"
  [attr.aria-controls]="open?id:null" (click)="toggle()" (keydown)="triggerKey($event)">
  <span>{{selected?.label||placeholder}}</span><span aria-hidden="true">⌄</span>
 </button>
 @if(open){
  <div class="select-panel" [class.above]="above" [style.max-height.px]="panelHeight">
   <div class="search-wrap"><input #search type="search" [value]="query" (input)="filter($event)" (change)="$event.stopPropagation()" (keydown)="searchKey($event)"
    [placeholder]="searchPlaceholder" [attr.aria-label]="'Buscar '+label" role="combobox" aria-autocomplete="list" aria-expanded="true"
    [attr.aria-controls]="id" [attr.aria-activedescendant]="filtered.length?id+'-'+active:null" autocomplete="off"/></div>
   <div class="select-options" role="listbox" [id]="id" [attr.aria-label]="label">
    @for(option of filtered;track option.value;let i=$index){
     <button type="button" role="option" tabindex="-1" [id]="id+'-'+i" [attr.aria-selected]="option.value===value"
      [class.active]="i===active" (mousedown)="$event.preventDefault()" (click)="choose(option.value)">
      <span>{{option.label}}</span>@if(option.value===value){<span aria-hidden="true">✓</span>}
     </button>
    }@empty{<p class="no-results" role="status">No hay coincidencias.</p>}
   </div>
  </div>
 }`,
 styleUrl:'./search-select.component.css'
})
export class SearchSelectComponent implements ControlValueAccessor {
 @Input() options:SelectOption[]=[];
 @Input() label='opción';
 @Input() placeholder='Seleccionar…';
 @Input() searchPlaceholder='Buscar…';
 @ViewChild('trigger') trigger?:ElementRef<HTMLButtonElement>;
 @ViewChild('search') search?:ElementRef<HTMLInputElement>;
 private readonly host:ElementRef<HTMLElement>=inject(ElementRef);
 readonly id='hse-select-'+nextSelectId++;
 value:number|string|null|undefined;disabled=false;open=false;query='';active=0;above=false;panelHeight=320;
 private change:(value:number|string)=>void=()=>{};
 private touched:()=>void=()=>{};
 get selected(){return this.options.find(option=>option.value===this.value);}
 private normalize(value:string){return value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase().trim();}
 get filtered(){const query=this.normalize(this.query);return this.options.filter(option=>this.normalize(option.label).includes(query));}
 writeValue(value:number|string|null|undefined){this.value=value;}
 registerOnChange(fn:(value:number|string)=>void){this.change=fn;}
 registerOnTouched(fn:()=>void){this.touched=fn;}
 setDisabledState(disabled:boolean){this.disabled=disabled;if(disabled)this.close();}
 toggle(){if(this.open)this.close();else this.show();}
 show(){
  if(this.disabled)return;
  this.query='';this.active=Math.max(0,this.options.findIndex(option=>option.value===this.value));
  const rect=this.host.nativeElement.getBoundingClientRect();
  const modal=this.host.nativeElement.closest('.data-modal')?.getBoundingClientRect();
  const below=Math.min(window.innerHeight,modal?.bottom??window.innerHeight)-rect.bottom-12;
  const above=rect.top-Math.max(0,modal?.top??0)-12;
  this.above=below<260&&above>below;this.panelHeight=Math.min(320,Math.max(140,this.above?above:below));
  this.open=true;setTimeout(()=>{if(this.open){this.search?.nativeElement.focus();this.scrollActive();}});
 }
 close(focus=false){this.open=false;this.touched();if(focus)this.trigger?.nativeElement.focus();}
 choose(value:number|string){if(this.disabled)return;this.value=value;this.change(value);this.close(true);}
 filter(event:Event){this.query=(event.target as HTMLInputElement).value;this.active=0;event.stopPropagation();}
 triggerKey(event:KeyboardEvent){if(['ArrowDown','ArrowUp'].includes(event.key)){event.preventDefault();this.show();}}
 searchKey(event:KeyboardEvent){
  if(event.key==='Escape'){event.preventDefault();event.stopPropagation();this.close(true);}
  else if(event.key==='ArrowDown'||event.key==='ArrowUp'){
   event.preventDefault();this.active=Math.max(0,Math.min(this.filtered.length-1,this.active+(event.key==='ArrowDown'?1:-1)));this.scrollActive();
  }else if(event.key==='Enter'){
   event.preventDefault();event.stopPropagation();const option=this.filtered[this.active];if(option)this.choose(option.value);
  }
 }
 private scrollActive(){this.host.nativeElement.querySelector<HTMLElement>('#'+this.id+'-'+this.active)?.scrollIntoView({block:'nearest'});}
 @HostListener('document:click',['$event']) outside(event:MouseEvent){if(this.open&&!this.host.nativeElement.contains(event.target as Node))this.close();}
 @HostListener('focusout',['$event']) blur(event:FocusEvent){if(!this.host.nativeElement.contains(event.relatedTarget as Node)){this.close();}}
}
