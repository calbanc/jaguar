import { TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { AreaMapComponent } from './area-map.component';
import { PolygonMapComponent } from './polygon-map.component';
describe('Mapa de áreas',()=>{
 const polygon={type:'Polygon',coordinates:[[[-77.03,-12.05],[-77.02,-12.05],[-77.02,-12.04],[-77.03,-12.05]]]};
 beforeEach(async()=>{spyOn(PolygonMapComponent.prototype,'initialize').and.resolveTo();await TestBed.configureTestingModule({imports:[AreaMapComponent]}).compileComponents();});
 it('passes saved polygons to Mapbox and opens the selected area',()=>{const f=TestBed.createComponent(AreaMapComponent);const c=f.componentInstance;const area={id:1,nombre:'Informática',sede_nombre:'Lima',coordenadas:polygon};c.areas=[area];const open=spyOn(c.openArea,'emit');f.detectChanges();const map=f.debugElement.query(By.directive(PolygonMapComponent)).componentInstance;expect(map.areas).toEqual([area]);map.openArea.emit(area);expect(open).toHaveBeenCalledWith(area);f.nativeElement.querySelector('.legend button').click();expect(open).toHaveBeenCalledTimes(2);f.destroy();});
 it('filters sites and distinguishes missing geometry from mapped areas',()=>{const f=TestBed.createComponent(AreaMapComponent);const c=f.componentInstance;c.areas=[{id:1,nombre:'Informática',sede_nombre:'Lima',coordenadas:polygon},{id:2,nombre:'Almacén',sede_nombre:'Piura',coordenadas:polygon},{id:3,nombre:'Oficina',sede_nombre:'Lima',coordenadas:null}];c.site='Lima';expect(c.visible.map(a=>a['id'])).toEqual([1]);expect(c.missing.map(a=>a['id'])).toEqual([3]);c.selected='3';f.detectChanges();expect(f.nativeElement.textContent).toContain('No hay polígonos');c.changeSite();expect(c.selected).toBe('');f.destroy();});
});
