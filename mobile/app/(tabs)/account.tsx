import mobileService from "@/src/services/mobileService";
import { colors } from "@/src/theme/colors";
import { getApiUrlRuntime, getAssetsUrlRuntime } from "@/src/utils/apiConfig";
import { handleAuthError } from "@/src/utils/authHelpers";
import AsyncStorage from "@/src/utils/storage";
import { Feather, MaterialCommunityIcons } from "@expo/vector-icons";
import * as ImagePicker from "expo-image-picker";
import { useRouter } from "expo-router";
import React, { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

interface UserProfile {
  id?: number;
  nome: string;
  email: string;
  cpf?: string;
  telefone?: string;
  data_nascimento?: string;
  foto_base64?: string;
  foto_caminho?: string;
  membro_desde?: string;
  tenant?: { nome: string };
  tenants?: { id: string; nome: string; email?: string; telefone?: string }[];
  estatisticas?: {
    total_checkins: number;
    checkins_mes: number;
    sequencia_dias: number;
    ultimo_checkin?: { data: string; hora: string };
  };
  ranking_modalidades?: {
    modalidade_id: number;
    modalidade_nome: string;
    modalidade_icone?: string;
    modalidade_cor?: string;
    posicao: number;
    total_checkins: number;
    total_participantes: number;
  }[];
}

interface RankingUsuario {
  id: number;
  nome: string;
  foto_caminho?: string;
}

interface RankingItem {
  posicao: number;
  usuario: RankingUsuario;
  total_checkins: number;
}

interface RankingModalidade {
  id: number;
  nome: string;
  icone?: string;
  cor?: string;
}

export default function AccountScreen() {
  const router = useRouter();
  const [userProfile, setUserProfile] = useState<UserProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [rankingLoading, setRankingLoading] = useState(false);
  const [rankingError, setRankingError] = useState<string | null>(null);
  const [ranking, setRanking] = useState<RankingItem[]>([]);
  const [rankingPeriodo, setRankingPeriodo] = useState<string>("");
  const [rankingModalidades, setRankingModalidades] = useState<
    RankingModalidade[]
  >([]);
  const [selectedModalidadeId, setSelectedModalidadeId] = useState<
    number | null
  >(null);
  const [modalidadesFromTurmasLoaded, setModalidadesFromTurmasLoaded] =
    useState(false);
  const [updatingPhoto, setUpdatingPhoto] = useState(false);
  const [photoUrl, setPhotoUrl] = useState<string | null>(null);
  const [apiUrl, setApiUrl] = useState<string>("");
  const [assetsUrl, setAssetsUrl] = useState<string>("");

  const getInitials = (nome: string = "") => {
    const parts = nome.split(" ").filter(Boolean);
    if (parts.length === 0) return "?";
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (
      parts[0].charAt(0) + parts[parts.length - 1].charAt(0)
    ).toUpperCase();
  };

  useEffect(() => {
    // Initialize API URL and Assets URL
    setApiUrl(getApiUrlRuntime());
    setAssetsUrl(getAssetsUrlRuntime());
    console.log("📍 API URL (Account):", getApiUrlRuntime());
    console.log("📷 ASSETS URL (Account):", getAssetsUrlRuntime());
    loadUserProfile();
  }, []);

  useEffect(() => {
    if (userProfile) {
      loadRanking();
      if (userProfile.ranking_modalidades?.length) {
        const modalidades = userProfile.ranking_modalidades.map((item) => ({
          id: item.modalidade_id,
          nome: item.modalidade_nome,
          icone: item.modalidade_icone,
          cor: item.modalidade_cor,
        }));
        setRankingModalidades(modalidades);
        // Sempre seta a primeira modalidade ao carregar
        if (!selectedModalidadeId) {
          setSelectedModalidadeId(modalidades[0].id);
        }
      }
    }
  }, [userProfile]);

  // Debug: quando assetsUrl ou userProfile mudam
  useEffect(() => {
    if (userProfile && assetsUrl) {
      console.log("\n🔍 DEBUG: Render Photo");
      console.log("   assetsUrl:", assetsUrl);
      console.log("   photoUrl:", photoUrl || "vazio");
      console.log("   foto_caminho:", userProfile.foto_caminho || "vazio");
      if (userProfile.foto_caminho) {
        console.log(
          "   URL completa:",
          `${assetsUrl}${userProfile.foto_caminho}`,
        );
      }
    }
  }, [userProfile, assetsUrl, photoUrl]);

  // Carregar ranking quando modalidade selecionada muda
  useEffect(() => {
    if (selectedModalidadeId && userProfile) {
      loadRanking(selectedModalidadeId);
    }
  }, [selectedModalidadeId]);

  const formatCPF = (cpf) => {
    if (!cpf) return "";
    const cleaned = cpf.replace(/\D/g, "");
    return cleaned.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
  };

  const formatPhone = (phone: string) => {
    if (!phone) return "";
    const cleaned = phone.replace(/\D/g, "");
    if (cleaned.length === 11) {
      return cleaned.replace(/(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
    }
    return cleaned.replace(/(\d{2})(\d{4})(\d{4})/, "($1) $2-$3");
  };

  const loadUserProfile = async () => {
    try {
      console.log("\n🔄 INICIANDO CARREGAMENTO DE PERFIL");

      const token = await AsyncStorage.getItem("@appcheckin:token");

      if (!token) {
        console.error("❌ Token não encontrado");
        router.replace("/(auth)/login");
        return;
      }
      console.log("✅ Token encontrado");

      const headers = {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      };

      const baseUrl = getApiUrlRuntime();
      const url = `${baseUrl}/mobile/perfil`;
      console.log("📍 URL:", url);

      const profileResponse = await fetch(url, {
        method: "GET",
        headers: headers,
      });

      console.log("📡 RESPOSTA DO SERVIDOR");
      console.log("   Status:", profileResponse.status);
      console.log("   Status Text:", profileResponse.statusText);

      const responseText = await profileResponse.text();
      console.log(
        "   Body (primeiros 500 chars):",
        responseText.substring(0, 500),
      );

      if (!profileResponse.ok) {
        // Se for 401, token expirou ou é inválido
        if (profileResponse.status === 401) {
          console.log("🔑 Detectado 401 - Token inválido/expirado");
          await handleAuthError();
          router.replace("/(auth)/login");
          return;
        }

        console.error("❌ ERRO NA REQUISIÇÃO");
        console.error("   Status:", profileResponse.status);
        console.error("   Body completo:", responseText);
        throw new Error(`Erro HTTP: ${profileResponse.status}`);
      }

      let profileData;
      try {
        profileData = JSON.parse(responseText);
        console.log("✅ JSON parseado com sucesso");
        console.log("   Dados:", JSON.stringify(profileData, null, 2));
      } catch (parseError) {
        console.error("❌ ERRO AO FAZER PARSE DO JSON");
        console.error("   Erro:", parseError.message);
        console.error("   Body:", responseText);
        throw parseError;
      }

      if (profileData.success) {
        console.log("✅ Perfil carregado com sucesso");
        console.log(
          "📸 foto_base64:",
          profileData.data.foto_base64 ? "SIM" : "NÃO",
        );
        console.log(
          "📸 foto_caminho:",
          profileData.data.foto_caminho || "NÃO TEM",
        );
        if (profileData.data.foto_caminho) {
          const fullPhotoUrl = apiUrl + profileData.data.foto_caminho;
          console.log("🖼️ URL COMPLETA DA FOTO:", fullPhotoUrl);
        }
        setUserProfile(profileData.data);
      } else {
        Alert.alert(
          "Erro",
          profileData.error || "Não foi possível carregar o perfil",
        );
      }
    } catch (error: any) {
      if (error instanceof SyntaxError) {
        Alert.alert(
          "Servidor",
          "Servidor indisponível. Tente novamente em alguns instantes.",
        );
      } else {
        Alert.alert("Erro", "Erro ao conectar com o servidor");
      }
    } finally {
      setLoading(false);
    }
  };

  const handleChangePhoto = async () => {
    try {
      // Pedir permissão para acessar galeria
      const { status } =
        await ImagePicker.requestMediaLibraryPermissionsAsync();

      if (status !== "granted") {
        Alert.alert(
          "Permissão Negada",
          "Você precisa permitir acesso à galeria para trocar sua foto",
        );
        return;
      }

      // Abrir seletor de imagem
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: "images",
        allowsEditing: true,
        aspect: [1, 1],
        quality: 0.8,
      });

      if (!result.canceled && result.assets && result.assets.length > 0) {
        const asset = result.assets[0];

        setUpdatingPhoto(true);

        // Criar FormData para upload (sem passar object, apenas uri)
        const formData = new FormData();
        const uri = asset.uri;
        const filename = uri.split("/").pop() || "photo.jpg";

        // No web, converter blob; no mobile, usar uri
        if (Platform.OS === "web") {
          // Para web, fazer fetch da imagem e converter para blob
          const response = await fetch(uri);
          const blob = await response.blob();
          formData.append("foto", blob, filename);
        } else {
          // Para mobile, usar uri diretamente
          formData.append("foto", {
            uri,
            type: "image/jpeg",
            name: filename,
          } as any);
        }

        // Enviar para servidor
        console.log("📸 Enviando foto para servidor...");
        const response = await mobileService.atualizarFoto(formData);
        console.log("📸 Resposta do servidor:", response);

        if (response?.success) {
          console.log("✅ Foto enviada com sucesso, recarregando perfil...");
          // Armazenar URL da foto se disponível
          if (response.data?.caminho_url) {
            const apiUrl = getApiUrlRuntime();
            setPhotoUrl(`${apiUrl}${response.data.caminho_url}`);
            console.log(
              "📸 URL da foto armazenada:",
              `${apiUrl}${response.data.caminho_url}`,
            );
          }
          Alert.alert("Sucesso", "Foto atualizada com sucesso!");
          // Recarregar perfil para pegar a nova foto
          setUpdatingPhoto(false); // Desabilita loading ANTES de recarregar
          await loadUserProfile();
        } else {
          console.error("❌ Erro na resposta:", response);
          Alert.alert(
            "Erro",
            response?.error || response?.message || "Erro ao atualizar foto",
          );
        }
      }
    } catch (error: any) {
      console.error("Erro ao trocar foto:", error);
      Alert.alert(
        "Erro",
        error.message || "Erro ao trocar foto. Tente novamente.",
      );
    } finally {
      setUpdatingPhoto(false);
    }
  };

  const loadRanking = async (modalidadeId?: number | null) => {
    try {
      setRankingError(null);
      setRankingLoading(true);
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        setRankingError("Token não encontrado");
        return;
      }

      const params = modalidadeId ? `?modalidade_id=${modalidadeId}` : "";
      const url = `${getApiUrlRuntime()}/mobile/ranking/mensal${params}`;
      const response = await fetch(url, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });

      const data = await response.json();
      if (!response.ok || !data?.success) {
        throw new Error(data?.error || "Erro ao carregar ranking");
      }

      const rankingData = data?.data;
      setRanking(rankingData?.ranking || []);
      setRankingPeriodo(rankingData?.periodo || "");

      if (
        Array.isArray(rankingData?.modalidades) &&
        rankingData.modalidades.length > 0
      ) {
        setRankingModalidades(rankingData.modalidades);
        // Não fazer chamada recursiva aqui, deixar para useEffect
      } else if (!modalidadesFromTurmasLoaded) {
        await loadModalidadesFromTurmas();
      }
    } catch (error: any) {
      setRankingError(error?.message || "Erro ao carregar ranking");
    } finally {
      setRankingLoading(false);
    }
  };

  const loadModalidadesFromTurmas = async () => {
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        return;
      }
      const hoje = new Date().toISOString().split("T")[0];
      const url = `${getApiUrlRuntime()}/mobile/horarios-disponiveis?data=${hoje}`;
      const response = await fetch(url, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });
      if (!response.ok) {
        return;
      }
      const data = await response.json();
      const turmas = data?.data?.turmas || [];
      const modalidadesMap = new Map<number, RankingModalidade>();
      turmas.forEach((turma: any) => {
        const mod = turma?.modalidade;
        if (mod?.id && mod?.nome) {
          if (!modalidadesMap.has(mod.id)) {
            modalidadesMap.set(mod.id, {
              id: mod.id,
              nome: mod.nome,
              icone: mod.icone,
              cor: mod.cor,
            });
          }
        }
      });
      const modalidades = Array.from(modalidadesMap.values());
      setRankingModalidades(modalidades);
      setModalidadesFromTurmasLoaded(true);
      // Não fazer chamada recursiva aqui, deixar para useEffect
    } catch {
      setModalidadesFromTurmasLoaded(true);
    }
  };

  const rankingMock = [
    { id: 101, nome: "Marina Souza", checkins: 28 },
    { id: 102, nome: "Joao Pedro", checkins: 25 },
    { id: 103, nome: "Ana Clara", checkins: 23 },
    { id: 104, nome: "Felipe Costa", checkins: 19 },
    { id: 105, nome: "Livia Mendes", checkins: 17 },
  ];

  const handleLogout = async () => {
    console.log("🔴 [LOGOUT] handleLogout chamado");
    console.log("🔴 [LOGOUT] Platform.OS:", Platform.OS);

    // Usar confirm() nativo para web, Alert para mobile
    let confirmed = false;

    if (Platform.OS === "web") {
      // Web: usar window.confirm()
      confirmed = window.confirm("Deseja realmente sair?");
      console.log("🔴 [LOGOUT] Web confirm result:", confirmed);
    } else {
      // Mobile: usar Alert.alert() com Promise
      confirmed = await new Promise((resolve) => {
        Alert.alert("Sair", "Deseja realmente sair?", [
          {
            text: "Cancelar",
            style: "cancel",
            onPress: () => resolve(false),
          },
          {
            text: "Sair",
            style: "destructive",
            onPress: () => resolve(true),
          },
        ]);
      });
      console.log("🔴 [LOGOUT] Mobile alert result:", confirmed);
    }

    if (!confirmed) {
      console.log("🔵 [LOGOUT] Cancelado pelo usuário");
      return;
    }

    try {
      console.log("🟡 [LOGOUT] Iniciando logout...");

      // Log do estado antes de remover
      const tokenBefore = await AsyncStorage.getItem("@appcheckin:token");
      console.log(
        "🟡 [LOGOUT] Token antes de remover:",
        tokenBefore ? "EXISTE" : "NÃO EXISTE",
      );

      // Remover token
      console.log("🟡 [LOGOUT] Removendo token...");
      const result1 = await AsyncStorage.removeItem("@appcheckin:token");
      console.log("✅ [LOGOUT] Token removido - resultado:", result1);

      // Verificar se removeu
      const tokenAfter = await AsyncStorage.getItem("@appcheckin:token");
      console.log(
        "✅ [LOGOUT] Token após remover:",
        tokenAfter ? "AINDA EXISTE" : "FOI REMOVIDO",
      );

      // Remover usuário
      console.log("🟡 [LOGOUT] Removendo usuário...");
      const result2 = await AsyncStorage.removeItem("@appcheckin:user");
      console.log("✅ [LOGOUT] Usuário removido - resultado:", result2);

      // Remover tenant
      console.log("🟡 [LOGOUT] Removendo tenant...");
      const result3 = await AsyncStorage.removeItem("@appcheckin:tenant");
      console.log("✅ [LOGOUT] Tenant removido - resultado:", result3);

      // Limpar estado local
      console.log("🟡 [LOGOUT] Limpando estado local...");
      setUserProfile(null);
      console.log("✅ [LOGOUT] Estado local limpo");

      // Redirecionar para login
      console.log("🟡 [LOGOUT] Redirecionando para login...");
      router.replace("/(auth)/login");
      console.log("✅ [LOGOUT] Replace chamado");

      console.log("🟢 [LOGOUT] Logout completo!");
    } catch (error) {
      console.error("❌ [LOGOUT] Erro ao fazer logout:", error);
      console.error("❌ [LOGOUT] Error type:", typeof error);
      console.error("❌ [LOGOUT] Error message:", error?.message);
      console.error("❌ [LOGOUT] Error stack:", error?.stack);
      Alert.alert(
        "Erro",
        "Erro ao fazer logout: " + (error?.message || "Tente novamente"),
      );
    }
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      </SafeAreaView>
    );
  }

  if (!userProfile) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.loadingContainer}>
          <Text style={styles.errorText}>Erro ao carregar perfil</Text>
          <TouchableOpacity
            style={styles.retryButton}
            onPress={loadUserProfile}
          >
            <Text style={styles.retryButtonText}>Tentar Novamente</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      {/* Header com Botão Recarregar */}
      <View style={styles.headerTop}>
        <Text style={styles.headerTitle}>Minha Conta</Text>
        <TouchableOpacity
          style={styles.refreshButton}
          onPress={loadUserProfile}
        >
          <Feather name="refresh-cw" size={20} color={colors.primary} />
        </TouchableOpacity>
      </View>

      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Profile Header */}
        <View style={styles.profileSection}>
          {/* Photo Placeholder */}
          <View style={styles.photoWrapper}>
            <TouchableOpacity
              style={styles.photoContainer}
              onPress={handleChangePhoto}
              disabled={updatingPhoto}
            >
              {photoUrl ? (
                <Image
                  source={{ uri: photoUrl }}
                  style={styles.photoImage}
                  onError={(error) => {
                    console.error("❌ Erro ao carregar photoUrl:", error);
                  }}
                />
              ) : userProfile.foto_caminho ? (
                <Image
                  source={{
                    uri: `${assetsUrl}${userProfile.foto_caminho}`,
                  }}
                  style={styles.photoImage}
                  onError={(error) => {
                    console.error(
                      "❌ Erro ao carregar foto_caminho:",
                      `${assetsUrl}${userProfile.foto_caminho}`,
                      error,
                    );
                  }}
                />
              ) : userProfile.foto_base64 ? (
                <Image
                  source={{
                    uri: `data:image/jpeg;base64,${userProfile.foto_base64}`,
                  }}
                  style={styles.photoImage}
                  onError={(error) => {
                    console.error("❌ Erro ao carregar foto_base64:", error);
                  }}
                />
              ) : (
                <Text style={styles.photoText}>👤</Text>
              )}
              {updatingPhoto && (
                <View style={styles.photoLoading}>
                  <ActivityIndicator size="small" color={colors.primary} />
                </View>
              )}
            </TouchableOpacity>
            <View style={styles.photoCameraIcon}>
              <Feather name="camera" size={16} color="#fff" />
            </View>
          </View>

          {/* Name and Email */}
          <Text style={styles.userName}>{userProfile.nome}</Text>
          <Text style={styles.userEmail}>{userProfile.email}</Text>

          {/* Tenant */}
          {userProfile.tenant && (
            <TouchableOpacity
              style={styles.tenantButton}
              onPress={() => router.push("/planos")}
            >
              <Text style={styles.tenantName}>{userProfile.tenant.nome}</Text>
              <Feather name="chevron-right" size={16} color={colors.primary} />
            </TouchableOpacity>
          )}
        </View>

        {/* Statistics Section */}
        {userProfile.estatisticas && (
          <View style={styles.statisticsSection}>
            <Text style={styles.sectionTitle}>Estatísticas</Text>

            <View style={styles.statsGrid}>
              <View style={styles.statCard}>
                <Feather name="check-circle" size={24} color={colors.primary} />
                <Text style={styles.statLabel}>Total de Check-ins</Text>
                <Text style={styles.statValue}>
                  {userProfile.estatisticas.total_checkins}
                </Text>
              </View>

              <View style={styles.statCard}>
                <Feather name="calendar" size={24} color={colors.primary} />
                <Text style={styles.statLabel}>Este Mês</Text>
                <Text style={styles.statValue}>
                  {userProfile.estatisticas.checkins_mes}
                </Text>
              </View>

              <View style={styles.statCard}>
                <Feather name="zap" size={24} color={colors.primary} />
                <Text style={styles.statLabel}>Sequência</Text>
                <Text style={styles.statValue}>
                  {userProfile.estatisticas.sequencia_dias} dia(s)
                </Text>
              </View>
            </View>

            {userProfile.estatisticas.ultimo_checkin && (
              <View style={styles.lastCheckinCard}>
                <Feather name="clock" size={18} color={colors.primary} />
                <View style={styles.lastCheckinContent}>
                  <Text style={styles.lastCheckinLabel}>Último Check-in</Text>
                  <Text style={styles.lastCheckinValue}>
                    {userProfile.estatisticas.ultimo_checkin.data} às{" "}
                    {userProfile.estatisticas.ultimo_checkin.hora}
                  </Text>
                </View>
              </View>
            )}
          </View>
        )}

        {/* Ranking de Check-ins */}
        <View style={styles.rankingSection}>
          <Text style={styles.sectionTitle}>
            Ranking de Check-ins{rankingPeriodo ? ` • ${rankingPeriodo}` : ""}
          </Text>
          {rankingModalidades.length > 1 && (
            <View style={styles.modalidadeFilter}>
              {rankingModalidades.map((modalidade) => {
                const active = selectedModalidadeId === modalidade.id;
                const chipColor = modalidade.cor || colors.primary;
                return (
                  <TouchableOpacity
                    key={modalidade.id}
                    style={[
                      styles.modalidadeChip,
                      active && styles.modalidadeChipActive,
                      { borderColor: active ? chipColor : "#f8e5d1" },
                      active && { backgroundColor: `${chipColor}15` },
                    ]}
                    onPress={() => {
                      setSelectedModalidadeId(modalidade.id);
                      loadRanking(modalidade.id);
                    }}
                  >
                    <View style={styles.modalidadeChipContent}>
                      {modalidade.icone ? (
                        <MaterialCommunityIcons
                          name={modalidade.icone as any}
                          size={14}
                          color={chipColor}
                        />
                      ) : null}
                      <Text
                        style={[
                          styles.modalidadeChipText,
                          active && styles.modalidadeChipTextActive,
                          { color: active ? chipColor : "#8b6b3b" },
                        ]}
                      >
                        {modalidade.nome}
                      </Text>
                    </View>
                  </TouchableOpacity>
                );
              })}
            </View>
          )}
          <View style={styles.rankingCard}>
            {rankingLoading ? (
              <View style={styles.rankingLoading}>
                <ActivityIndicator size="small" color={colors.primary} />
                <Text style={styles.rankingLoadingText}>
                  Carregando ranking...
                </Text>
              </View>
            ) : rankingError ? (
              <Text style={styles.rankingErrorText}>{rankingError}</Text>
            ) : (
              <>
                <View style={styles.rankingList}>
                  {ranking.slice(0, 3).map((item) => (
                    <View key={item.usuario.id} style={styles.rankingListItem}>
                      <View style={styles.rankingPosition}>
                        <Text style={styles.rankingPositionNumber}>
                          {item.posicao}
                        </Text>
                      </View>
                      <View style={styles.rankingAvatar}>
                        {item.usuario.foto_caminho && assetsUrl ? (
                          <Image
                            source={{
                              uri: `${assetsUrl}${item.usuario.foto_caminho}`,
                            }}
                            style={styles.rankingAvatarImage}
                          />
                        ) : (
                          <Text style={styles.rankingAvatarText}>👤</Text>
                        )}
                      </View>
                      <View style={styles.rankingListContent}>
                        <Text style={styles.rankingName}>
                          {item.usuario.nome}
                        </Text>
                        <Text style={styles.rankingCheckins}>
                          {item.total_checkins} check-ins
                        </Text>
                      </View>
                    </View>
                  ))}
                </View>
                <View style={styles.rankingDivider} />
                <View style={styles.rankingUserRow}>
                  <View>
                    <Text style={styles.rankingUserLabel}>Sua posicao</Text>
                    <Text style={styles.rankingUserName}>
                      {userProfile.nome}
                    </Text>
                  </View>
                  <View style={styles.rankingUserPosition}>
                    <Text style={styles.rankingUserPositionText}>
                      {userProfile.ranking_modalidades?.find(
                        (item) => item.modalidade_id === selectedModalidadeId,
                      )?.posicao ||
                        ranking.find(
                          (item) => item.usuario.id === userProfile.id,
                        )?.posicao ||
                        "--"}
                    </Text>
                  </View>
                </View>
              </>
            )}
          </View>
        </View>

        {/* Personal Information */}
        <View style={styles.infoSection}>
          <Text style={styles.sectionTitle}>Informações Pessoais</Text>

          <View style={styles.infoItem}>
            <Feather name="mail" size={18} color={colors.primary} />
            <View style={styles.infoContent}>
              <Text style={styles.infoLabel}>Email</Text>
              <Text style={styles.infoValue}>{userProfile.email}</Text>
            </View>
          </View>

          {userProfile.cpf && (
            <View style={styles.infoItem}>
              <Feather name="credit-card" size={18} color={colors.primary} />
              <View style={styles.infoContent}>
                <Text style={styles.infoLabel}>CPF</Text>
                <Text style={styles.infoValue}>
                  {formatCPF(userProfile.cpf)}
                </Text>
              </View>
            </View>
          )}

          {userProfile.telefone && (
            <View style={styles.infoItem}>
              <Feather name="phone" size={18} color={colors.primary} />
              <View style={styles.infoContent}>
                <Text style={styles.infoLabel}>Telefone</Text>
                <Text style={styles.infoValue}>
                  {formatPhone(userProfile.telefone)}
                </Text>
              </View>
            </View>
          )}

          {userProfile.data_nascimento && (
            <View style={styles.infoItem}>
              <Feather name="calendar" size={18} color={colors.primary} />
              <View style={styles.infoContent}>
                <Text style={styles.infoLabel}>Data de Nascimento</Text>
                <Text style={styles.infoValue}>
                  {new Date(userProfile.data_nascimento).toLocaleDateString(
                    "pt-BR",
                  )}
                </Text>
              </View>
            </View>
          )}
        </View>

        {/* Academias Section */}
        {userProfile.tenants && userProfile.tenants.length > 0 && (
          <View style={styles.academiasSection}>
            <Text style={styles.sectionTitle}>Minhas Academias</Text>

            {userProfile.tenants.map((tenant) => (
              <TouchableOpacity
                key={tenant.id}
                style={styles.academiaCard}
                onPress={() => router.push(`/planos?tenantId=${tenant.id}`)}
                activeOpacity={0.7}
              >
                <View style={styles.academiaCardContent}>
                  {/* Nome da Academia */}
                  <Text style={styles.academiaName}>{tenant.nome}</Text>

                  {/* Email */}
                  {tenant.email && (
                    <View style={styles.academiaInfo}>
                      <Feather name="mail" size={14} color={colors.primary} />
                      <Text style={styles.academiaInfoText}>
                        {tenant.email}
                      </Text>
                    </View>
                  )}

                  {/* Telefone */}
                  {tenant.telefone && (
                    <View style={styles.academiaInfo}>
                      <Feather name="phone" size={14} color={colors.primary} />
                      <Text style={styles.academiaInfoText}>
                        {tenant.telefone}
                      </Text>
                    </View>
                  )}
                </View>
                <Feather
                  name="chevron-right"
                  size={20}
                  color={colors.primary}
                />
              </TouchableOpacity>
            ))}
          </View>
        )}

        {/* Member Since */}
        {userProfile.membro_desde && (
          <View style={styles.memberSinceSection}>
            <Feather name="heart" size={16} color={colors.primary} />
            <Text style={styles.memberSinceText}>
              Membro desde{" "}
              {new Date(userProfile.membro_desde).toLocaleDateString("pt-BR")}
            </Text>
          </View>
        )}

        {/* Logout Button */}
        <TouchableOpacity style={styles.logoutButton} onPress={handleLogout}>
          <Feather name="log-out" size={20} color="#fff" />
          <Text style={styles.logoutText}>Sair</Text>
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f5f5f5",
  },
  headerTop: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: "#fff",
    borderBottomWidth: 1,
    borderBottomColor: "#f0f0f0",
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: "700",
    color: "#000",
  },
  refreshButton: {
    padding: 8,
  },
  scrollContent: {
    padding: 16,
    paddingBottom: 40,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    paddingVertical: 50,
  },
  errorText: {
    fontSize: 16,
    color: "#666",
    marginBottom: 16,
  },
  retryButton: {
    backgroundColor: colors.primary,
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 8,
  },
  retryButtonText: {
    color: "#fff",
    fontWeight: "600",
  },
  profileSection: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 24,
    alignItems: "center",
    marginBottom: 20,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  photoWrapper: {
    position: "relative",
    width: 100,
    height: 100,
    marginBottom: 16,
  },
  photoContainer: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: "#f0f0f0",
    justifyContent: "center",
    alignItems: "center",
    overflow: "hidden",
  },
  photoImage: {
    width: "100%",
    height: "100%",
  },
  photoText: {
    fontSize: 48,
  },
  photoLoading: {
    position: "absolute",
    width: "100%",
    height: "100%",
    backgroundColor: "rgba(0,0,0,0.5)",
    justifyContent: "center",
    alignItems: "center",
  },
  photoCameraIcon: {
    position: "absolute",
    bottom: 0,
    right: 0,
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: colors.primary,
    justifyContent: "center",
    alignItems: "center",
    borderWidth: 2,
    borderColor: "#fff",
    zIndex: 10,
  },
  photoInitials: {
    fontSize: 42,
    fontWeight: "700",
    color: "#fff",
  },
  userName: {
    fontSize: 24,
    fontWeight: "700",
    color: "#000",
    marginBottom: 4,
  },
  userEmail: {
    fontSize: 14,
    color: "#666",
    marginBottom: 8,
  },
  tenantName: {
    fontSize: 12,
    color: colors.primary,
    fontWeight: "600",
  },
  tenantButton: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    marginTop: 4,
    backgroundColor: colors.primary + "10",
    borderRadius: 6,
    borderWidth: 1,
    borderColor: colors.primary + "30",
  },
  statisticsSection: {
    marginBottom: 20,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
    color: "#000",
    marginBottom: 12,
  },
  statsGrid: {
    flexDirection: "row",
    gap: 12,
    marginBottom: 12,
  },
  statCard: {
    flex: 1,
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 12,
    alignItems: "center",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  statLabel: {
    fontSize: 11,
    color: "#999",
    marginTop: 8,
    marginBottom: 4,
    textAlign: "center",
  },
  statValue: {
    fontSize: 18,
    fontWeight: "bold",
    color: colors.primary,
  },
  lastCheckinCard: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 12,
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  lastCheckinContent: {
    flex: 1,
  },
  lastCheckinLabel: {
    fontSize: 12,
    color: "#999",
    marginBottom: 2,
  },
  lastCheckinValue: {
    fontSize: 14,
    fontWeight: "600",
    color: "#000",
  },
  infoSection: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 16,
    marginBottom: 20,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  infoItem: {
    flexDirection: "row",
    alignItems: "flex-start",
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#f0f0f0",
    gap: 12,
  },
  infoContent: {
    flex: 1,
  },
  infoLabel: {
    fontSize: 12,
    color: "#999",
    marginBottom: 4,
  },
  infoValue: {
    fontSize: 14,
    fontWeight: "500",
    color: "#000",
  },
  membershipSection: {
    marginBottom: 20,
  },
  membershipCard: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 16,
    borderLeftWidth: 4,
    borderLeftColor: colors.primary,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  membershipHeader: {
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 12,
    gap: 12,
  },
  membershipInfo: {
    flex: 1,
  },
  membershipName: {
    fontSize: 16,
    fontWeight: "600",
    color: "#000",
  },
  membershipStatus: {
    fontSize: 12,
    color: colors.primary,
    fontWeight: "500",
    marginTop: 2,
    textTransform: "capitalize",
  },
  membershipDates: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 8,
    gap: 8,
  },
  membershipLabel: {
    fontSize: 12,
    color: "#999",
    fontWeight: "500",
  },
  membershipValue: {
    fontSize: 13,
    color: "#000",
    fontWeight: "500",
  },
  membershipValue2: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 8,
    gap: 8,
  },
  membershipPriceValue: {
    fontSize: 14,
    color: colors.primary,
    fontWeight: "600",
  },
  viewDetailsButton: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    marginTop: 16,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: "#f0f0f0",
  },
  viewDetailsText: {
    fontSize: 14,
    color: colors.primary,
    fontWeight: "600",
  },
  memberSinceSection: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 12,
    marginBottom: 20,
    gap: 6,
  },
  memberSinceText: {
    fontSize: 12,
    color: "#999",
  },
  contratosSection: {
    marginBottom: 20,
  },
  academiasSection: {
    marginBottom: 20,
  },
  rankingSection: {
    marginBottom: 20,
  },
  modalidadeFilter: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 8,
    marginBottom: 10,
  },
  modalidadeChip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    backgroundColor: "#fff",
    borderWidth: 1,
    borderColor: "#f8e5d1",
  },
  modalidadeChipActive: {
    backgroundColor: colors.primary + "15",
    borderColor: colors.primary + "60",
  },
  modalidadeChipContent: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  modalidadeChipText: {
    fontSize: 12,
    color: "#8b6b3b",
    fontWeight: "600",
  },
  modalidadeChipTextActive: {
    color: colors.primary,
  },
  rankingCard: {
    backgroundColor: "#fffaf5",
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: "#fde2c2",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  rankingList: {
    gap: 10,
  },
  rankingListItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    backgroundColor: "#fff",
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 8,
    borderWidth: 1,
    borderColor: "#f8e5d1",
  },
  rankingPosition: {
    width: 44,
    height: 36,
    borderRadius: 10,
    backgroundColor: "#fffaf5",
    borderWidth: 1,
    borderColor: "#f4c595",
    alignItems: "center",
    justifyContent: "center",
  },
  rankingPositionNumber: {
    fontSize: 13,
    fontWeight: "700",
    color: "#d97706",
  },
  rankingAvatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: colors.primary,
    justifyContent: "center",
    alignItems: "center",
    overflow: "hidden",
  },
  rankingAvatarImage: {
    width: 36,
    height: 36,
    borderRadius: 18,
  },
  rankingAvatarText: {
    fontSize: 20,
  },
  rankingNameRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  rankingListContent: {
    flex: 1,
  },
  rankingName: {
    fontSize: 13,
    fontWeight: "600",
    color: "#111827",
  },
  rankingCheckins: {
    fontSize: 11,
    color: "#6b7280",
    marginTop: 2,
  },
  rankingLoading: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingVertical: 8,
  },
  rankingLoadingText: {
    fontSize: 12,
    color: "#6b7280",
  },
  rankingErrorText: {
    fontSize: 12,
    color: "#b91c1c",
  },
  rankingDivider: {
    height: 1,
    backgroundColor: "#fed7aa",
    marginVertical: 12,
  },
  rankingUserRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  rankingUserLabel: {
    fontSize: 11,
    color: "#9ca3af",
    marginBottom: 2,
  },
  rankingUserName: {
    fontSize: 14,
    fontWeight: "600",
    color: "#111827",
  },
  rankingUserPosition: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 10,
    backgroundColor: colors.primary + "15",
    borderWidth: 1,
    borderColor: colors.primary + "40",
  },
  rankingUserPositionText: {
    fontSize: 12,
    fontWeight: "700",
    color: colors.primary,
  },
  academiaCard: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
    borderLeftWidth: 4,
    borderLeftColor: colors.primary,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  academiaCardContent: {
    flex: 1,
  },
  modalidadeBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
    alignSelf: "flex-start",
    marginBottom: 8,
  },
  modalidadeDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  modalidadeText: {
    fontSize: 12,
    fontWeight: "600",
  },
  academiaName: {
    fontSize: 14,
    fontWeight: "600",
    color: "#000",
    marginBottom: 8,
  },
  academiaInfo: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    marginBottom: 6,
  },
  academiaInfoText: {
    fontSize: 12,
    color: "#666",
  },
  contratoCard: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 16,
    borderLeftWidth: 4,
    borderLeftColor: colors.primary,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  contratoHeader: {
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 16,
    gap: 12,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#f0f0f0",
  },
  contratoInfo: {
    flex: 1,
  },
  contratoNome: {
    fontSize: 16,
    fontWeight: "600",
    color: "#000",
  },
  contratoStatus: {
    fontSize: 12,
    color: colors.primary,
    fontWeight: "500",
    marginTop: 2,
    textTransform: "capitalize",
  },
  contratoSection2: {
    marginVertical: 12,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#f0f0f0",
  },
  contratoSectionTitle: {
    fontSize: 13,
    fontWeight: "600",
    color: "#666",
    marginBottom: 8,
    textTransform: "uppercase",
  },
  contratoItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 6,
  },
  contratoLabel: {
    fontSize: 12,
    color: "#999",
    fontWeight: "500",
  },
  contratoValue: {
    fontSize: 13,
    color: "#000",
    fontWeight: "600",
  },
  contratoDiasRestantes: {
    fontSize: 13,
    color: colors.primary,
    fontWeight: "600",
  },
  progressContainer: {
    width: "100%",
    height: 6,
    backgroundColor: "#f0f0f0",
    borderRadius: 3,
    marginTop: 8,
    overflow: "hidden",
  },
  progressBar: {
    height: "100%",
    backgroundColor: colors.primary,
  },
  contratoFeatures: {
    marginTop: 8,
  },
  featureItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingVertical: 6,
  },
  featureText: {
    fontSize: 12,
    color: "#666",
    fontWeight: "500",
    flex: 1,
  },
  pagamentoItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 8,
    paddingHorizontal: 8,
    backgroundColor: "#f9f9f9",
    borderRadius: 6,
    marginVertical: 6,
  },
  pagamentoInfo: {
    flex: 1,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4,
  },
  statusText: {
    fontSize: 11,
    color: "#fff",
    fontWeight: "600",
  },
  logoutButton: {
    backgroundColor: "#f44336",
    borderRadius: 12,
    paddingVertical: 16,
    flexDirection: "row",
    justifyContent: "center",
    alignItems: "center",
    gap: 10,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 4,
    elevation: 4,
  },
  logoutText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "600",
  },
});
