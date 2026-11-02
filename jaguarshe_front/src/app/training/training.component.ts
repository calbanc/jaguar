import { Component, ElementRef, OnInit, OnDestroy, ViewChild, inject, signal } from '@angular/core';
import { IconComponent } from '../core/icon.component';
import { FormsModule } from '@angular/forms';
import { ApiService, Row } from '../core/api.service';
import { SearchSelectComponent, SelectOption } from '../core/search-select.component';
import { ColumnFilterComponent } from '../core/column-filter.component';
import { matchesColumns } from '../core/table-filters';

@Component({selector:'hse-training',standalone:true,imports:[IconComponent,FormsModule,SearchSelectComponent,ColumnFilterComponent],templateUrl:'./training.component.html',styleUrl:'./training.component.css'})
export class TrainingComponent implements OnInit, OnDestroy {
 readonly api=inject(ApiService);
 @ViewChild('reportDialog') reportDialog?:ElementRef<HTMLDialogElement>;
 reportFormats:Row[]=[];reportFormatId:number|null=null;reportNumber:number|null=null;
 reportLoading=signal(false);reportDownloading=signal(false);reportError=signal('');private destroyed=false;
 @ViewChild('signatureDialog') dialog?:ElementRef<HTMLDialogElement>;
 signatureImages=signal<Record<number,string>>({});signatureFailures=signal<Record<number,boolean>>({});private imageLoad=0;
 @ViewChild('signatureCanvas') canvas?:ElementRef<HTMLCanvasElement>;
 loading=signal(false);saving=signal(false);error=signal('');notice=signal('');
 options:Row={};form:Row={};number:number|null=null;loadedNumber:number|null=null;id:number|null=null;
 participants:Row[]=[];filters:Record<string,string>={};search='';worker:number|null=null;
 signing:Row|null=null;preview='';previewExisting=false;drawing=false;hasInk=false;private pointer:number|null=null;
 catalog='tema_capacitacion';catalogName='';
 readonly catalogs=[{key:'tema_capacitacion',label:'Tema'},{key:'lugar_capacitacion',label:'Lugar'},{key:'capacitador',label:'Expositor'},{key:'empresa_capacitacion',label:'Empresa expositora'}];
 readonly columns=[{key:'numero_documento',label:'DNI / documento'},{key:'nombres',label:'Nombres'},{key:'apellidos',label:'Apellidos'},{key:'puesto',label:'Puesto'},{key:'firma_estado',label:'Firma'}];
 get company(){return this.api.company()?.['id'];}
 get query(){return '?empresa_id='+encodeURIComponent(this.company);}
 get pendingSearch(){return this.number!==this.loadedNumber;}
 get editable(){return !this.loading()&&!this.saving()&&!this.pendingSearch&&this.form['activo']!==false&&this.form['activo']!==0;}
 get canEdit(){return this.api.can('capacitaciones',this.id?'editar':'crear');}
 get hasParticipantFilters(){return !!this.search||Object.values(this.filters).some(value=>value.trim()!=='');}
 get filtered():Row[]{return this.participants.map(row=>({...row,firma_estado:row['firma_fecha']?'Firmado':row['firma_disponible']?'Firma guardada · pendiente de aplicar':'Pendiente'})).filter(row=>matchesColumns(row,this.filters)&&(!this.search||Object.values(row).join(' ').toLocaleLowerCase().includes(this.search.toLocaleLowerCase())));}
 choices(key:string):SelectOption[]{return (this.options[key]||[]).map((r:Row)=>({value:r['id'],label:r['nombre']}));}
 get workers():SelectOption[]{return (this.options['trabajadores']||[]).filter((r:Row)=>!this.participants.some(p=>p['trabajador_id']===r['id'])).map((r:Row)=>({value:r['id'],label:`${r['numero_documento']} · ${r['apellidos']} ${r['nombres']}`}));}
 async ngOnInit(){await this.newPlanilla();}
 ngOnDestroy(){this.destroyed=true;this.imageLoad++;this.clearSignatureImages();this.clearPreview();}
 message(e:unknown){return e instanceof Error?e.message:'No se pudo completar la operación.';}
 async openReport(){
  if(!this.id||!this.loadedNumber||this.pendingSearch||this.loading()||this.saving()||this.reportLoading()||this.reportDownloading()||!this.api.can('capacitaciones','descargar'))return;
  this.reportNumber=this.loadedNumber;this.reportFormats=[];this.reportFormatId=null;this.reportError.set('');this.reportLoading.set(true);this.reportDialog?.nativeElement.showModal();
  try{const result=await this.api.request('capacitaciones/formatos'+this.query);if(this.destroyed)return;this.reportFormats=result.data;if(this.reportFormats.length===1)this.reportFormatId=this.reportFormats[0]['id'];}
  catch(e){this.reportError.set(this.message(e));}finally{this.reportLoading.set(false);}
 }
 closeReport(){if(this.reportLoading()||this.reportDownloading())return;this.reportDialog?.nativeElement.close();}
 cancelReport(event:Event){event.preventDefault();this.closeReport();}
 async downloadReport(){
  if(!this.reportNumber||!this.reportFormatId||this.reportLoading()||this.reportDownloading()||!this.api.can('capacitaciones','descargar'))return;
  const format=this.reportFormats.find(row=>row['id']===this.reportFormatId);if(!format)return;
  const number=this.reportNumber;this.reportDownloading.set(true);this.reportError.set('');
  try{const blob=await this.api.downloadBlob(`capacitaciones/formatos/${format['id']}/pdf${this.query}&numero_planilla=${number}`);if(this.destroyed)return;const url=URL.createObjectURL(blob);try{const link=document.createElement('a');link.href=url;link.download=`${String(format['nombre']).replace(/[^a-zA-Z0-9áéíóúñÁÉÍÓÚÑ _-]/g,'_')}-planilla-${number}.pdf`;link.click();}finally{setTimeout(()=>URL.revokeObjectURL(url),1000);}this.notice.set(`Informe de la planilla N.º ${number} descargado.`);this.reportDialog?.nativeElement.close();}
  catch(e){this.reportError.set(this.message(e));}finally{this.reportDownloading.set(false);}
 }
 async newPlanilla(){
  if(this.saving()||this.loading())return;
  this.loading.set(true);this.error.set('');this.notice.set('');this.closeSignature();
  try{this.options=await this.api.request('capacitaciones/opciones'+this.query);this.id=null;this.number=this.loadedNumber=this.options['siguiente_numero'];this.form={fecha:this.options['fecha'],responsable_registro_id:this.options['responsable_actual'],hora_inicio:'',hora_termino:'',activo:true};this.participants=[];this.imageLoad++;this.clearSignatureImages();this.worker=null;this.filters={};this.search='';}
  catch(e){this.error.set(this.message(e));}finally{this.loading.set(false);}
 }
 async find(event:Event){
  event.preventDefault();event.stopPropagation();if(this.loading()||this.saving())return;
  if(!this.number||!Number.isSafeInteger(this.number)||this.number<1){this.error.set('Ingresa un número de planilla válido.');return;}
  this.loading.set(true);this.error.set('');this.notice.set('');this.closeSignature();
  try{const result=await this.api.request('capacitaciones/planillas/'+this.number+this.query);const rows=await this.api.request('capacitaciones/capacitacion/'+result.data.id+'/participantes'+this.query);this.id=result.data.id;this.form={...result.data,hora_inicio:result.data.hora_inicio.slice(0,5),hora_termino:result.data.hora_termino.slice(0,5)};this.loadedNumber=this.number;this.participants=rows.data;await this.loadSignatureImages();this.filters={};this.search='';this.worker=null;}
  catch(e){this.error.set(this.message(e));}finally{this.loading.set(false);}
 }
 async save(){
  if(!this.editable||!this.canEdit)return;
  this.saving.set(true);this.error.set('');this.notice.set('');
  try{const body:Row={empresa_id:this.company};for(const key of ['fecha','hora_inicio','hora_termino','tema_capacitacion_id','lugar_capacitacion_id','capacitador_id','empresa_capacitacion_id','responsable_registro_id','observaciones'])body[key]=this.form[key];const result=await this.api.request('capacitaciones/capacitacion'+(this.id?'/'+this.id:''),this.id?'PATCH':'POST',body);this.id=result.data.id;this.number=this.loadedNumber=result.data.numero_planilla;this.notice.set('Planilla N.º '+this.number+' guardada. Ya puedes agregar trabajadores.');}
  catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}
 }
 async refreshParticipants(){this.participants=(await this.api.request('capacitaciones/capacitacion/'+this.id+'/participantes'+this.query)).data;await this.loadSignatureImages();}
 clearSignatureImages(){for(const url of Object.values(this.signatureImages()))URL.revokeObjectURL(url);this.signatureImages.set({});this.signatureFailures.set({});}
 async loadSignatureImages(){
  const load=++this.imageLoad;this.clearSignatureImages();
  await Promise.all(this.participants.filter(row=>row['firma_fecha']||row['firma_disponible']).map(async row=>{
   try{const blob=await this.api.downloadBlob(this.participantPath(row)+'/firma'+this.query+(!row['firma_fecha']?'&fuente=trabajador':''));if(load!==this.imageLoad)return;const url=URL.createObjectURL(blob);this.signatureImages.update(images=>({...images,[row['id']]:url}));}
   catch{if(load===this.imageLoad)this.signatureFailures.update(errors=>({...errors,[row['id']]:true}));}
  }));
 }
 async addWorker(){
  if(!this.id||!this.worker||!this.editable||!this.api.can('capacitaciones','editar'))return;
  this.saving.set(true);this.error.set('');
  try{await this.api.request('capacitaciones/capacitacion/'+this.id+'/participantes','POST',{empresa_id:this.company,trabajador_id:this.worker});this.worker=null;await this.refreshParticipants();this.notice.set('Trabajador agregado. Selecciona Firmar para registrar su firma.');}
  catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}
 }
 async remove(row:Row){
  if(!this.editable||!this.api.can('capacitaciones','editar'))return;
  this.saving.set(true);this.error.set('');try{await this.api.request(this.participantPath(row)+this.query,'DELETE');await this.refreshParticipants();}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}
 }
 participantPath(row:Row){return 'capacitaciones/capacitacion/'+this.id+'/participantes/'+row['id'];}
 openSignature(row:Row){this.closeSignature();this.error.set('');this.signing=row;this.hasInk=false;this.dialog?.nativeElement.showModal();}
 cancelSignature(event:Event){event.preventDefault();if(!this.saving())this.closeSignature();}
 clearPreview(){if(this.preview)URL.revokeObjectURL(this.preview);this.preview='';}
 closeSignature(){this.dialog?.nativeElement.close();this.signing=null;this.previewExisting=false;this.hasInk=false;this.drawing=false;this.pointer=null;this.clearPreview();}
 clearCanvas(){const c=this.canvas?.nativeElement;if(c)c.getContext('2d')?.clearRect(0,0,c.width,c.height);this.hasInk=false;}
 point(e:PointerEvent){const c=this.canvas!.nativeElement,r=c.getBoundingClientRect();return {x:(e.clientX-r.left)*c.width/r.width,y:(e.clientY-r.top)*c.height/r.height};}
 start(e:PointerEvent){if(this.saving()||this.pointer!==null)return;e.preventDefault();const c=this.canvas!.nativeElement;c.setPointerCapture(e.pointerId);this.pointer=e.pointerId;this.drawing=true;const ctx=c.getContext('2d')!,p=this.point(e);ctx.strokeStyle='#172c24';ctx.lineWidth=2.5;ctx.lineCap='round';ctx.lineJoin='round';ctx.beginPath();ctx.moveTo(p.x,p.y);}
 move(e:PointerEvent){if(!this.drawing||this.pointer!==e.pointerId)return;e.preventDefault();const p=this.point(e),ctx=this.canvas!.nativeElement.getContext('2d')!;ctx.lineTo(p.x,p.y);ctx.stroke();this.hasInk=true;}
 end(e:PointerEvent){if(this.pointer!==e.pointerId)return;this.drawing=false;this.pointer=null;}
 async saveSignature(){
  if(!this.signing||!this.hasInk||this.saving())return;
  this.saving.set(true);this.error.set('');
  try{const blob=await new Promise<Blob|null>(resolve=>this.canvas!.nativeElement.toBlob(resolve,'image/png'));if(!blob)throw new Error('No se pudo generar la firma.');const body=new FormData();body.append('empresa_id',String(this.company));if(this.signing['firma_fecha'])body.append('reemplazar','1');body.append('firma',blob,String(this.signing['numero_documento'])+'.png');await this.api.request(this.participantPath(this.signing)+'/firma','POST',body);this.closeSignature();await this.refreshParticipants();this.notice.set('Firma guardada.');}
  catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}
 }
 async viewSignature(row:Row){if(this.saving())return;this.saving.set(true);this.error.set('');try{const blob=await this.api.downloadBlob(this.participantPath(row)+'/firma'+this.query+(!row['firma_fecha']?'&fuente=trabajador':''));this.closeSignature();this.signing=row;this.previewExisting=!row['firma_fecha'];this.preview=URL.createObjectURL(blob);this.dialog?.nativeElement.showModal();}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}}
 async useExistingSignature(){
  if(!this.signing||!this.previewExisting||!this.editable||!this.api.can('capacitaciones','editar'))return;
  this.saving.set(true);this.error.set('');
  try{await this.api.request(this.participantPath(this.signing)+'/firma','POST',{empresa_id:this.company,usar_guardada:true});this.closeSignature();await this.refreshParticipants();this.notice.set('Firma guardada del trabajador agregada a esta planilla.');}
  catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}
 }
 async createCatalog(){if(!this.catalogName.trim()||this.saving()||!this.api.can('capacitaciones','crear'))return;this.saving.set(true);this.error.set('');try{const result=await this.api.request('capacitaciones/'+this.catalog,'POST',{empresa_id:this.company,nombre:this.catalogName.trim()});this.options[this.catalog]=[...(this.options[this.catalog]||[]),result.data];this.form[this.catalog+'_id']=result.data.id;this.catalogName='';this.notice.set('Opción creada.');}catch(e){this.error.set(this.message(e));}finally{this.saving.set(false);}}
}
