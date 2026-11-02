import { Component, EventEmitter, OnInit, Output, inject, signal } from '@angular/core';
import { ColumnFilterComponent } from '../core/column-filter.component';
import { matchesColumns } from '../core/table-filters';
import { FormsModule } from '@angular/forms';
import { SearchSelectComponent, SelectOption } from '../core/search-select.component';
import { ApiService, Row } from '../core/api.service';
@Component({selector:'hse-delivery',standalone:true,imports:[ColumnFilterComponent,FormsModule,SearchSelectComponent],templateUrl:'./delivery.component.html'})
export class DeliveryComponent implements OnInit {
 previewFilters:Record<string,string>={};
 get filteredPreview(){return (this.preview()?.['filas']||[]).filter((row:Row)=>matchesColumns({...row,trabajador:row['trabajador']||'No encontrado',validacion:row['errores'].length?row['errores'].join(' '):'Correcto'},this.previewFilters));}
 readonly api=inject(ApiService);
 @Output() saved=new EventEmitter<string>();@Output() savingChange=new EventEmitter<boolean>();
 bulk=false;file:File|null=null;preview=signal<Row|null>(null);
 loading=signal(true);saving=signal(false);error=signal('');options:Row={};
 form:Row={uuid_cliente:crypto.randomUUID(),cantidad:1,motivo:'nuevo'};
 readonly reasons:SelectOption[]=[{value:'nuevo',label:'Nuevo'},{value:'reposicion',label:'Reposición'}];
 get workerOptions():SelectOption[]{return (this.options['trabajadores']||[]).map((row:Row)=>({value:row['id'],label:`${row['numero_documento']} · ${row['nombres']} ${row['apellidos']}`}));}
 get equipmentOptions():SelectOption[]{return (this.options['epps']||[]).map((row:Row)=>({value:row['id'],label:[row['codigo'],row['nombre'],row['talla'],row['unidad']].filter(Boolean).join(' · ')}));}
 get warehouseOptions():SelectOption[]{return (this.options['almacenes']||[]).map((row:Row)=>({value:row['id'],label:row['nombre']}));}
 get responsibleOptions():SelectOption[]{return (this.options['responsables']||[]).map((row:Row)=>({value:row['id'],label:row['nombre']}));}
 get stock(){return Number((this.options['stock']||[]).find((s:Row)=>s['epp_id']===this.form['epp_id']&&s['almacen_id']===this.form['almacen_id'])?.['disponible']||0);}
 get unit(){return (this.options['epps']||[]).find((e:Row)=>e['id']===this.form['epp_id'])?.['unidad']||'';}
 async ngOnInit(){await this.loadOptions();}
 async loadOptions(){this.loading.set(true);this.error.set('');try{this.options=await this.api.request('entregas-epp/opciones');this.form={...this.form,fecha:this.options['fecha'],hora:this.options['hora'],entregado_por:this.options['responsable_actual']};}catch(e){this.error.set(e instanceof Error?e.message:'No se pudieron cargar las opciones.');}finally{this.loading.set(false);}}
 changeMode(value:boolean){if(this.saving())return;this.bulk=value;this.preview.set(null);this.error.set('');this.form['uuid_cliente']=crypto.randomUUID();}
 invalidate(){this.preview.set(null);}
 selectFile(event:Event){this.previewFilters={};this.preview.set(null);this.file=(event.target as HTMLInputElement).files?.[0]||null;this.error.set('');if(this.file&&(!this.file.name.toLowerCase().endsWith('.xlsx')||this.file.size>5*1024*1024)){this.file=null;this.error.set('Selecciona un archivo .xlsx de hasta 5 MB.');}}
 async template(){this.error.set('');try{const blob=await this.api.downloadBlob('entregas-epp/plantilla');const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='plantilla-entrega-masiva.xlsx';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}catch(e){this.error.set(e instanceof Error?e.message:'No se pudo descargar la plantilla.');}}
 async saveBulk(confirm=false){if(this.saving()||this.loading()||!this.file||!this.api.can('epp','crear')||(confirm&&!this.preview()?.['validacion']))return;this.saving.set(true);this.savingChange.emit(true);this.error.set('');try{const body=new FormData();for(const key of ['uuid_cliente','epp_id','almacen_id','cantidad','motivo','fecha','hora','entregado_por','observacion']){if(this.form[key]!==undefined&&this.form[key]!==null)body.append(key,String(this.form[key]));}body.append('archivo',this.file);body.append('confirmar',confirm?'1':'0');if(confirm)body.append('validacion',this.preview()!['validacion']);const result=await this.api.request('entregas-epp/masiva','POST',body);if(result.registrados){this.saved.emit('Planilla N.º '+result.planilla_numero+'. '+result.message);}else{this.preview.set(result);}}catch(e){this.preview.set(null);this.error.set(e instanceof Error?e.message:'No se pudo procesar la entrega masiva.');}finally{this.saving.set(false);this.savingChange.emit(false);}}
 async save(){if(this.saving()||this.loading()||!this.api.can('epp','crear'))return;this.saving.set(true);this.savingChange.emit(true);this.error.set('');try{const body:Row={};for(const key of ['uuid_cliente','trabajador_id','epp_id','almacen_id','cantidad','motivo','fecha','hora','entregado_por','observacion'])body[key]=this.form[key];const result=await this.api.request('entregas-epp','POST',body);this.saved.emit('Planilla N.º '+result.planilla_numero+'. Entrega registrada. Se descontaron las existencias del almacén.');}catch(e){this.error.set(e instanceof Error?e.message:'No se pudo registrar la entrega.');}finally{this.saving.set(false);this.savingChange.emit(false);}}
}
