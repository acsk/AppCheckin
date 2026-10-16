import React from "react";
import Svg, { Path } from "react-native-svg";

// Símbolo oficial do PIX (Banco Central) em SVG, sem depender de fonte de ícones,
// que nem sempre carrega na web.
const PIX_PATH =
  "M242 305Q252 296 263 305L340 382Q362 403 393 404H408L311 501Q287 523 256 523Q225 523 201 501L103 403H113Q143 403 166 381L242 305ZM263 231Q252 239 242 231L166 154Q144 132 113 132H103L201 35Q225 12 256 12Q287 12 311 35L408 132H393Q362 132 340 154L263 231ZM113 155Q134 155 150 170L226 247Q238 257 253 258Q267 257 279 247L356 170Q371 155 393 155H430L489 213Q511 237 511 268Q511 299 489 323L430 381H393Q371 381 356 366L279 289Q267 278 252 278Q237 278 226 289L150 365Q134 380 113 381H81L23 323Q0 299 0 268Q0 237 23 213L81 155H113Z";

export default function PixIcon({ size = 22, color }: { size?: number; color: string }) {
  return (
    <Svg width={size} height={size} viewBox="0 12 512 512" accessibilityElementsHidden importantForAccessibility="no">
      <Path d={PIX_PATH} fill={color} />
    </Svg>
  );
}
