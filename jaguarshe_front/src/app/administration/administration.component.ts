import { Component, ElementRef, HostListener, OnDestroy, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiService, Row } from '../core/api.service';
import { ColumnFilterComponent } from '../core/column-filter.component';
import { filterQuery } from '../core/table-filters';
import { IconComponent } from '../core/icon.component';

type Section = 'usuarios' | 'modulos' | 'roles' | 'permisos' | 'permisos-usuarios' | 'permisos-roles';
interface AdminTab { key: Section; name: string; description: string; columns: { key: string; name: string }[]; readonly?: boolean; }
@Component({
  selector: 'hse-administration', standalone: true, imports: [FormsModule, IconComponent, ColumnFilterComponent],
  templateUrl: './administration.component.html', styleUrl: './administration.component.css',
})
export class AdministrationComponent implements OnInit, OnDestroy {
  readonly api = inject(ApiService);
  private readonly element: ElementRef<HTMLElement> = inject(ElementRef);
  readonly tabs: AdminTab[] = [
    {key:'usuarios',name:'Usuarios',description:'Crea cuentas, actualiza sus datos y administra su estado.',columns:[{key:'nombre',name:'Usuario'},{key:'email',name:'Correo electrónico'},{key:'roles',name:'Roles y plataformas'},{key:'activo',name:'Estado'}]},
    {key:'modulos',name:'Módulos',description:'Consulta los módulos y su disponibilidad para tu empresa.',readonly:true,columns:[{key:'nombre',name:'Módulo'},{key:'codigo',name:'Código'},{key:'activo',name:'Habilitado'}]},
    {key:'roles',name:'Roles',description:'Define perfiles de acceso y selecciona los permisos de cada rol.',columns:[{key:'nombre',name:'Rol'},{key:'descripcion',name:'Descripción'},{key:'permisos_detalle',name:'Permisos asignados'},{key:'activo',name:'Estado'}]},
    {key:'permisos',name:'Permisos',description:'Consulta las acciones disponibles en cada módulo.',readonly:true,columns:[{key:'modulo',name:'Módulo'},{key:'descripcion',name:'Permiso'},{key:'codigo',name:'Código'}]},
    {key:'permisos-usuarios',name:'Permisos usuarios',description:'Asigna un rol por usuario y plataforma. Consulta los permisos heredados de ese rol.',columns:[{key:'usuario',name:'Usuario'},{key:'rol',name:'Rol'},{key:'plataforma',name:'Plataforma'},{key:'permisos_detalle',name:'Permisos heredados'},{key:'activo',name:'Estado'}]},
    {key:'permisos-roles',name:'Permisos por rol',description:'Agrega, modifica o retira permisos de los roles de tu empresa.',columns:[{key:'rol',name:'Rol'},{key:'modulo',name:'Módulo'},{key:'descripcion',name:'Permiso'}]},
  ];
  section = signal<Section>('usuarios'); rows = signal<Row[]>([]); busy = signal(false); saving = signal(false); exporting = signal(false);
  error = signal(''); notice = signal(''); modal = signal<'detail'|'edit'|'remove'|null>(null); selected = signal<Row|null>(null);
  options = signal<Record<string, Row[]>>({usuarios:[],roles:[],permisos:[],plataformas:[]}); optionsBusy = signal(false); optionsReady = signal(false);
  page = 1; total = 0; lastPage = 1; query = ''; appliedQuery = ''; form: Row = {}; permissionSearch = '';
  columnFilters:Record<string,string>={}; roleFilters:Record<string,string>={};
  readonly booleanFilter=[{value:'1',label:'Activo'},{value:'0',label:'Inactivo'}];
  readonly availableFilter=[{value:'1',label:'Disponible'},{value:'0',label:'No disponible'}];
  readonly assignedFilter=[{value:'1',label:'Asignado'},{value:'0',label:'Sin asignar'}];
  get hasColumnFilters(){return Object.values(this.columnFilters).some(Boolean);}
  async filterColumn(key:string,value:string){this.columnFilters={...this.columnFilters,[key]:value};await this.load(1);}
  async clearFilters(){this.columnFilters={};this.query='';this.appliedQuery='';await this.load(1);}
  private generation = 0; private alive = true; private previousFocus: HTMLElement|null = null;
  get tab(){ return this.tabs.find(t=>t.key===this.section())!; }
  get canManage(){ return this.api.can('administracion','editar'); }
  get canCreate(){ return this.api.can('administracion','crear') && !this.tab.readonly; }
  get currentRole(){ return Number(this.api.user()?.['rol_id']); }
  get currentMember(){ return Number(this.api.user()?.['miembro_id']); }
  get availableRoles(){ return (this.options()['roles']||[]).filter(r=>r['activo'] && r['asignable']); }
  get editableRoles(){ return (this.options()['roles']||[]).filter(r=>Number(r['id'])!==this.currentRole); }
  readonly permissionActions=[{key:'ver',label:'Consultar'},{key:'crear',label:'Crear'},{key:'editar',label:'Editar'},{key:'eliminar',label:'Eliminar'},{key:'descargar',label:'Descargar informes'}];
  matrix(permissions:Row[], search=''){
    const groups=new Map<string,{name:string;cells:Record<string,Row>}>();
    for(const p of permissions){const action=String(p['codigo']).split('.').pop()!;if(!this.permissionActions.some(a=>a.key===action))continue;
      if(search&&!`${p['modulo']} ${p['descripcion']}`.toLocaleLowerCase().includes(search.toLocaleLowerCase()))continue;
      const group:{name:string;cells:Record<string,Row>}=groups.get(p['modulo'])||{name:p['modulo'],cells:{}};group.cells[action]=p;groups.set(group.name,group);
    }
    return Array.from(groups.values());
  }
  get roleMatrix(){return this.matrix(this.options()['permisos']||[],this.permissionSearch).filter(group=>Object.entries(this.roleFilters).every(([key,value])=>!value||(key==='modulo'?group.name.toLocaleLowerCase().includes(value.toLocaleLowerCase()):(!!group.cells[key]&&this.hasPermission(group.cells[key]['id']))===(value==='1'))));}
  get catalogMatrix(){return this.matrix(this.rows());}
  get specialPermissions(){return (this.options()['permisos']||[]).filter(p=>p['codigo']==='documentos.confidenciales');}
  ngOnInit(){ void this.load(); }
  ngOnDestroy(){ this.alive=false; this.generation++; }
  async selectTab(key:Section){ if(this.saving())return; this.section.set(key);this.query='';this.appliedQuery='';this.columnFilters={};this.error.set('');this.notice.set('');await this.load(1); }
  async search(){this.appliedQuery=this.query.trim();await this.load(1);}
  async load(page=1){
    const generation=++this.generation;this.page=page;this.busy.set(true);this.error.set('');
    try{ const result=await this.api.request(`administracion/${this.section()}?per_page=${this.section()==='permisos'?100:20}&page=${page}&q=${encodeURIComponent(this.appliedQuery)}${filterQuery(this.columnFilters)}`);
      if(generation!==this.generation)return;this.rows.set(result.data);this.total=result.total;this.lastPage=result.last_page;
    }catch(e){if(generation===this.generation){this.rows.set([]);this.total=0;this.lastPage=1;this.error.set(this.message(e));}}
    finally{if(generation===this.generation)this.busy.set(false);}
  }
  protectedRow(row:Row){
    return (this.section()==='roles'&&Number(row['id'])===this.currentRole)
      ||(this.section()==='permisos-roles'&&Number(row['rol_id'])===this.currentRole)
      ||(this.section()==='permisos-usuarios'&&Number(row['id'])===Number(this.api.user()?.['acceso_id']));
  }
  canEdit(row:Row){return !this.tab.readonly&&this.api.can('administracion','editar')&&!this.protectedRow(row);}
  canRemove(row:Row){return !this.tab.readonly&&this.api.can('administracion','eliminar')&&!this.protectedRow(row)&&(this.section()==='permisos-roles'||!!row['activo'])&&!(this.section()==='usuarios'&&Number(row['id'])===this.currentMember);}
  text(value:unknown){return value===null||value===undefined||value===''?'Sin asignar':String(value);}
  message(error:unknown){return error instanceof Error?error.message:'No se pudo completar la operación.';}
  private open(kind:'detail'|'edit'|'remove'){
    this.previousFocus=document.activeElement as HTMLElement;this.modal.set(kind);this.error.set('');
    setTimeout(()=>this.element.nativeElement.querySelector<HTMLElement>('[role="dialog"] button, [role="dialog"] input')?.focus());
  }
  close(){if(this.saving())return;this.modal.set(null);this.form={};this.previousFocus?.focus();}
  detail(row:Row){this.selected.set(row);this.open('detail');}
  remove(row:Row){if(!this.canRemove(row))return;this.selected.set(row);this.open('remove');}
  async edit(row:Row|null=null){
    if(row?!this.canEdit(row):!this.canCreate)return;
    this.selected.set(row);this.form={...row,activo:row?!!row['activo']:true,permisos:(row?.['permisos']||[]).map((p:Row)=>p['id'])};
    this.permissionSearch='';this.roleFilters={};this.open('edit');this.optionsReady.set(false);this.optionsBusy.set(true);
    try{const result=await this.api.request('administracion/opciones');if(!this.alive)return;this.options.set(result.data);this.optionsReady.set(true);
      if(!row&&this.section()==='usuarios')this.form['plataforma_id']=result.data.plataformas.find((p:Row)=>p['codigo']==='web')?.id;
    }catch(e){this.error.set(this.message(e));}finally{this.optionsBusy.set(false);}
  }
  hasPermission(id:number){return (this.form['permisos']||[]).includes(id);}
  togglePermission(id:number,checked:boolean){this.form['permisos']=checked?[...new Set([...(this.form['permisos']||[]),id])]:(this.form['permisos']||[]).filter((p:number)=>p!==id);}
  toggleGroup(permissions:Row[],checked:boolean){for(const p of permissions)if(p['asignable'])this.togglePermission(p['id'],checked);}
  async save(){
    if((this.selected()?!this.canEdit(this.selected()!):!this.canCreate)||this.optionsBusy()||!this.optionsReady())return;this.saving.set(true);this.error.set('');const selected=this.selected();const section=this.section();
    try{
      let body:Row;
      if(section==='usuarios'){
        body={nombre:this.form['nombre'],email:this.form['email']};
        if(this.form['password'])body['password']=this.form['password'];
        if(selected)body['activo']=this.form['activo'];else{body['rol_id']=this.form['rol_id'];body['plataforma_id']=this.form['plataforma_id'];}
      }else if(section==='roles')body={nombre:this.form['nombre'],descripcion:this.form['descripcion']||null,activo:this.form['activo'],permisos:this.form['permisos']||[]};
      else if(section==='permisos-usuarios')body={miembro_id:this.form['miembro_id'],rol_id:this.form['rol_id'],plataforma_id:this.form['plataforma_id'],activo:this.form['activo']};
      else body={rol_id:this.form['rol_id'],permiso_id:this.form['permiso_id']};
      const result=await this.api.request(`administracion/${section}${selected?'/'+selected['id']:''}`,selected?'PATCH':'POST',body);
      if(!this.alive)return;this.modal.set(null);this.form={};this.notice.set(result.message||'Cambios guardados correctamente.');await this.load(this.page);this.previousFocus?.focus();
    }catch(e){if(this.alive)this.error.set(this.message(e));}finally{this.saving.set(false);}
  }
  async confirmRemove(){
    const row=this.selected();if(!row||!this.canRemove(row))return;this.saving.set(true);this.error.set('');
    try{await this.api.request(`administracion/${this.section()}/${row['id']}`,'DELETE');if(!this.alive)return;this.modal.set(null);this.notice.set(this.section()==='permisos-roles'?'Permiso retirado del rol.':'Registro desactivado. Su historial se conserva.');await this.load(this.rows().length===1&&this.page>1?this.page-1:this.page);this.previousFocus?.focus();}
    catch(e){if(this.alive)this.error.set(this.message(e));}finally{this.saving.set(false);}
  }
  async exportExcel(){
    if(!this.api.can('administracion','descargar'))return;
    const section=this.section();this.exporting.set(true);this.error.set('');
    try{const blob=await this.api.downloadBlob(`administracion/${section}/excel?q=${encodeURIComponent(this.appliedQuery)}${filterQuery(this.columnFilters)}`);if(!this.alive)return;
      const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=`${section}-${new Date().toISOString().slice(0,10)}.xlsx`;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
    }catch(e){if(this.alive)this.error.set(this.message(e));}finally{this.exporting.set(false);}
  }
  @HostListener('document:keydown',['$event']) keydown(event:KeyboardEvent){
    if(!this.modal())return;if(event.key==='Escape'){event.preventDefault();this.close();}
    if(event.key!=='Tab')return;
    const dialog=this.element.nativeElement.querySelector<HTMLElement>('[role="dialog"]');
    const items=Array.from(dialog?.querySelectorAll<HTMLElement>('button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex="0"]')||[]);
    const first=items[0],last=items[items.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}
  }
}
