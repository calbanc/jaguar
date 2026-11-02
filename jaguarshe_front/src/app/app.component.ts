import { Component, afterNextRender, inject, signal, HostListener, effect } from '@angular/core';
import { PolygonMapComponent } from './core/polygon-map.component';
import { AreaMapComponent } from './core/area-map.component';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService, ApiError, Row } from './core/api.service';
import { SCHEMA, Field } from './core/resource-schema';
import { MODULES, label } from './core/catalog';
import { IconComponent } from './core/icon.component';
import { ColumnFilterComponent } from './core/column-filter.component';
import { filterQuery, matchesColumns } from './core/table-filters';
import { DeliveryReportComponent } from './epp/delivery-report.component';
import { DeliveryComponent } from './epp/delivery.component';
import { TrainingManagementComponent } from './training/training-management.component';
import { SafetyImplementsComponent } from './safety/safety-implements.component';
import { TrainingComponent } from './training/training.component';
import { WorkersComponent } from './workers/workers.component';
import { AdministrationComponent } from './administration/administration.component';

@Component({selector:'app-root', imports:[PolygonMapComponent,AreaMapComponent,SafetyImplementsComponent,TrainingManagementComponent,TrainingComponent,DeliveryReportComponent,DeliveryComponent,CommonModule, FormsModule, IconComponent, AdministrationComponent, ColumnFilterComponent, WorkersComponent], templateUrl:'./app.component.html', styleUrl:'./app.component.css'})
export class AppComponent {
 sidebarCollapsed=signal(false);
 sidebarTooltip=signal<{text:string;top:number;left:number}|null>(null);
 showSidebarTooltip(event:Event,text:string){
  if(!this.sidebarCollapsed()||!window.matchMedia('(min-width:761px)').matches)return;
  const rect=(event.currentTarget as HTMLElement).getBoundingClientRect();
  this.sidebarTooltip.set({text,top:rect.top+rect.height/2,left:rect.right+14});
 }
 toggleSidebar(){this.sidebarTooltip.set(null);this.sidebarCollapsed.update(value=>!value);this.menuSearch='';}
 costFilters:Record<string,string>={};purchaseFilters:Record<string,string>={};
 get filteredWorkerCosts(){return this.workerCostDetails.filter(row=>matchesColumns({...row,costo_unitario:this.money(row['costo_unitario']),costo_neto:this.money(row['costo_neto'])},this.costFilters));}
 get filteredPurchaseDetails(){return this.purchaseDetails.filter(row=>matchesColumns({...row,costo_unitario_original:this.money(row['costo_unitario_original']),costo_total_original:this.money(row['costo_total_original']),costo_total:this.money(row['costo_total'])},this.purchaseFilters));}
 api=inject(ApiService); modules=MODULES; label=label; schema=SCHEMA;
 view=signal('dashboard'); resource=signal('hallazgos'); rows=signal<Row[]>([]); dashboard=signal<Record<string,Row[]>>({});
 busy=signal(false); saving=signal(false); error=signal(''); notice=signal(''); checkingSession=signal(true); menu=signal(false);
 modal=signal<'detail'|'edit'|'action'|'password'|'inventory'|'checklist'|'deactivate'|'purchase'|'delivery'|null>(null); selected=signal<Row|null>(null); lookup=signal<Record<string,Row[]>>({});
 form:Row={}; fields:Field[]=[]; search=''; status=''; page=1; total=0; lastPage=1; login={email:'',password:''};
 columnFilters:Record<string,string>={}; dashboardFilters:Record<string,string>={}; tableKeys=signal<string[]>([]);
 movementMonth=new Intl.DateTimeFormat('en-CA',{year:'numeric',month:'2-digit'}).format(new Date());movementExchangeRate:number|null=null;
 get movementQuery(){return this.resource()==='epp_movimientos'?`&mes=${encodeURIComponent(this.movementMonth)}${this.movementExchangeRate!==null?'&tipo_cambio_consulta='+encodeURIComponent(this.movementExchangeRate):''}`:'';}
 returnWorkerId:number|undefined;
 get returnWorkers(){const workers=new Map<number,Row>();for(const row of this.lookup()['epp_entrega_detalles']||[])workers.set(row['trabajador_id'],row);return [...workers.values()].sort((a,b)=>`${a['nombres']} ${a['apellidos']}`.localeCompare(`${b['nombres']} ${b['apellidos']}`));}
 get workerReturnOptions(){return (this.lookup()['epp_entrega_detalles']||[]).filter(row=>row['trabajador_id']===this.returnWorkerId);}
 changeReturnWorker(){this.form['entrega_detalle_id']=undefined;this.form['cantidad']=undefined;}
 get returnSelection(){return this.lookup()['epp_entrega_detalles']?.find(row=>row['id']===this.form['entrega_detalle_id']);}
 readonly booleanFilter=[{value:'1',label:'Activo'},{value:'0',label:'Inactivo'}];
 showLoginPassword=signal(false);
 verificationRequired=signal(false); verificationNotice=signal(''); resendingVerification=signal(false);
 resendAvailableAt=0;
 actionName=''; actionOptions:string[]=[]; dashboardErrors=signal<string[]>([]); loadId=0;
 today=new Intl.DateTimeFormat('es-PE',{day:'numeric',month:'long',year:'numeric'}).format(new Date());
 constructor(){
  effect(()=>{const open=this.modal();if(open && typeof document!=='undefined')setTimeout(()=>document.querySelector<HTMLElement>('[role="dialog"] input, [role="dialog"] button')?.focus(),0);});
  effect(()=>{if(!this.api.user())this.resetWorkspace();});
  afterNextRender(async()=>{
   const initial=new URLSearchParams(location.search).get('modulo');
   try {
    await this.api.restore();
    if(this.api.user()) await this.navigate(initial && MODULES.some(m=>m.key===initial)?initial:'dashboard',false);
   } catch(e){this.api.clear();this.error.set(this.message(e));}
   finally{this.checkingSession.set(false);}
  });
 }
 @HostListener('window:popstate') onPop(){this.navigate(new URLSearchParams(location.search).get('modulo')||'dashboard',false);}
 @HostListener('document:keydown', ['$event']) trapFocus(event:KeyboardEvent){if(event.key!=='Tab'||!this.modal())return;const dialog=document.querySelector('[role="dialog"]');const focusable=Array.from(dialog?.querySelectorAll<HTMLElement>('button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),a[href]')||[]);const first=focusable[0],last=focusable[focusable.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}}
 @HostListener('document:keydown.escape') escape(){if(!this.saving()){this.modal.set(null);this.menu.set(false);}}
 get authenticated(){return !!this.api.user() && !!this.api.company() && !!this.api.token();}
 get companyName(){return this.api.company()?.['nombre_comercial']||this.api.company()?.['razon_social']||'';}
 resetWorkspace(){this.menuSearch='';this.columnFilters={};this.dashboardFilters={};this.tableKeys.set([]);this.search='';this.status='';this.loadId++;this.dashboard.set({});this.rows.set([]);this.areaMapRows.set([]);this.lookup.set({});this.selected.set(null);this.checklist.set(null);this.answers={};this.form={};this.modal.set(null);this.menu.set(false);this.view.set('dashboard');this.busy.set(false);this.total=0;this.page=1;this.notice.set('');this.dashboardErrors.set([]);}
 get module(){return MODULES.find(m=>m.key===this.view());}
 get visibleModules(){return MODULES.filter(m=>this.api.can(m.key==='gestion_capacitaciones'?'capacitaciones':m.key==='configuracion_implementos'?'implementos':m.key));}
 menuSearch='';
 matchesMenu(name:string){const normalize=(value:string)=>value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();return normalize(name).includes(normalize(this.menuSearch));}
 get filteredModules(){return this.visibleModules.filter(m=>this.matchesMenu(label(m.key))||(m.key==='gestion_capacitaciones'&&this.matchesMenu('Temas lugares expositores empresas expositoras')));}
 get showDashboardMenu(){return this.matchesMenu(label('dashboard'));}
 get filteredRows(){return this.rows();}
 get columns(){if(this.resource()==='epp_entregas')return ['fecha','trabajador','motivo','responsable','estado'];if(this.resource()==='sedes')return ['codigo','nombre','pais','direccion','activo'];if(this.resource()==='zonas')return ['nombre','sede_nombre','pais','activo'];if(this.resource()==='areas')return ['nombre','zona_nombre','sede_nombre','pais','activo'];if(this.resource()==='reportes/epp-trabajadores')return ['numero_documento','nombres','apellidos','puesto','entregas_confirmadas','costo_entregado','costo_devuelto','costo_neto'];if(this.resource()==='epp_compras')return ['proveedor','factura','fecha','moneda','costo_total_original','tipo_cambio','costo_total'];if(this.resource()==='epp_movimientos')return ['fecha','movimiento','epp','almacen','unidad','entrada','salida','costo_unitario','costo_total','costo_unitario_usd','costo_total_usd','factura','trabajador_nombres','trabajador_apellidos'];if(this.resource()==='epp_entrega_detalles')return ['entrega_id','epp_id','almacen_id','cantidad','costo_unitario','costo_total'];if(this.resource()==='reportes/stock')return ['almacen','epp','unidad','stock','costo_promedio','valor_stock'];if(this.resource()==='almacenes')return ['nombre','sede_nombre','activo'];const keys=this.tableKeys().length?this.tableKeys():SCHEMA[this.resource()]?.fields.map(f=>f.key)||[];const priority=['codigo','titulo','nombre','descripcion','nombres','apellidos','fecha','severidad','estado','fecha_compromiso','fecha_vencimiento','cantidad','unidad','activo'];return [...priority.filter(k=>keys.includes(k)),...keys.filter(k=>!priority.includes(k)&&!['empresa_id','created_at','updated_at','uuid_cliente','id'].includes(k))].slice(0,5);}
 get hasColumnFilters(){return Object.values(this.columnFilters).some(Boolean);}
 filterOptions(column:string){if(column==='pais')return this.countryOptions().map(country=>({value:country.code,label:country.name}));if(column==='activo')return this.booleanFilter;const options=SCHEMA[this.resource()]?.fields.find(f=>f.key===column)?.options;return options?.map(value=>({value,label:label(value)}))||[];}
 async filterColumn(column:string,value:string){this.columnFilters={...this.columnFilters,[column]:value};await this.load(1);}
 async clearTableFilters(){this.columnFilters={};this.search='';this.status='';await this.load(1);}
 get hasDashboardFilters(){return Object.values(this.dashboardFilters).some(Boolean);}
 get dashboardRows(){return this.pending.filter(row=>matchesColumns({...row,titulo:row['titulo']||row['descripcion']},this.dashboardFilters)).slice(0,4);}
 get statuses(){return ['borrador','pendiente','abierto','en_proceso','por_validar','enviado','revisado','aprobado','observado','rechazado','publicado','retirado','confirmado','cerrado','completada','anulado'];}
 get hasStatus(){return ['hallazgos','acciones_correctivas','documento_versiones','checklist_registros','checklist_plantillas','epp_entregas'].includes(this.resource());}
 get canCreate(){return !!SCHEMA[this.resource()] && !SCHEMA[this.resource()]?.readonly&&(this.api.can(this.module?.key||'','crear'));}
 get canEdit(){const r=this.selected();return !!SCHEMA[this.resource()] && !SCHEMA[this.resource()]?.readonly&&this.resource()!=='documento_versiones'&&!['publicado','retirado','enviado','revisado','confirmado','confirmada','cerrado','anulado','completada','anulada'].includes(r?.['estado'])&&(this.api.can(this.module?.key||'','editar'));}
 get canDeactivate(){return this.resource()==='almacenes'&&!!this.selected()?.['activo']&&this.api.can('almacenes','eliminar');}
 async deactivateWarehouse(){if(!this.canDeactivate||this.saving())return;this.saving.set(true);this.error.set('');try{await this.api.request(`almacenes/${this.selected()!['id']}`,'DELETE');this.modal.set(null);this.notice.set('Almacén desactivado correctamente.');await this.load(this.page);}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}}
 get detailEntries(){return Object.entries(this.selected()||{}).filter(([k])=>!['empresa_id','uuid_cliente',...(['almacenes','zonas','areas'].includes(this.resource())?['sede_id','zona_id']:[]),...(this.resource()==='epp_catalogo'?['ficha_archivo_id']:[])].includes(k));}
 get pending(){return (this.dashboard()['hallazgos']||[]).filter(r=>!['cerrado','anulado'].includes(r['estado']));}
 get compliance(){const a=this.dashboard()['reportes/temas']||[];const values=a.map(r=>r['porcentaje_cumplimiento']).filter(v=>v!==null&&v!==undefined).map(Number);return values.length?Math.round(values.reduce((a,b)=>a+b,0)/values.length):null;}
 get metrics(){const d=this.dashboard();return [
 {label:'Cumplimiento documental',value:this.compliance===null?'—':this.compliance+'%',icon:'shield',note:'Promedio de temas evaluables',color:'green'},
 {label:'Inspecciones registradas',value:!this.api.can('inspecciones')?'—':(d['checklist_registros']||[]).length,icon:'clipboard',note:'Registros de inspección',color:'blue'},
 {label:'Hallazgos abiertos',value:!this.api.can('hallazgos')?'—':this.pending.length,icon:'alert',note:'Pendientes de cierre',color:'amber'},
 {label:'Trabajadores registrados',value:!this.api.can('organizacion')?'—':(d['trabajadores']||[]).length,icon:'users',note:'Personal de la organización',color:'purple'}];}
 localDateTime(value:string){const date=new Date(value.replace(' ','T')+'Z');return new Date(date.getTime()-date.getTimezoneOffset()*60000).toISOString().slice(0,16);}
 message(e:unknown){return e instanceof Error?e.message:'No se pudo completar la operación.';}
 display(value:any){if(value===null||value===undefined||value==='')return '—';if(typeof value==='object')return JSON.stringify(value);if(typeof value==='boolean')return value?'Sí':'No';return String(value);}
 tone(value:any){return ['aprobado','vigente','cumplido','cerrado','completada','revisado','publicado','confirmado','baja'].includes(String(value))?'green':['alta','critica','rechazado','vencido'].includes(String(value))?'red':['media','abierto','pendiente','observado','por_validar'].includes(String(value))?'amber':'blue';}
 async navigate(key:string,history=true){
  if(!this.authenticated)return;
  this.loadId++;this.view.set(key);this.menu.set(false);this.modal.set(null);this.error.set('');this.search='';this.status='';this.columnFilters={};this.tableKeys.set([]);
  if(history&&typeof window!=='undefined')window.history.pushState({},'',key==='dashboard'?location.pathname:`?modulo=${key}`);
  if(key==='implementos'||key==='configuracion_implementos'||key==='administracion'||key==='capacitaciones'||key==='gestion_capacitaciones'){this.busy.set(false);return;}
  if(key==='dashboard')await this.loadDashboard();else {this.page=1;this.resource.set(this.module?.resources[0]||'hallazgos');await this.load();}
 }
 async selectResource(key:string){this.resource.set(key);this.search='';this.status='';this.columnFilters={};this.tableKeys.set([]);this.page=1;await this.load();}
 async load(page=1){
  if(!this.authenticated)return;
  if(['trabajadores','informe-entregas'].includes(this.resource())){this.busy.set(false);return;}
  const id=++this.loadId;this.page=page;this.error.set('');this.rows.set([]);this.busy.set(true);
  try {
   if(['sedes','zonas','areas'].includes(this.resource()))await this.loadCountries();
   const result=await this.api.list(this.resource()==='miembros_empresa'?'administracion/miembros':this.resource(),page,`&q=${encodeURIComponent(this.search)}${this.status?'&filter[estado]='+encodeURIComponent(this.status):''}${filterQuery(this.columnFilters)}${this.movementQuery}`);if(id!==this.loadId)return;this.rows.set(result.data);this.tableKeys.set(result.columns||Object.keys(result.data[0]||{}));this.total=result.total;this.lastPage=result.last_page;
   if(this.resource()==='areas'){this.areaMapRows.set([]);const areas=await this.api.all('areas');if(id!==this.loadId)return;this.areaMapRows.set(areas);}
  }catch(e){if(id===this.loadId)this.error.set(this.message(e));}finally{if(id===this.loadId)this.busy.set(false);}
 }
 async loadDashboard(){
  if(!this.authenticated)return;
  const loadId=++this.loadId;this.busy.set(true);this.dashboardErrors.set([]);const data:Record<string,Row[]>={};
  const endpoints=[['reportes/temas','gestion_sst'],['documento_versiones','documentos'],['checklist_registros','inspecciones'],['hallazgos','hallazgos'],['trabajadores','organizacion']];
  const errors:string[]=[];
  await Promise.all(endpoints.filter(([,m])=>this.api.can(m)).map(async([r])=>{try{data[r]=await this.api.all(r);}catch(e){errors.push(`${label(r)}: ${this.message(e)}`);}}));
  if(loadId!==this.loadId)return;this.dashboard.set(data);this.dashboardErrors.set(errors);this.busy.set(false);
 }
 async signIn(){if(this.saving()||this.resendingVerification())return;this.verificationRequired.set(false);this.verificationNotice.set('');this.saving.set(true);this.error.set('');try{await this.api.login(this.login.email,this.login.password);this.resetWorkspace();this.login.password='';this.showLoginPassword.set(false);this.lookup.set({});const target=new URLSearchParams(location.search);if(target.get('modulo')==='implementos'&&target.has('implemento'))await this.navigate('implementos',false);else await this.navigate('dashboard');}catch(e){this.verificationRequired.set(e instanceof ApiError && e.code==='EMAIL_NOT_VERIFIED');this.error.set(this.message(e));}finally{this.saving.set(false);}}
 async resendVerification(){
  if(this.resendingVerification()||this.saving())return;
  if(!this.login.email||!this.login.password){this.error.set('Ingresa tu correo y contraseña para solicitar la verificación.');return;}
  if(Date.now()<this.resendAvailableAt){this.error.set('Espera un minuto antes de volver a enviar el correo.');return;}
  this.resendingVerification.set(true);this.error.set('');this.verificationNotice.set('');
  try{const result=await this.api.resendVerification(this.login.email,this.login.password);this.verificationNotice.set(result.message);this.resendAvailableAt=Date.now()+60000;}
  catch(e){this.error.set(this.message(e));}finally{this.resendingVerification.set(false);}
 }
 async signOut(){this.saving.set(true);try{await this.api.logout();}catch(e){this.error.set(this.message(e));}finally{this.resetWorkspace();this.login.password='';this.showLoginPassword.set(false);this.saving.set(false);window.history.replaceState({},'',location.pathname);}}
 async dashboardDetail(row:Row){await this.navigate('hallazgos');await this.openDetail(row);}
 async openDetail(row:Row){this.costFilters={};this.purchaseFilters={};this.selected.set(row);this.purchaseDetails=[];this.workerCostDetails=[];this.modal.set('detail');this.error.set('');if(this.resource()==='reportes/epp-trabajadores'){try{const result=await this.api.request(`reportes/epp-trabajadores/${row['id']}`);this.selected.set(result.data||row);this.workerCostDetails=result.detalles;}catch(e){this.error.set(this.message(e));}return;}if(this.resource()==='epp_compras'){try{const result=await this.api.request(`compras-epp/${row['id']}`);this.selected.set(result.data);this.purchaseDetails=result.detalles;}catch(e){this.error.set(this.message(e));}return;}if(SCHEMA[this.resource()]){try{const result=await this.api.request(`${this.resource()}/${row['id']}?${this.resource()==='epp_movimientos'&&this.movementExchangeRate!==null?'tipo_cambio_consulta='+encodeURIComponent(this.movementExchangeRate):''}`);this.selected.set(result.data);}catch(e){this.error.set(this.message(e));}}}
 async deliverySaved(message?:string){this.modal.set(null);this.notice.set(message||'Entrega registrada. Se descontaron las existencias del almacén.');await this.load(1);}
 async edit(row:Row|null=null){
  if(!this.authenticated)return;
  if(this.resource()==='epp_entregas'&&!row){if(!this.api.can('epp','crear'))return;this.selected.set(null);this.error.set('');this.modal.set('delivery');return;}
  this.selected.set(row);this.error.set('');this.fields=SCHEMA[this.resource()].fields.filter(f=>this.resource()!=='epp_catalogo'||f.key!=='ficha_archivo_id');this.form={};
  if(!row&&SCHEMA[this.resource()].uuid)this.form['uuid_cliente']=crypto.randomUUID();
  for(const f of this.fields){const value=row?.[f.key];if(value!==undefined&&value!==null)this.form[f.key]=f.type==='JSON'?JSON.stringify(value):f.type==='BOOLEAN'?Number(value):f.type==='DATETIME'?this.localDateTime(String(value)):value;else if(!row&&f.default!==null)this.form[f.key]=f.type==='BOOLEAN'?Number(f.default==='TRUE'||f.default==='1'):f.default;}
  this.areaSiteId=row?.['sede_id'];
  this.areaCoordinates=(row?.['coordenadas']?.coordinates?.[0]||[]).map((point:number[])=>[...point]);
  if(this.areaCoordinates.length>1&&JSON.stringify(this.areaCoordinates[0])===JSON.stringify(this.areaCoordinates.at(-1)))this.areaCoordinates.pop();
  if(this.resource()==='sedes'&&!row)this.form['pais']=this.api.company()?.['pais'];
  this.editLoading.set(true);this.modal.set('edit');
  const refs=[...new Set(this.fields.map(f=>f.reference).filter(Boolean))] as string[];
  if(this.resource()==='areas')refs.push('sedes');
  if(this.resource()==='sedes')refs.push('paises');
  for(const ref of refs)this.lookup.update(value=>({...value,[ref]:[]}));
  await Promise.all(refs.map(async ref=>{try{
   if(ref==='paises'){await this.loadCountries();return;}
   if(['modulos','plataformas','permisos'].includes(ref)){const c=await this.api.request('catalogos');this.lookup.update(v=>({...v,[ref]:c.data[ref]}));}
   else if(SCHEMA[ref]){const rows=ref==='sedes'&&this.resource()==='almacenes'?(await this.api.request('almacenes/opciones')).data:ref==='epp_catalogo'&&this.view()==='epp'?(await this.api.request('inventario/catalogo')).data:ref==='almacenes'&&this.view()==='epp'?(await this.api.request('inventario/almacenes')).data:await this.api.all(ref==='miembros_empresa'?'administracion/miembros':ref);this.lookup.update(v=>({...v,[ref]:rows}));}
  }catch(e){this.error.set(`No se pudo cargar ${label(ref)}. ${this.message(e)}`);}}));
  this.editLoading.set(false);
 }
 areaSiteId:number|undefined;
 areaCoordinates:(number|null)[][]=[];
 areaMapRows=signal<Row[]>([]);
 editLoading=signal(false);
 countryOptions=signal<{code:string,name:string}[]>([]);
 private countryNames=new Intl.DisplayNames(['es'],{type:'region'});
 countryName(code:unknown){return typeof code==='string'&&/^[A-Z]{2}$/.test(code)?this.countryNames.of(code)||code:this.display(code);}
 async loadCountries(){if(this.countryOptions().length)return;const result=await this.api.request('catalogos');this.countryOptions.set((result.data.paises as string[]).map(code=>({code,name:this.countryName(code)})).sort((a,b)=>a.name.localeCompare(b.name,'es')));}
 changeAreaSite(){this.form['zona_id']=undefined;}
 referenceOptions(field:Field){const options=this.lookup()[field.reference||'']||[];return this.resource()==='areas'&&field.key==='zona_id'?options.filter(zone=>zone['sede_id']===this.areaSiteId):options;}
 optionLabel(row:Row){const name=row['nombre']||row['titulo']||row['descripcion']||[row['nombres'],row['apellidos']].filter(Boolean).join(' ')||row['codigo']||`Registro #${row['id']}`;return [name,row['zona_nombre'],row['sede_nombre'],row['pais']?this.countryName(row['pais']):null].filter(Boolean).join(' · ');}
 inputType(f:Field){return f.type==='DATE'?'date':f.type==='DATETIME'?'datetime-local':['BIGINT','INT','DECIMAL'].includes(f.type)?'number':f.key.includes('email')?'email':'text';}
 async save(){
  if(this.saving()||this.editLoading())return;
  this.saving.set(true);this.error.set('');try{const body:Row={};for(const f of this.fields){const value=this.form[f.key];if(value===undefined||value===''){if(this.selected()&&f.nullable)body[f.key]=null;continue;}body[f.key]=f.type==='JSON'?JSON.parse(value):f.type==='DATETIME'?new Date(value).toISOString().slice(0,19).replace('T',' '):['BIGINT','INT','DECIMAL','BOOLEAN'].includes(f.type)?Number(value):value;}
   if(this.resource()==='areas'){
    if(this.areaCoordinates.length&&this.areaCoordinates.length<3)throw new Error('Agrega al menos tres puntos para delimitar el área.');
    if(this.areaCoordinates.some(point=>point.some(value=>value===null||!Number.isFinite(value))))throw new Error('Completa la latitud y longitud de cada punto.');
    body['coordenadas']=this.areaCoordinates.length?{type:'Polygon',coordinates:[this.areaCoordinates.map(point=>[...point])]}:null;
   }
   if(!this.selected()&&SCHEMA[this.resource()].uuid)body['uuid_cliente']=this.form['uuid_cliente'];
   await this.api.request(`${this.resource()}${this.selected()?'/'+this.selected()!['id']:''}`,this.selected()?'PATCH':'POST',body);
   this.modal.set(null);this.notice.set('Registro guardado correctamente.');await this.load(this.page);
  }catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}
 }
 get actions():string[]{
  const r=this.selected();if(!r||!this.authenticated)return [];const state=r['estado'];let actions:string[]=[];
  if(['hallazgos','acciones_correctivas'].includes(this.resource())&&!['cerrado','anulado','completada','anulada'].includes(state))actions=['transicion'];
  if(this.resource()==='documento_versiones'&&state==='pendiente')actions=['revisar'];
  if(this.resource()==='checklist_plantillas')actions=state==='borrador'?['publicar']:state==='publicado'?['versionar','retirar']:['versionar'];
  if(this.resource()==='epp_entregas')actions=state==='borrador'?['confirmar','anular']:state==='confirmada'?['anular']:[];
  if(this.resource()==='checklist_registros')actions=state==='borrador'?['enviar','anular']:state==='enviado'?['revisar','anular']:[];
  return actions.filter(a=>a==='transicion'?(this.api.can(this.module!.key,'editar')||this.api.can(this.module!.key,'eliminar')):this.api.can(this.module!.key,['retirar','anular'].includes(a)?'eliminar':a==='versionar'?'crear':'editar'));
 }
 prepareAction(action:string){this.actionName=action;this.form={comentario:''};this.error.set('');const state=this.selected()?.['estado'];this.actionOptions=action==='transicion'?(state==='por_validar'?['en_proceso',this.resource()==='hallazgos'?'cerrado':'completada']:state==='en_proceso'?['por_validar',this.resource()==='hallazgos'?'anulado':'anulada']:['en_proceso',this.resource()==='hallazgos'?'anulado':'anulada']):['aprobado','observado','rechazado'];if(action==='transicion')this.actionOptions=this.actionOptions.filter(s=>this.api.can(this.module!.key,['anulado','anulada'].includes(s)?'eliminar':'editar'));this.modal.set('action');}
 async runAction(){this.saving.set(true);this.error.set('');try{await this.api.request(`${this.resource()}/${this.selected()!['id']}/${this.actionName}`,'POST',this.actionName==='transicion'||(this.actionName==='revisar'&&this.resource()==='documento_versiones')?this.form:{});this.modal.set(null);this.notice.set('Operación completada.');await this.load(this.page);}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}}
 async exportCsv(){
  if(!this.api.can(this.module?.key||'','descargar'))return;
  this.saving.set(true);this.error.set('');
  try{const resource=this.resource().replace('/','-');const blob=await this.api.downloadBlob(`exportar/${resource}?q=${encodeURIComponent(this.search)}&estado=${encodeURIComponent(this.status)}${filterQuery(this.columnFilters)}${this.movementQuery}`);
   const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=resource==='epp_movimientos'?'kardex-epp.xlsx':`${resource}.xlsx`;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
  }catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}
 }

 async uploadEquipmentSheet(event:Event){const input=event.target as HTMLInputElement,file=input.files?.[0],row=this.selected();if(!file||!row||this.saving()||!this.api.can('almacenes','editar'))return;this.saving.set(true);this.error.set('');try{const body=new FormData();body.append('archivo',file);const result=await this.api.request(`epp_catalogo/${row['id']}/ficha`,'POST',body);this.selected.set({...row,ficha_archivo_id:result.data.id,ficha_tecnica:result.data.nombre_original});this.notice.set('Ficha técnica almacenada correctamente.');}catch(e){this.error.set(this.message(e));}finally{input.value='';this.saving.set(false);}}
 async downloadEquipmentSheet(){const row=this.selected();if(!row||this.saving())return;this.saving.set(true);this.error.set('');try{const blob=await this.api.downloadBlob(`epp_catalogo/${row['id']}/ficha`);const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=row['ficha_tecnica']||'ficha-tecnica';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}}
 async upload(event:Event){const file=(event.target as HTMLInputElement).files?.[0];if(!file)return;if(!this.authenticated)return;this.saving.set(true);this.error.set('');try{const body=new FormData();body.append('archivo',file);body.append('clasificacion','interno');await this.api.request('archivos/subir','POST',body);this.notice.set('Archivo cargado. Ya puedes vincularlo a una versión documental.');await this.load();}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}}
 async download(){try{const res=await fetch(`${this.api.base}/archivos/${this.selected()!['id']}/descargar`,{headers:{Authorization:`Bearer ${this.api.token()}`,Accept:'application/json'}});if(!res.ok)throw new Error('No se pudo descargar el archivo.');const url=URL.createObjectURL(await res.blob());const a=document.createElement('a');a.href=url;a.download=this.selected()?.['nombre_original']||'archivo';a.click();URL.revokeObjectURL(url);}catch(e){this.error.set(this.message(e));}}
 openPassword(){this.form={};this.error.set('');this.modal.set('password');}
 async password(){this.saving.set(true);try{await this.api.request('auth/password','POST',this.form);this.api.clear();this.modal.set(null);this.resetWorkspace();this.notice.set('Contraseña actualizada. Inicia sesión nuevamente.');await this.navigate('dashboard');}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}}
 purchaseLines:Row[]=[];purchaseDetails:Row[]=[];workerCostDetails:Row[]=[];
 get purchaseSymbol(){return this.form['moneda']==='USD'?'US$':'S/';}
 get purchaseTotalPen(){return this.purchaseTotal*(this.form['moneda']==='USD'?Number(this.form['tipo_cambio']||0):1);}
 get purchaseTotal(){return this.purchaseLines.reduce((total,line)=>total+Number(line['cantidad']||0)*Number(line['costo_unitario']||0),0);}
 money(value:any){return value===null||value===undefined?'Pendiente de valorización':Number(value).toLocaleString('es-PE',{minimumFractionDigits:2,maximumFractionDigits:6});}
 async openPurchase(){if(!this.api.can('almacenes','crear')||this.saving())return;this.error.set('');this.form={moneda:'PEN',tipo_cambio:1,clave_operacion:crypto.randomUUID(),fecha:new Intl.DateTimeFormat('en-CA',{year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date())};this.purchaseLines=[{cantidad:1}];this.modal.set('purchase');this.saving.set(true);try{await Promise.all(['almacenes','epp_catalogo'].map(async r=>{const rows=await this.api.all(r);this.lookup.update(v=>({...v,[r]:rows.filter(row=>row['activo'])}));}));}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}}
 async savePurchase(){if(this.saving()||!this.api.can('almacenes','crear'))return;this.saving.set(true);this.error.set('');try{await this.api.request('compras-epp','POST',{...this.form,detalles:this.purchaseLines.map(line=>({epp_id:line['epp_id'],almacen_id:line['almacen_id'],cantidad:line['cantidad'],costo_unitario:line['costo_unitario']}))});this.modal.set(null);this.notice.set('Compra registrada. Las existencias y su valorización se actualizaron.');await this.load(1);}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}}
 inventoryMode='movimientos';
 checklist=signal<Row|null>(null);
 answers:Record<number,Row>={};
 answerKey(type:string){return ({texto:'valor_texto',texto_largo:'valor_texto',numero:'valor_numero',fecha:'valor_fecha',fecha_hora:'valor_fecha_hora',si_no:'valor_booleano',cumplimiento:'cumplimiento'} as Record<string,string>)[type]||'valor_texto';}
 async openInventory(mode='movimientos'){
  if(!this.authenticated)return;
  this.inventoryMode=mode;this.returnWorkerId=undefined;this.form={tipo:'ajuste',clave_operacion:crypto.randomUUID()};this.error.set('');this.modal.set('inventory');
  try{const refs=mode==='movimientos'?['epp_catalogo','almacenes']:['epp_entrega_detalles'];for(const r of refs)this.lookup.update(v=>({...v,[r]:[]}));await Promise.all(refs.map(async r=>{const rows=mode==='devoluciones'?(await this.api.request('inventario/devoluciones/opciones')).data:await this.api.all(r);this.lookup.update(v=>({...v,[r]:rows}));}));}catch(e){this.error.set(this.message(e));}
 }
 async saveInventory(){if(this.saving())return;if(this.inventoryMode==='devoluciones'&&(!this.returnSelection||this.returnSelection['trabajador_id']!==this.returnWorkerId)){this.error.set('Selecciona el trabajador y uno de sus EPP pendientes de devolución.');return;}this.saving.set(true);this.error.set('');try{const body={...this.form};if(this.inventoryMode==='devoluciones')delete body['tipo'];if(Number(body['cantidad'])<=0)delete body['costo_unitario'];await this.api.request('inventario/'+this.inventoryMode,'POST',body);this.modal.set(null);this.notice.set(this.inventoryMode==='devoluciones'?`Devolución de ${this.returnSelection?.['nombres']} ${this.returnSelection?.['apellidos']} registrada. Existencias y costo del trabajador actualizados.`:'Inventario actualizado correctamente.');await this.load();}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}}
 async openChecklist(){
  if(!this.authenticated)return;
  this.saving.set(true);this.error.set('');
  try{const record=this.selected()!;const [template,detail]=await Promise.all([this.api.request(`checklist_plantillas/${record['plantilla_id']}/formulario`),this.api.request(`checklist_registros/${record['id']}/detalle`)]);
   this.checklist.set(template.data);this.answers={};
   for(const section of template.data.secciones)for(const q of section.preguntas){const prior=detail.data.respuestas.find((a:Row)=>a['pregunta_id']===q.id);const key=this.answerKey(q.tipo);this.answers[q.id]={pregunta_id:q.id,opciones:prior?.opciones||[],evidencias:(prior?.evidencias||[]).map((e:Row)=>({archivo_id:e['archivo_id'],tipo:e['tipo']})),observacion:prior?.observacion||''};if(prior?.[key]!=null)this.answers[q.id][key]=q.tipo==='si_no'?Boolean(Number(prior[key])):q.tipo==='fecha_hora'?this.localDateTime(prior[key]):prior[key];}
   this.modal.set('checklist');
  }catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}
 }
 toggleOption(id:number,option:number,event:Event){const selected=(event.target as HTMLInputElement).checked;const a=this.answers[id];a['opciones']=selected?[...a['opciones'],option]:a['opciones'].filter((v:number)=>v!==option);}
 async attachEvidence(event:Event,id:number,type:string){const file=(event.target as HTMLInputElement).files?.[0];if(!file)return;this.saving.set(true);try{const body=new FormData();body.append('archivo',file);const result=await this.api.request('archivos/subir','POST',body);this.answers[id]['evidencias'].push({archivo_id:result.data.id,tipo:['fotografia','firma'].includes(type)?type:'archivo'});}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}}
 async saveAnswers(){this.saving.set(true);this.error.set('');try{const answers=Object.values(this.answers).map(a=>{const copy={...a};if(copy['valor_fecha_hora'])copy['valor_fecha_hora']=new Date(copy['valor_fecha_hora']).toISOString().slice(0,19).replace('T',' ');return copy;});await this.api.request(`checklist_registros/${this.selected()!['id']}/respuestas`,'POST',{respuestas:answers});this.modal.set('detail');this.notice.set('Respuestas guardadas. Puedes enviar la inspección para validarla.');}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}}

}
