// Ícones da barra inferior (traço de 2px do design system), via react-native-svg.
import React from "react";
import Svg, { Circle, Path, Rect } from "react-native-svg";

export type TabIconName = "home" | "target" | "check-square" | "cart" | "user";

type Props = { name: TabIconName; color: string; size?: number; strokeWidth?: number };

export function TabIcon({ name, color, size = 22, strokeWidth = 2 }: Props) {
  const p = {
    stroke: color,
    strokeWidth,
    strokeLinecap: "round" as const,
    strokeLinejoin: "round" as const,
    fill: "none",
  };
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24">
      {name === "home" && (
        <>
          <Path d="M3.5 10.5L12 3.5l8.5 7" {...p} />
          <Path d="M5.5 9v10a1.5 1.5 0 001.5 1.5h3.5v-6h3v6H17a1.5 1.5 0 001.5-1.5V9" {...p} />
        </>
      )}
      {name === "target" && (
        <>
          <Circle cx={12} cy={12} r={9} {...p} />
          <Circle cx={12} cy={12} r={5} {...p} />
          <Circle cx={12} cy={12} r={1.2} {...p} />
        </>
      )}
      {name === "check-square" && (
        <>
          <Rect x={3.5} y={3.5} width={17} height={17} rx={4} {...p} />
          <Path d="M8 12.2l2.8 2.8L16.5 9" {...p} />
        </>
      )}
      {name === "cart" && (
        <>
          <Path d="M2.5 4h2.6l2.3 10.5h10.3L20 7.5H6.4" {...p} />
          <Circle cx={9} cy={19.5} r={1.5} {...p} />
          <Circle cx={16.5} cy={19.5} r={1.5} {...p} />
        </>
      )}
      {name === "user" && (
        <>
          <Circle cx={12} cy={8} r={4} {...p} />
          <Path d="M4 21c0-4.2 3.6-7 8-7s8 2.8 8 7" {...p} />
        </>
      )}
    </Svg>
  );
}
