<script setup>
import axios from "axios";
import { computed, ref } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  needsQrSetup: {
    type: Boolean,
    default: false,
  },
  qrCodeUrl: {
    type: String,
    default: null,
  },
  qrCodeSecret: {
    type: String,
    default: null,
  },
});

const emit = defineEmits(["update:modelValue", "verified"]);

const code = ref("");
const isLoading = ref(false);
const error = ref("");
const isCopied = ref(false);

const copySecretKey = async () => {
  if (!props.qrCodeSecret) return;
  try {
    await navigator.clipboard.writeText(props.qrCodeSecret);
    isCopied.value = true;
    setTimeout(() => {
      isCopied.value = false;
    }, 3000);
  } catch (err) {
    console.error("No se pudo copiar el texto: ", err);
  }
};

const isDialogVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const handleClose = () => {
  isDialogVisible.value = false;
  code.value = "";
  error.value = "";
  isLoading.value = false;
};

const handleSubmit = async () => {
  isLoading.value = true;
  error.value = "";

  try {
    await axios.post("/api/two-factor-challenge", {
      code: code.value,
    });

    emit("verified");
    handleClose();
  } catch (err) {
    if (err.response && err.response.status === 422) {
      error.value =
        err.response.data.errors?.code?.[0] ||
        "El código proporcionado no es válido.";
    } else {
      error.value = "Ocurrió un error inesperado. Inténtalo de nuevo.";
      console.error("Error en la verificación 2FA:", err);
    }
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <VDialog v-model="isDialogVisible" max-width="550" persistent>
    <VCard>
      <VCardItem class="py-4">
        <VCardTitle class="text-h5"> Verificación de dos factores </VCardTitle>
      </VCardItem>

      <VCardText>
        <!-- Mensaje de error -->
        <VAlert v-if="error" type="error" variant="tonal" class="mb-4">
          {{ error }}
        </VAlert>

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <!-- Lado izquierdo: Instrucciones y Formulario -->
            <VCol cols="12" :md="props.needsQrSetup ? 6 : 12">
              <p v-if="props.needsQrSetup" class="mb-4">
                Escanea este código QR con tu aplicación de autenticación y
                luego ingresa el código generado.
              </p>
              <p v-else>
                Por favor, ingresa el código de verificación de tu aplicación de
                autenticación.
              </p>

              <AppTextField
                v-model="code"
                autofocus
                label="Código de Verificación"
                placeholder="123456"
                class="mb-4"
              />

              <VBtn
                block
                type="submit"
                :loading="isLoading"
                :disabled="isLoading"
              >
                Verificar
              </VBtn>
            </VCol>

            <!-- Lado derecho: Código QR (si es necesario) -->
            <VCol
              v-if="props.needsQrSetup && props.qrCodeUrl"
              cols="12"
              md="6"
              class="d-flex flex-column align-center justify-center"
            >
              <p class="text-body-2 mb-2">Escanear Código</p>
              <img
                :src="props.qrCodeUrl"
                alt="Código QR de autenticación"
                style="
                  padding: 5px;
                  border: 1px solid #ddd;
                  block-size: 180px;
                  inline-size: 180px;
                "
                class="mb-3"
              />
              <VBtn
                v-if="props.qrCodeSecret"
                size="small"
                variant="tonal"
                color="primary"
                @click="copySecretKey"
              >
                <VIcon start icon="tabler-copy" />
                {{ isCopied ? '¡Copiado!' : 'Copiar Clave Manual' }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions class="mt-2">
        <VSpacer />
        <VBtn color="secondary" text @click="handleClose"> Cancelar </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
