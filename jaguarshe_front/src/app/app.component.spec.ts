import { TestBed } from '@angular/core/testing';
import { AppComponent } from './app.component';
import { ApiService, ApiError } from './core/api.service';

describe('Jaguar HSE', () => {
  let app: AppComponent;
  let api: ApiService;
  beforeEach(async () => {
    sessionStorage.clear();
    await TestBed.configureTestingModule({ imports: [AppComponent] }).compileComponents();
    const fixture = TestBed.createComponent(AppComponent);
    app = fixture.componentInstance;
    api = TestBed.inject(ApiService);
  });
  it('shows both implement modules only with the existing implement permission',()=>{
    api.permissions.set(['implementos.ver']);
    expect(app.visibleModules.map(m=>m.key)).toContain('implementos');
    expect(app.visibleModules.map(m=>m.key)).toContain('configuracion_implementos');
    api.permissions.set(['organizacion.ver']);
    expect(app.visibleModules.map(m=>m.key)).not.toContain('implementos');
    expect(app.visibleModules.map(m=>m.key)).not.toContain('configuracion_implementos');
  });
  it('filters area zones by site and clears the previous zone when the site changes', () => {
    app.resource.set('areas');app.areaSiteId=1;app.form={zona_id:10};
    app.lookup.set({zonas:[{id:10,sede_id:1,nombre:'Zona 1'},{id:20,sede_id:2,nombre:'Zona 1'}]});
    const field=app.schema['areas'].fields.find(f=>f.key==='zona_id')!;
    expect(app.referenceOptions(field).map(zone=>zone['id'])).toEqual([10]);
    app.areaSiteId=2;app.changeAreaSite();
    expect(app.form['zona_id']).toBeUndefined();
    expect(app.referenceOptions(field).map(zone=>zone['id'])).toEqual([20]);
    app.areaSiteId=undefined;app.changeAreaSite();
    expect(app.referenceOptions(field)).toEqual([]);
  });
  it('edits an area with its existing site and saves only its zone relationship', async () => {
    api.token.set('test');api.user.set({id:1});api.company.set({id:1});
    app.resource.set('areas');
    spyOn(api,'all').and.callFake(async resource=>resource==='zonas'?[{id:10,sede_id:2,nombre:'Zona 1'}]:[{id:2,nombre:'Piura',pais:'PE'}]);
    const request=spyOn(api,'request').and.resolveTo({data:{}});
    spyOn(app,'load').and.resolveTo();
    await app.edit({id:5,zona_id:10,sede_id:2,nombre:'Taller',activo:1});
    expect(app.areaSiteId).toBe(2);expect(app.form['zona_id']).toBe(10);
    await app.save();
    expect(request).toHaveBeenCalledWith('areas/5','PATCH',{zona_id:10,nombre:'Taller',activo:1,coordenadas:null});
    expect(app.optionLabel({nombre:'Taller',zona_nombre:'Zona 1',sede_nombre:'Piura',pais:'PE'})).toBe('Taller · Zona 1 · Piura · Perú');
  });
  it('loads and saves area vertices without duplicating the closing point', async () => {
    api.token.set('test');api.user.set({id:1});api.company.set({id:1});app.resource.set('areas');
    spyOn(api,'all').and.resolveTo([]);const request=spyOn(api,'request').and.resolveTo({data:{}});spyOn(app,'load').and.resolveTo();
    const points=[[-77.03,-12.05],[-77.02,-12.05],[-77.02,-12.04]];
    await app.edit({id:5,zona_id:10,sede_id:2,nombre:'Taller',activo:1,coordenadas:{type:'Polygon',coordinates:[[...points,points[0]]]}});
    expect(app.areaCoordinates).toEqual(points);app.areaCoordinates[1][0]=-77.01;await app.save();
    expect(request).toHaveBeenCalledWith('areas/5','PATCH',jasmine.objectContaining({coordenadas:{type:'Polygon',coordinates:[[[-77.03,-12.05],[-77.01,-12.05],[-77.02,-12.04]]]}}));
  });
  it('creates areas with coordinates and refuses incomplete vertices', async () => {
    api.token.set('test');api.user.set({id:1});api.company.set({id:1});app.resource.set('areas');
    spyOn(api,'all').and.resolveTo([]);const request=spyOn(api,'request').and.resolveTo({data:{}});spyOn(app,'load').and.resolveTo();
    await app.edit();app.form={zona_id:10,nombre:'Nueva área',activo:1};app.areaCoordinates=[[null,null],[1,2],[3,4]];await app.save();expect(request).not.toHaveBeenCalled();expect(app.error()).toContain('Completa');
    app.areaCoordinates=[[-77.03,-12.05],[-77.02,-12.05],[-77.02,-12.04]];await app.save();expect(request).toHaveBeenCalledWith('areas','POST',jasmine.objectContaining({coordenadas:{type:'Polygon',coordinates:[app.areaCoordinates]}}));
  });
  it('manages warehouses under their own module and preserves them through deactivation', async () => {
    app.view.set('almacenes'); app.resource.set('almacenes');
    api.permissions.set(['almacenes.ver','almacenes.eliminar']);
    app.selected.set({id:7,nombre:'Central',sede_id:4,sede_nombre:'Lima',activo:1});
    expect(app.module?.resources).toContain('almacenes');
    expect(app.columns).toEqual(['nombre','sede_nombre','activo']);
    expect(app.detailEntries.some(([key])=>key==='sede_id')).toBeFalse();
    const request=spyOn(api,'request').and.resolveTo({data:{id:7,activo:false}});
    spyOn(app,'load').and.resolveTo();
    await app.deactivateWarehouse();
    expect(request).toHaveBeenCalledWith('almacenes/7','DELETE');
    api.permissions.set(['organizacion.ver']);
    request.calls.reset();
    await app.deactivateWarehouse();
    expect(request).not.toHaveBeenCalled();
  });
  it('uploads the technical sheet to its equipment record and shows its filename', async () => {
    api.permissions.set(['almacenes.editar']);
    app.resource.set('epp_catalogo'); app.selected.set({id:8,nombre:'Casco'});
    const file=new File(['sheet'],'ficha.pdf',{type:'application/pdf'});
    const input={files:[file],value:'ficha.pdf'};
    const request=spyOn(api,'request').and.resolveTo({data:{id:20,nombre_original:'ficha.pdf'}});
    await app.uploadEquipmentSheet({target:input} as unknown as Event);
    expect(request.calls.mostRecent().args[0]).toBe('epp_catalogo/8/ficha');
    expect((request.calls.mostRecent().args[2] as FormData).get('archivo')).toBe(file);
    expect(app.selected()?.['ficha_tecnica']).toBe('ficha.pdf');
    expect(input.value).toBe('');
  });
  it('registers multiple purchase lines and keeps the operation key when a request fails', async () => {
    api.permissions.set(['almacenes.crear']);app.resource.set('epp_compras');
    app.form={clave_operacion:'stable-key',proveedor:'Proveedor',factura:'F001-1',fecha:'2026-10-07'};
    app.purchaseLines=[{epp_id:1,almacen_id:2,cantidad:10,costo_unitario:5},{epp_id:3,almacen_id:2,cantidad:2,costo_unitario:20}];
    expect(app.purchaseTotal).toBe(90);
    app.modal.set('purchase');
    const request=spyOn(api,'request').and.rejectWith(new Error('No hay conexión'));
    await app.savePurchase();
    expect(app.modal()).toBe('purchase');expect(app.form['clave_operacion']).toBe('stable-key');
    request.and.resolveTo({data:{id:1}});spyOn(app,'load').and.resolveTo();
    await app.savePurchase();
    expect(request.calls.mostRecent().args[0]).toBe('compras-epp');
    expect((request.calls.mostRecent().args[2] as any).detalles.length).toBe(2);
    expect(app.modal()).toBeNull();
  });
  it('labels missing valuation and allows cancellation of a confirmed delivery', () => {
    expect(app.money(null)).toBe('Pendiente de valorización');
    api.token.set('test');api.user.set({id:1});api.company.set({id:1});api.permissions.set(['epp.eliminar']);
    app.view.set('epp');app.resource.set('epp_entregas');app.selected.set({id:1,estado:'confirmada'});
    expect(app.actions).toEqual(['anular']);expect(app.canEdit).toBeFalse();
  });
  it('calculates a dollar purchase preview in soles without replacing original costs', () => {
    app.form={moneda:'USD',tipo_cambio:3.75};
    app.purchaseLines=[{cantidad:10,costo_unitario:5}];
    expect(app.purchaseSymbol).toBe('US$');expect(app.purchaseTotal).toBe(50);expect(app.purchaseTotalPen).toBe(187.5);
    expect(app.purchaseLines[0]['costo_unitario']).toBe(5);
    app.form={moneda:'PEN',tipo_cambio:1};expect(app.purchaseSymbol).toBe('S/');expect(app.purchaseTotalPen).toBe(50);
  });
  it('opens a worker valuation report with the equipment detail', async () => {
    app.resource.set('reportes/epp-trabajadores');
    spyOn(api,'request').and.resolveTo({data:{id:1,nombres:'Ana',costo_neto:20},detalles:[{id:3,epp:'Casco',cantidad_neta:1,costo_neto:20}]});
    await app.openDetail({id:1});
    expect(api.request).toHaveBeenCalledWith('reportes/epp-trabajadores/1');
    expect(app.workerCostDetails[0]['epp']).toBe('Casco');
    expect(app.selected()?.['costo_neto']).toBe(20);
    expect(app.columns).toContain('costo_neto');expect(app.canCreate).toBeFalse();
  });
  it('keeps month and reference exchange rate in movement filters and finds pending returns', () => {
    app.resource.set('epp_movimientos');app.movementMonth='2026-10';app.movementExchangeRate=3.75;
    expect(app.movementQuery).toBe('&mes=2026-10&tipo_cambio_consulta=3.75');
    app.lookup.set({epp_entrega_detalles:[{id:5,cantidad_neta:2,epp:'Casco'}]});app.form={entrega_detalle_id:5};
    expect(app.returnSelection?.['cantidad_neta']).toBe(2);
    app.resource.set('epp_compras');expect(app.movementQuery).toBe('');
  });
  it('filters returnable equipment by worker and clears the prior selection when changing worker', () => {
    app.lookup.set({epp_entrega_detalles:[{id:1,trabajador_id:10,nombres:'Ana',apellidos:'Pérez',numero_documento:'12345678'},{id:2,trabajador_id:10,nombres:'Ana',apellidos:'Pérez'},{id:3,trabajador_id:20,nombres:'Luis',apellidos:'Díaz'}]});
    app.returnWorkerId=10;expect(app.returnWorkers.length).toBe(2);expect(app.workerReturnOptions.map(row=>row['id'])).toEqual([1,2]);
    app.form={entrega_detalle_id:1,cantidad:1};app.returnWorkerId=20;app.changeReturnWorker();
    expect(app.form['entrega_detalle_id']).toBeUndefined();expect(app.form['cantidad']).toBeUndefined();expect(app.workerReturnOptions.map(row=>row['id'])).toEqual([3]);
  });
  it('rejects a return selection belonging to a different worker', async () => {
    app.inventoryMode='devoluciones';app.returnWorkerId=10;app.form={entrega_detalle_id:3,cantidad:1};
    app.lookup.set({epp_entrega_detalles:[{id:3,trabajador_id:20}]});const request=spyOn(api,'request');
    await app.saveInventory();expect(request).not.toHaveBeenCalled();expect(app.error()).toContain('Selecciona el trabajador');
  });
  it('requires authentication and starts without company data', () => {
    expect(app.authenticated).toBeFalse();
    expect(api.token()).toBe('');
    expect(app.pending.length).toBe(0);
  });
  it('never loads or creates records without authentication', async () => {
    const request = spyOn(api, 'request');
    await app.edit();
    await app.navigate('hallazgos');
    await app.loadDashboard();
    expect(request).not.toHaveBeenCalled();
  });
  it('preserves false and zero without sending server-owned fields', async () => {

    app.resource.set('temas_hse');
    app.fields = app.schema['temas_hse'].fields;
    app.form = { nombre: 'Tema', codigo: 'T-01', aplica: 0, motivo_no_aplica: 'Fuera de alcance', empresa_id: 99, estado: 'aprobado' };
    const request = spyOn(api, 'request').and.resolveTo({data:{id:1}});
    spyOn(app, 'load').and.resolveTo();
    await app.save();
    const body = request.calls.mostRecent().args[2] as Record<string, unknown>;
    expect(body['aplica']).toBe(0);
    expect(body['empresa_id']).toBeUndefined();
    expect(body['estado']).toBeUndefined();
  });
  it('does not expose create or edit on report views', () => {
    app.resource.set('reportes/stock');
    expect(app.canCreate).toBeFalse();
    expect(app.canEdit).toBeFalse();
  });
  it('shows validation failures and keeps the editor open', async () => {
 app.modal.set('edit');
    spyOn(api,'request').and.rejectWith(new Error('Referencia inválida para esta empresa.'));
    await app.save();
    expect(app.error()).toContain('Referencia inválida');
    expect(app.modal()).toBe('edit');
    expect(app.saving()).toBeFalse();
  });
  it('clears the session after a 401 response', async () => {
    api.token.set('test');
    spyOn(window,'fetch').and.resolveTo(new Response(JSON.stringify({message:'Sesión expirada'}),{status:401,headers:{'Content-Type':'application/json'}}));
    await expectAsync(api.request('auth/me')).toBeRejectedWithError('Sesión expirada');
    expect(api.token()).toBe('');
  });
  it('clears all company data on logout', async () => {
    api.token.set('company-a'); api.user.set({empresa_id:1}); api.company.set({id:1,razon_social:'Empresa A'});
    app.dashboard.set({hallazgos:[{id:1,estado:'abierto'}]}); app.lookup.set({sedes:[{id:1}]});
    spyOn(api,'request').and.resolveTo({});
    await app.signOut();
    expect(app.authenticated).toBeFalse(); expect(app.dashboard()).toEqual({}); expect(app.lookup()).toEqual({});
  });
  it('loads dashboard results for the newly authenticated company', async () => {
    api.token.set('company-b'); api.user.set({empresa_id:2}); api.company.set({id:2,razon_social:'Empresa B'});
    api.permissions.set(['hallazgos.ver']);
    spyOn(api,'all').and.resolveTo([{id:9,empresa_id:2,estado:'abierto'}]);
    await app.loadDashboard();
    expect(app.companyName).toBe('Empresa B'); expect(app.pending.map(r=>r['id'])).toEqual([9]);
  });
  it('rejects a previous company response after the session changes', async () => {
    api.token.set('company-a');
    let resolve!: (value:Response)=>void;
    spyOn(window,'fetch').and.returnValue(new Promise<Response>(done=>resolve=done));
    const pending=api.request('hallazgos');
    api.token.set('company-b');
    resolve(new Response(JSON.stringify({data:[{empresa_id:1}]}),{status:200}));
    await expectAsync(pending).toBeRejectedWithError('La sesión cambió. Vuelve a cargar los datos de tu empresa.');
  });

  it('offers verification after an unverified login without creating a session', async () => {
    spyOn(api, 'login').and.rejectWith(new ApiError('Verifica tu correo.', 'EMAIL_NOT_VERIFIED', 403));
    app.login={email:'pending@example.test',password:'password-user-123'};
    await app.signIn();
    expect(app.verificationRequired()).toBeTrue();
    expect(app.authenticated).toBeFalse();
    const resend=spyOn(api, 'resendVerification').and.resolveTo({message:'Correo enviado.'});
    await app.resendVerification();
    expect(resend).toHaveBeenCalledWith('pending@example.test','password-user-123');
    expect(app.verificationNotice()).toBe('Correo enviado.');
    await app.resendVerification();
    expect(resend).toHaveBeenCalledTimes(1);
  });
  it('does not offer verification for incorrect credentials', async () => {
    spyOn(api, 'login').and.rejectWith(new ApiError('Credenciales inválidas.', '', 401));
    app.verificationRequired.set(true);
    await app.signIn();
    expect(app.verificationRequired()).toBeFalse();
  });
  it('preserves the verification error code and clears an invalid existing session', async () => {
    api.token.set('old-session');
    spyOn(window,'fetch').and.resolveTo(new Response(JSON.stringify({message:'Verifica tu correo.',code:'EMAIL_NOT_VERIFIED'}),{status:403}));
    try { await api.request('auth/me'); fail('Expected verification error'); }
    catch(error){expect(error instanceof ApiError).toBeTrue();expect((error as ApiError).code).toBe('EMAIL_NOT_VERIFIED');}
    expect(api.token()).toBe('');
  });

  it('keeps columns after an empty filtered response and sends filters to the server', async () => {
    api.token.set('token');api.user.set({empresa_id:1});api.company.set({id:1});
    app.resource.set('sedes');app.page=3;
    spyOn(api,'request').and.resolveTo({data:{paises:['PE','CL']}});
    const list=spyOn(api,'list').and.resolveTo({data:[],total:0,current_page:1,last_page:1,columns:['id','nombre','activo']});
    await app.filterColumn('nombre','Sin coincidencia');
    expect(list.calls.mostRecent().args[2]).toContain('columns[nombre]=Sin%20coincidencia');
    expect(app.page).toBe(1);expect(app.columns).toContain('nombre');
    await app.clearTableFilters();expect(app.columnFilters).toEqual({});
  });

});
