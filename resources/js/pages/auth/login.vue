<script setup>
import AuthProvider from "@/views/pages/authentication/AuthProvider.vue";
import authV1BottomShape from "@images/svg/auth-v1-bottom-shape.svg?raw";
import authV1TopShape from "@images/svg/auth-v1-top-shape.svg?raw";
import { VNodeRenderer } from "@layouts/components/VNodeRenderer";
import { themeConfig } from "@themeConfig";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { onMounted, ref } from "vue";

const brandingStore = useBrandingStore();

definePage({
  meta: {
    layout: "blank",
    public: true,
  },
});

onMounted(async () => {
  await brandingStore.fetchSettings();
});

const form = ref({
  email: "",
  password: "",
  remember: false,
});

const isPasswordVisible = ref(false);
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

      <!-- 👉 Auth Card -->
      <VCard
        class="auth-card"
        max-width="460"
        :class="$vuetify.display.smAndUp ? 'pa-6' : 'pa-0'"
      >
        <VCardItem class="justify-center">
          <VCardTitle>
            <RouterLink to="/">
              <div class="app-logo">
                <img
                  :src="brandingStore.settings.app_logo || '/logo.svg'"
                  alt="logo"
                  style="max-height: 80px; max-width: 100%; object-fit: contain;"
                >
              </div>
            </RouterLink>
          </VCardTitle>
        </VCardItem>

        <VCardText>
          <h4 class="text-h4 mb-1">
            Welcome to
            <span class="text-capitalize">{{ themeConfig.app.title }}</span
            >! 👋🏻
          </h4>
          <p class="mb-0">
            Please sign-in to your account and start the adventure
          </p>
        </VCardText>

        <VCardText>
          <VForm @submit.prevent="() => {}">
            <VRow>
              <!-- email -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.email"
                  autofocus
                  label="Email or Username"
                  type="email"
                  placeholder="johndoe@email.com"
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.password"
                  label="Password"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="password"
                  :append-inner-icon="
                    isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'
                  "
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />

                <!-- remember me checkbox -->
                <div
                  class="d-flex align-center justify-space-between flex-wrap my-6"
                >
                  <VCheckbox v-model="form.remember" label="Remember me" />

                  <RouterLink class="text-primary" :to="{ name: 'login' }">
                    Forgot Password?
                  </RouterLink>
                </div>

                <!-- login button -->
                <VBtn block color="primary" type="submit"> Login </VBtn>
              </VCol>

              <!-- create account -->
              <VCol cols="12" class="text-body-1 text-center">
                <span class="d-inline-block"> New on our platform? </span>
                <RouterLink
                  class="text-primary ms-1 d-inline-block text-body-1"
                  :to="{ name: 'login' }"
                >
                  Create an account
                </RouterLink>
              </VCol>

              <VCol cols="12" class="d-flex align-center">
                <VDivider />
                <span class="mx-4 text-high-emphasis">or</span>
                <VDivider />
              </VCol>

              <!-- auth providers -->
              <VCol cols="12" class="text-center">
                <AuthProvider />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </div>
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
    width: 90% !important;
    max-width: 546px !important;
    min-width: 320px !important;
  }
}

.app-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  
  img {
    height: 100px !important;
    max-width: 350px !important;
    width: auto !important;
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
