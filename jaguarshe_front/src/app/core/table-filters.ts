import { Row } from './api.service';
export function filterQuery(filters:Record<string,string>):string {
  return Object.entries(filters).filter(([,value])=>value!=='').map(([key,value])=>`&columns[${encodeURIComponent(key)}]=${encodeURIComponent(value)}`).join('');
}
export function matchesColumns(row:Row,filters:Record<string,string>):boolean {
  return Object.entries(filters).every(([key,value])=>!value||String(row[key]??'').toLocaleLowerCase().includes(value.toLocaleLowerCase()));
}
