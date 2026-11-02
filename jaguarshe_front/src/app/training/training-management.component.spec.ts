import { TestBed } from '@angular/core/testing';
import { TrainingManagementComponent } from './training-management.component';
import { ApiService } from '../core/api.service';

describe('Gestión de capacitaciones',()=>{
 let component:TrainingManagementComponent;let api:ApiService;
 beforeEach(async()=>{await TestBed.configureTestingModule({imports:[TrainingManagementComponent]}).compileComponents();component=TestBed.createComponent(TrainingManagementComponent).componentInstance;api=TestBed.inject(ApiService);api.company.set({id:7});api.permissions.set(['capacitaciones.ver','capacitaciones.crear','capacitaciones.editar','capacitaciones.eliminar']);});
 it('uses each catalog endpoint with company for create, update and deactivate',async()=>{
  const request=spyOn(api,'request').and.resolveTo({data:[],last_page:1});
  for(const catalog of component.catalogs){await component.change(catalog.key);component.open('new');component.form={nombre:'Prueba'};await component.save();expect(request).toHaveBeenCalledWith('capacitaciones/'+catalog.key,'POST',jasmine.objectContaining({empresa_id:7,nombre:'Prueba'}));component.open('edit',{id:3,nombre:'Prueba',activo:true});component.form['nombre']='Editado';await component.save();expect(request).toHaveBeenCalledWith('capacitaciones/'+catalog.key+'/3','PATCH',jasmine.objectContaining({empresa_id:7,nombre:'Editado'}));component.removing={id:3};await component.deactivate();expect(request).toHaveBeenCalledWith('capacitaciones/'+catalog.key+'/3?empresa_id=7','DELETE');}
 });
 it('loads every page and filters name and inactive records',async()=>{
  spyOn(api,'request').and.callFake(async(path)=>path.includes('page=2')?{data:[{id:2,nombre:'Lima',activo:0}]}:{data:[{id:1,nombre:'Piura',activo:1}],last_page:2});await component.load();expect(component.rows.length).toBe(2);expect(component.filtered.length).toBe(1);component.status='';component.filters={nombre:'Lima'};expect(component.filtered[0]['id']).toBe(2);
 });
 it('preserves failed changes and respects permissions',async()=>{
  const request=spyOn(api,'request').and.rejectWith(new Error('Registro en uso'));component.removing={id:3};await component.deactivate();expect(component.error()).toBe('Registro en uso');expect(component.removing?.['id']).toBe(3);api.permissions.set(['capacitaciones.ver']);request.calls.reset();component.open('new');expect(component.mode).toBeNull();await component.deactivate();expect(request).not.toHaveBeenCalled();component.open('view',{id:3,nombre:'Tema'});expect(component.mode).toBe('view');
 });
 it('paginates filtered records and resets the page when a column changes',()=>{
  component.rows=Array.from({length:26},(_,i)=>({id:i+1,nombre:'Tema '+(i+1),activo:1}));
  expect(component.pageRows.length).toBe(10);expect(component.lastPage).toBe(3);component.goToPage(3);expect(component.pageRows.length).toBe(6);expect(component.firstRecord).toBe(21);expect(component.lastRecord).toBe(26);component.filterColumn('nombre','Tema 1');expect(component.currentPage).toBe(1);expect(component.filtered.length).toBe(11);component.goToPage(2);component.filterStatus('0');expect(component.pageRows.length).toBe(0);expect(component.firstRecord).toBe(0);expect(component.currentPage).toBe(1);component.clearFilters();expect(component.filtered.length).toBe(26);
 });
 it('renders compact footer controls without the redundant search toolbar',async()=>{
  spyOn(api,'request').and.resolveTo({data:[{id:1,nombre:'Tema',activo:1}],last_page:1});const fixture=TestBed.createComponent(TrainingManagementComponent);fixture.detectChanges();await fixture.whenStable();fixture.detectChanges();const el=fixture.nativeElement as HTMLElement;expect(el.querySelector('.toolbar')).toBeNull();expect(el.querySelector('input[placeholder="Buscar registros…"]')).toBeNull();expect(el.querySelector('.catalog-footer button[aria-label="Actualizar registros"] hse-icon')).not.toBeNull();expect(el.querySelector('nav[aria-label="Paginación de catálogos"]')).not.toBeNull();expect(el.querySelector('.range')?.textContent).toContain('1–1 de 1');
 });

});
