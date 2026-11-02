import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiService, Row } from '../core/api.service';
@Component({selector:'hse-delivery-report',standalone:true,imports:[FormsModule],template: `
<section class="panel" style="padding:24px"><h2>Informe de entregas de EPP</h2><p class="form-help">Busca el número de referencia o planilla. Cada entrega masiva reúne a todos sus trabajadores en una misma planilla.</p>
@if(error()){<p class="error" role="alert">{{error()}}</p>}
@if(loading()){<p role="status">Cargando planillas…</p>}@else if(!options){<button class="button secondary" (click)="load()">Reintentar</button>}@else if(!planillas.length){<p class="form-help">Todavía no hay planillas registradas. Se generan automáticamente al confirmar una entrega.</p>}@else{
<form #report="ngForm" (ngSubmit)="report.valid&&download()"><div class="form-grid">
<label>Número de referencia / planilla *<input type="number" name="planilla" [(ngModel)]="numero" list="delivery-planillas" min="1" step="1" required placeholder="Ej.: 1" [disabled]="busy()"/><datalist id="delivery-planillas">@for(item of planillas;track item['numero']){<option [value]="item['numero']">Planilla {{item['numero']}} · {{item['fecha']}} · {{item['trabajadores']}} trabajadores</option>}</datalist></label>
</div>
@if(selected;as item){<div class="form-grid" style="margin-top:16px"><div><strong>Fecha de entrega</strong><p>{{item['fecha']}}</p></div><div><strong>Zona</strong><p>{{item['zona']}}</p></div><div><strong>Responsable del registro</strong><p>{{item['responsable']}}</p></div><div><strong>Trabajadores en la planilla</strong><p>{{item['trabajadores']}}</p></div></div>}@else if(numero){<p class="form-help">No existe una planilla con ese número en esta empresa.</p>}
<p class="form-help">El PDF incluye el número de planilla, fecha de entrega, zona y responsable del registro, junto con DNI, nombre, EPP, cantidad y firma de cada trabajador. Las entregas anuladas no se incluyen en el detalle.</p><div class="modal-actions"><button class="button primary" [disabled]="busy()||report.invalid||!selected">{{busy()?'Generando PDF…':'Generar y descargar PDF'}}</button></div></form>}
</section>`})
export class DeliveryReportComponent implements OnInit {
 readonly api=inject(ApiService);loading=signal(true);busy=signal(false);error=signal('');options:Row|null=null;numero:number|null=null;
 get planillas():Row[]{return this.options?.['planillas']||[];}
 get selected(){return this.planillas.find(item=>Number(item['numero'])===this.numero);}
 ngOnInit(){void this.load();}
 async load(){this.loading.set(true);this.error.set('');try{this.options=await this.api.request('informe-entregas/opciones');}catch(e){this.error.set(e instanceof Error?e.message:'No se pudieron cargar las planillas.');}finally{this.loading.set(false);}}
 get query(){return new URLSearchParams({planilla_numero:String(this.numero)}).toString();}
 async download(){if(this.busy()||!this.selected||!this.api.can('epp','descargar'))return;this.busy.set(true);this.error.set('');try{const blob=await this.api.downloadBlob('informe-entregas/pdf?'+this.query);const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='planilla-entrega-'+this.numero+'.pdf';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}catch(e){this.error.set(e instanceof Error?e.message:'No se pudo generar el PDF.');}finally{this.busy.set(false);}}
}
