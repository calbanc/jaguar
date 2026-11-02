import type * as GeoJSON from 'geojson';
import { Component, ElementRef, EventEmitter, Input, Output, ViewChild, afterNextRender, OnChanges, OnDestroy, NgZone, inject, signal } from '@angular/core';
import type { Map, Marker, GeoJSONSource } from 'mapbox-gl';
import { Row } from './api.service';
import { mapboxToken } from './mapbox.config';

@Component({selector:'hse-polygon-map',standalone:true,template:`
 <div class="tools">
 @if(editable){<button type="button" (click)="drawing=!drawing" [disabled]="disabled">{{drawing?'Terminar dibujo':'Agregar vértices'}}</button><button type="button" (click)="undo()" [disabled]="disabled||!points.length">Deshacer punto</button><button type="button" (click)="clear()" [disabled]="disabled||!points.length">Borrar polígono</button>}
 <button type="button" (click)="locate()">Mi ubicación</button><button type="button" (click)="fit()">Ver polígono(s)</button>
 </div>
 <div #canvas class="canvas" aria-label="Mapa de áreas Mapbox"></div>
 <p role="status">{{status()}}</p>
 @if(editable){<p>{{points.length}} vértices. Activa «Agregar vértices» y pulsa el mapa siguiendo el borde del área. Arrastra los puntos para ajustarlos. Se requieren al menos tres vértices; guarda el formulario para conservar el polígono.</p>}
 `,styles:[`:host{display:block;min-width:0}.canvas{height:400px;width:100%;border-radius:10px;background:#edf3ef}.tools{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}button{padding:8px 12px;border:1px solid #ccd9d0;border-radius:7px;background:white;color:#24543b;cursor:pointer}button:disabled{opacity:.5;cursor:default}p{font-size:12px;line-height:1.5;color:#526b5b;margin:8px 0}`]})
export class PolygonMapComponent implements OnChanges,OnDestroy {
 @ViewChild('canvas') canvas!:ElementRef<HTMLDivElement>;
 @Input() editable=false;
 @Input() disabled=false;
 @Input() points:(number|null)[][]=[];
 @Input() areas:Row[]=[];
 @Output() pointsChange=new EventEmitter<number[][]>();
 @Output() openArea=new EventEmitter<Row>();
 status=signal('Cargando mapa…');drawing=true;
 private map?:Map;private markers:Marker[]=[];private lib?:typeof import('mapbox-gl');private destroyed=false;private observer?:ResizeObserver;
 private zone=inject(NgZone);private geometryKey='';
 constructor(){afterNextRender(()=>void this.initialize());}
 async initialize(){
  try{
   const lib=await import('mapbox-gl');if(this.destroyed)return;this.lib=lib;
   // Serve the worker unchanged: Angular async transforms break its isolated scope.
   lib.default.workerUrl=new URL('assets/mapbox/mapbox-gl-csp-worker.js',document.baseURI).href;
   const map=this.map=new lib.Map({container:this.canvas.nativeElement,accessToken:mapboxToken,style:'mapbox://styles/mapbox/streets-v12',center:[-77.0428,-12.0464],zoom:12});
   map.addControl(new lib.NavigationControl(),'top-right');
   this.observer=new ResizeObserver(()=>map.resize());this.observer.observe(this.canvas.nativeElement);
   map.on('load',()=>{if(this.destroyed)return;
    map.addSource('areas',{type:'geojson',data:{type:'FeatureCollection',features:[]}});
    map.addLayer({id:'area-fill',type:'fill',source:'areas',paint:{'fill-color':'#268457','fill-opacity':.25}});
    map.addLayer({id:'area-line',type:'line',source:'areas',paint:{'line-color':'#16683f','line-width':3}});
    map.addLayer({id:'area-label',type:'symbol',source:'areas',filter:['has','name'],layout:{'text-field':['get','name'],'text-font':['Open Sans Semibold','Arial Unicode MS Bold'],'text-size':14,'text-max-width':14,'text-allow-overlap':true,'text-ignore-placement':true},paint:{'text-color':'#164b32','text-halo-color':'#ffffff','text-halo-width':2}});
    this.render();this.fit();this.status.set('Mapa listo.');if(this.editable)this.locate();
   });
   map.on('click',event=>this.zone.run(()=>{
    if(this.editable){if(this.drawing&&!this.disabled&&this.points.length<499)this.update([...this.validPoints(),[event.lngLat.lng,event.lngLat.lat]]);}
    else if(map.getLayer('area-fill')){const feature=map.queryRenderedFeatures(event.point,{layers:['area-label','area-fill']})[0];const area=this.areas.find(a=>String(a['id'])===String(feature?.properties?.['id']));if(area)this.openArea.emit(area);}
   }));
   map.on('error',()=>this.status.set('No se pudo cargar parte del mapa. Revisa la conexión y la configuración de Mapbox.'));
  }catch{this.status.set('No se pudo abrir Mapbox. Verifica la conexión y que el navegador admita WebGL.');}
 }
 ngOnChanges(){const key=JSON.stringify([this.editable,this.points,this.areas,this.disabled]);if(key===this.geometryKey)return;this.geometryKey=key;this.render();if(!this.editable)this.fit();}
 private validPoints():number[][]{return this.points.filter(p=>p.length===2&&p.every(v=>v!==null&&Number.isFinite(v))) as number[][];}
 private ring(points:number[][]){return points.length&&JSON.stringify(points[0])!==JSON.stringify(points.at(-1))?[...points,points[0]]:points;}
 private render(){
  const source=this.map?.getSource('areas') as GeoJSONSource|undefined;if(!source||!this.lib)return;
  const points=this.validPoints();
  const features:GeoJSON.Feature[] = this.editable ? (points.length>=2?[{type:'Feature',properties:{},geometry:points.length>=3?{type:'Polygon',coordinates:[this.ring(points)]}:{type:'LineString',coordinates:points}}]:[]) : this.areas.filter(a=>a['coordenadas']?.type==='Polygon').map(a=>({type:'Feature',properties:{id:a['id'],name:a['nombre']},geometry:a['coordenadas']}));
  source.setData({type:'FeatureCollection',features});
  this.markers.forEach(m=>m.remove());this.markers=[];
  if(this.editable)points.forEach((p,index)=>{const marker=new this.lib!.Marker({draggable:!this.disabled,color:'#16683f'}).setLngLat([p[0],p[1]]).addTo(this.map!);marker.getElement().setAttribute('aria-label',`Vértice ${index+1}`);marker.on('dragend',()=>this.zone.run(()=>{const position=marker.getLngLat();const next=points.map(p=>[...p]);next[index]=[position.lng,position.lat];this.update(next);}));this.markers.push(marker);});
 }
 private update(points:number[][]){this.points=points;this.pointsChange.emit(points);this.render();}
 undo(){if(!this.disabled)this.update(this.validPoints().slice(0,-1));}
 clear(){if(!this.disabled){this.update([]);this.drawing=true;}}
 fit(){if(!this.map||!this.lib)return;const points=this.editable?this.validPoints():this.areas.flatMap(a=>a['coordenadas']?.coordinates?.[0]||[]);if(!points.length)return;const bounds=new this.lib.LngLatBounds();points.forEach(p=>bounds.extend([Number(p[0]),Number(p[1])]));this.map.fitBounds(bounds,{padding:55,maxZoom:19,duration:0});}
 locate(){
  if(!navigator.geolocation){this.status.set('Este navegador no ofrece ubicación. Desplaza y amplía el mapa para encontrar el área.');return;}
  this.status.set('Obteniendo la ubicación del dispositivo…');
  navigator.geolocation.getCurrentPosition(position=>{if(this.destroyed)return;this.map?.flyTo({center:[position.coords.longitude,position.coords.latitude],zoom:18});this.status.set('Mapa centrado en la ubicación del dispositivo.');},()=>{if(!this.destroyed)this.status.set('No se pudo obtener la ubicación. Permite el acceso a la ubicación o desplaza el mapa hasta el área.');},{enableHighAccuracy:true,timeout:12000,maximumAge:60000});
 }
 ngOnDestroy(){this.destroyed=true;this.observer?.disconnect();this.markers.forEach(m=>m.remove());this.map?.remove();}
}
