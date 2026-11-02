import { Component, ElementRef, OnDestroy, OnInit, ViewChild, inject, signal, HostListener } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { ApiService, Row } from '../core/api.service';
import { IconComponent } from '../core/icon.component';

type Design={html:string;orientacion:string;margen:number;fuente:number;columnas:string[];detalle?:'tabla'|'listado'};
type FieldGroup={key:string;label:string;fields:{key:string;label:string}[]};
const INITIAL='<h1 style="text-align:center">INFORME DE CAPACITACIÓN</h1><p><b>Empresa:</b> {{empresa.razon_social}}</p><p><b>Planilla:</b> {{planilla.numero_planilla}} · <b>Fecha:</b> {{planilla.fecha}}</p><h2>{{tema.nombre}}</h2><p>{{tema.descripcion}}</p><p><b>Horario:</b> {{planilla.hora_inicio}} a {{planilla.hora_termino}}</p><p><b>Lugar:</b> {{lugar.nombre}} · {{lugar.direccion}}</p><p><b>Expositor:</b> {{expositor.nombre}}</p><p><b>Empresa expositora:</b> {{empresa_expositora.nombre}}</p><p><b>Responsable:</b> {{responsable.nombre}}</p><h2>Objetivo de la capacitación</h2><p>Escribe aquí el objetivo y los contenidos desarrollados.</p><h2>Participantes</h2><p>Total: {{participantes.total}} · Firmas registradas: {{participantes.firmados}}</p><div>{{participantes.tabla}}</div><h2>Observaciones</h2><p>{{planilla.observaciones}}</p><p>Escribe aquí tus conclusiones y recomendaciones.</p>';

@Component({selector:'hse-training-report-designer',standalone:true,imports:[FormsModule,IconComponent],templateUrl:'./training-report-designer.component.html',styleUrls:['./training-report-designer.component.css']})
export class TrainingReportDesignerComponent implements OnInit,OnDestroy {
 readonly api=inject(ApiService);private sanitizer=inject(DomSanitizer);
 @ViewChild('editor') editor?:ElementRef<HTMLDivElement>;
 @ViewChild('previewDialog') previewDialog?:ElementRef<HTMLDialogElement>;
 @ViewChild('previewFrame') previewFrame?:ElementRef<HTMLIFrameElement>;
 planillas:Row[]=[];
 templates:Row[]=[];groups:FieldGroup[]=[];columns:{key:string;label:string}[]=[];
 selectedId:number|null=null;version=1;name='Nuevo formato';fieldSearch='';numero:number|null=null;
 design:Design={html:INITIAL,orientacion:'vertical',margen:15,fuente:11,detalle:'tabla',columnas:['numero','numero_documento','nombres','apellidos','puesto','firma']};
 busy=signal(false);error=signal('');notice=signal('');dirty=false;ready=false;previewReady=false;previewUrl:SafeResourceUrl|null=null;
 private previewObjectUrl='';private range:Range|null=null;private destroyed=false;
 get query(){return 'empresa_id='+encodeURIComponent(this.api.company()?.['id']);}
 get editable(){return this.api.can('capacitaciones',this.selectedId?'editar':'crear');}
 get filteredGroups(){const search=this.fieldSearch.toLocaleLowerCase();return this.groups.map(group=>({...group,fields:group.fields.filter(field=>(group.label+' '+field.label).toLocaleLowerCase().includes(search))})).filter(group=>group.fields.length);}
 async ngOnInit(){this.busy.set(true);try{const [list,meta]=await Promise.all([this.api.request(`capacitaciones/formatos?${this.query}`),this.api.request(`capacitaciones/formatos/campos?${this.query}`)]);if(this.destroyed)return;this.templates=list.data;this.groups=Object.entries(meta.campos as Record<string,{label:string;fields:Record<string,string>}>).map(([key,value])=>({key,label:value.label,fields:Object.entries(value.fields).map(([key,label])=>({key,label}))}));this.columns=Object.entries(meta.columnas as Record<string,string>).map(([key,label])=>({key,label}));this.ready=true;this.paint();void this.loadPlanillas();}catch(e){this.fail(e);}finally{this.busy.set(false);}}
 async loadPlanillas(){try{const first=await this.api.request(`capacitaciones/capacitacion?${this.query}&per_page=100`);let rows:Row[]=[...first.data];for(let page=2;page<=first.last_page;page++)rows.push(...(await this.api.request(`capacitaciones/capacitacion?${this.query}&per_page=100&page=${page}`)).data);if(!this.destroyed)this.planillas=rows.sort((a,b)=>b['numero_planilla']-a['numero_planilla']);}catch(e){this.fail(e);}}
 async download(){
  if(this.busy()||!this.selectedId||!this.numero||!this.api.can('capacitaciones','descargar'))return;
  if(this.dirty){this.error.set('Guarda los cambios del formato antes de descargar el informe.');return;}
  this.busy.set(true);this.error.set('');
  try{const blob=await this.api.downloadBlob(`capacitaciones/formatos/${this.selectedId}/pdf?${this.query}&numero_planilla=${this.numero}`);if(this.destroyed)return;const url=URL.createObjectURL(blob);const link=document.createElement('a');link.href=url;link.download=`capacitacion-${this.numero}-formato-${this.selectedId}.pdf`;link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);this.notice.set('Informe PDF descargado con el formato guardado.');}catch(e){this.fail(e);}finally{this.busy.set(false);}
 }
 private paint(){this.range=null;if(this.editor)this.editor.nativeElement.innerHTML=this.design.html;}
 remember(){const selection=window.getSelection();if(selection?.rangeCount&&this.editor?.nativeElement.contains(selection.anchorNode)){this.range=selection.getRangeAt(0).cloneRange();}}
 changed(){this.design.html=this.editor?.nativeElement.innerHTML||'';this.dirty=true;this.remember();}
 private restore(){const el=this.editor?.nativeElement;if(!el)return;el.focus();const selection=window.getSelection();if(!selection)return;selection.removeAllRanges();if(this.range&&el.contains(this.range.commonAncestorContainer))selection.addRange(this.range);else{const range=document.createRange();range.selectNodeContents(el);range.collapse(false);selection.addRange(range);}}
 command(command:string,value?:string){if(!this.editable||this.busy())return;this.restore();document.execCommand(command,false,value);this.changed();}
 insert(group:string,field:string){this.command('insertText',`{{${group}.${field}}}`);}
 paste(event:ClipboardEvent){event.preventDefault();this.command('insertText',event.clipboardData?.getData('text/plain')||'');}
 insertTable(){this.command('insertHTML','<table><tbody><tr><td>Etiqueta</td><td>Dato o texto</td></tr><tr><td>Etiqueta</td><td>Dato o texto</td></tr></tbody></table><p><br></p>');}
 toggleColumn(key:string,checked:boolean){this.design.columnas=checked?[...this.design.columnas,key]:this.design.columnas.filter(column=>column!==key);this.dirty=true;}
 moveColumn(index:number,delta:number){const next=index+delta;if(next<0||next>=this.design.columnas.length)return;const columns=[...this.design.columnas];[columns[index],columns[next]]=[columns[next],columns[index]];this.design.columnas=columns;this.dirty=true;}
 columnLabel(key:string){return this.columns.find(column=>column.key===key)?.label||key;}
 private canDiscard(){return !this.dirty||window.confirm('Hay cambios sin guardar. ¿Quieres descartarlos?');}
 newTemplate(blank=false){if(this.busy()||!this.canDiscard())return;this.selectedId=null;this.version=1;this.name='Nuevo formato';this.design={html:blank?'<p>Escribe aquí tu informe.</p>':INITIAL,orientacion:'vertical',margen:15,fuente:11,detalle:'tabla',columnas:['numero','numero_documento','nombres','apellidos','puesto','firma']};this.paint();this.dirty=false;this.notice.set('');this.error.set('');}
 async open(id:number|null){if(!id||this.busy()||!this.canDiscard())return;this.busy.set(true);this.error.set('');try{const result=await this.api.request(`capacitaciones/formatos/${id}?${this.query}`);this.selectedId=result.data.id;this.name=result.data.nombre;this.version=result.data.version;this.design={...result.data.diseno,detalle:result.data.diseno.detalle||'tabla'};this.paint();this.dirty=false;this.notice.set('');}catch(e){this.fail(e);}finally{this.busy.set(false);}}
 duplicate(){if(!this.selectedId||this.busy())return;this.selectedId=null;this.version=1;this.name+=' (copia)';this.dirty=true;}
 async save(){if(this.busy()||!this.editable||!this.name.trim()||!this.design.columnas.length)return;this.busy.set(true);this.error.set('');try{const result=await this.api.request('capacitaciones/formatos'+(this.selectedId?'/'+this.selectedId:''),this.selectedId?'PATCH':'POST',{empresa_id:this.api.company()?.['id'],nombre:this.name,version:this.version,diseno:this.design});this.selectedId=result.data.id;this.version=result.data.version;this.design={...result.data.diseno,detalle:result.data.diseno.detalle||'tabla'};this.paint();this.dirty=false;this.templates=(await this.api.request(`capacitaciones/formatos?${this.query}`)).data;this.notice.set('Formato guardado para esta empresa.');}catch(e){this.fail(e);}finally{this.busy.set(false);}}
 async remove(){if(!this.selectedId||this.busy()||!window.confirm(`¿Desactivar el formato «${this.name}»?`))return;this.busy.set(true);this.error.set('');try{await this.api.request(`capacitaciones/formatos/${this.selectedId}?${this.query}`,'DELETE');this.templates=this.templates.filter(row=>row['id']!==this.selectedId);this.dirty=false;this.busy.set(false);this.newTemplate();this.notice.set('Formato desactivado.');}catch(e){this.fail(e);}finally{this.busy.set(false);}}
 async preview(){if(this.busy()||!this.numero||!this.design.columnas.length)return;this.busy.set(true);this.error.set('');try{const result=await this.api.request('capacitaciones/formatos/vista-previa','POST',{empresa_id:this.api.company()?.['id'],numero_planilla:this.numero,diseno:this.design});if(this.destroyed)return;this.releasePreview();this.previewReady=false;this.previewObjectUrl=URL.createObjectURL(new Blob([result.html],{type:'text/html'}));this.previewUrl=this.sanitizer.bypassSecurityTrustResourceUrl(this.previewObjectUrl);this.previewDialog?.nativeElement.showModal();}catch(e){this.fail(e);}finally{this.busy.set(false);}}
 print(){if(this.previewReady)this.previewFrame?.nativeElement.contentWindow?.print();}
 closePreview(){this.previewDialog?.nativeElement.close();this.releasePreview();}
 private releasePreview(){if(this.previewObjectUrl)URL.revokeObjectURL(this.previewObjectUrl);this.previewObjectUrl='';this.previewUrl=null;this.previewReady=false;}
 fail(error:unknown){this.error.set(error instanceof Error?error.message:'No se pudo completar la operación.');}
 @HostListener('window:beforeunload',['$event']) beforeUnload(event:BeforeUnloadEvent){if(this.dirty){event.preventDefault();event.returnValue='';}}
 ngOnDestroy(){this.destroyed=true;this.releasePreview();}
}
