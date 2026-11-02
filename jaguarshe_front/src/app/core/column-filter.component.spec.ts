import { fakeAsync, tick, TestBed } from '@angular/core/testing';
import { ColumnFilterComponent } from './column-filter.component';
describe('Column filters',()=>{
  it('debounces typing without cancelling on options refresh',fakeAsync(()=>{
    const component=new ColumnFilterComponent();const emit=spyOn(component.valueChange,'emit');
    component.change('A');tick(100);component.change('Ana');component.ngOnChanges({options:{} as any});tick(349);
    expect(emit).not.toHaveBeenCalled();tick(1);expect(emit).toHaveBeenCalledOnceWith('Ana');
    component.ngOnDestroy();
  }));
  it('cancels pending input when the table is destroyed',fakeAsync(()=>{
    const component=new ColumnFilterComponent();const emit=spyOn(component.valueChange,'emit');
    component.change('Ana');component.ngOnDestroy();tick(400);expect(emit).not.toHaveBeenCalled();
  }));
  it('shows only a funnel until clicked and applies the popup filter',async()=>{
    await TestBed.configureTestingModule({imports:[ColumnFilterComponent]}).compileComponents();
    const fixture=TestBed.createComponent(ColumnFilterComponent);fixture.componentRef.setInput('label','Nombre');fixture.detectChanges();
    const trigger=fixture.nativeElement.querySelector('.filter-trigger') as HTMLButtonElement;
    const panel=fixture.nativeElement.querySelector('[popover]') as HTMLDivElement;
    expect(trigger.getAttribute('aria-label')).toBe('Filtrar Nombre');expect(getComputedStyle(panel).display).toBe('none');trigger.click();fixture.detectChanges();expect(panel.matches(':popover-open')).toBeTrue();
    const emit=spyOn(fixture.componentInstance.valueChange,'emit');const input=panel.querySelector('input')!;input.value='Ana';input.dispatchEvent(new Event('input'));(panel.querySelector('.filter-actions button:last-child') as HTMLButtonElement).click();expect(emit).toHaveBeenCalledWith('Ana');expect(panel.matches(':popover-open')).toBeFalse();
  });
  it('preserves the visible global search when inline is enabled',async()=>{
    await TestBed.configureTestingModule({imports:[ColumnFilterComponent]}).compileComponents();const fixture=TestBed.createComponent(ColumnFilterComponent);fixture.componentRef.setInput('inline',true);fixture.detectChanges();expect(fixture.nativeElement.querySelector('.filter-trigger')).toBeNull();expect(fixture.nativeElement.querySelector('input[type=search]')).not.toBeNull();
  });

});
