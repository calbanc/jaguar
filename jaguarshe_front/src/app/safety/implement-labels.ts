import type { Row } from '../core/api.service';

export function implementUrl(base:string,companyId:number,itemId:number):string {
 const url=new URL(base);url.search='';url.hash='';
 url.searchParams.set('modulo','implementos');url.searchParams.set('implemento',String(itemId));url.searchParams.set('empresa',String(companyId));
 return url.href;
}

export async function printImplementLabels(popup:Window,rows:Row[],companyId:number,base:string):Promise<void>{
 const {toDataURL}=await import('qrcode');
 const doc=popup.document;doc.title='Etiquetas de implementos de seguridad';
 const style=doc.createElement('style');style.textContent=`body{font:14px Arial,sans-serif;color:#163e2a;margin:24px}header{margin-bottom:20px}button{padding:10px 20px;cursor:pointer}.labels{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.label{border:1px dashed #889b8f;padding:12px;text-align:center;break-inside:avoid}.label img{display:block;width:140px;height:140px;margin:auto}.label strong{display:block;font-size:15px;overflow-wrap:anywhere}.label p{margin:6px 0;overflow-wrap:anywhere}@media print{header{display:none}body{margin:0}.labels{gap:5mm}.label{color:black}@page{size:A4;margin:10mm}}`;
 doc.head.append(style);const header=doc.createElement('header');const title=doc.createElement('h1');title.textContent='Etiquetas QR · '+rows.length+' implementos';header.append(title);
 const note=doc.createElement('p');note.textContent='Coloca una etiqueta diferente en cada equipo. Al escanearla se abre su ficha; se requiere iniciar sesión.';header.append(note);
 if(['localhost','127.0.0.1','[::1]'].includes(new URL(base).hostname)){const warning=doc.createElement('p');warning.textContent='Estas etiquetas apuntan a localhost. Para escanear desde un teléfono, genera las etiquetas entrando al sistema desde su dirección accesible en la red o en internet.';header.append(warning);}
 const print=doc.createElement('button');print.type='button';print.textContent='Imprimir etiquetas';print.disabled=true;print.addEventListener('click',()=>popup.print());header.append(print);doc.body.replaceChildren(header);
 const grid=doc.createElement('div');grid.className='labels';doc.body.append(grid);
 for(const row of rows){if(popup.closed)return;const url=implementUrl(base,companyId,row['id']);const image=doc.createElement('img');image.alt='QR '+row['codigo'];image.src=await toDataURL(url,{width:280,margin:4,errorCorrectionLevel:'M'});await image.decode();const label=doc.createElement('article');label.className='label';const code=doc.createElement('strong');code.textContent=row['codigo'];const name=doc.createElement('p');name.textContent=row['nombre'];const supplier=doc.createElement('p');supplier.textContent=row['proveedor'];label.append(image,code,name,supplier);grid.append(label);}
 print.disabled=false;
}
