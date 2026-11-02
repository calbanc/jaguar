import { TestBed } from '@angular/core/testing';
import { DeliveryReportComponent } from './delivery-report.component';
import { ApiService } from '../core/api.service';
describe('Delivery report',()=>{
 let component:DeliveryReportComponent;let api:ApiService;
 beforeEach(async()=>{await TestBed.configureTestingModule({imports:[DeliveryReportComponent]}).compileComponents();component=TestBed.createComponent(DeliveryReportComponent).componentInstance;api=TestBed.inject(ApiService);});
 it('selects a planilla and requests its PDF without date or location filters',async()=>{
  spyOn(api,'request').and.resolveTo({planillas:[{numero:1,fecha:'2026-10-10',zona:'Zona norte',responsable:'Ana',trabajadores:20}]});
  await component.load();component.numero=1;expect(component.selected?.['trabajadores']).toBe(20);expect(component.query).toBe('planilla_numero=1');
  const download=spyOn(api,'downloadBlob').and.rejectWith(new Error('Sin permiso'));api.permissions.set(['epp.descargar']);await component.download();expect(download).toHaveBeenCalledWith('informe-entregas/pdf?planilla_numero=1');expect(component.error()).toBe('Sin permiso');expect(component.busy()).toBeFalse();
 });
 it('requires an existing planilla and download permission',async()=>{
  component.options={planillas:[{numero:1}]};const download=spyOn(api,'downloadBlob');
  component.numero=1;await component.download();expect(download).not.toHaveBeenCalled();
  api.permissions.set(['epp.descargar']);component.numero=2;await component.download();component.numero=null;await component.download();expect(download).not.toHaveBeenCalled();
 });
 it('downloads a file named after the selected planilla',async()=>{
  component.options={planillas:[{numero:7}]};component.numero=7;api.permissions.set(['epp.descargar']);
  spyOn(api,'downloadBlob').and.resolveTo(new Blob(['pdf']));spyOn(URL,'createObjectURL').and.returnValue('blob:test');spyOn(URL,'revokeObjectURL');
  const anchor=document.createElement('a');spyOn(anchor,'click');spyOn(document,'createElement').and.returnValue(anchor);
  await component.download();expect(anchor.download).toBe('planilla-entrega-7.pdf');expect(anchor.click).toHaveBeenCalled();expect(component.busy()).toBeFalse();
 });
});
