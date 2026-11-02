import { TestBed } from '@angular/core/testing';
import { WorkersComponent } from './workers.component';
import { ApiService } from '../core/api.service';
describe('Workers',()=>{
 let component:WorkersComponent;let api:ApiService;
 beforeEach(async()=>{await TestBed.configureTestingModule({imports:[WorkersComponent]}).compileComponents();component=TestBed.createComponent(WorkersComponent).componentInstance;api=TestBed.inject(ApiService);api.permissions.set(['organizacion.ver','organizacion.crear','organizacion.editar','organizacion.eliminar']);});
 it('saves only editable worker fields and preserves leading zeroes',async()=>{
  component.form={tipo_documento:'DNI',numero_documento:'01234567',nombres:'Ana',apellidos:'Torres',puesto:'Operadora',fecha_ingreso:'2026-01-15',fecha_cese:null,periodo_desde:'2026-02-01',periodo_hasta:'2026-07-31',zona_id:3,area_id:null,empresa_id:99,miembro_id:99};
  const request=spyOn(api,'request').and.resolveTo({data:{id:1}});spyOn(component,'load').and.resolveTo();
  await component.save();const body=request.calls.mostRecent().args[2] as any;
  expect(body.asignacion_inicial).toEqual({fecha_inicio:'2026-02-01',fecha_fin:'2026-07-31'});expect(body.numero_documento).toBe('01234567');expect(body.empresa_id).toBeUndefined();expect(body.miembro_id).toBeUndefined();
 });
 it('defaults the first period to employment start without overwriting a custom date',()=>{
  component.open('edit');component.changeEmploymentStart('2026-01-01');expect(component.form['periodo_desde']).toBe('2026-01-01');
  component.changeEmploymentStart('2026-01-15');expect(component.form['periodo_desde']).toBe('2026-01-15');
  component.form['periodo_desde']='2026-02-01';component.changeEmploymentStart('2026-01-20');expect(component.form['periodo_desde']).toBe('2026-02-01');
 });
 it('filters by site and dependent zone and resets the zone when switching sites',async()=>{
  component.zones.set([{id:10,sede_id:1},{id:20,sede_id:2}]);
  const list=spyOn(api,'list').and.resolveTo({data:[],total:0,current_page:1,last_page:1});
  await component.selectFilterSite(1);expect(component.filterZones.map(row=>row['id'])).toEqual([10]);
  await component.selectFilterZone(10);expect(list.calls.mostRecent().args[2]).toContain('&sede_id=1&zona_id=10');
  await component.selectFilterSite(2);expect(component.filterZoneId).toBeNull();expect(list.calls.mostRecent().args[2]).not.toContain('&zona_id=');
  expect(component.page).toBe(1);await component.clear();expect(component.filtered).toBeFalse();
 });
 it('opens the new period form directly for the chosen worker',async()=>{
  const worker={id:7,nombres:'Ana'};spyOn(api,'request').and.resolveTo({data:[],trabajador:worker});
  await component.addPeriod(worker);expect(component.modal()).toBe('history');expect(component.historyEditor()).toBeTrue();expect(component.selected()?.['id']).toBe(7);expect(component.historyEditing()).toBeNull();
 });
 it('searches workers independently from list location filters and selects one for periods',async()=>{
  component.filterSiteId=9;component.filterZoneId=10;component.switchTab('periods');component.workerSearch='01234567';
  const worker={id:7,numero_documento:'01234567',nombres:'Ana'};
  const list=spyOn(api,'list').and.resolveTo({data:[worker],total:1,current_page:1,last_page:1});spyOn(api,'request').and.resolveTo({data:[],trabajador:worker});
  await component.searchWorkers();expect(list.calls.mostRecent().args[2]).toContain('q=01234567');expect(list.calls.mostRecent().args[2]).not.toContain('sede_id');
  await component.chooseWorker(worker);expect(component.selected()?.['id']).toBe(7);expect(component.modal()).toBe('history');expect(component.isOverlay).toBeFalse();
 });
 it('preserves the registration draft when visiting another tab',()=>{
  component.open('edit');component.form['nombres']='Ana';component.siteId=3;
  component.switchTab('periods');expect(component.selected()).toBeNull();component.switchTab('register');
  expect(component.form['nombres']).toBe('Ana');expect(component.siteId).toBe(3);expect(component.modal()).toBe('edit');
 });
 it('ignores search results arriving after changing tabs',async()=>{
  component.switchTab('periods');component.workerSearch='Ana';let resolve!:(value:any)=>void;
  spyOn(api,'list').and.returnValue(new Promise(r=>resolve=r));const pending=component.searchWorkers();component.switchTab('list');
  resolve({data:[{id:1}],last_page:1});await pending;expect(component.searchDone()).toBeFalse();expect(component.searchRows()).toEqual([]);
 });
 it('requires a successful preview before confirming import',async()=>{
  component.form={zona_id:3,area_id:null};component.file=new File(['data'],'workers.xlsx');const request=spyOn(api,'request').and.resolveTo({total:2,validos:1,invalidos:1,filas:[]});
  await component.importFile(true);expect(request).not.toHaveBeenCalled();
  await component.importFile();expect((request.calls.mostRecent().args[2] as FormData).get('confirmar')).toBe('0');
  await component.importFile(true);expect(request).toHaveBeenCalledTimes(1);
  component.preview.set({total:1,validos:1,invalidos:0});request.and.resolveTo({importados:1,message:'Importado'});spyOn(component,'load').and.resolveTo();
  await component.importFile(true);expect((request.calls.mostRecent().args[2] as FormData).get('confirmar')).toBe('1');expect(component.notice()).toBe('Importado');
 });
 it('filters zones and areas by their parents and invalidates import previews on changes',()=>{
  component.sites.set([{id:1},{id:2}]);component.zones.set([{id:10,sede_id:1},{id:20,sede_id:2}]);component.areas.set([{id:100,zona_id:10},{id:200,zona_id:20}]);
  component.siteId=1;component.form={zona_id:10,area_id:100};component.preview.set({invalidos:0});
  expect(component.zonesForSite.map(row=>row['id'])).toEqual([10]);expect(component.areasForZone.map(row=>row['id'])).toEqual([100]);
  component.siteId=2;component.changeSite();expect(component.form['zona_id']).toBeNull();expect(component.form['area_id']).toBeNull();expect(component.preview()).toBeNull();
  expect(component.zonesForSite.map(row=>row['id'])).toEqual([20]);expect(component.areasForZone).toEqual([]);
 });
 it('requires a zone for manual registration and import and includes it in import requests',async()=>{
  const request=spyOn(api,'request').and.resolveTo({total:1,validos:1,invalidos:0,filas:[]});component.file=new File(['data'],'workers.xlsx');
  await component.save();await component.importFile();expect(request).not.toHaveBeenCalled();
  component.form={zona_id:3,area_id:5};await component.importFile();
  const body=request.calls.mostRecent().args[2] as FormData;expect(body.get('zona_id')).toBe('3');expect(body.get('area_id')).toBe('5');
 });
 it('edits personal data without overwriting the dated location history',async()=>{
  component.selected.set({id:7});component.form={tipo_documento:'DNI',numero_documento:'71462409',nombres:'Persona',apellidos:'Prueba',puesto:'Operador',fecha_ingreso:'2026-01-01',fecha_cese:null,zona_id:9,area_id:4,activo:true};
  const request=spyOn(api,'request').and.resolveTo({data:{id:7}});spyOn(component,'load').and.resolveTo();
  await component.save();const body=request.calls.mostRecent().args[2] as any;
  expect(body.asignacion_inicial).toBeUndefined();expect(body.zona_id).toBeUndefined();expect(body.area_id).toBeUndefined();expect(request.calls.mostRecent().args[0]).toBe('trabajadores/7');
 });
 it('registers a transfer on the same worker and preserves the previous period through the explicit close option',async()=>{
  component.selected.set({id:7});component.locationDate='2026-08-01';component.editAssignment();component.form={zona_id:2,area_id:4,fecha_inicio:'2026-08-01',fecha_fin:'2026-12-31',observaciones:'Traslado',cerrar_anterior:true};
  const request=spyOn(api,'request').and.resolveTo({data:{id:8}});spyOn(component,'loadHistory').and.resolveTo();spyOn(component,'load').and.resolveTo();
  await component.saveAssignment();
  expect(request).toHaveBeenCalledWith('trabajadores/7/asignaciones','POST',{zona_id:2,area_id:4,fecha_inicio:'2026-08-01',fecha_fin:'2026-12-31',observaciones:'Traslado',cerrar_anterior:true});
  expect(component.historyEditor()).toBeFalse();
 });
 it('keeps a conflicting period open for correction and does not replace another assignment',async()=>{
  component.selected.set({id:7});component.editAssignment({id:8,sede_id:2,zona_id:3,area_id:null,fecha_inicio:'2026-08-01',fecha_fin:null,observaciones:''});
  const request=spyOn(api,'request').and.rejectWith(new Error('El período se superpone con otra asignación.'));
  await component.saveAssignment();expect(component.historyEditor()).toBeTrue();expect(component.error()).toContain('superpone');expect(component.siteId).toBe(2);
  expect(request.calls.mostRecent().args[0]).toBe('trabajadores/7/asignaciones/8');expect((request.calls.mostRecent().args[2] as any).cerrar_anterior).toBeUndefined();
 });
 it('uses the reference date when listing and filtering historical worker locations',async()=>{
  const list=spyOn(api,'list').and.resolveTo({data:[],total:0,current_page:1,last_page:1});
  component.filters={sede_nombre:'Piura'};await component.changeLocationDate('2026-07-31');
  expect(list.calls.mostRecent().args[2]).toContain('fecha_ubicacion=2026-07-31');expect(list.calls.mostRecent().args[2]).toContain('columns[sede_nombre]=Piura');
 });
 it('clears validation when choosing another file and rejects unsupported files',()=>{
  component.preview.set({invalidos:0});component.selectFile({target:{files:[new File(['data'],'workers.csv')]}} as unknown as Event);
  expect(component.preview()).toBeNull();expect(component.file).toBeNull();expect(component.error()).toContain('.xlsx');
 });
 it('does not open creation or deletion without the corresponding permission',()=>{
  api.permissions.set(['organizacion.ver']);component.open('edit');expect(component.modal()).toBeNull();component.open('remove',{id:1});expect(component.modal()).toBeNull();component.open('import');expect(component.modal()).toBeNull();
 });
});
