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
  { title: "ID", key: "product_id", sortable: false, width: "80px", cellClass: "font-weight-black text-primary" },
  { title: "PRODUCTO", key: "product_name", sortable: false, width: "45%" },
  { title: "# LOTE", key: "lot_number", align: "center", sortable: false },
  { title: "CANT. DONADA", key: "expired_quantity", align: "center", sortable: false },
  { title: "ACCIÓN", key: "actions", sortable: false, align: "center", width: "80px" },
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
    <VCard class="donation-dialog rounded-lg overflow-hidden">
      <!-- Cabecera con Gradiente Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient px-4 py-3 d-flex align-center">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="36" class="me-3 elevation-1">
              <VIcon icon="tabler-file-text" color="primary" size="20" />
            </VAvatar>
            <div>
              <h3 class="text-subtitle-1 font-weight-black text-white mb-0" style="color: white !important;">Generar Carta de Donación</h3>
              <p class="text-caption text-white opacity-75 mb-0" style="color: white !important;">Documentación oficial de entrega</p>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon
            variant="text"
            color="white"
            size="small"
            density="compact"
            @click="closeDialog"
          >
            <VIcon size="20">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-0" style="overflow-y: auto;">
        <div class="pa-4">
          <!-- Sección de Institución -->
          <div class="mb-4">
            <div class="d-flex align-center mb-2">
              <VIcon icon="tabler-building" size="18" class="text-primary me-2" />
              <h4 class="text-subtitle-2 font-weight-black text-uppercase">Información de la Institución</h4>
            </div>
            <VCard variant="flat" class="pa-3 bg-light border rounded-lg">
              <AppTextField
                v-model="institutionName"
                label="Nombre de la Institución Receptora"
                placeholder="Ej. Fundación Hospital de Niños"
                variant="outlined"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-building-estate"
                autofocus
              />
            </VCard>
          </div>

          <!-- Sección de Productos -->
          <div>
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="d-flex align-center">
                <VIcon icon="tabler-package" size="18" class="text-primary me-2" />
                <h4 class="text-subtitle-2 font-weight-black text-uppercase">Productos a Donar</h4>
              </div>
              <VChip color="primary" variant="tonal" size="small" class="font-weight-black">
                {{ donationProducts.length }} ITEMS SELECCIONADOS
              </VChip>
            </div>

            <!-- Vista de Escritorio (Tabla) -->
            <div class="d-none d-md-block">
              <VDataTable
                :headers="donationHeaders"
                :items="donationProducts"
                class="elevation-0 rounded-lg overflow-hidden border"
                density="compact"
                no-data-text="No hay productos seleccionados."
                hide-default-footer
              >
                <!-- ID con enlace a trazabilidad -->
                <template #item.product_id="{ item }">
                  <a
                    :href="'/inventory/traceability?q=' + (item.product?.id || item.product_id)"
                    target="_blank"
                    class="text-decoration-none font-weight-black text-primary"
                  >
                    {{ item.product?.id || item.product_id }}
                  </a>
                </template>

                <!-- PRODUCTO: formato unificado igual a inventario -->
                <template #item.product_name="{ item }">
                  <div class="d-flex align-center gap-x-2 py-1">
                    <div class="d-flex flex-column min-width-0">
                      <span
                        class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
                        :class="{ 
                          'text-warning': item.product?.psychotropic == 1 || item.product?.psychotropic === true 
                        }"
                        style="max-inline-size: 360px;"
                        :title="item.product_name || item.product?.name"
                      >
                        {{ (item.product_name || item.product?.name || '—').toUpperCase() }}
                        <span v-if="item.product?.iva == 1 || item.product?.iva === true" class="text-xs text-disabled"> (G)</span>
                        <span v-if="item.product?.is_colombian_origin == 1 || item.product?.is_colombian_origin === true" class="text-xs text-disabled"> (COL)</span>
                      </span>
                      <div class="d-flex align-center gap-1 text-super-xs">
                        <span class="text-disabled truncate" style="max-inline-size: 160px;">
                          {{ item.product?.active_ingredient || item.product?.presentation || "Sin principio" }}
                        </span>
                        <span class="text-disabled mx-1">|</span>
                        <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 140px;">
                          {{ item.product?.laboratory?.name || 'S/L' }}
                        </span>
                      </div>
                    </div>
                  </div>
                </template>

                <template #item.lot_number="{ item }">
                  <span class="font-weight-medium text-caption">{{ item.lot_number || "—" }}</span>
                </template>

                <template #item.expired_quantity="{ item }">
                  <VChip size="small" label variant="tonal" color="success" class="font-weight-black">
                    {{ Math.trunc(item.expired_quantity ?? 0) }}
                  </VChip>
                </template>

                <template #item.actions="{ item }">
                  <IconBtn
                    color="error"
                    variant="text"
                    size="small"
                    @click="discardProduct(item)"
                  >
                    <VIcon icon="tabler-trash" size="18" />
                    <VTooltip activator="parent">Quitar de donación</VTooltip>
                  </IconBtn>
                </template>
              </VDataTable>
            </div>

            <!-- Vista de Móvil (Tarjetas) -->
            <div class="d-block d-md-none">
              <div v-if="donationProducts.length === 0" class="text-center py-8 opacity-50">
                <VIcon icon="tabler-package-off" size="36" class="mb-2" />
                <p class="text-caption mb-0">No hay productos para mostrar</p>
              </div>
              <div v-else class="d-flex flex-column gap-2">
                <VCard
                  v-for="item in donationProducts"
                  :key="item.id"
                  variant="flat"
                  class="rounded-lg border overflow-hidden"
                >
                  <div class="pa-3">
                    <div class="d-flex justify-space-between align-start mb-1">
                      <div class="flex-grow-1 min-width-0 pe-2">
                        <div class="d-flex align-center gap-1 mb-1">
                          <a
                            :href="'/inventory/traceability?q=' + (item.product?.id || item.product_id)"
                            target="_blank"
                            class="text-decoration-none font-weight-black text-primary text-xs"
                          >
                            {{ item.product?.id || item.product_id }}
                          </a>
                          <span class="mx-1 text-disabled">|</span>
                          <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate mb-0">
                            {{ item.product_name || item.product?.name || '—' }}
                          </h3>
                        </div>
                        <div class="d-flex align-center flex-wrap gap-1 text-super-xs">
                          <span class="text-disabled truncate" style="max-inline-size: 140px;">
                            {{ item.product?.active_ingredient || item.product?.presentation || "Sin principio" }}
                          </span>
                          <span class="text-disabled mx-1">|</span>
                          <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 120px;">
                            {{ item.product?.laboratory?.name || "S/L" }}
                          </span>
                        </div>
                      </div>
                      <IconBtn
                        color="error"
                        variant="text"
                        size="small"
                        @click="discardProduct(item)"
                      >
                        <VIcon icon="tabler-trash" size="18" />
                      </IconBtn>
                    </div>
                    
                    <div class="d-flex justify-space-between align-center mt-2 bg-light rounded pa-2">
                      <span class="text-super-xs text-uppercase font-weight-bold text-disabled">Lote: {{ item.lot_number || '—' }}</span>
                      <VChip size="x-small" color="success" label variant="tonal" class="font-weight-black">
                        {{ Math.trunc(item.expired_quantity ?? 0) }}
                      </VChip>
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
      <VCardActions class="pa-4 bg-light border-t">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="closeDialog"
          class="rounded-lg font-weight-black"
          :class="{ 'flex-grow-1': $vuetify.display.smAndDown }"
          height="44"
        >
          <VIcon icon="tabler-arrow-left" class="me-2" />
          Cancelar
        </VBtn>
        <VSpacer v-if="!$vuetify.display.smAndDown" />
        <VBtn
          color="primary"
          variant="flat"
          @click="handleGenerate"
          class="rounded-lg font-weight-black elevation-2"
          :class="{ 'flex-grow-1': $vuetify.display.smAndDown, 'ms-3': $vuetify.display.smAndDown }"
          height="44"
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
  background: var(--brand-gradient) !important;
  color: white;
}

.text-super-xs {
  font-size: 0.72rem !important;
  line-height: 1.1;
}

.bg-light {
  background-color: #f8fafc !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>

