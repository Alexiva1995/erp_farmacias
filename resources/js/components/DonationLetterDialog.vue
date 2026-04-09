<script setup>
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  loading: { type: Boolean, default: false },
  initialProducts: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue", "generate"]);

const institutionName = ref("");
const donationProducts = ref([]);

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      donationProducts.value = JSON.parse(
        JSON.stringify(props.initialProducts)
      );
      institutionName.value = "";
    }
  }
);

const donationHeaders = [
  { title: "Producto", key: "product_name" },
  { 
    title: "Laboratorio", 
    key: "laboratory_name", 
    sortable: false,
    value: (item) => item.product?.laboratory?.name || "—"
  },
  { title: "Unds.", key: "expired_quantity", align: "end" },
  { title: "Acción", key: "actions", sortable: false, align: "center" },
];

const discardProduct = (productToDiscard) => {
  donationProducts.value = donationProducts.value.filter(
    (p) => p.id !== productToDiscard.id
  );
  toast.success(
    `"${productToDiscard.product_name}" descartado de la donación.`
  );
};

const handleGenerate = () => {
  if (!institutionName.value.trim()) {
    toast.warning("Por favor, ingrese el nombre de la institución.");
    return;
  }
  if (donationProducts.value.length === 0) {
    toast.warning("No se puede generar una donación sin productos.");
    return;
  }
  emit("generate", {
    institution: institutionName.value,
    products: donationProducts.value,
  });
};

const closeDialog = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="850px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    :fullscreen="$vuetify.display.smAndDown"
    transition="dialog-bottom-transition"
  >
    <VCard class="donation-dialog">
      <!-- Cabecera con Gradiente Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient px-6 py-5 d-flex align-center">
          <div class="d-flex align-center">
            <VAvatar color="rgba(255,255,255,0.2)" size="44" class="me-4 rounded-lg">
              <VIcon icon="tabler-file-text" color="white" size="24" />
            </VAvatar>
            <div>
              <h3 class="text-h5 font-weight-bold text-white mb-0">Generar Carta de Donación</h3>
              <p class="text-caption text-white opacity-75 mb-0">Documentación oficial de entrega</p>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            @click="closeDialog"
            class="rounded-circle"
          >
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-0" style="overflow-y: auto;">
        <div class="pa-6">
          <!-- Sección de Institución -->
          <div class="mb-8">
            <div class="d-flex align-center mb-4">
              <div class="section-badge me-3">1</div>
              <h4 class="text-h6 font-weight-bold color-primary-dark">Información de la Institución</h4>
            </div>
            <VCard variant="outlined" class="pa-4 bg-var-theme-background border-dashed rounded-xl">
              <AppTextField
                v-model="institutionName"
                label="Nombre de la Institución Receptora"
                placeholder="Ej. Fundación Hospital de Niños"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="tabler-building-estate"
                autofocus
              />
            </VCard>
          </div>

          <!-- Sección de Productos -->
          <div>
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center">
                <div class="section-badge me-3">2</div>
                <h4 class="text-h6 font-weight-bold color-primary-dark">Productos a Donar</h4>
              </div>
              <VChip color="primary" variant="tonal" size="small" class="font-weight-bold px-3">
                {{ donationProducts.length }} ITEMS SELECCIONADOS
              </VChip>
            </div>

            <!-- Vista de Escritorio (Tabla) -->
            <div class="d-none d-md-block">
              <VDataTable
                :headers="donationHeaders"
                :items="donationProducts"
                class="donation-table elevation-0 rounded-xl overflow-hidden border"
                no-data-text="No hay productos seleccionados."
                hide-default-footer
              >
                <template #item.product_name="{ item }">
                  <div class="py-2">
                    <p class="font-weight-black mb-0 text-primary">{{ item.product_name?.toUpperCase() }}</p>
                    <p class="text-xs text-medium-emphasis mb-0">{{ item.lot_number || 'S/L' }}</p>
                  </div>
                </template>

                <template #item.laboratory_name="{ item }">
                  <VChip size="x-small" label class="text-uppercase font-weight-bold px-2">
                    {{ item.product?.laboratory?.name || "—" }}
                  </VChip>
                </template>

                <template #item.expired_quantity="{ item }">
                  <span class="text-h6 font-weight-black">{{ item.expired_quantity }}</span>
                </template>

                <template #item.actions="{ item }">
                  <IconBtn
                    color="error"
                    variant="tonal"
                    size="small"
                    @click="discardProduct(item)"
                    class="rounded-lg"
                  >
                    <VIcon icon="tabler-trash-x" />
                  </IconBtn>
                </template>
              </VDataTable>
            </div>

            <!-- Vista de Móvil (Tarjetas) -->
            <div class="d-block d-md-none">
              <div v-if="donationProducts.length === 0" class="text-center py-8 opacity-50">
                <VIcon icon="tabler-package-off" size="48" class="mb-2" />
                <p>No hay productos para mostrar</p>
              </div>
              <div v-else class="d-flex flex-column gap-3">
                <VCard
                  v-for="item in donationProducts"
                  :key="item.id"
                  variant="outlined"
                  class="mobile-product-card rounded-xl border-dashed-thin"
                >
                  <div class="pa-4">
                    <div class="d-flex justify-space-between align-start mb-2">
                      <div class="flex-grow-1 pe-2">
                        <p class="text-subtitle-1 font-weight-black text-primary mb-1 line-height-tight">
                          {{ item.product_name?.toUpperCase() }}
                        </p>
                        <div class="d-flex align-center">
                          <VIcon icon="tabler-flask" size="14" class="me-1 text-medium-emphasis" />
                          <span class="text-caption font-weight-medium text-medium-emphasis">
                            {{ item.product?.laboratory?.name || "SIN LAB" }}
                          </span>
                        </div>
                      </div>
                      <IconBtn
                        color="error"
                        variant="tonal"
                        size="small"
                        @click="discardProduct(item)"
                        class="rounded-lg ms-2"
                      >
                        <VIcon icon="tabler-trash-x" size="20" />
                      </IconBtn>
                    </div>
                    
                    <div class="d-flex justify-space-between align-center mt-3 bg-light rounded-lg pa-2">
                      <span class="text-xs text-uppercase font-weight-bold text-disabled ps-2">Cantidad</span>
                      <div class="d-flex align-center">
                        <span class="text-h5 font-weight-black pe-2">{{ item.expired_quantity }}</span>
                        <VChip size="x-small" color="secondary" label font-weight-bold>UNDS</VChip>
                      </div>
                    </div>
                  </div>
                </VCard>
              </div>
            </div>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <!-- Acciones de Pie de Página -->
      <VCardActions class="pa-6 bg-var-theme-background">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="closeDialog"
          class="rounded-xl px-6"
          :class="{ 'flex-grow-1': $vuetify.display.smAndDown }"
          height="50"
        >
          <VIcon icon="tabler-arrow-left" class="me-2" />
          Cancelar
        </VBtn>
        <VSpacer v-if="!$vuetify.display.smAndDown" />
        <VBtn
          color="primary"
          variant="flat"
          @click="handleGenerate"
          class="rounded-xl px-8 elevation-4"
          :class="{ 'flex-grow-1': $vuetify.display.smAndDown, 'ms-4': $vuetify.display.smAndDown }"
          height="50"
          :loading="props.loading"
        >
          <VIcon icon="tabler-file-check" class="me-2" />
          Generar Carta
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
  color: white;
}

.section-badge {
  width: 28px;
  height: 28px;
  background: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
  border-radius: 8px;
  display: flex;
  align-center: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.9rem;
}

.color-primary-dark {
  color: #1e3a8a;
}

.donation-table :deep(thead) {
  background-color: rgba(var(--v-theme-primary), 0.03);
}

.donation-table :deep(th) {
  font-weight: 700 !important;
  text-transform: uppercase;
  font-size: 0.75rem !important;
  color: #64748b !important;
}

.line-height-tight {
  line-height: 1.2;
}

.mobile-product-card {
  transition: all 0.2s ease;
  background-color: white;
  border-color: rgba(0,0,0,0.08) !important;
}

.mobile-product-card:active {
  transform: scale(0.98);
  background-color: rgba(var(--v-theme-primary), 0.02);
}

.bg-light {
  background-color: #f8fafc;
}

.border-dashed-thin {
  border-style: dashed !important;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

@media (max-width: 600px) {
  .donation-dialog {
    border-radius: 0 !important;
  }
}
</style>

