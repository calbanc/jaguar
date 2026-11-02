import { Component, OnInit, ViewChild, inject, signal } from '@angular/core';
import { TrainingReportDesignerComponent } from './training-report-designer.component';
import { IconComponent } from '../core/icon.component';
import { FormsModule } from '@angular/forms';
import { ApiService, Row } from '../core/api.service';
import { ColumnFilterComponent } from '../core/column-filter.component';
import { matchesColumns } from '../core/table-filters';

@Component({selector:'hse-training-management',standalone:true,imports:[IconComponent,FormsModule,ColumnFilterComponent,TrainingReportDesignerComponent],templateUrl:'./training-management.component.html',styleUrls:['./training.component.css','./training-management.component.css']})
export class TrainingManagementComponent implements OnInit {
 @ViewChild(TrainingReportDesignerComponent) designer?:TrainingReportDesignerComponent;
 reportTab=false;
 showReports(){if(this.mode||this.removing){if(!window.confirm('¿Cerrar el formulario actual y abrir formatos de informe?'))return;}this.mode=null;this.removing=null;this.reportTab=true;}
 readonly api=inject(ApiService);
 readonly catalogs=[{key:'tema_capacitacion',label:'Temas'},{key:'lugar_capacitacion',label:'Lugares'},{key:'capacitador',label:'Expositores'},{key:'empresa_capacitacion',label:'Empresas expositoras'}];
 resource='tema_capacitacion';rows:Row[]=[];page=1;pageSize=10;status='1';filters:Record<string,string>={};
 loading=signal(false);saving=signal(false);error=signal('');notice=signal('');
 form:Row={};selected:Row|null=null;mode:'new'|'edit'|'view'|null=null;removing:Row|null=null;
 get title(){return this.catalogs.find(c=>c.key===this.resource)!.label;}
 get fields(){const fields=[{key:'nombre',label:'Nombre',type:'text',max:200},{key:'descripcion',label:'Descripción',type:'textarea',max:10000}];if(this.resource==='lugar_capacitacion')fields.push({key:'direccion',label:'Dirección',type:'text',max:255});if(['capacitador','empresa_capacitacion'].includes(this.resource))fields.push({key:'numero_documento',label:this.resource==='capacitador'?'DNI / documento':'RUC / identificación fiscal',type:'text',max:30},{key:'email',label:'Correo electrónico',type:'email',max:200},{key:'telefono',label:'Teléfono',type:'tel',max:30});return fields;}
 get filtered():Row[]{return this.rows.filter(r=>(!this.status||Boolean(r['activo'])===(this.status==='1'))&&matchesColumns(r,this.filters));}
 get lastPage(){return Math.max(1,Math.ceil(this.filtered.length/this.pageSize));}
 get currentPage(){return Math.min(this.page,this.lastPage);}
 get pageRows(){return this.filtered.slice((this.currentPage-1)*this.pageSize,this.currentPage*this.pageSize);}
 get firstRecord(){return this.filtered.length?(this.currentPage-1)*this.pageSize+1:0;}
 get lastRecord(){return Math.min(this.currentPage*this.pageSize,this.filtered.length);}
 get pages(){const start=Math.max(1,Math.min(this.currentPage-2,this.lastPage-4));return Array.from({length:Math.min(5,this.lastPage)},(_,i)=>start+i);}
 get hasFilters(){return !!this.status||Object.values(this.filters).some(value=>value!=='');}
 filterColumn(key:string,value:string){this.filters={...this.filters,[key]:value};this.page=1;}
 filterStatus(value:string){this.status=value;this.page=1;}
 clearFilters(){this.filters={};this.status='';this.page=1;}
 goToPage(page:number){if(this.loading()||this.saving())return;this.page=Math.max(1,Math.min(page,this.lastPage));}
 get query(){return 'empresa_id='+encodeURIComponent(this.api.company()?.['id']);}
 async ngOnInit(){await this.load();}
 async load(){this.loading.set(true);this.error.set('');try{const first=await this.api.request(`capacitaciones/${this.resource}?${this.query}&per_page=100`);const rows=[...first.data];for(let page=2;page<=first.last_page;page++)rows.push(...(await this.api.request(`capacitaciones/${this.resource}?${this.query}&per_page=100&page=${page}`)).data);this.rows=rows;this.page=this.currentPage;}catch(e){this.rows=[];this.error.set(this.message(e));}finally{this.loading.set(false);}}
 async change(key:string){if(this.loading()||this.saving()||!this.catalogs.some(c=>c.key===key))return;if(this.reportTab&&this.designer?.dirty&&!window.confirm('Hay cambios sin guardar en el formato. ¿Descartarlos?'))return;this.reportTab=false;this.resource=key;this.mode=null;this.removing=null;this.selected=null;this.filters={};this.status='1';this.page=1;this.notice.set('');await this.load();}
 open(mode:'new'|'edit'|'view',row:Row|null=null){if(this.loading()||this.saving())return;if(mode!=='view'&&!this.api.can('capacitaciones',mode==='new'?'crear':'editar'))return;if(mode==='edit'&&!row?.['activo'])return;this.mode=mode;this.selected=row;this.form={...row};this.error.set('');this.notice.set('');this.removing=null;}
 async save(){if(this.saving()||this.loading()||!['new','edit'].includes(this.mode||'')||!this.api.can('capacitaciones',this.mode==='new'?'crear':'editar'))return;this.saving.set(true);this.error.set('');try{const body:Row={empresa_id:this.api.company()?.['id']};for(const f of this.fields)body[f.key]=this.form[f.key]||null;await this.api.request('capacitaciones/'+this.resource+(this.selected?'/'+this.selected['id']:''),this.selected?'PATCH':'POST',body);this.mode=null;this.selected=null;await this.load();this.notice.set('Registro guardado.');}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}}
 async deactivate(){if(!this.removing||this.saving()||this.loading()||!this.api.can('capacitaciones','eliminar'))return;this.saving.set(true);this.error.set('');try{await this.api.request(`capacitaciones/${this.resource}/${this.removing['id']}?${this.query}`,'DELETE');this.removing=null;this.mode=null;await this.load();this.notice.set('Registro desactivado. Se conserva su historial.');}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}}
 message(e:unknown){return e instanceof Error?e.message:'No se pudo completar la operación.';}
}
