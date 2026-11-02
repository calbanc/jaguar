import { Component, Input, ElementRef, OnDestroy, OnInit, ViewChild, inject, signal } from '@angular/core';
import { printImplementLabels } from './implement-labels';
import { PolygonMapComponent } from '../core/polygon-map.component';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { ApiService, Row } from '../core/api.service';
import { ColumnFilterComponent } from '../core/column-filter.component';
import { matchesColumns } from '../core/table-filters';
import { IconComponent } from '../core/icon.component';

type Field={key:string;label:string;type?:string;required?:boolean;options?:Row[];min?:number;max?:number;step?:string};
@Component({selector:'hse-safety-implements',standalone:true,imports:[ColumnFilterComponent,PolygonMapComponent,FormsModule,CommonModule,IconComponent],templateUrl:'./safety-implements.component.html',styleUrl:'./safety-implements.component.css'})
export class SafetyImplementsComponent implements OnInit,OnDestroy {
 @Input() set moduleMode(value:'operacion'|'gestion'){this.selectSection(value);}
 readonly api=inject(ApiService);
 @ViewChild('editor') editor?:ElementRef<HTMLDialogElement>;
 data:Row={categorias:[],catalogo:[],proveedores:[],implementos:[],instalaciones:[],inspecciones:[],costos:[],areas:[],responsables:[],fotos:[]};
 section:'operacion'|'gestion'='operacion';stockSearch='';stockUnitSearch='';stockCatalogId:number|null=null;stockStatus='';
 tab='unidades';areaId:number|null=null;search='';status='';agendaStatus='pendiente';selectedId:number|null=null;
 printingLabels=signal(false);
 busy=signal(false);saving=signal(false);error=signal('');notice=signal('');formError=signal('');
 photoUrls=signal<Record<number,string>>({});private generation=0;private destroyed=false;
 mode='';title='';form:Row={};fields:Field[]=[];endpoint='';method='POST';points:number[][]=[];
 readonly operationTabs=[{key:'unidades',label:'Implementos por área'},{key:'mapa',label:'Mapa'},{key:'agenda',label:'Inspecciones'},{key:'areas',label:'Áreas y coordenadas'}];
 readonly managementTabs=[{key:'stock',label:'Stock de implementos'},{key:'catalogo',label:'Maestro de implementos'},{key:'categorias',label:'Categorías'},{key:'proveedores',label:'Proveedores de seguridad'},{key:'costos',label:'Costos'}];
 get tabs(){return this.section==='gestion'?this.managementTabs:this.operationTabs;}
 onTabKey(event:KeyboardEvent,key:string){
  const index=this.tabs.findIndex(t=>t.key===key);let next=index;
  if(event.key==='ArrowRight')next=(index+1)%this.tabs.length;
  else if(event.key==='ArrowLeft')next=(index-1+this.tabs.length)%this.tabs.length;
  else if(event.key==='Home')next=0;
  else if(event.key==='End')next=this.tabs.length-1;
  else return;
  event.preventDefault();this.tab=this.tabs[next].key;this.selectedId=null;
  const list=(event.currentTarget as HTMLElement).closest('[role="tablist"]');
  list?.querySelectorAll<HTMLButtonElement>('[role="tab"]')[next]?.focus();
 }
 selectSection(section:'operacion'|'gestion'){this.section=section;this.tab=section==='gestion'?'stock':'unidades';this.selectedId=null;this.areaId=null;this.search='';this.status='';this.stockSearch='';this.stockUnitSearch='';this.stockCatalogId=null;this.stockStatus='';this.tableFilters={};this.notice.set('');this.editor?.nativeElement.close();}
 tableFilters:Record<string,Record<string,string>>={};
 filters(table:string){return this.tableFilters[table]||(this.tableFilters[table]={});}
 filterRows(table:string,rows:Row[]):Row[]{const {activo,...filters}=this.filters(table);return rows.filter(row=>{const values=this.filterValues(table,row);return (!activo||values['activo']===activo)&&matchesColumns(values,filters);});}
 filterValues(table:string,r:Row):Row{
  if(table==='stock')return {...r,implemento:[r['catalog']['codigo'],r['catalog']['nombre']].join(' '),categoria:r['catalog']['categoria']};
  if(table==='stockUnits')return {...r,proveedor:this.stockProvider(r['id']),area:this.area(this.installation(r['id'])?.['area_id'])['nombre']||'Sin instalar'};
  if(table==='categorias')return {...r,activo:r['activo']?'Activo':'Inactivo'};
  if(table==='catalogo')return {...r,periodicidad:r['periodicidad_cantidad']+' '+this.label(r['periodicidad_unidad']),activo:r['activo']?'Activo':'Inactivo'};
  if(table==='proveedores')return {...r,comunicacion:[r['email'],r['telefono']].join(' '),activo:r['activo']?'Activo':'Inactivo'};
  if(table==='costos')return {...r,implemento:this.item(r['implemento_id'])['codigo'],concepto:this.label(r['tipo']),proveedor:this.provider(r['proveedor_id']),importe:r['moneda']+' '+Number(r['importe']).toFixed(2),area:this.area(this.installation(r['implemento_id'])?.['area_id'])['nombre']||'Sin instalar'};
  return r;
 }
 get stockTypeOptions(){return this.data['catalogo'].map((r:Row)=>({value:String(r['id']),label:r['nombre']}));}
 get stockStateOptions(){return ['disponible','instalado','mantenimiento','baja'].map(value=>({value,label:this.label(value)}));}
 readonly activeOptions=[{value:'Activo',label:'Activo'},{value:'Inactivo',label:'Inactivo'}];
 get filteredStockRows(){return this.filterRows('stock',this.stockRows);}
 get filteredStockUnits(){return this.filterRows('stockUnits',this.stockUnits);}
 get stockRows(){
  const query=this.stockSearch.trim().toLocaleLowerCase();
  return this.data['catalogo'].filter((c:Row)=>[c['codigo'],c['nombre']].join(' ').toLocaleLowerCase().includes(query)).map((catalog:Row)=>{
   const units:Row[]=this.data['implementos'].filter((r:Row)=>r['catalogo_id']===catalog['id']);
   const count=(state:string)=>units.filter(r=>r['estado']===state).length;
   return {catalog,disponible:count('disponible'),instalado:count('instalado'),mantenimiento:count('mantenimiento'),baja:count('baja'),total:units.length};
  });
 }
 get stockTotals(){return this.stockRows.reduce((sum:{disponible:number;instalado:number;mantenimiento:number;baja:number;total:number},r:{disponible:number;instalado:number;mantenimiento:number;baja:number;total:number})=>({disponible:sum.disponible+r.disponible,instalado:sum.instalado+r.instalado,mantenimiento:sum.mantenimiento+r.mantenimiento,baja:sum.baja+r.baja,total:sum.total+r.total}),{disponible:0,instalado:0,mantenimiento:0,baja:0,total:0});}
 get stockUnits():Row[]{return this.data['implementos'].filter((r:Row)=>(this.stockCatalogId===null||r['catalogo_id']===this.stockCatalogId)&&(!this.stockStatus||r['estado']===this.stockStatus)&&[r['codigo'],r['serie'],r['marca'],r['modelo']].join(' ').toLocaleLowerCase().includes(this.stockUnitSearch.trim().toLocaleLowerCase()));}
 showStock(catalogId:number,state=''){this.stockCatalogId=catalogId;this.stockStatus=state;this.selectedId=null;}
 stockProvider(id:number){const supplier=this.item(id)['proveedor_id'];if(supplier)return this.provider(supplier);const purchase=this.data['costos'].filter((r:Row)=>r['implemento_id']===id&&r['tipo']==='compra').sort((a:Row,b:Row)=>String(a['fecha']).localeCompare(String(b['fecha']))||a['id']-b['id'])[0];return purchase?this.provider(purchase['proveedor_id']):'Sin proveedor registrado';}
 get query(){return '?empresa_id='+encodeURIComponent(this.api.company()?.['id']);}
 can(action:string){return this.api.can('implementos',action);}
 get today(){return this.data['hoy']||'';}
 get selected(){return this.data['implementos'].find((r:Row)=>r['id']===this.selectedId);}
 catalog(id:number){return this.data['catalogo'].find((r:Row)=>r['id']===id)||{};}
 area(id:number){return this.data['areas'].find((r:Row)=>r['id']===id)||{};}
 item(id:number){return this.data['implementos'].find((r:Row)=>r['id']===id)||{};}
 provider(id:number){return this.data['proveedores'].find((r:Row)=>r['id']===id)?.['nombre']||'—';}
 responsible(id:number){return this.data['responsables'].find((r:Row)=>r['id']===id)?.['nombre']||'Sin asignar';}
 installation(id:number){return this.data['instalaciones'].find((r:Row)=>r['implemento_id']===id&&!r['fecha_retiro']);}
 next(id:number){return this.data['inspecciones'].find((r:Row)=>r['implemento_id']===id&&r['estado']==='pendiente');}
 last(id:number){return this.data['inspecciones'].filter((r:Row)=>r['implemento_id']===id&&r['estado']==='realizada').sort((a:Row,b:Row)=>b['fecha_realizada'].localeCompare(a['fecha_realizada']))[0];}
 label(value:string){return ({disponible:'Disponible',instalado:'Instalado',mantenimiento:'En mantenimiento',baja:'Baja',pendiente:'Pendiente',realizada:'Realizada',cancelada:'Cancelada',operativo:'Operativo',requiere_mantenimiento:'Requiere mantenimiento',no_operativo:'No operativo',dias:'días',meses:'meses',anios:'años',compra:'Compra',instalacion:'Instalación',recarga:'Recarga',renovacion:'Renovación'} as Record<string,string>)[value]||value;}
 overdue(date:string){return !!date&&date<this.today;}
 due(date:string){if(!date)return 'Sin fecha';if(this.overdue(date))return 'Vencida';const days=(Date.parse(date)-Date.parse(this.today))/86400000;return days<=30?'Próxima':'Vigente';}
 get units():Row[]{return this.data['implementos'].filter((r:Row)=>{const i=this.installation(r['id']);const c=this.catalog(r['catalogo_id']);const a=this.area(i?.['area_id']);return (!this.areaId||i?.['area_id']===this.areaId)&&(!this.status||r['estado']===this.status)&&[r['codigo'],c['nombre'],c['categoria'],a['nombre'],a['zona'],a['sede'],r['serie']].join(' ').toLocaleLowerCase().includes(this.search.toLocaleLowerCase());});}
 get inspections():Row[]{return this.data['inspecciones'].filter((r:Row)=>{const i=this.data['instalaciones'].find((i:Row)=>i['id']===r['instalacion_id']);return (!this.areaId||i?.['area_id']===this.areaId)&&(!this.agendaStatus||r['estado']===this.agendaStatus||this.agendaStatus==='vencida'&&r['estado']==='pendiente'&&this.overdue(r['fecha_programada']));}).sort((a:Row,b:Row)=>a['fecha_programada'].localeCompare(b['fecha_programada']));}
 get costs():Row[]{return this.filterRows('costos',this.data['costos'].filter((r:Row)=>!this.areaId||this.installation(r['implemento_id'])?.['area_id']===this.areaId));}
 total(currency:string,rows:Row[]=this.costs){return rows.filter(r=>r['moneda']===currency).reduce((sum,r)=>sum+Number(r['importe']),0);}
 history(key:string):Row[]{return this.data[key].filter((r:Row)=>r['implemento_id']===this.selectedId);}
 thumbnail(id:number){const p=this.data['fotos'].filter((r:Row)=>r['implemento_id']===id).at(-1);return p?this.photoUrls()[p['id']]:null;}
 get photo(){return this.thumbnail(this.selectedId!);}
 get metrics(){return {total:this.units.length,overdue:this.units.filter(r=>this.overdue(this.next(r['id'])?.['fecha_programada'])).length,renew:this.units.filter(r=>this.overdue(this.installation(r['id'])?.['fecha_renovacion'])).length};}
 async ngOnInit(){await this.load();this.openScannedItem();}
 openScannedItem(){if(typeof window==='undefined')return;const query=new URLSearchParams(window.location.search);const id=query.get('implemento');if(!id)return;if(query.get('empresa')!==String(this.api.company()?.['id'])){this.error.set('Esta etiqueta pertenece a otra empresa. Inicia sesión en la empresa correspondiente.');return;}const item=this.data['implementos'].find((r:Row)=>String(r['id'])===id);if(item){this.selectedId=item['id'];setTimeout(()=>{if(!this.destroyed)document.querySelector('.safety .detail')?.scrollIntoView({block:'start'});},0);}else if(!this.error())this.error.set('No se encontró el implemento o no tienes acceso a su ficha.');}
 async printLabels(rows:Row[]){if(this.printingLabels()||!rows.length)return;const popup=window.open('','_blank');if(!popup){this.error.set('Permite abrir la ventana de etiquetas para imprimir los códigos QR.');return;}popup.opener=null;popup.document.body.textContent='Preparando etiquetas…';this.printingLabels.set(true);this.error.set('');try{await printImplementLabels(popup,rows.map(r=>({...r,nombre:this.catalog(r['catalogo_id'])['nombre'],proveedor:this.stockProvider(r['id'])})),Number(this.api.company()?.['id']),window.location.href);}catch(e){popup.close();this.error.set(this.message(e));}finally{this.printingLabels.set(false);}}

 ngOnDestroy(){this.destroyed=true;this.generation++;this.clearPhotos();}
 clearPhotos(){Object.values(this.photoUrls()).forEach(url=>URL.revokeObjectURL(url));this.photoUrls.set({});}
 async load(){if(this.busy())return;this.busy.set(true);this.error.set('');try{const data=await this.api.request('implementos/datos'+this.query);if(this.destroyed)return;this.data=data;void this.loadPhotos();}catch(e){this.error.set(this.message(e));}finally{this.busy.set(false);}}
 async loadPhotos(){const generation=++this.generation;this.clearPhotos();for(const photo of this.data['fotos']){try{const blob=await this.api.downloadBlob('implementos/fotos/'+photo['id']+this.query);if(generation!==this.generation)return;const url=URL.createObjectURL(blob);this.photoUrls.update(values=>({...values,[photo['id']]:url}));}catch{/* Individual unavailable images retain their placeholder. */}}}
 message(e:unknown){return e instanceof Error?e.message:'No se pudo completar la operación.';}
 choices(rows:Row[],label='nombre'){return rows.map(row=>({id:row['id'],nombre:row[label]}));}
 start(mode:string,title:string,endpoint:string,fields:Field[],form:Row={},method='POST'){this.mode=mode;this.title=title;this.endpoint=endpoint;this.fields=fields;this.form={...form};this.method=method;this.formError.set('');this.editor?.nativeElement.showModal();}
 openCatalog(row?:Row){this.start('catalogo',row?'Editar tipo de implemento':'Nuevo tipo de implemento','catalogo'+(row?'/'+row['id']:''),[{key:'codigo',label:'Código',required:true},{key:'nombre',label:'Nombre',required:true},{key:'categoria_id',label:'Categoría',required:true,options:this.choices((this.data['categorias']||[]).filter((c:Row)=>c['activo']||c['id']===row?.['categoria_id']))},{key:'descripcion',label:'Descripción',type:'textarea'},{key:'periodicidad_cantidad',label:'Inspeccionar cada',type:'number',required:true,min:1,max:3650},{key:'periodicidad_unidad',label:'Unidad de tiempo',required:true,options:[{id:'dias',nombre:'Días'},{id:'meses',nombre:'Meses'},{id:'anios',nombre:'Años'}]},...(row?[{key:'activo',label:'Estado',options:[{id:true,nombre:'Activo'},{id:false,nombre:'Inactivo'}]}]:[])],row?{...row,activo:!!row['activo']}:{periodicidad_cantidad:1,periodicidad_unidad:'meses'},row?'PATCH':'POST');}
 openCategory(row?:Row){this.start('categoria',row?'Editar categoría':'Nueva categoría','categorias'+(row?'/'+row['id']:''),[{key:'nombre',label:'Nombre de la categoría',required:true},{key:'descripcion',label:'Descripción',type:'textarea'},...(row?[{key:'activo',label:'Estado',options:[{id:true,nombre:'Activo'},{id:false,nombre:'Inactivo'}]}]:[])],row?{...row,activo:!!row['activo']}:{},row?'PATCH':'POST');}
 openProvider(row?:Row){this.start('proveedor',row?'Editar proveedor':'Nuevo proveedor','proveedores'+(row?'/'+row['id']:''),[{key:'nombre',label:'Razón social / nombre',required:true},{key:'documento',label:'RUC / documento'},{key:'contacto',label:'Contacto'},{key:'email',label:'Correo',type:'email'},{key:'telefono',label:'Teléfono'},...(row?[{key:'activo',label:'Estado',options:[{id:true,nombre:'Activo'},{id:false,nombre:'Inactivo'}]}]:[])],row?{...row,activo:!!row['activo']}:{},row?'PATCH':'POST');}
 openStockEntry(){const selected=this.catalog(this.stockCatalogId!);this.start('ingreso','Ingreso de implementos al stock','ingresos',[{key:'categoria_id',label:'Categoría',required:true,options:this.choices((this.data['categorias']||[]).filter((r:Row)=>r['activo']))},{key:'catalogo_id',label:'Subcategoría / tipo de implemento',required:true,options:[]},{key:'proveedor_id',label:'Proveedor del ingreso',required:true,options:this.choices(this.data['proveedores'].filter((r:Row)=>r['activo']))},{key:'cantidad',label:'Cantidad de unidades',type:'number',required:true,min:1,max:200},{key:'marca',label:'Marca (para todas las unidades)'},{key:'modelo',label:'Modelo (para todas las unidades)'},{key:'fecha_adquisicion',label:'Fecha de adquisición',type:'date'},{key:'observaciones',label:'Observaciones del ingreso',type:'textarea'}],{categoria_id:selected['categoria_id']||null,catalogo_id:selected['activo']?selected['id']:null,cantidad:1,fecha_adquisicion:this.today});}
 fieldOptions(field:Field):Row[]{if(this.mode==='ingreso'&&field.key==='catalogo_id')return this.choices(this.data['catalogo'].filter((r:Row)=>r['activo']&&r['categoria_id']===this.form['categoria_id']));return field.options||[];}
 fieldChanged(key:string){if(this.mode==='ingreso'&&key==='categoria_id')this.form['catalogo_id']=null;}
 openItem(row?:Row){this.start('unidad',row?'Editar implemento':'Registrar implemento','unidades'+(row?'/'+row['id']:''),[{key:'codigo',label:'Código único de la unidad',required:true},...(!row?[{key:'catalogo_id',label:'Tipo de implemento',required:true,options:this.choices(this.data['catalogo'].filter((r:Row)=>r['activo']))}]:[]),{key:'marca',label:'Marca'},{key:'modelo',label:'Modelo'},{key:'serie',label:'Número de serie'},{key:'fecha_adquisicion',label:'Fecha de adquisición',type:'date'},{key:'observaciones',label:'Observaciones',type:'textarea'}],row||{catalogo_id:this.tab==='stock'?this.stockCatalogId:null},row?'PATCH':'POST');}
 openInstall(row:Row){this.start('instalar','Instalar / trasladar / renovar · '+row['codigo'],`unidades/${row['id']}/instalaciones`,[{key:'area_id',label:'Área',required:true,options:this.data['areas'].filter((a:Row)=>a['activo']).map((a:Row)=>({id:a['id'],nombre:`${a['sede']} / ${a['zona']} / ${a['nombre']}`}))},{key:'ubicacion',label:'Ubicación dentro del área',required:true},{key:'piso',label:'Piso / nivel'},{key:'latitud',label:'Latitud',type:'number',min:-90,max:90,step:'0.0000001'},{key:'longitud',label:'Longitud',type:'number',min:-180,max:180,step:'0.0000001'},{key:'fecha_instalacion',label:'Fecha de instalación / traslado',type:'date',required:true},{key:'fecha_renovacion',label:'Próxima renovación',type:'date'},{key:'responsable_id',label:'Responsable de inspección',options:this.choices(this.data['responsables'])}],{fecha_instalacion:this.today,area_id:this.areaId});}
 openRetire(row:Row){this.start('retiro','Retirar implemento · '+row['codigo'],`unidades/${row['id']}/retiro`,[{key:'estado',label:'Estado después del retiro',required:true,options:[{id:'disponible',nombre:'Disponible para otra instalación'},{id:'mantenimiento',nombre:'En mantenimiento'},{id:'baja',nombre:'Baja definitiva'}]}],{estado:'disponible'});}
 openInspection(row:Row){this.start('inspeccion','Registrar inspección · '+this.item(row['implemento_id'])['codigo'],`inspecciones/${row['id']}/realizar`,[{key:'fecha_realizada',label:'Fecha realizada',type:'date',required:true},{key:'resultado',label:'Resultado',required:true,options:[{id:'operativo',nombre:'Operativo'},{id:'requiere_mantenimiento',nombre:'Requiere mantenimiento'},{id:'no_operativo',nombre:'No operativo'}]},{key:'observaciones',label:'Revisiones realizadas, hallazgos y acciones',type:'textarea',required:true}],{fecha_realizada:this.today,resultado:'operativo'});}
 openAssign(row:Row){this.start('asignar','Asignar responsable',`inspecciones/${row['id']}/responsable`,[{key:'responsable_id',label:'Responsable',required:true,options:this.choices(this.data['responsables'])}],{responsable_id:row['responsable_id']},'PATCH');}
 openCost(row:Row){this.start('costo','Registrar costo · '+row['codigo'],`unidades/${row['id']}/costos`,[{key:'proveedor_id',label:'Proveedor',required:true,options:this.choices(this.data['proveedores'].filter((r:Row)=>r['activo']))},{key:'tipo',label:'Concepto',required:true,options:['compra','instalacion','recarga','mantenimiento','renovacion'].map(id=>({id,nombre:this.label(id)}))},{key:'fecha',label:'Fecha',type:'date',required:true},{key:'comprobante',label:'Comprobante'},{key:'moneda',label:'Moneda',required:true,options:[{id:'PEN',nombre:'Soles (PEN)'},{id:'USD',nombre:'Dólares (USD)'}]},{key:'importe',label:'Importe total',required:true,type:'number',min:0,step:'0.01'},{key:'observaciones',label:'Observaciones',type:'textarea'}],{tipo:'compra',fecha:this.today,moneda:'PEN'});}
 openArea(row:Row){this.points=(row['coordenadas']?.coordinates?.[0]||[]).map((p:number[])=>[...p]);if(this.points.length>1&&JSON.stringify(this.points[0])===JSON.stringify(this.points.at(-1)))this.points.pop();this.start('area','Delimitar área · '+row['nombre'],`areas/${row['id']}`,[],{},'PATCH');}
 close(event?:Event){event?.preventDefault();if(!this.saving())this.editor?.nativeElement.close();}
 async save(){if(this.saving())return;this.saving.set(true);this.formError.set('');try{const body:Row={empresa_id:this.api.company()?.['id']};for(const field of this.fields)body[field.key]=this.form[field.key]??null;if(this.mode==='unidad'&&this.method==='PATCH')body['catalogo_id']=this.form['catalogo_id'];if(this.mode==='area')body['coordenadas']=this.points;const result=await this.api.request('implementos/'+this.endpoint,this.method,body);const message=this.mode==='ingreso'?`${result.cantidad} implementos ingresados al stock, disponibles para instalar.`:'Registro guardado.';this.editor?.nativeElement.close();this.notice.set(message);await this.load();}catch(e){this.formError.set(this.message(e));}finally{this.saving.set(false);}}
 async upload(event:Event,itemId:number,inspectionId?:number){const input=event.target as HTMLInputElement;const file=input.files?.[0];if(!file||this.saving())return;this.saving.set(true);this.error.set('');try{const body=new FormData();body.append('empresa_id',String(this.api.company()?.['id']));body.append('archivo',file);if(inspectionId)body.append('inspeccion_id',String(inspectionId));await this.api.request(`implementos/unidades/${itemId}/fotos`,'POST',body);await this.load();this.notice.set('Fotografía guardada.');}catch(e){this.error.set(this.message(e));}finally{input.value='';this.saving.set(false);}}
 get mapAreas():Row[]{return this.data['areas'].filter((a:Row)=>(!this.areaId||a['id']===this.areaId)&&a['coordenadas']);}
 get mapUnits():Row[]{return this.units.map(row=>({...row,location:this.installation(row['id'])})).filter(row=>row.location?.['latitud']!==null&&row.location?.['latitud']!==undefined&&row.location?.['longitud']!==null);}
 get bounds(){const points:number[][]=this.mapAreas.flatMap(a=>a['coordenadas'].coordinates[0]);for(const row of this.mapUnits)points.push([Number(row['location']['longitud']),Number(row['location']['latitud'])]);if(!points.length)return {x:0,y:0,w:1,h:1};const xs=points.map(p=>Number(p[0])),ys=points.map(p=>Number(p[1]));const minX=Math.min(...xs),minY=Math.min(...ys);return {x:minX,y:minY,w:Math.max(.0001,Math.max(...xs)-minX),h:Math.max(.0001,Math.max(...ys)-minY)};}
 x(longitude:number){const b=this.bounds;const scale=Math.min(820/b.w,400/b.h);return 450+(Number(longitude)-b.x-b.w/2)*scale;}
 y(latitude:number){const b=this.bounds;const scale=Math.min(820/b.w,400/b.h);return 250-(Number(latitude)-b.y-b.h/2)*scale;}
 polygon(row:Row){return row['coordenadas'].coordinates[0].map((p:number[])=>`${this.x(p[0])},${this.y(p[1])}`).join(' ');}
 center(row:Row){const points=row['coordenadas'].coordinates[0];return {x:points.reduce((sum:number,p:number[])=>sum+this.x(p[0]),0)/points.length,y:points.reduce((sum:number,p:number[])=>sum+this.y(p[1]),0)/points.length};}
}
