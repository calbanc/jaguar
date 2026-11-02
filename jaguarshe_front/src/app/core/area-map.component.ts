import { Component, EventEmitter, Input, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { PolygonMapComponent } from './polygon-map.component';
import { Row } from './api.service';

@Component({selector:'hse-area-map',standalone:true,imports:[FormsModule,PolygonMapComponent],templateUrl:'./area-map.component.html',styleUrl:'./area-map.component.css'})
export class AreaMapComponent {
 @Input() areas:Row[]=[];
 @Input() loading=false;
 @Output() openArea=new EventEmitter<Row>();
 site='';selected='';
 get sites(){return [...new Set(this.areas.map(a=>String(a['sede_nombre']||'Sin sede')))].sort();}
 get scoped(){return this.areas.filter(a=>!this.site||a['sede_nombre']===this.site);}
 get visible(){return this.scoped.filter(a=>(!this.selected||String(a['id'])===this.selected)&&this.ring(a).length>=4);}
 get missing(){return this.scoped.filter(a=>!this.ring(a).length);}
 ring(area:Row):number[][]{const geometry=area['coordenadas'];if(geometry?.type!=='Polygon')return [];const points=geometry.coordinates?.[0];return Array.isArray(points)&&points.every((p:unknown)=>Array.isArray(p)&&p.length===2&&p.every(Number.isFinite))?points:[];}
 changeSite(){this.selected='';}
}
