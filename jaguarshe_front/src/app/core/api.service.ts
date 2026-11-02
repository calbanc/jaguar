import { Injectable, signal } from '@angular/core';
import { deploymentConfig } from './deployment.config';
export class ApiError extends Error {
  constructor(message: string, public code = '', public status = 0) { super(message); }
}
export type Row = Record<string, any>;
export interface Page { columns?:string[]; data: Row[]; total: number; current_page: number; last_page: number; }
@Injectable({ providedIn: 'root' })
export class ApiService {
  readonly base = '/api/v1';
  token = signal('');
  user = signal<Row | null>(null);
  permissions = signal<string[]>([]);
  company = signal<Row | null>(null);
  async request(path: string, method = 'GET', body?: unknown): Promise<any> {
    const requestToken = this.token();
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (this.token()) headers['Authorization'] = `Bearer ${this.token()}`;
    if (body && !(body instanceof FormData)) headers['Content-Type'] = 'application/json';
    let response: Response;
    try { response = await fetch(`${this.base}/${path}`, { method, headers, body: body instanceof FormData ? body : body ? JSON.stringify(body) : undefined }); }
    catch { throw new Error('No se pudo conectar con el servidor. Comprueba que Laravel esté disponible.'); }
    const payload = await response.json().catch(() => ({}));
    if (requestToken !== this.token()) throw new Error('La sesión cambió. Vuelve a cargar los datos de tu empresa.');
    if (!response.ok) {
      if ((response.status === 401 && !path.startsWith('auth/login') && !path.startsWith('auth/email/')) || payload.code === 'EMAIL_NOT_VERIFIED') this.clear();
      const detail = response.status >= 500 ? 'El servidor no pudo completar la operación. Revisa el servicio HSE e inténtalo nuevamente.' : payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message;
      throw new ApiError(detail || `No se pudo completar la operación (${response.status}).`, payload.code || '', response.status);
    }
    return payload;
  }
  async downloadBlob(path: string): Promise<Blob> {
    const token = this.token();
    let response: Response;
    try { response = await fetch(`${this.base}/${path}`, { headers: { Authorization: `Bearer ${token}` } }); }
    catch { throw new Error('No se pudo conectar con el servidor para descargar el Excel.'); }
    if (token !== this.token()) throw new Error('La sesión cambió. Vuelve a descargar los datos de tu empresa.');
    if (!response.ok) {
      if (response.status === 401) this.clear();
      const payload = await response.json().catch(() => ({}));
      throw new Error(payload.message || 'No se pudo descargar el Excel.');
    }
    const blob = await response.blob();
    if (token !== this.token()) throw new Error('La sesión cambió. Vuelve a descargar los datos de tu empresa.');
    return blob;
  }
  async login(email: string, password: string) {
    const empresa_codigo = deploymentConfig.companyCode;
    const result = await this.request('auth/login', 'POST', { email, password, empresa_codigo, plataforma: 'web', dispositivo: 'Jaguar HSE · Web' });
    this.token.set(result.token);
    try { await this.loadContext(); sessionStorage.setItem('jaguar-session', result.token); }
    catch (error) { this.clear(); throw error; }
  }
  async resendVerification(email: string, password: string) {
    return this.request('auth/email/resend', 'POST', { email, password, empresa_codigo: deploymentConfig.companyCode, plataforma: 'web' });
  }
  async restore() {
    const token = sessionStorage.getItem('jaguar-session');
    if (!token) return;
    this.token.set(token);
    await this.loadContext();
  }
  async loadContext() {
    const [me, catalogs] = await Promise.all([this.request('auth/me'), this.request('catalogos')]);
    this.user.set(me.data); this.company.set(catalogs.data.empresa);
    this.permissions.set(catalogs.data.permisos.map((p: Row) => p['codigo']));
  }
  can(module: string, action = 'ver') { return this.permissions().includes(`${module}.${action}`); }
  clear() { this.token.set(''); this.user.set(null); this.permissions.set([]); this.company.set(null); if (typeof sessionStorage !== 'undefined') sessionStorage.removeItem('jaguar-session'); }
  async logout() { try { await this.request('auth/logout', 'POST'); } finally { this.clear(); } }
  list(resource: string, page = 1, filter = ''): Promise<Page> { return this.request(`${resource}?per_page=20&page=${page}${filter}`); }
  async all(resource: string): Promise<Row[]> {
    const first: Page = await this.request(`${resource}?per_page=100`);
    const rows = [...first.data];
    for (let page = 2; page <= first.last_page; page++) rows.push(...(await this.request(`${resource}?per_page=100&page=${page}`)).data);
    return rows;
  }
}
