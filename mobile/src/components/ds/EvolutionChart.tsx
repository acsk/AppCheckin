import React, { useState } from "react";
import { StyleSheet, Text, View } from "react-native";
import Svg, { Circle, Line, Path, Rect, Text as SvgText } from "react-native-svg";
import { fmtData, fmtMarca, type Marca, type RecordeDefinicao } from "@/src/utils/recordes";
import { ds, space } from "./tokens";

type Props = { marcas: Marca[]; definicao: RecordeDefinicao; melhorId: number | null };
export default function EvolutionChart({ marcas, definicao, melhorId }: Props) {
  const [selected, setSelected] = useState<number | null>(null);
  const [width, setWidth] = useState(320);
  if (marcas.length < 2) return <Text style={styles.hint}>Registre pelo menos duas marcas neste período para ver a evolução.</Text>;
  const W = Math.max(260, width), H = 210, L = 68, R = 18, T = 24, B = 32;
  const values = marcas.map(m => m.valor), lo = Math.min(...values), hi = Math.max(...values), range = hi - lo || Math.max(1, lo * 0.05);
  const dates = marcas.map(m => new Date(`${m.data}T12:00:00`).getTime());
  const start = dates[0], duration = dates[dates.length - 1] - start;
  const pts = marcas.map((m, i) => ({
    x: L + (duration ? (dates[i] - start) / duration : i / (marcas.length - 1)) * (W - L - R),
    y: T + (definicao.direcao === "menor_melhor" ? (m.valor - lo) / range : (hi - m.valor) / range) * (H - T - B),
  }));
  const line = pts.map((p, i) => `${i ? "L" : "M"}${p.x} ${p.y}`).join("");
  const current = marcas.find(m => m.id === selected);
  return <View style={styles.container} onLayout={e => setWidth(e.nativeEvent.layout.width)}>
    <Svg width="100%" height={H} viewBox={`0 0 ${W} ${H}`} accessibilityLabel={`Evolução de ${definicao.nome}: ${fmtMarca(marcas[0].valor, definicao)} para ${fmtMarca(marcas[marcas.length - 1].valor, definicao)}`}>
      {[0, 0.5, 1].map(f => {
        const y = T + f * (H - T - B), value = definicao.direcao === "menor_melhor" ? lo + f * (hi - lo) : hi - f * (hi - lo);
        return <React.Fragment key={f}><Line x1={L} x2={W - R} y1={y} y2={y} stroke={ds.line} /><SvgText x={L - 8} y={y + 4} fontSize={10} fill={ds.inkMuted} textAnchor="end">{fmtMarca(value, definicao)}</SvgText></React.Fragment>;
      })}
      <Path d={`${line}L${pts[pts.length - 1].x} ${H - B}L${L} ${H - B}Z`} fill={ds.brand} opacity={0.1} />
      <Path d={line} fill="none" stroke={ds.brand} strokeWidth={2.5} />
      {marcas.map((m, i) => <React.Fragment key={m.id}>
        <Circle cx={pts[i].x} cy={pts[i].y} r={m.id === melhorId ? 6 : 4} fill={m.id === melhorId ? ds.medalGold : ds.brand} />
        <Rect x={pts[i].x - 12} y={0} width={24} height={H - B} fill="transparent" onPress={() => setSelected(m.id)} accessibilityLabel={`${fmtData(m.data)}: ${fmtMarca(m.valor, definicao)}`} />
      </React.Fragment>)}
      <SvgText x={L} y={H - 8} fontSize={11} fill={ds.inkMuted}>{fmtData(marcas[0].data)}</SvgText>
      <SvgText x={W - R} y={H - 8} fontSize={11} fill={ds.inkMuted} textAnchor="end">{fmtData(marcas[marcas.length - 1].data)}</SvgText>
    </Svg>
    <Text accessibilityLiveRegion="polite" style={styles.hint}>{current ? `${fmtData(current.data)} · ${fmtMarca(current.valor, definicao)}${current.id === melhorId ? " · Recorde" : ""}` : "Toque nos pontos para ver a marca. Melhorar sempre sobe."}</Text>
  </View>;
}
const styles = StyleSheet.create({
  container: { gap: space[2], minWidth: 0 },
  hint: { color: ds.inkMuted, fontSize: 13, lineHeight: 19, fontWeight: "500" },
});
