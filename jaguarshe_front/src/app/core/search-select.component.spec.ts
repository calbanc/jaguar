import { Component } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormsModule } from '@angular/forms';
import { SearchSelectComponent } from './search-select.component';

@Component({standalone:true,imports:[FormsModule,SearchSelectComponent],template:`
<form #form="ngForm"><label>EPP<hse-search-select name="epp" label="EPP" [options]="options" [(ngModel)]="value" [disabled]="disabled" required/></label><button id="save" [disabled]="form.invalid">Guardar</button></form><button id="outside">Fuera</button>`})
class HostComponent {
 value:number|undefined;disabled=false;
 options=[{value:1,label:'C-01 · Casco · Única'},{value:2,label:'G-02 · Guantes · L'}];
}

describe('Search select',()=>{
 let fixture:ComponentFixture<HostComponent>;
 const element=(selector:string)=>fixture.nativeElement.querySelector(selector) as HTMLElement;
 const input=()=>element('input') as HTMLInputElement;
 async function render(){fixture.detectChanges();await fixture.whenStable();fixture.detectChanges();}
 async function open(){element('.select-trigger').click();await render();}
 async function filter(value:string){input().value=value;input().dispatchEvent(new Event('input',{bubbles:true}));await render();}
 beforeEach(async()=>{await TestBed.configureTestingModule({imports:[HostComponent]}).compileComponents();fixture=TestBed.createComponent(HostComponent);await render();});
 it('places the filter inside the opened selector and preserves required validation until selection',async()=>{
  expect(element('input')).toBeNull();expect((element('#save') as HTMLButtonElement).disabled).toBeTrue();
  await open();expect(element('.select-panel input')).not.toBeNull();await filter('unica');
  expect(fixture.nativeElement.querySelectorAll('[role=option]').length).toBe(1);expect(element('[role=option]').textContent).toContain('Casco');expect(fixture.componentInstance.value).toBeUndefined();
  element('[role=option]').click();await render();expect(fixture.componentInstance.value).toBe(1);expect(element('.select-panel')).toBeNull();expect(element('.select-trigger').textContent).toContain('Casco');expect((element('#save') as HTMLButtonElement).disabled).toBeFalse();
 });
 it('keeps the selected value when filtering or closing without choosing',async()=>{
  fixture.componentInstance.value=1;await render();await open();await filter('G-02');expect(fixture.componentInstance.value).toBe(1);
  element('#outside').click();await render();expect(element('.select-panel')).toBeNull();expect(fixture.componentInstance.value).toBe(1);
  await open();expect(input().value).toBe('');await filter('no existe');expect(element('[role=status]').textContent).toContain('No hay coincidencias');
  input().dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));await render();expect(element('.select-panel')).toBeNull();expect(fixture.componentInstance.value).toBe(1);
 });
 it('supports keyboard navigation and blocks disabled interaction',async()=>{
  await open();input().dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true}));await render();input().dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));await render();expect(fixture.componentInstance.value).toBe(2);
  fixture.componentInstance.disabled=true;await render();expect((element('.select-trigger') as HTMLButtonElement).disabled).toBeTrue();element('.select-trigger').click();await render();expect(element('.select-panel')).toBeNull();
 });
});
