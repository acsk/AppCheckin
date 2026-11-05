/** Usa apenas datas civis da API, sem deslocamento por UTC. */
export function diasAteAssinatura(date?:string|null,today=new Date()):number|null {
  const match=/^(\d{4})-(\d{2})-(\d{2})/.exec(String(date || ''));
  if(!match)return null;
  const target=Date.UTC(Number(match[1]),Number(match[2])-1,Number(match[3]));
  const parsed=new Date(target);
  if(parsed.getUTCFullYear()!==Number(match[1])||parsed.getUTCMonth()!==Number(match[2])-1||parsed.getUTCDate()!==Number(match[3]))return null;
  const now=Date.UTC(today.getFullYear(),today.getMonth(),today.getDate());
  return Math.round((target-now)/86400000);
}
export function assinaturaEncerrada(status?:string|null):boolean {
  return ['cancelada','cancelado','cancelled','canceled','encerrada','encerrado','expirada','expirado','expired'].includes(String(status || '').toLowerCase());
}
export type ProximaCobrancaAssinatura={date:string;amount:number};
export function primeiraCobrancaAssinatura(items:ProximaCobrancaAssinatura[],today=new Date()):ProximaCobrancaAssinatura|null {
  const ordered=items.filter(item=>{const days=diasAteAssinatura(item.date,today);return days!=null&&days>=0&&Number.isFinite(item.amount)&&item.amount>=0;}).sort((a,b)=>a.date.localeCompare(b.date));
  return ordered[0] || null;
}
