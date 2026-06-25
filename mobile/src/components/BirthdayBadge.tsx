import { MaterialCommunityIcons } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, View } from "react-native";

type Props = {
  show?: boolean;
  size?: number;
};

/** Ícone de bolo ao lado do nome de aniversariante na lista de check-in. */
export default function BirthdayBadge({ show, size = 18 }: Props) {
  if (!show) return null;

  return (
    <View style={styles.wrap} accessibilityLabel="Aniversariante do dia">
      <MaterialCommunityIcons name="cake-variant" size={size} color="#E91E8C" />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginLeft: 6,
    justifyContent: "center",
  },
});
