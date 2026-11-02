import { TestBed } from '@angular/core/testing';
import { TrainingReportDesignerComponent } from './training-report-designer.component';
import { ApiService } from '../core/api.service';

describe('Training report designer',()=>{
 async function setup(){
  await TestBed.configureTestingModule({imports:[TrainingReportDesignerComponent]}).compileComponents();
  const api=TestBed.inject(ApiService);api.company.set({id:7});api.permissions.set(['capacitaciones.ver','capacitaciones.crear','capacitaciones.editar']);
  const request=spyOn(api,'request').and.callFake(async(url:string)=>url.includes('/campos')?{campos:{planilla:{label:'Planilla',fields:{numero_planilla:'Número'}}},columnas:{nombres:'Nombres',firma:'Firma'}}:{data:[]});
  const fixture=TestBed.createComponent(TrainingReportDesignerComponent);fixture.detectChanges();await fixture.whenStable();fixture.detectChanges();return {fixture,component:fixture.componentInstance,request};
 }
 it('inserts fields at the saved cursor without removing free text',async()=>{
  const {component,fixture}=await setup();const editor=component.editor!.nativeElement;editor.innerHTML='<p>Antes Después</p>';const text=editor.firstChild!.firstChild!;const range=document.createRange();range.setStart(text,6);range.collapse(true);const selection=window.getSelection()!;selection.removeAllRanges();selection.addRange(range);component.remember();component.insert('planilla','numero_planilla');expect(editor.textContent).toBe('Antes {{planilla.numero_planilla}}Después');expect(component.dirty).toBeTrue();fixture.destroy();
 });
 it('saves the document settings and ordered participant columns with the company',async()=>{
  const {component,fixture,request}=await setup();component.design.orientacion='horizontal';component.moveColumn(5,-1);const expected=[...component.design.columnas];request.and.callFake(async(_url:string,method?:string)=>method==='POST'?{data:{id:4,version:1,diseno:component.design}}:{data:[{id:4,nombre:'Formato'}]});await component.save();expect(request).toHaveBeenCalledWith('capacitaciones/formatos','POST',jasmine.objectContaining({empresa_id:7,diseno:jasmine.objectContaining({orientacion:'horizontal',columnas:expected})}));expect(component.selectedId).toBe(4);expect(component.dirty).toBeFalse();fixture.destroy();
 });
 it('keeps the edited draft when the server rejects a stale version',async()=>{
  const {component,fixture,request}=await setup();component.selectedId=9;component.version=2;component.design.html='<p>Mis cambios</p>';component.dirty=true;request.and.rejectWith(new Error('Otro usuario modificó este formato.'));await component.save();expect(component.error()).toContain('Otro usuario');expect(component.design.html).toBe('<p>Mis cambios</p>');expect(component.dirty).toBeTrue();fixture.destroy();
 });
 it('previews unsaved content using the selected planilla and company',async()=>{
  const {component,fixture,request}=await setup();component.numero=8;component.design.html='<p>Contenido nuevo</p>';request.and.resolveTo({html:'<!doctype html><html><body>Contenido nuevo</body></html>'});await component.preview();expect(request).toHaveBeenCalledWith('capacitaciones/formatos/vista-previa','POST',jasmine.objectContaining({empresa_id:7,numero_planilla:8,diseno:jasmine.objectContaining({html:'<p>Contenido nuevo</p>'})}));expect(component.previewUrl).not.toBeNull();component.closePreview();expect(component.previewUrl).toBeNull();fixture.destroy();
 });
 it('downloads the saved format by ID and planilla without submitting a client design',async()=>{
  const {component,fixture}=await setup();component.api.permissions.set(['capacitaciones.ver','capacitaciones.descargar']);component.selectedId=5;component.numero=8;const download=spyOn(component.api,'downloadBlob').and.resolveTo(new Blob(['%PDF-1.4'],{type:'application/pdf'}));const click=spyOn(HTMLAnchorElement.prototype,'click');await component.download();expect(download).toHaveBeenCalledWith('capacitaciones/formatos/5/pdf?empresa_id=7&numero_planilla=8');expect(click).toHaveBeenCalled();expect(component.notice()).toContain('PDF descargado');fixture.destroy();
 });
 it('requires saving changes before downloading the stored design',async()=>{
  const {component,fixture}=await setup();component.api.permissions.set(['capacitaciones.descargar']);component.selectedId=5;component.numero=8;component.dirty=true;const download=spyOn(component.api,'downloadBlob');await component.download();expect(download).not.toHaveBeenCalled();expect(component.error()).toContain('Guarda los cambios');fixture.destroy();
 });

});
