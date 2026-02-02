import { Stack } from "expo-router";

export default function AuthLayout() {
  return (
    <Stack>
      <Stack.Screen
        name="login"
        options={{
          headerShown: false,
        }}
      />
      <Stack.Screen
        name="register-mobile"
        options={{
          headerShown: false,
        }}
      />
      <Stack.Screen
        name="register-success"
        options={{
          headerShown: false,
        }}
      />
    </Stack>
  );
}
