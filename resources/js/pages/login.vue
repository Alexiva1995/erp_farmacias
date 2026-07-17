<script setup>
import TwoFactorAuthModal from "@/components/TwoFactorAuthModal.vue";
import authV1BottomShape from "@images/svg/auth-v1-bottom-shape.svg?raw";
import authV1TopShape from "@images/svg/auth-v1-top-shape.svg?raw";
import { VNodeRenderer } from "@layouts/components/VNodeRenderer";
import { themeConfig } from "@themeConfig";

import { useBrandingStore } from "@/stores/useBrandingStore";
import axios from "@/plugins/axios";
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";

const brandingStore = useBrandingStore();

definePage({
  meta: {
    layout: "blank",
    public: true,
  },
});

onMounted(async () => {
  try {
    await brandingStore.fetchSettings();
  } catch (e) {
    // Silenciar error en el login
  }
});

const form = ref({
  login: "",
  password: "",
});

const isPasswordVisible = ref(false);

const router = useRouter();

const errors = ref({
  login: "",
  password: "",
  general: "",
});

const is2FAModalVisible = ref(false);
const twoFactorData = ref({
  needsQrSetup: false,
  qrCodeUrl: null,
  qrCodeSecret: null,
});

const isLoading = ref(false);

const handleLogin = async () => {
  errors.value = { login: "", password: "", general: "" };
  isLoading.value = true;

  try {
    const formData = {
      login: form.value.login,
      password: form.value.password,
    };

    const response = await axios.post("/login", formData);
    const data = response.data;

    if (data.two_factor) {
      twoFactorData.value.needsQrSetup = data.needs_qr_setup;
      twoFactorData.value.qrCodeUrl = data.qr_code_url;
      twoFactorData.value.qrCodeSecret = data.qr_code_secret;
      is2FAModalVisible.value = true;
    } else if (data.redirect) {
      window.location.href = data.redirect;
    } else {
      errors.value.general = "Respuesta inesperada del servidor.";
    }
  } catch (error) {
    // Manejar errores de validación (422)
    if (error.response?.status === 422) {
      const validationErrors = error.response.data?.errors || error.response.data;
      if (validationErrors.login) {
        errors.value.login = Array.isArray(validationErrors.login) 
          ? validationErrors.login[0] 
          : validationErrors.login;
      }
      if (validationErrors.password) {
        errors.value.password = Array.isArray(validationErrors.password) 
          ? validationErrors.password[0] 
          : validationErrors.password;
      }
      if (!errors.value.login && !errors.value.password && validationErrors.message) {
        errors.value.general = validationErrors.message;
      }
    } else if (error.response?.status === 429) {
      // Error de rate limiting
      errors.value.general = error.response.data?.message || "Demasiados intentos. Por favor, espera unos minutos.";
    } else {
      // Otros errores
      errors.value.general = error.response?.data?.message || "Error al iniciar sesión. Por favor, intenta de nuevo.";
    }
  } finally {
    isLoading.value = false;
  }
};
const on2FAVerified = () => {
  window.location.href = "/dashboard";
};
</script>

<template>
  <div class="auth-wrapper branding-auth-bg d-flex align-center justify-center pa-4">
    <div class="position-relative my-sm-16">
      <!-- Isotipos de TOVA como marca de agua de fondo -->
      <img
        src="/isotipo.svg"
        alt="tova-brand-shape-top"
        class="auth-v1-top-shape d-none d-sm-block"
      >
      <img
        src="/isotipo.svg"
        alt="tova-brand-shape-bottom"
        class="auth-v1-bottom-shape d-none d-sm-block"
      >

      <VCard
        class="auth-card login-card"
        :class="$vuetify.display.smAndUp ? 'pa-8' : 'pa-4'"
      >
        <VCardItem class="justify-center">
          <VCardTitle>
            <RouterLink to="/">
              <div class="app-logo">
                <img
                  src="/logo.png"
                  alt="logo"
                  style="max-height: 80px; max-width: 100%; object-fit: contain;"
                >
              </div>
            </RouterLink>
          </VCardTitle>
        </VCardItem>

        <VCardText>
          <VAlert
            v-if="errors.general"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errors.general }}
          </VAlert>

          <VForm @submit.prevent="handleLogin">
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="form.login"
                  autofocus
                  label="Email"
                  type="text"
                  placeholder="johndoe@email.com"
                  :error-messages="errors.login"
                />
              </VCol>

              <VCol cols="12">
                <AppTextField
                  v-model="form.password"
                  label="Contraseña"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="current-password"
                  :append-inner-icon="
                    isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'
                  "
                  :error-messages="errors.password"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />

                <VBtn
                  class="mt-6"
                  block
                  color="primary"
                  type="submit"
                  :loading="isLoading"
                  :disabled="isLoading"
                >
                  Ingresar
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </div>

    <TwoFactorAuthModal
      v-model="is2FAModalVisible"
      :needs-qr-setup="twoFactorData.needsQrSetup"
      :qr-code-url="twoFactorData.qrCodeUrl"
      :qr-code-secret="twoFactorData.qrCodeSecret"
      @verified="on2FAVerified"
    />
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";

.login-card {
  width: 546px !important;
  max-width: 546px !important;
  min-width: 546px !important;
}

// Asegurar que la tarjeta tenga el ancho correcto en todos los tamaños de pantalla
@media (max-width: 959px) {
  .login-card {
    width: 100% !important;
    max-width: 450px !important;
    min-width: 0 !important;
  }
}

.app-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  
  img {
    height: auto !important;
    max-height: 80px !important;
    max-width: 100% !important;
    width: auto !important;
    object-fit: contain !important;
  }
}

.branding-auth-bg {
  background: linear-gradient(135deg, #7A0099, #E20074) !important;
}

// Estilos premium de marcas de agua para isotipos de TOVA
.auth-v1-top-shape,
.auth-v1-bottom-shape {
  position: absolute;
  z-index: -1;
  width: 260px !important;
  height: auto !important;
  opacity: 0.15 !important;
  filter: brightness(0) invert(1); // Hacerlos blancos para contrastar sobre el gradiente
  pointer-events: none;
}

.auth-v1-top-shape {
  top: -80px !important;
  left: -80px !important;
  transform: rotate(-15deg);
}

.auth-v1-bottom-shape {
  bottom: -80px !important;
  right: -80px !important;
  transform: rotate(15deg);
}
</style>
