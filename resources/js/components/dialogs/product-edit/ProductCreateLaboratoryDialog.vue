<script setup>
import { computed, ref } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
});

const emit = defineEmits(["update:modelValue", "created"]);

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings?.business_type === "restaurant");

const newLabName = ref("");
const isSavingLab = ref(false);

const closeDialog = () => {
  emit("update:modelValue", false);
  newLabName.value = "";
};

const createLaboratory = async () => {
  if (!newLabName.value.trim()) return;

  isSavingLab.value = true;
  try {
    const response = await axios.post("/laboratories", {
      name: newLabName.value,
    });

    toast.success("Laboratorio / Marca creada con éxito");
    emit("created", response.data.laboratory);
    closeDialog();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      toast.error(error.response.data.errors?.name?.[0] || "Error de validación");
    } else {
      toast.error("Error al crear el laboratorio / marca");
    }
  } finally {
    isSavingLab.value = false;
  }
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="450px"
    transition="dialog-bottom-transition"
    @update:model-value="closeDialog"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="32"
            class="me-3 elevation-1 rounded-lg"
          >
            <VIcon
              icon="tabler-flask-2"
              size="18"
              class="modal-avatar-icon"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-subtitle-1 font-weight-black leading-tight mb-0 uppercase">
              {{ isRestaurant ? 'Nueva Marca' : 'Nuevo Laboratorio' }}
            </h2>
            <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">Registro Maestro</span>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="x-small"
            class="rounded-lg"
            @click="closeDialog"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-6 bg-light">
        <VCard variant="flat" class="pa-5 bg-white rounded-xl border shadow-sm">
          <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">
            {{ isRestaurant ? 'Nombre Oficial de la Marca' : 'Nombre Oficial del Laboratorio' }}
          </span>
          <VTextField
            v-model="newLabName"
            :placeholder="isRestaurant ? 'EJ: MARCA NESTLÉ' : 'EJ: LABORATORIOS GOVIMAR'"
            variant="outlined"
            density="comfortable"
            autofocus
            hide-details="auto"
            class="rounded-lg font-weight-black"
            @keydown.enter="createLaboratory"
          />
        </VCard>
      </VCardText>

      <VCardActions class="pa-4 bg-surface border-t px-6">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6">
            <VBtn
              color="secondary"
              variant="tonal"
              height="44"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="closeDialog"
            >
              Cerrar
            </VBtn>
          </VCol>
          <VCol cols="6">
            <VBtn
              color="primary"
              variant="flat"
              height="44"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="isSavingLab"
              :disabled="!newLabName.trim()"
              @click="createLaboratory"
            >
              Guardar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-secondary)) 0%, rgb(var(--v-theme-primary)) 100%) !important;
}

.modal-avatar-icon {
  color: #7A0099 !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.text-button {
  font-size: 0.875rem !important;
  letter-spacing: 1px !important;
}

.uppercase {
  text-transform: uppercase;
}
</style>
