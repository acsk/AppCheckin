import { useEffect, useMemo, useState } from 'react';
import Splash from './src/screens/Splash';
import Login from './src/screens/Login';
import Tabs from './src/screens/Tabs';

export default function App() {
  const [mostrarSplash, setMostrarSplash] = useState(true);
  const [logado, setLogado] = useState(false);
  const [usuario, setUsuario] = useState(null);

  const baseUrl = useMemo(() => {
    // Para Android emulador use http://10.0.2.2:8080, em iOS simulador use http://localhost:8080.
    return 'http://localhost:8080';
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => setMostrarSplash(false), 1800);
    return () => clearTimeout(timer);
  }, []);

  if (mostrarSplash) {
    return <Splash />;
  }

  if (!logado) {
    return (
      <Login
        baseUrl={baseUrl}
        onSucesso={(user) => {
          setUsuario(user || null);
          setLogado(true);
        }}
      />
    );
  }

  return <Tabs usuario={usuario} />;
}
