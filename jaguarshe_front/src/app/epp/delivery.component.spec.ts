import { TestBed } from '@angular/core/testing';
import { DeliveryComponent } from './delivery.component';
import { ApiService } from '../core/api.service';
describe('EPP delivery',()=>{
 let component:DeliveryComponent;let api:ApiService;
 beforeEach(async()=>{await TestBed.configureTestingModule({imports:[DeliveryComponent]}).compileComponents();component=TestBed.createComponent(DeliveryComponent).componentInstance;api=TestBed.inject(ApiService);api.permissions.set(['epp.crear']);});
 it('submits all fields atomically and retains its retry key after an error',async()=>{
  component.loading.set(false);component.form={uuid_cliente:'same-key',trabajador_id:1,epp_id:2,almacen_id:3,cantidad:4,motivo:'reposicion',fecha:'2026-10-09',hora:'09:30',entregado_por:5};
  const request=spyOn(api,'request').and.rejectWith(new Error('Stock insuficiente'));await component.save();expect(component.error()).toContain('Stock');expect(component.form['uuid_cliente']).toBe('same-key');
  request.and.resolveTo({data:{id:1},planilla_numero:1});const saved=spyOn(component.saved,'emit');await component.save();expect(request.calls.mostRecent().args[0]).toBe('entregas-epp');expect(request.calls.mostRecent().args[2]).toEqual({...component.form,observacion:undefined});expect(saved).toHaveBeenCalledWith('Planilla N.º 1. Entrega registrada. Se descontaron las existencias del almacén.');
 });
 it('uses company local time and supports DNI search and warehouse-specific stock',async()=>{
  spyOn(api,'request').and.resolveTo({fecha:'2026-10-09',hora:'09:30',responsable_actual:7,trabajadores:[{id:1,numero_documento:'01234567',nombres:'Ana',apellidos:'Prueba'}],stock:[{epp_id:2,almacen_id:3,disponible:'8.000'}]});await component.loadOptions();expect(component.form['entregado_por']).toBe(7);expect(component.form['hora']).toBe('09:30');expect(component.workerOptions[0].label).toContain('01234567');component.form['epp_id']=2;component.form['almacen_id']=3;expect(component.stock).toBe(8);component.form['almacen_id']=4;expect(component.stock).toBe(0);
 });
 it('requires a valid preview for bulk confirmation and sends shared fields plus the file',async()=>{
  component.loading.set(false);component.changeMode(true);component.file=new File(['excel'],'dni.xlsx');component.form={uuid_cliente:'batch-key',epp_id:2,almacen_id:3,cantidad:2,motivo:'nuevo',fecha:'2026-10-09',hora:'10:00',entregado_por:7};
  const request=spyOn(api,'request').and.resolveTo({total:2,invalidos:0,cantidad_total:'4.000',stock_suficiente:true,validacion:'validated-hash',filas:[]});
  await component.saveBulk(true);expect(request).not.toHaveBeenCalled();await component.saveBulk();expect(component.preview()?.['total']).toBe(2);
  request.and.resolveTo({registrados:2,planilla_numero:2,message:'2 entregas registradas'});await component.saveBulk(true);
  const body=request.calls.mostRecent().args[2] as FormData;expect(body.get('confirmar')).toBe('1');expect(body.get('cantidad')).toBe('2');expect(body.get('validacion')).toBe('validated-hash');expect(body.has('trabajador_id')).toBeFalse();
  component.invalidate();expect(component.preview()).toBeNull();
 });
 it('clears the preview when a different Excel is chosen and rejects unsupported files',()=>{
  component.preview.set({validacion:'old'});component.selectFile({target:{files:[new File(['data'],'dni.csv')]}} as unknown as Event);expect(component.preview()).toBeNull();expect(component.file).toBeNull();expect(component.error()).toContain('.xlsx');
 });
 it('shows the next automatic reference as readonly in both delivery modes',async()=>{
  spyOn(api,'request').and.resolveTo({proxima_planilla:12,trabajadores:[],epps:[],almacenes:[],responsables:[],fecha:'2026-10-14',hora:'09:00'});
  const fixture=TestBed.createComponent(DeliveryComponent);fixture.detectChanges();await fixture.whenStable();fixture.detectChanges();
  const reference=fixture.nativeElement.querySelector('#delivery-reference') as HTMLInputElement;
  expect(reference.value).toBe('12');expect(reference.readOnly).toBeTrue();
  fixture.componentInstance.changeMode(true);fixture.detectChanges();expect(reference.value).toBe('12');
  expect(fixture.componentInstance.form['proxima_planilla']).toBeUndefined();
 });

});
