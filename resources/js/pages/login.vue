<script setup>
import TwoFactorAuthModal from "@/components/TwoFactorAuthModal.vue";
import AuthProvider from "@/views/pages/authentication/AuthProvider.vue";
import authV1BottomShape from "@images/svg/auth-v1-bottom-shape.svg?raw";
import authV1TopShape from "@images/svg/auth-v1-top-shape.svg?raw";
import { VNodeRenderer } from "@layouts/components/VNodeRenderer";
import { themeConfig } from "@themeConfig";

import axios from "axios";
import { ref } from "vue";
import { useRouter } from "vue-router";

definePage({
  meta: {
    layout: "blank",
    public: true,
  },
});

const form = ref({
  login: "",
  password: "",
  remember: false,
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

    const response = await axios.post("/api/login", formData);
    const data = response.data;

    if (data.two_factor) {
      twoFactorData.value.needsQrSetup = data.needs_qr_setup;
      twoFactorData.value.qrCodeUrl = data.qr_code_url;
      is2FAModalVisible.value = true;
    } else if (data.redirect) {
      window.location.href = data.redirect;
    } else {
      errors.value.general = "Respuesta inesperada del servidor.";
    }
  } catch (error) {
  } finally {
    isLoading.value = false;
  }
};
const on2FAVerified = () => {
  window.location.href = "/";
};
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <div class="position-relative my-sm-16">
      <VNodeRenderer
        :nodes="h('div', { innerHTML: authV1TopShape })"
        class="text-primary auth-v1-top-shape d-none d-sm-block"
      />
      <VNodeRenderer
        :nodes="h('div', { innerHTML: authV1BottomShape })"
        class="text-primary auth-v1-bottom-shape d-none d-sm-block"
      />

      <VCard
        class="auth-card"
        max-width="460"
        :class="$vuetify.display.smAndUp ? 'pa-6' : 'pa-0'"
      >
        <VCardItem class="justify-center">
          <VCardTitle>
            <RouterLink to="/">
              <div class="app-logo">
                <VNodeRenderer :nodes="themeConfig.app.logo" />
                <h1 class="app-logo-title">
                  {{ themeConfig.app.title }}
                </h1>
              </div>
            </RouterLink>
          </VCardTitle>
        </VCardItem>

        <VCardText>
          <h4 class="text-h4 mb-1">
            ¡Bienvenido a
            <span class="text-capitalize">{{ themeConfig.app.title }}</span
            >! 👋🏻
          </h4>
          <p class="mb-0">Por favor, inicia sesión en tu cuenta.</p>
        </VCardText>

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
                  label="Email o Usuario"
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

                <div
                  class="d-flex align-center justify-space-between flex-wrap my-6"
                >
                  <VCheckbox v-model="form.remember" label="Recordarme" />
                  <RouterLink class="text-primary" :to="{ name: 'login' }">
                    ¿Olvidaste tu contraseña?
                  </RouterLink>
                </div>

                <VBtn
                  block
                  type="submit"
                  :loading="isLoading"
                  :disabled="isLoading"
                >
                  Ingresar
                </VBtn>
              </VCol>

              <VCol cols="12" class="text-body-1 text-center">
                <span class="d-inline-block">
                  ¿Nuevo en nuestra plataforma?
                </span>
                <RouterLink
                  class="text-primary ms-1 d-inline-block text-body-1"
                  :to="{ name: 'login' }"
                >
                  Crea una cuenta
                </RouterLink>
              </VCol>
              <VCol cols="12" class="d-flex align-center">
                <VDivider />
                <span class="mx-4 text-high-emphasis">o</span>
                <VDivider />
              </VCol>
              <VCol cols="12" class="text-center">
                <AuthProvider />
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
      @verified="on2FAVerified"
    />
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
