import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import {
  METODOS_PAGAMENTO_OPCOES,
  type MetodoPagamentoOpcao,
} from "@/src/utils/metodosPagamento";
import PixIcon from "./PixIcon";
import { ds, space } from "./tokens";

type Props = {
  opcoes: MetodoPagamentoOpcao[];
  value: MetodoPagamentoOpcao | null;
  onChange: (id: MetodoPagamentoOpcao) => void;
  /** Versão do CheckoutDock: ícone ao lado do nome e uma palavra de apoio. */
  compact?: boolean;
};

// Uma linha curta por forma; o detalhe (QR, provedor) fica na etapa seguinte.
const SUBTITULO: Record<MetodoPagamentoOpcao, string> = {
  pix: "Aprovado na hora",
  cartao: "Crédito, via provedor",
  boleto: "Compensa em até 3 dias",
};
const SUBTITULO_COMPACTO: Record<MetodoPagamentoOpcao, string> = {
  pix: "Na hora",
  cartao: "Crédito",
  boleto: "Até 3 dias",
};

function IconeMetodo({ id, on, size }: { id: MetodoPagamentoOpcao; on: boolean; size: number }) {
  if (id === "pix") {
    return <PixIcon size={size} color={on ? ds.onPix : ds.pixInk} />;
  }
  return <Feather name={id === "boleto" ? "file-text" : "credit-card"} size={size} color={on ? ds.onBrand : ds.ink} />;
}

// Formas de pagamento como opções lado a lado (rádio). PIX usa a cor oficial (#32bcad) no quadro
// do ícone selecionado; as demais seguem a marca.
export default function PaymentMethodOptions({ opcoes, value, onChange, compact }: Props) {
  if (opcoes.length === 0) {
    return null;
  }

  const ORDEM: MetodoPagamentoOpcao[] = ["pix", "cartao", "boleto"];
  const lista = METODOS_PAGAMENTO_OPCOES.filter((opcao) => opcoes.includes(opcao.id)).sort(
    (a, b) => ORDEM.indexOf(a.id) - ORDEM.indexOf(b.id),
  );
  const iconSize = compact ? 18 : 22;

  return (
    <View
      style={[styles.grid, compact && { gap: space[2] }]}
      accessibilityRole="radiogroup"
      accessibilityLabel="Forma de pagamento"
    >
      {lista.map((opcao) => {
        const on = value === opcao.id;
        const pix = opcao.id === "pix";
        const sub = (compact ? SUBTITULO_COMPACTO : SUBTITULO)[opcao.id];
        const iconStyle = [styles.icon, compact && styles.iconCompact];
        return (
          <TouchableOpacity
            key={opcao.id}
            activeOpacity={0.85}
            onPress={() => onChange(opcao.id)}
            accessibilityRole="radio"
            accessibilityState={{ checked: on }}
            accessibilityLabel={`${opcao.label}, ${sub}`}
            style={[
              styles.card,
              compact && styles.cardCompact,
              compact && lista.length > 2 && { flexBasis: "30%" },
              on && (pix ? styles.cardPixOn : styles.cardBrandOn),
            ]}
          >
            {(() => {
              const icone =
                on && !pix ? (
                  <LinearGradient
                    colors={[ds.brandYellow, ds.brand, ds.brandRed]}
                    start={{ x: 0, y: 0 }}
                    end={{ x: 1, y: 1 }}
                    style={iconStyle}
                  >
                    <IconeMetodo id={opcao.id} on size={iconSize} />
                  </LinearGradient>
                ) : (
                  <View style={[iconStyle, pix && { backgroundColor: on ? ds.pix : ds.pixSoft }]}>
                    <IconeMetodo id={opcao.id} on={on} size={iconSize} />
                  </View>
                );
              const radio = (
                <View
                  style={[
                    styles.radio,
                    compact && styles.radioCompact,
                    on && { backgroundColor: pix ? ds.pixInk : ds.brand, borderWidth: 0 },
                  ]}
                >
                  {on ? <Feather name="check" size={compact ? 10 : 13} color={pix ? ds.surface : ds.onBrand} /> : null}
                </View>
              );
              // No compacto o ícone fica ao lado do nome e o rádio vai para o canto do cartão.
              return compact ? (
                <>
                  {icone}
                  {radio}
                </>
              ) : (
                <View style={styles.top}>
                  {icone}
                  {radio}
                </View>
              );
            })()}
            <View style={styles.text}>
              <Text
                style={[styles.title, compact && styles.titleCompact, on && pix && { color: ds.pixInk }]}
                numberOfLines={1}
              >
                {opcao.label}
              </Text>
              <Text style={[styles.sub, compact && styles.subCompact]} numberOfLines={1}>
                {sub}
              </Text>
            </View>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  grid: { flexDirection: "row", flexWrap: "wrap", gap: space[3] },
  card: {
    flexGrow: 1,
    flexBasis: "40%",
    minWidth: 0,
    gap: 2,
    padding: space[4],
    borderRadius: 18,
    backgroundColor: ds.surface,
    borderWidth: 1.5,
    borderColor: ds.line,
  },
  cardCompact: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    paddingVertical: 10,
    paddingHorizontal: 12,
    paddingRight: 22,
    borderRadius: 14,
  },
  cardPixOn: { backgroundColor: ds.pixSoft, borderWidth: 2, borderColor: ds.pixInk },
  cardBrandOn: { backgroundColor: ds.brandSoft, borderWidth: 2, borderColor: ds.brand },
  top: { flexDirection: "row", alignItems: "flex-start", justifyContent: "space-between", marginBottom: space[3] },
  icon: {
    width: 44,
    height: 44,
    borderRadius: 14,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: ds.surfaceSunken,
  },
  iconCompact: { width: 36, height: 36, borderRadius: 11 },
  radio: {
    width: 22,
    height: 22,
    borderRadius: 11,
    borderWidth: 2,
    borderColor: ds.lineStrong,
    alignItems: "center",
    justifyContent: "center",
  },
  radioCompact: { position: "absolute", top: 8, right: 8, width: 16, height: 16, borderRadius: 8 },
  text: { flexShrink: 1, minWidth: 0, gap: 2 },
  title: { fontSize: 17, lineHeight: 22, fontWeight: "900", letterSpacing: -0.2, color: ds.ink },
  titleCompact: { fontSize: 15, lineHeight: 18 },
  sub: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted },
  subCompact: { fontSize: 12, lineHeight: 16 },
});
