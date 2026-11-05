import { Feather } from '@expo/vector-icons';
import React, { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import Button from './Button';
import { ds, radius, space } from './tokens';

type Action = { label: string; onPress: () => void; loading?: boolean; disabled?: boolean };
export function SubscriptionAction({ title, text, tone = 'warning', action }: { title: string; text?: string; tone?: 'warning'|'danger'|'info'; action?: Action }) {
  const color = tone === 'danger' ? ds.danger : tone === 'info' ? ds.info : ds.warning;
  return <View style={[styles.alert,{backgroundColor:tone==='danger'?ds.dangerSoft:tone==='info'?ds.infoSoft:ds.warningSoft}]} accessibilityLiveRegion="polite">
    <Feather name={tone==='info'?'info':'alert-circle'} size={20} color={color}/><View style={styles.alertBody}><Text style={styles.alertTitle}>{title}</Text>{text?<Text style={styles.meta}>{text}</Text>:null}</View>
    {action?<Button size="sm" variant="dark" loading={action.loading} disabled={action.disabled} onPress={action.onPress}>{action.label}</Button>:null}
  </View>;
}
export function NextSubscriptionCharge({ children }: { children: React.ReactNode }) {
  return <View style={styles.next}><Feather name="calendar" size={18} color={ds.inkMuted}/><Text style={[styles.meta,{flex:1}]}>{children}</Text></View>;
}
export type SubscriptionCardProps = {
  title: string; price: string; unit?: string; subtitle?: string; days?: number|null;
  fidelity?: {paid:number;total:number;until?:string}; children?:React.ReactNode;
  expanded?:boolean; onToggle?:()=>void;
};
export function SubscriptionOverviewCard({ title,price,unit,subtitle,days,fidelity,children,expanded,onToggle }:SubscriptionCardProps) {
  const [open,setOpen]=useState(false);
  const visible=expanded ?? open;
  const urgent=days!=null&&days<=7;
  const warning=days!=null&&days>7&&days<=15;
  const dayColor=urgent?ds.danger:warning?ds.warning:ds.ink;
  const progress=fidelity&&fidelity.total>0?Math.max(0,Math.min(fidelity.paid,fidelity.total))/fidelity.total:0;
  const remaining=days==null?'':days<0?`vencida há ${Math.abs(days)} dias`:days===0?'vence hoje':`faltam ${days} dias`;
  return <View style={styles.card}>
    <Pressable accessibilityRole="button" accessibilityState={{expanded:visible}} accessibilityLabel={`${title}${remaining?`, ${remaining}`:''}. ${visible?'Recolher':'Ver'} detalhes.`} onPress={onToggle ?? (()=>setOpen(value=>!value))} style={({pressed})=>[styles.head,pressed&&{opacity:0.8}]}>
      {days!=null?<View style={[styles.days,{backgroundColor:urgent?ds.dangerSoft:warning?ds.warningSoft:ds.surfaceSunken}]}>
        <Text style={[styles.dayValue,{color:dayColor}]} maxFontSizeMultiplier={1.2}>{days===0?'Hoje':Math.abs(days)}</Text>
        {days!==0?<Text style={[styles.dayLabel,{color:dayColor}]} maxFontSizeMultiplier={1.2}>{days<0?'ATRASO':days===1?'DIA':'DIAS'}</Text>:null}
      </View>:null}
      <View style={styles.titleBlock}><View style={styles.titleRow}><Text style={styles.title} maxFontSizeMultiplier={1.3}>{title}</Text><Text style={styles.price} maxFontSizeMultiplier={1.3}>{price}{unit?<Text style={styles.unit}>{unit}</Text>:null}</Text></View>
        {subtitle?<Text style={styles.meta} maxFontSizeMultiplier={1.3}>{subtitle}</Text>:null}
        {fidelity&&fidelity.total>0?<View style={styles.bar} accessibilityRole="progressbar" accessibilityLabel={`Fidelidade: ${fidelity.paid} de ${fidelity.total} mensalidades`} accessibilityValue={{min:0,max:fidelity.total,now:Math.min(fidelity.total,Math.max(0,fidelity.paid))}}><View style={[styles.fill,{width:`${progress*100}%`}]}/></View>:null}
      </View><Feather name={visible?'chevron-down':'chevron-right'} size={18} color={ds.inkMuted}/>
    </Pressable>
    {visible?<View style={styles.details}>{fidelity?<Text style={styles.meta}>Fidelidade: {fidelity.paid} de {fidelity.total} mensalidades{fidelity.until?` · até ${fidelity.until}`:''}</Text>:null}{children}</View>:null}
  </View>;
}
export function PastSubscriptions({ count,children }: { count:number;children:React.ReactNode }) {
  const [open,setOpen]=useState(false);
  if(!count)return null;
  return <View style={styles.past}><Pressable style={styles.pastToggle} onPress={()=>setOpen(value=>!value)} accessibilityRole="button" accessibilityState={{expanded:open}}><Feather name="clock" size={16} color={ds.inkMuted}/><Text style={[styles.meta,{flex:1}]}>{count} {count===1?'assinatura encerrada':'assinaturas encerradas'}</Text><Text style={styles.link}>{open?'Ocultar':'Ver'}</Text></Pressable>{open?<View style={styles.pastBody}>{children}</View>:null}</View>;
}
const styles=StyleSheet.create({
 card:{borderRadius:radius.lg,backgroundColor:ds.surface,borderWidth:1,borderColor:ds.line,overflow:'hidden'},
 head:{flexDirection:'row',alignItems:'center',gap:space[3],padding:space[3]},
 titleBlock:{flex:1,minWidth:0,gap:2},titleRow:{flexDirection:'row',flexWrap:'wrap',alignItems:'baseline',justifyContent:'space-between',gap:space[2]},
 title:{fontSize:16,lineHeight:20,fontWeight:'900',color:ds.ink,flexShrink:1},price:{fontSize:15,lineHeight:20,fontWeight:'900',color:ds.ink,fontVariant:['tabular-nums']},
 unit:{fontSize:12,fontWeight:'700',color:ds.inkMuted},meta:{fontSize:13,lineHeight:18,fontWeight:'600',color:ds.inkMuted},
 days:{width:52,minHeight:52,flexShrink:0,borderRadius:14,alignItems:'center',justifyContent:'center',borderWidth:1,borderColor:ds.line,paddingVertical:4},
 dayValue:{fontSize:21,lineHeight:24,fontWeight:'900',fontVariant:['tabular-nums']},dayLabel:{fontSize:10,lineHeight:14,fontWeight:'800',letterSpacing:0.6},
 bar:{height:6,borderRadius:radius.full,backgroundColor:ds.surfaceSunken,borderWidth:1,borderColor:ds.line,overflow:'hidden',marginTop:4},fill:{height:'100%',backgroundColor:ds.brand,borderRadius:radius.full},
 details:{gap:space[3],padding:space[4],paddingTop:space[3],borderTopWidth:1,borderColor:ds.line,backgroundColor:ds.surfaceSunken},
 alert:{flexDirection:'row',flexWrap:'wrap',alignItems:'center',gap:space[3],padding:space[3],borderRadius:radius.lg},alertBody:{flex:1,minWidth:140,gap:2},alertTitle:{fontSize:15,lineHeight:20,fontWeight:'800',color:ds.ink},
 next:{flexDirection:'row',alignItems:'center',gap:space[2],paddingVertical:space[2]},
 past:{borderWidth:1,borderColor:ds.line,borderRadius:14,overflow:'hidden'},pastToggle:{flexDirection:'row',alignItems:'center',gap:space[2],minHeight:48,padding:space[3]},
 pastBody:{gap:space[2],padding:space[3],paddingTop:0},link:{fontSize:14,fontWeight:'800',color:ds.brandInk},
});
