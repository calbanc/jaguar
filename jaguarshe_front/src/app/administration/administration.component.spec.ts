import { TestBed } from '@angular/core/testing';
import { AdministrationComponent } from './administration.component';
import { ApiService } from '../core/api.service';

describe('Administration', () => {
  let component: AdministrationComponent;
  let api: ApiService;
  beforeEach(async () => {
    await TestBed.configureTestingModule({imports:[AdministrationComponent]}).compileComponents();
    component=TestBed.createComponent(AdministrationComponent).componentInstance;
    api=TestBed.inject(ApiService);
    api.user.set({rol_id:1,miembro_id:1,acceso_id:1});
    api.permissions.set(['administracion.ver','administracion.crear','administracion.editar','administracion.eliminar','administracion.descargar']);
  });
  it('keeps modules and permissions read only and protects the current role and access', () => {
    component.section.set('modulos');expect(component.canCreate).toBeFalse();
    component.section.set('permisos');expect(component.canCreate).toBeFalse();
    component.section.set('roles');expect(component.canEdit({id:1})).toBeFalse();expect(component.canEdit({id:2})).toBeTrue();
    component.section.set('permisos-usuarios');expect(component.canEdit({id:1})).toBeFalse();
    component.section.set('permisos-roles');expect(component.canRemove({id:4,rol_id:1})).toBeFalse();
    component.section.set('usuarios');expect(component.canRemove({id:1,activo:true})).toBeFalse();
  });
  it('ignores results from a previous tab after changing sections', async () => {
    let resolve!: (value:unknown)=>void;
    spyOn(api,'request').and.callFake(async (path:string) => path.includes('/usuarios?') ? new Promise(done=>resolve=done) : {data:[{id:4,nombre:'Documentos'}],total:1,last_page:1});
    const old=component.load();await component.selectTab('modulos');
    resolve({data:[{id:1,nombre:'Old user'}],total:1,last_page:1});await old;
    expect(component.rows()[0]['nombre']).toBe('Documentos');
  });
  it('submits only user fields and omits an unchanged password', async () => {
    component.selected.set({id:2});component.optionsReady.set(true);
    component.form={nombre:'Ana',email:'ana@example.test',password:'',activo:true,usuario_id:98,roles:'Consulta',empresa_id:99};
    const request=spyOn(api,'request').and.resolveTo({});spyOn(component,'load').and.resolveTo();
    await component.save();
    expect(request).toHaveBeenCalledWith('administracion/usuarios/2','PATCH',{nombre:'Ana',email:'ana@example.test',activo:true});
  });
  it('does not select permissions that the current administrator cannot delegate', () => {
    component.form={permisos:[]};component.toggleGroup([{id:1,asignable:true},{id:2,asignable:false}],true);
    expect(component.form['permisos']).toEqual([1]);
  });
  it('exports the entire filtered tab without a pagination parameter', async () => {
    component.section.set('permisos-roles');component.page=3;component.appliedQuery='Gestión SST';component.columnFilters={rol:'Supervisor'};
    const download=spyOn(api,'downloadBlob').and.rejectWith(new Error('Network failure'));
    await component.exportExcel();
    expect(download).toHaveBeenCalledWith('administracion/permisos-roles/excel?q=Gesti%C3%B3n%20SST&columns[rol]=Supervisor');
    expect(component.exporting()).toBeFalse();expect(component.error()).toBe('Network failure');
  });
  it('separates editing, creation, deletion, and downloads', () => {
    api.permissions.set(['administracion.ver','administracion.editar']);
    component.section.set('roles');
    expect(component.canCreate).toBeFalse();
    expect(component.canEdit({id:2,activo:true})).toBeTrue();
    expect(component.canRemove({id:2,activo:true})).toBeFalse();
  });
  it('shows one module row with five action columns and keeps confidential access separate', () => {
    component.options.set({permisos:[{id:1,codigo:'documentos.ver',modulo:'Documentos',descripcion:'Consultar'},
      {id:2,codigo:'documentos.editar',modulo:'Documentos',descripcion:'Editar'},
      {id:3,codigo:'documentos.confidenciales',modulo:'Documentos',descripcion:'Confidenciales'}]});
    expect(component.permissionActions.length).toBe(5);
    expect(component.roleMatrix.length).toBe(1);
    expect(Object.keys(component.roleMatrix[0].cells)).toEqual(['ver','editar']);
    expect(component.specialPermissions.length).toBe(1);
  });

  it('combines column filters and resets pagination and filters when changing tabs', async () => {
    const request=spyOn(api,'request').and.resolveTo({data:[],total:0,last_page:1});
    component.page=4;component.columnFilters={nombre:'Ana'};
    await component.filterColumn('activo','0');
    expect(component.page).toBe(1);
    expect(request.calls.mostRecent().args[0]).toContain('columns[nombre]=Ana&columns[activo]=0');
    await component.selectTab('roles');
    expect(component.columnFilters).toEqual({});
  });

});
