import { DebugLogsViewer } from "@/components/DebugLogsViewer";
import PasswordRecoveryModal from "@/components/PasswordRecoveryModal";
import mobileService from "@/src/services/mobileService";
import { colors } from "@/src/theme/colors";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { handleAuthError } from "@/src/utils/authHelpers";
import {
    compressImage,
    logCompressionInfo,
} from "@/src/utils/imageCompression";
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
  const [showRecoveryModal, setShowRecoveryModal] = useState(false);

  const normalizeProfileModalidades = (
    items: UserProfile["ranking_modalidades"] = [],
  ): RankingModalidade[] =>
    items
      .filter((item) => item?.modalidade_id && item?.modalidade_nome)
      .map((item) => ({
        id: item.modalidade_id,
        nome: item.modalidade_nome,
        icone: item.modalidade_icone,
        cor: item.modalidade_cor,
      }));

  const normalizeApiModalidades = (
    items: RankingModalidade[] = [],
  ): RankingModalidade[] =>
    items
      .filter((item) => item?.id && item?.nome)
      .map((item) => ({
        id: item.id,
        nome: item.nome,
        icone: item.icone,
        cor: item.cor,
      }));

  const mergeModalidades = (...lists: RankingModalidade[][]) => {
    const map = new Map<number, RankingModalidade>();
    lists.forEach((list) => {
      list.forEach((item) => {
        if (!item?.id) {
          return;
        }
        if (!map.has(item.id)) {
          map.set(item.id, item);
        }
      });
    });
    return Array.from(map.values());
  };

  const getInitials = (nome: string = "") => {
    const parts = nome.split(" ").filter(Boolean);
    if (parts.length === 0) return "?";
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (
      parts[0].charAt(0) + parts[parts.length - 1].charAt(0)
    ).toUpperCase();
  };

  useEffect(() => {
    // Initialize API URL
    setApiUrl(getApiUrlRuntime());
    console.log("📍 API URL (Account):", getApiUrlRuntime());
    loadUserProfile();
  }, []);

  useEffect(() => {
    if (userProfile) {
      loadRanking();
      if (userProfile.ranking_modalidades?.length) {
        const modalidades = normalizeProfileModalidades(
          userProfile.ranking_modalidades,
        );
        setRankingModalidades(modalidades);
        // Sempre seta a primeira modalidade ao carregar
        if (!selectedModalidadeId) {
          setSelectedModalidadeId(modalidades[0].id);
        }
      }
    }
  }, [userProfile]);

  // Debug: quando apiUrl ou userProfile mudam
  useEffect(() => {
    if (userProfile && apiUrl) {
      console.log("\n🔍 DEBUG: Render Photo");
      console.log("   apiUrl:", apiUrl);
      console.log("   photoUrl:", photoUrl || "vazio");
      console.log("   foto_caminho:", userProfile.foto_caminho || "vazio");
      if (userProfile.foto_caminho) {
        console.log("   URL completa:", `${apiUrl}${userProfile.foto_caminho}`);
      }
    }
  }, [userProfile, apiUrl, photoUrl]);

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

        console.log("\n\n");
        console.log("═══════════════════════════════════════════════════════");
        console.log("🖼️ NOVO FLUXO DE UPLOAD - handleChangePhoto");
        console.log("═══════════════════════════════════════════════════════");
        console.log("📸 Asset selecionado:", {
          uri: asset.uri,
          width: asset.width,
          height: asset.height,
          type: asset.type,
        });

        setUpdatingPhoto(true);

        try {
          // 🎨 Comprimir imagem antes de enviar
          console.log("🎨 Iniciando compressão de imagem...");
          console.log("📸 Asset URI:", asset.uri);
          console.log("📱 Platform.OS:", Platform.OS);

          let uploadUri = asset.uri;
          let uploadType = "image/jpeg";

          // 🚫 COMPRESSÃO TEMPORARIAMENTE DESATIVADA PARA TESTES
          console.log("❌ COMPRESSÃO DESATIVADA - Enviando imagem original");
          
          /*
          try {
            console.log("⏳ Chamando compressImage()...");
            console.log(
              "📦 compressImage function type:",
              typeof compressImage,
            );
            console.log("📦 compressImage function exists:", !!compressImage);

            const compressResult = await compressImage(asset.uri, {
              maxWidth: 1080,
              maxHeight: 1080,
              quality: 0.8,
              outputFormat: "jpeg",
            });
            console.log("✅ compressImage retornou:", compressResult);

            // Log das informações de compressão
            logCompressionInfo(compressResult);
            uploadUri = compressResult.uri;
          } catch (compressionError: any) {
            console.warn(
              "⚠️ Compressão falhou, usando imagem original:",
              compressionError,
            );
            console.error(
              "❌ Erro de compressão (stack):",
              compressionError.stack,
            );
            console.log(
              "📸 Usando imagem original:",
              asset.uri,
              "Tamanho desconhecido",
            );
          }
          */

          // Criar FormData para upload
          const formData = new FormData();
          const filename = uploadUri.split("/").pop() || "photo.jpg";

          // No web, converter blob; no mobile, usar uri
          if (Platform.OS === "web") {
            // Para web, fazer fetch da imagem e converter para blob
            const response = await fetch(uploadUri);
            const blob = await response.blob();
            formData.append("foto", blob, filename);
          } else {
            // Para mobile, usar uri diretamente
            formData.append("foto", {
              uri: uploadUri,
              type: uploadType,
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
        } catch (error: any) {
          console.error("Erro ao processar foto:", error);
          Alert.alert(
            "Erro",
            error.message || "Erro ao processar foto. Tente novamente.",
          );
        } finally {
          setUpdatingPhoto(false);
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

      const apiModalidades = normalizeApiModalidades(
        Array.isArray(rankingData?.modalidades) ? rankingData.modalidades : [],
      );
      const profileModalidades = normalizeProfileModalidades(
        userProfile?.ranking_modalidades || [],
      );
      const mergedModalidades = mergeModalidades(
        apiModalidades,
        profileModalidades,
        rankingModalidades,
      );

      if (mergedModalidades.length > 0) {
        setRankingModalidades(mergedModalidades);
        if (!selectedModalidadeId) {
          setSelectedModalidadeId(mergedModalidades[0].id);
        }
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
          <Feather name="refresh-cw" size={20} color="#fff" />
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
                    uri: `${apiUrl}${userProfile.foto_caminho}`,
                  }}
                  style={styles.photoImage}
                  onError={(error) => {
                    console.error(
                      "❌ Erro ao carregar foto_caminho:",
                      `${apiUrl}${userProfile.foto_caminho}`,
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
                <Text style={styles.photoInitials}>
                  {getInitials(userProfile.nome)}
                </Text>
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
              <Feather name="chevron-right" size={16} color="#fff" />
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
                <Text style={styles.statLabel}>Check-ins</Text>
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
          {rankingModalidades.length > 0 && (
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
                        {item.usuario.foto_caminho && apiUrl ? (
                          <Image
                            source={{
                              uri: `${apiUrl}${item.usuario.foto_caminho}`,
                            }}
                            style={styles.rankingAvatarImage}
                          />
                        ) : (
                          <View style={styles.rankingAvatarPlaceholder}>
                            <MaterialCommunityIcons
                              name="account"
                              size={20}
                              color="#9ca3af"
                            />
                          </View>
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

        {/* Change Password Button */}
        <TouchableOpacity
          style={styles.changePasswordButton}
          onPress={() => setShowRecoveryModal(true)}
        >
          <Feather name="key" size={20} color="#fff" />
          <Text style={styles.changePasswordText}>Alterar Senha</Text>
        </TouchableOpacity>
      </ScrollView>

      {/* Password Recovery Modal */}
      <PasswordRecoveryModal
        visible={showRecoveryModal}
        onClose={() => setShowRecoveryModal(false)}
      />

      {/* Debug Logs Viewer - Apenas em Desenvolvimento */}
      {__DEV__ && <DebugLogsViewer />}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.backgroundSecondary,
  },
  headerTop: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 18,
    paddingVertical: 16,
    backgroundColor: colors.primary,
    borderBottomWidth: 0,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.2,
    shadowRadius: 6,
    elevation: 8,
  },
  headerTitle: {
    fontSize: 24,
    fontWeight: "800",
    color: "#fff",
  },
  refreshButton: {
    padding: 8,
  },
  scrollContent: {
    padding: 18,
    paddingBottom: 48,
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
    borderRadius: 20,
    padding: 26,
    alignItems: "center",
    marginBottom: 20,
    borderWidth: 1,
    borderColor: "#f0f1f4",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.08,
    shadowRadius: 12,
    elevation: 4,
  },
  photoWrapper: {
    position: "relative",
    width: 112,
    height: 112,
    marginBottom: 18,
  },
  photoContainer: {
    width: 112,
    height: 112,
    borderRadius: 56,
    backgroundColor: "#f3f4f6",
    justifyContent: "center",
    alignItems: "center",
    overflow: "hidden",
    borderWidth: 2,
    borderColor: "#fff",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.12,
    shadowRadius: 8,
    elevation: 4,
  },
  photoImage: {
    width: "100%",
    height: "100%",
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
    bottom: -2,
    right: -2,
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: colors.primary,
    justifyContent: "center",
    alignItems: "center",
    borderWidth: 3,
    borderColor: "#fff",
    zIndex: 10,
  },
  photoInitials: {
    fontSize: 36,
    fontWeight: "800",
    color: "#fff",
  },
  userName: {
    fontSize: 28,
    fontWeight: "800",
    color: colors.text,
    marginBottom: 4,
  },
  userEmail: {
    fontSize: 16,
    color: colors.textSecondary,
    marginBottom: 10,
  },
  tenantName: {
    fontSize: 12,
    color: "#fff",
    fontWeight: "700",
  },
  tenantButton: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 14,
    paddingVertical: 9,
    marginTop: 4,
    backgroundColor: colors.primary,
    borderRadius: 999,
  },
  statisticsSection: {
    marginBottom: 20,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: "800",
    color: colors.text,
    marginBottom: 16,
  },
  statsGrid: {
    flexDirection: "row",
    gap: 12,
    marginBottom: 12,
  },
  statCard: {
    flex: 1,
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 14,
    alignItems: "center",
    borderWidth: 1,
    borderColor: "#f0f1f4",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.06,
    shadowRadius: 10,
    elevation: 2,
  },
  statLabel: {
    fontSize: 13,
    color: colors.textMuted,
    marginTop: 8,
    marginBottom: 4,
    textAlign: "center",
  },
  statValue: {
    fontSize: 21,
    fontWeight: "800",
    color: colors.primaryDark,
  },
  lastCheckinCard: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 14,
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    borderWidth: 1,
    borderColor: "#f0f1f4",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.06,
    shadowRadius: 10,
    elevation: 2,
  },
  lastCheckinContent: {
    flex: 1,
  },
  lastCheckinLabel: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 2,
  },
  lastCheckinValue: {
    fontSize: 16,
    fontWeight: "700",
    color: colors.text,
  },
  infoSection: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 18,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: "#f0f1f4",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.06,
    shadowRadius: 10,
    elevation: 2,
  },
  infoItem: {
    flexDirection: "row",
    alignItems: "flex-start",
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#eef2f7",
    gap: 12,
  },
  infoContent: {
    flex: 1,
  },
  infoLabel: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 4,
  },
  infoValue: {
    fontSize: 16,
    fontWeight: "600",
    color: colors.text,
  },
  membershipSection: {
    marginBottom: 20,
  },
  membershipCard: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: "#f0f1f4",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.06,
    shadowRadius: 10,
    elevation: 2,
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
    borderTopColor: "#eef2f7",
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
    fontSize: 13,
    color: colors.textMuted,
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
    gap: 10,
    marginBottom: 14,
  },
  modalidadeChip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 999,
    backgroundColor: "#fff2e6",
    borderWidth: 1,
    borderColor: colors.primary + "50",
  },
  modalidadeChipActive: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  modalidadeChipContent: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  modalidadeChipText: {
    fontSize: 14,
    color: colors.primaryDark,
    fontWeight: "700",
  },
  modalidadeChipTextActive: {
    color: "#fff",
  },
  rankingCard: {
    backgroundColor: "#fff7ed",
    borderRadius: 18,
    padding: 18,
    borderWidth: 1,
    borderColor: colors.primary + "35",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.1,
    shadowRadius: 12,
    elevation: 4,
  },
  rankingList: {
    gap: 10,
  },
  rankingListItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    backgroundColor: "#fff",
    borderRadius: 14,
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderWidth: 1,
    borderColor: colors.primary + "25",
  },
  rankingPosition: {
    width: 46,
    height: 38,
    borderRadius: 12,
    backgroundColor: colors.primary,
    borderWidth: 1,
    borderColor: colors.primary,
    alignItems: "center",
    justifyContent: "center",
  },
  rankingPositionNumber: {
    fontSize: 14,
    fontWeight: "800",
    color: "#fff",
  },
  rankingAvatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: "#f3f4f6",
    justifyContent: "center",
    alignItems: "center",
    overflow: "hidden",
  },
  rankingAvatarImage: {
    width: 36,
    height: 36,
    borderRadius: 18,
  },
  rankingAvatarPlaceholder: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: "#e5e7eb",
    justifyContent: "center",
    alignItems: "center",
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
    fontSize: 15,
    fontWeight: "700",
    color: "#111827",
  },
  rankingCheckins: {
    fontSize: 13,
    color: colors.textSecondary,
    marginTop: 2,
  },
  rankingLoading: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingVertical: 8,
  },
  rankingLoadingText: {
    fontSize: 13,
    color: "#6b7280",
  },
  rankingErrorText: {
    fontSize: 13,
    color: "#b91c1c",
  },
  rankingDivider: {
    height: 1,
    backgroundColor: colors.primary + "30",
    marginVertical: 14,
  },
  rankingUserRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  rankingUserLabel: {
    fontSize: 13,
    color: colors.textSecondary,
    marginBottom: 2,
  },
  rankingUserName: {
    fontSize: 17,
    fontWeight: "700",
    color: "#111827",
  },
  rankingUserPosition: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 12,
    backgroundColor: colors.primary,
    borderWidth: 1,
    borderColor: colors.primary,
  },
  rankingUserPositionText: {
    fontSize: 14,
    fontWeight: "800",
    color: "#fff",
  },
  academiaCard: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    borderWidth: 1,
    borderColor: "#f0f1f4",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.06,
    shadowRadius: 10,
    elevation: 2,
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
    fontSize: 16,
    fontWeight: "700",
    color: colors.text,
    marginBottom: 10,
  },
  academiaInfo: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    marginBottom: 6,
  },
  academiaInfoText: {
    fontSize: 13,
    color: colors.textSecondary,
  },
  contratoCard: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: "#f0f1f4",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.06,
    shadowRadius: 10,
    elevation: 2,
  },
  contratoHeader: {
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 16,
    gap: 12,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#eef2f7",
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
    borderBottomColor: "#eef2f7",
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
  changePasswordButton: {
    backgroundColor: colors.primary,
    borderRadius: 16,
    paddingVertical: 16,
    flexDirection: "row",
    justifyContent: "center",
    alignItems: "center",
    gap: 10,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.12,
    shadowRadius: 10,
    elevation: 3,
  },
  changePasswordText: {
    color: "#fff",
    fontSize: 17,
    fontWeight: "700",
  },
});
