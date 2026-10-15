import React from "react";
import { StyleSheet, View } from "react-native";
import Svg, {
  Circle,
  Defs,
  G,
  Path,
  Pattern,
  Polygon,
  RadialGradient,
  Rect,
  Stop,
} from "react-native-svg";
import { ds } from "./tokens";

// Fundo do login: malha de triângulos que some no centro e recortes low-poly
// no degradê da logo nos cantos superior esquerdo e inferior direito.
// Mesma geometria do componente TechBackground do design system.
const W = 390;
const H = 844;
const TRI_W = 32;
const TRI_H = 27.7128;

export default function TechBackground() {
  const poly = (points: string, fill: string, opacity = 1) => (
    <Polygon points={points} fill={fill} opacity={opacity} />
  );

  return (
    <View pointerEvents="none" style={styles.root} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
      {/* Malha triangular, mais visível nas bordas */}
      <Svg width="100%" height="100%" style={StyleSheet.absoluteFill}>
        <Defs>
          <Pattern id="acTri" width={TRI_W} height={TRI_H * 2} patternUnits="userSpaceOnUse">
            <Path
              d={`M0 0H${TRI_W}M0 ${TRI_H}H${TRI_W}M0 0L${TRI_W} ${TRI_H * 2}M${TRI_W} 0L0 ${TRI_H * 2}`}
              stroke={ds.ink}
              strokeOpacity={0.09}
              strokeWidth={1}
              fill="none"
            />
          </Pattern>
          <RadialGradient id="acFade" cx="50%" cy="50%" rx="60%" ry="38%">
            <Stop offset="0.3" stopColor={ds.surfaceSunken} stopOpacity={1} />
            <Stop offset="1" stopColor={ds.surfaceSunken} stopOpacity={0} />
          </RadialGradient>
          <RadialGradient id="acGlow" cx="50%" cy="30%" rx="46%" ry="22%">
            <Stop offset="0" stopColor={ds.brand} stopOpacity={0.14} />
            <Stop offset="1" stopColor={ds.brand} stopOpacity={0} />
          </RadialGradient>
        </Defs>
        <Rect width="100%" height="100%" fill="url(#acTri)" />
        <Rect width="100%" height="100%" fill="url(#acFade)" />
        <Rect width="100%" height="100%" fill="url(#acGlow)" />
      </Svg>

      {/* Recortes low-poly */}
      <Svg
        width="100%"
        height="100%"
        viewBox={`0 0 ${W} ${H}`}
        preserveAspectRatio="xMidYMid slice"
        style={StyleSheet.absoluteFill}
      >
        <G>
          {poly("0,0 168,0 0,146", ds.brand)}
          {poly("168,0 258,0 64,112", ds.brandYellow, 0.75)}
          {poly("0,146 64,112 0,236", ds.brandRed, 0.85)}
          {poly("64,112 168,0 132,96", ds.brand, 0.45)}
          {poly("258,0 316,0 204,66", ds.brandYellow, 0.3)}
          {poly("0,236 48,190 0,300", ds.brandRed, 0.3)}
          <Polygon points="228,52 300,10 282,88" fill="none" stroke={ds.brand} strokeWidth={1.5} opacity={0.7} />
          <Circle cx={300} cy={10} r={3} fill={ds.brand} />
          <Circle cx={48} cy={190} r={3} fill={ds.brand} />
        </G>
        <G>
          {poly(`${W},${H} ${W - 176},${H} ${W},${H - 156}`, ds.brand)}
          {poly(`${W - 176},${H} ${W - 270},${H} ${W - 66},${H - 118}`, ds.brandYellow, 0.75)}
          {poly(`${W},${H - 156} ${W - 66},${H - 118} ${W},${H - 214}`, ds.brandRed, 0.85)}
          {poly(`${W - 66},${H - 118} ${W - 176},${H} ${W - 138},${H - 100}`, ds.brand, 0.45)}
          {poly(`${W - 270},${H} ${W - 330},${H} ${W - 214},${H - 70}`, ds.brandYellow, 0.3)}
          <Polygon
            points={`${W - 236},${H - 56} ${W - 310},${H - 12} ${W - 292},${H - 92}`}
            fill="none"
            stroke={ds.brand}
            strokeWidth={1.5}
            opacity={0.7}
          />
          <Circle cx={W - 310} cy={H - 12} r={3} fill={ds.brand} />
          <Circle cx={W - 50} cy={H - 200} r={3} fill={ds.brand} />
        </G>
        <Polygon points="28,420 195,132 362,420" fill="none" stroke={ds.brand} strokeWidth={1} opacity={0.14} />
        <Polygon points="362,470 195,758 28,470" fill="none" stroke={ds.brand} strokeWidth={1} opacity={0.14} />
      </Svg>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    ...StyleSheet.absoluteFillObject,
    overflow: "hidden",
    backgroundColor: ds.surfaceSunken,
  },
});
