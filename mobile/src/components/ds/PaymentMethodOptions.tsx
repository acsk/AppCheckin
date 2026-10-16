import { Feather, FontAwesome6 } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import {
  METODOS_PAGAMENTO_OPCOES,
  type MetodoPagamentoOpcao,
} from "@/src/utils/metodosPagamento";
import { ds, space } from "./tokens";

type Props = {
  opcoes: MetodoPagamentoOpcao[];
  value: MetodoPagamentoOpcao | null;
  onChange: (id: MetodoPagamentoOpcao) => void;
};

// Uma linha curta por forma; o detalhe (QR, provedor) fica na etapa seguinte.
const SUBTITULO: Record<MetodoPagamentoOpcao, string> = {
  pix: "Aprovado na hora",
  cartao: "Crédito, via provedor",
  boleto: "Compensa em até 3 dias",
};

function IconeMetodo({ id, on }: { id: MetodoPagamentoOpcao; on: boolean }) {
  if (id === "pix") {
    // Símbolo oficial do PIX (FontAwesome6 brands).
    return <FontAwesome6 name="pix" brand size={22} color={on ? ds.onPix : ds.pixInk} />;
  }
  return (
    <Feather
      name={id === "boleto" ? "file-text" : "credit-card"}
      size={22}
      color={on ? ds.onBrand : ds.ink}
    />
  );
}

// Formas de pagamento como opções lado a lado (rádio), etapa "2 · Como você quer pagar?".
// PIX usa a cor oficial (#32bcad) no quadro do ícone selecionado; as demais seguem a marca.
export default function PaymentMethodOptions({ opcoes, value, onChange }: Props) {
  if (opcoes.length === 0) {
    return null;
  }

  // PIX e Cartão lado a lado; Boleto (quando houver) na linha de baixo.
  const ORDEM: MetodoPagamentoOpcao[] = ["pix", "cartao", "boleto"];
  const lista = METODOS_PAGAMENTO_OPCOES.filter((opcao) => opcoes.includes(opcao.id)).sort(
    (a, b) => ORDEM.indexOf(a.id) - ORDEM.indexOf(b.id),
  );

  return (
    <View style={styles.grid} accessibilityRole="radiogroup" accessibilityLabel="Forma de pagamento">
      {lista.map((opcao) => {
        const on = value === opcao.id;
        const pix = opcao.id === "pix";
        return (
          <TouchableOpacity
            key={opcao.id}
            activeOpacity={0.85}
            onPress={() => onChange(opcao.id)}
            accessibilityRole="radio"
            accessibilityState={{ checked: on }}
            accessibilityLabel={`${opcao.label}, ${SUBTITULO[opcao.id]}`}
            style={[
              styles.card,
              on && (pix ? styles.cardPixOn : styles.cardBrandOn),
            ]}
          >
            <View style={styles.top}>
              {on && !pix ? (
                <LinearGradient
                  colors={[ds.brandYellow, ds.brand, ds.brandRed]}
                  start={{ x: 0, y: 0 }}
                  end={{ x: 1, y: 1 }}
                  style={styles.icon}
                >
                  <IconeMetodo id={opcao.id} on />
                </LinearGradient>
              ) : (
                <View
                  style={[
                    styles.icon,
                    pix && { backgroundColor: on ? ds.pix : ds.pixSoft },
                  ]}
                >
                  <IconeMetodo id={opcao.id} on={on} />
                </View>
              )}
              <View
                style={[
                  styles.radio,
                  on && { backgroundColor: pix ? ds.pixInk : ds.brand, borderWidth: 0 },
                ]}
              >
                {on ? (
                  <Feather name="check" size={13} color={pix ? ds.surface : ds.onBrand} />
                ) : null}
              </View>
            </View>
            <Text style={[styles.title, on && pix && { color: ds.pixInk }]}>{opcao.label}</Text>
            <Text style={styles.sub} numberOfLines={1}>
              {SUBTITULO[opcao.id]}
            </Text>
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
  radio: {
    width: 22,
    height: 22,
    borderRadius: 11,
    borderWidth: 2,
    borderColor: ds.lineStrong,
    alignItems: "center",
    justifyContent: "center",
  },
  title: { fontSize: 17, lineHeight: 22, fontWeight: "900", letterSpacing: -0.2, color: ds.ink },
  sub: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted },
});
