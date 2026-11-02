import { Component, ElementRef, EventEmitter, Input, OnChanges, OnDestroy, Output, SimpleChanges, ViewChild } from '@angular/core';
import { IconComponent } from './icon.component';
export interface FilterOption { value: string; label: string; }
@Component({
  selector:'hse-column-filter', standalone:true, imports:[IconComponent],host:{'[class.inline-filter]':'inline'},
  template:`@if(inline){<input class="filter-control" type="search" [attr.aria-label]="'Filtrar '+label" [placeholder]="placeholder" [value]="value" maxlength="150" (input)="change($any($event.target).value)" (keydown.enter)="flush($event)"/>}@else{
   <button #trigger type="button" class="filter-trigger" [class.active]="!!value" [attr.aria-label]="'Filtrar '+label" [title]="'Filtrar '+label+(value?' (activo)':'')" [attr.aria-expanded]="open" (click)="toggle()"><hse-icon name="filter"/></button>
   <div #panel popover="auto" class="filter-panel" [style.left.px]="left" [style.top.px]="top" (toggle)="open=$any($event).newState==='open'">
    <label><span>Filtrar {{label}}</span>@if(options.length){<select class="filter-control" [value]="value" (change)="change($any($event.target).value,true)"><option value="">Todos</option>@for(option of options;track option.value){<option [value]="option.value">{{option.label}}</option>}</select>}@else{<input class="filter-control" type="search" [placeholder]="placeholder" [value]="value" maxlength="150" (input)="change($any($event.target).value)" (keydown.enter)="flush($event);close()"/>}</label>
    <div class="filter-actions"><button type="button" (click)="change('',true);close()">Limpiar</button><button type="button" (click)="flush($event);close()">Aplicar</button></div>
   </div>
  }`,
  styleUrl:'./column-filter.component.css',
})
export class ColumnFilterComponent implements OnDestroy, OnChanges {
  @Input() label=''; @Input() placeholder='Filtrar…'; @Input() value=''; @Input() options:FilterOption[]=[]; @Input() inline=false;
  @Output() valueChange=new EventEmitter<string>();
  @ViewChild('trigger') trigger?:ElementRef<HTMLButtonElement>;
  @ViewChild('panel') panel?:ElementRef<HTMLDivElement>;
  open=false;left=0;top=0;
  private timer?:ReturnType<typeof setTimeout>; private pending='';
  toggle(){const panel=this.panel?.nativeElement,trigger=this.trigger?.nativeElement;if(!panel||!trigger)return;if(panel.matches(':popover-open')){this.close();return;}const rect=trigger.getBoundingClientRect();this.left=Math.max(8,Math.min(rect.right-250,window.innerWidth-266));this.top=rect.bottom+170<window.innerHeight?rect.bottom+6:Math.max(8,rect.top-170);panel.showPopover();this.open=true;panel.querySelector<HTMLElement>('input,select')?.focus();}
  close(){this.panel?.nativeElement.hidePopover();this.open=false;this.trigger?.nativeElement.focus();}
  change(value:string, immediate=false){clearTimeout(this.timer);this.pending=value;if(immediate)this.valueChange.emit(value);else this.timer=setTimeout(()=>this.valueChange.emit(this.pending),350);}
  flush(event:Event){event.preventDefault();clearTimeout(this.timer);this.valueChange.emit(this.pending);}
  ngOnChanges(changes:SimpleChanges){if(changes['value']||changes['label']){clearTimeout(this.timer);this.pending=this.value;}}
  ngOnDestroy(){clearTimeout(this.timer);}
}
