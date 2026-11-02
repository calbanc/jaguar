import { TestBed } from '@angular/core/testing';
import { TrainingComponent } from './training.component';
import { ApiService } from '../core/api.service';

describe('Capacitaciones',()=>{
 let component:TrainingComponent;let api:ApiService;
 beforeEach(async()=>{await TestBed.configureTestingModule({imports:[TrainingComponent]}).compileComponents();component=TestBed.createComponent(TrainingComponent).componentInstance;api=TestBed.inject(ApiService);api.company.set({id:7});api.permissions.set(['capacitaciones.ver','capacitaciones.crear','capacitaciones.editar']);});
 it('requests the next number for the company and saves the parent without a client number or participants',async()=>{
  const request=spyOn(api,'request').and.resolveTo({siguiente_numero:5,fecha:'2026-10-23',responsable_actual:9});
  await component.newPlanilla();expect(request).toHaveBeenCalledWith('capacitaciones/opciones?empresa_id=7');expect(component.number).toBe(5);
  request.and.resolveTo({data:{id:80,numero_planilla:6}});await component.save();const body=request.calls.mostRecent().args[2] as Record<string,unknown>;expect(body['empresa_id']).toBe(7);expect(body['numero_planilla']).toBeUndefined();expect(body['trabajadores']).toBeUndefined();expect(component.id).toBe(80);expect(component.number).toBe(6);
 });
 it('does not save while a typed number has not been searched, and loads detail on Enter',async()=>{
  component.number=4;component.loadedNumber=8;component.form={activo:true};const request=spyOn(api,'request').and.callFake(async(path)=>path.includes('planillas/')?{data:{id:40,numero_planilla:4,hora_inicio:'09:00:00',hora_termino:'10:00:00',activo:true}}:{data:[{id:1,numero_documento:'01234567'}]});
  await component.save();expect(request).not.toHaveBeenCalled();await component.find(new KeyboardEvent('keydown',{key:'Enter'}));expect(component.id).toBe(40);expect(component.form['hora_inicio']).toBe('09:00');expect(component.participants.length).toBe(1);expect(component.pendingSearch).toBeFalse();
 });
 it('filters all participants by document, name and signature status',()=>{
  component.participants=[{id:1,numero_documento:'01234567',nombres:'Ana',firma_fecha:null},{id:2,numero_documento:'12345678',nombres:'Luis',firma_fecha:'2026-10-23'}];component.filters={firma_estado:'Pendiente'};expect(component.filtered.map(r=>r['id'])).toEqual([1]);component.filters={};component.search='12345678';expect(component.filtered.map(r=>r['id'])).toEqual([2]);
 });
 it('renders the next number and only searches when Enter is pressed',async()=>{
  const request=spyOn(api,'request').and.resolveTo({siguiente_numero:5,fecha:'2026-10-23',responsable_actual:9});const fixture=TestBed.createComponent(TrainingComponent);fixture.detectChanges();await fixture.whenStable();fixture.detectChanges();await fixture.whenStable();const input=fixture.nativeElement.querySelector('input[type=number]') as HTMLInputElement;
  expect(input.value).toBe('5');request.calls.reset();input.value='2';input.dispatchEvent(new Event('input'));fixture.detectChanges();expect(request).not.toHaveBeenCalled();request.and.resolveTo({data:{id:2,numero_planilla:2,hora_inicio:'08:00:00',hora_termino:'09:00:00'}});input.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter'}));await fixture.whenStable();expect(request.calls.first().args[0]).toBe('capacitaciones/planillas/2?empresa_id=7');
 });
 it('refuses empty signatures and sends a drawn PNG with the company',async()=>{
  const fixture=TestBed.createComponent(TrainingComponent);const c=fixture.componentInstance;spyOn(api,'request').and.resolveTo({siguiente_numero:1,fecha:'2026-10-23',responsable_actual:9});fixture.detectChanges();await fixture.whenStable();c.id=10;c.openSignature({id:3,numero_documento:'01234567'});fixture.detectChanges();const request=api.request as jasmine.Spy;request.calls.reset();await c.saveSignature();expect(request).not.toHaveBeenCalled();
  c.hasInk=true;request.and.callFake(async(path:string)=>path.includes('/firma')?{message:'Guardado'}:{data:[]});await c.saveSignature();const call=request.calls.first();expect(call.args[0]).toBe('capacitaciones/capacitacion/10/participantes/3/firma');const body=call.args[2] as FormData;expect(body.get('empresa_id')).toBe('7');expect((body.get('firma') as File).name).toBe('01234567.png');expect(c.signing).toBeNull();
 });
 it('opens a modal dialog for signing, blocks Escape while saving and closes afterward',async()=>{
  spyOn(api,'request').and.resolveTo({siguiente_numero:1});const fixture=TestBed.createComponent(TrainingComponent);fixture.detectChanges();await fixture.whenStable();const c=fixture.componentInstance;c.openSignature({id:3,nombres:'Ana'});fixture.detectChanges();const dialog=fixture.nativeElement.querySelector('dialog[aria-labelledby=signature-title]') as HTMLDialogElement;expect(dialog.open).toBeTrue();expect(dialog.querySelector('canvas')).not.toBeNull();c.saving.set(true);dialog.dispatchEvent(new Event('cancel',{cancelable:true}));expect(dialog.open).toBeTrue();c.saving.set(false);dialog.dispatchEvent(new Event('cancel',{cancelable:true}));expect(dialog.open).toBeFalse();expect(c.signing).toBeNull();
 });
 it('loads private signatures into table thumbnails and revokes them on cleanup',async()=>{
  spyOn(api,'request').and.resolveTo({siguiente_numero:1});const download=spyOn(api,'downloadBlob').and.resolveTo(new Blob(['image'],{type:'image/png'}));const fixture=TestBed.createComponent(TrainingComponent);fixture.detectChanges();await fixture.whenStable();const c=fixture.componentInstance;c.id=10;c.participants=[{id:3,nombres:'Ana',apellidos:'Prueba',firma_fecha:'2026-10-23'}];await c.loadSignatureImages();fixture.detectChanges();expect(download).toHaveBeenCalledWith('capacitaciones/capacitacion/10/participantes/3/firma?empresa_id=7');const img=fixture.nativeElement.querySelector('tbody .signature-thumbnail img') as HTMLImageElement;expect(img).not.toBeNull();expect(img.src).toBe(c.signatureImages()[3]);const revoke=spyOn(URL,'revokeObjectURL');const url=c.signatureImages()[3];c.ngOnDestroy();expect(revoke).toHaveBeenCalledWith(url);expect(c.signatureImages()).toEqual({});
 });

 it('previews the worker signature and applies it only when selected',async()=>{
  component.id=10;component.number=component.loadedNumber=1;component.form={activo:true};component.participants=[{id:3,firma_disponible:true,firma_fecha:null}];const download=spyOn(api,'downloadBlob').and.resolveTo(new Blob(['signature'],{type:'image/png'}));const request=spyOn(api,'request').and.resolveTo({data:[]});await component.loadSignatureImages();expect(download).toHaveBeenCalledWith('capacitaciones/capacitacion/10/participantes/3/firma?empresa_id=7&fuente=trabajador');expect(request).not.toHaveBeenCalled();await component.viewSignature(component.participants[0]);expect(component.previewExisting).toBeTrue();await component.useExistingSignature();expect(request).toHaveBeenCalledWith('capacitaciones/capacitacion/10/participantes/3/firma','POST',{empresa_id:7,usar_guardada:true});component.ngOnDestroy();
 });

 it('opens the report picker from the training screen and downloads the saved format for that planilla',async()=>{
  api.permissions.set(['capacitaciones.ver','capacitaciones.descargar']);
  const request=spyOn(api,'request').and.callFake(async(path:string)=>path.includes('/formatos')?{data:[{id:12,nombre:'informa capacitaciones jaguar'}]}:{siguiente_numero:5});
  const fixture=TestBed.createComponent(TrainingComponent);fixture.detectChanges();await fixture.whenStable();const c=fixture.componentInstance;
  c.id=80;c.number=c.loadedNumber=6;fixture.detectChanges();
  const button=Array.from(fixture.nativeElement.querySelectorAll('header button') as NodeListOf<HTMLButtonElement>).find(b=>b.textContent?.includes('Generar informe'))!;
  expect(button.disabled).toBeFalse();button.click();await fixture.whenStable();fixture.detectChanges();
  const dialog=fixture.nativeElement.querySelector('.report-dialog') as HTMLDialogElement;expect(dialog.open).toBeTrue();expect(dialog.textContent).toContain('informa capacitaciones jaguar');expect(c.reportFormatId).toBe(12);
  expect(request).toHaveBeenCalledWith('capacitaciones/formatos?empresa_id=7');
  const download=spyOn(api,'downloadBlob').and.resolveTo(new Blob(['%PDF-1.4'],{type:'application/pdf'}));const click=spyOn(HTMLAnchorElement.prototype,'click');
  await c.downloadReport();expect(download).toHaveBeenCalledWith('capacitaciones/formatos/12/pdf?empresa_id=7&numero_planilla=6');expect(click).toHaveBeenCalled();expect(dialog.open).toBeFalse();fixture.destroy();
 });
 it('blocks reports for new or unsearched planillas and users without download permission',async()=>{
  const request=spyOn(api,'request');component.number=component.loadedNumber=1;await component.openReport();expect(request).not.toHaveBeenCalled();
  component.id=10;await component.openReport();expect(request).not.toHaveBeenCalled();api.permissions.set(['capacitaciones.descargar']);component.number=2;await component.openReport();expect(request).not.toHaveBeenCalled();
 });
 it('handles empty formats and download failures without losing the selected format',async()=>{
  api.permissions.set(['capacitaciones.ver','capacitaciones.descargar']);component.id=10;component.number=component.loadedNumber=1;
  const request=spyOn(api,'request').and.resolveTo({data:[]});await component.openReport();expect(component.reportFormatId).toBeNull();const download=spyOn(api,'downloadBlob').and.rejectWith(new Error('No se pudo generar el PDF.'));await component.downloadReport();expect(download).not.toHaveBeenCalled();
  request.and.resolveTo({data:[{id:4,nombre:'Modelo'}]});await component.openReport();await component.downloadReport();expect(component.reportError()).toContain('No se pudo generar el PDF');expect(component.reportFormatId).toBe(4);expect(component.reportDownloading()).toBeFalse();
 });

});
