<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  employee: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

const { mobile } = useDisplay();

const closeDialog = () => {
  emit("update:modelValue", false);
};

const getProductColor = (index) => {
  const colors = [
    "success",
    "info",
    "warning",
    "secondary",
    "primary",
    "error",
  ];
  return colors[index % colors.length];
};

const hasProducts = computed(() => {
  return props.employee.products && props.employee.products.length > 0;
});
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    :max-width="mobile ? undefined : '600px'"
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    @update:model-value="closeDialog"
  >
    <VCard class="rounded-xl border-0 shadow-xl overflow-hidden d-flex flex-column">
      <!-- Header Premium -->
      <div class="premium-header pa-5 d-flex align-center">
        <div class="d-flex align-center gap-3">
          <VAvatar color="white" variant="tonal" size="40" class="rounded-lg">
            <VIcon icon="tabler-package" size="22" color="white" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-h6 font-weight-black text-white leading-none mb-1">Productos Asignados</span>
            <span class="text-xs text-white opacity-70 font-weight-medium">
              {{ props.employee.employee_name || 'Empleado' }}
            </span>
          </div>
        </div>
        <VSpacer />
        <VChip color="white" variant="tonal" size="small" class="font-weight-black me-3 rounded">
          {{ props.employee.products_count || 0 }} {{ (props.employee.products_count || 0) === 1 ? 'producto' : 'productos' }}
        </VChip>
        <VBtn icon="tabler-x" variant="text" color="white" size="small" class="rounded-lg bg-white-opacity-10" @click="closeDialog" />
      </div>

      <VDivider class="opacity-10" />

      <VCardText class="pa-6 flex-grow-1" style="max-block-size: 70vh; overflow-y: auto;">
        <!-- Info del Empleado -->
        <div class="d-flex align-center gap-3 mb-6 pa-4 rounded-xl bg-surface border shadow-sm">
          <VAvatar color="primary" variant="tonal" size="44" class="rounded font-weight-black">
            {{ props.employee.employee_name?.split(" ").map((n) => n[0]).join("").substring(0, 2) || "N/A" }}
          </VAvatar>
          <div class="flex-grow-1">
            <span class="text-sm font-weight-black text-high-emphasis d-block">{{ props.employee.employee_name }}</span>
            <span class="text-super-xs text-disabled uppercase font-weight-bold">{{ props.employee.identification }}</span>
          </div>
          <VChip
            :color="(props.employee.products_count || 0) > 0 ? 'success' : 'default'"
            size="x-small"
            variant="flat"
            class="font-weight-black rounded"
          >
            {{ props.employee.is_active ? 'ACTIVO' : 'INACTIVO' }}
          </VChip>
        </div>

        <!-- Lista de Productos -->
        <div v-if="hasProducts">
          <span class="text-super-xs font-weight-black text-disabled uppercase mb-3 d-block">Lista de Productos</span>
          <VCard variant="outlined" class="rounded-xl overflow-hidden border shadow-sm">
            <VList class="pa-0">
              <template
                v-for="(product, index) in props.employee.products"
                :key="product.id"
              >
                <VListItem class="px-4 py-3">
                  <template #prepend>
                    <VAvatar
                      :color="getProductColor(index)"
                      variant="tonal"
                      size="34"
                      class="rounded"
                    >
                      <VIcon icon="tabler-pill" size="18" />
                    </VAvatar>
                  </template>

                  <VListItemTitle class="text-xs font-weight-black text-high-emphasis">
                    {{ product.name }}
                  </VListItemTitle>

                  <template #append>
                    <VChip
                      :color="getProductColor(index)"
                      size="x-small"
                      variant="flat"
                      class="font-weight-black rounded tabular-nums"
                    >
                      #{{ product.id }}
                    </VChip>
                  </template>
                </VListItem>
                <VDivider v-if="index < props.employee.products.length - 1" class="opacity-10" />
              </template>
            </VList>
          </VCard>
        </div>

        <!-- Estado vacío -->
        <div v-else class="text-center py-8">
          <VIcon icon="tabler-package-off" size="56" color="disabled" class="mb-3 opacity-20" />
          <div class="text-sm font-weight-black text-disabled uppercase">Sin productos asignados</div>
        </div>
      </VCardText>

      <VDivider class="opacity-10" />

      <VCardActions class="pa-6">
        <VBtn
          color="primary"
          variant="flat"
          block
          class="rounded-lg font-weight-black h-44"
          @click="closeDialog"
        >
          CERRAR
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.premium-header {
  background: linear-gradient(135deg, rgb(var(--v-theme-success)) 0%, #1a3a2a 100%) !important;
}

.bg-white-opacity-10 {
  background-color: rgba(255, 255, 255, 10%) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.leading-none {
  line-height: 1;
}

.h-44 {
  block-size: 44px !important;
}
</style>
