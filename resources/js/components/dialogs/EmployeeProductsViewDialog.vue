<script setup>
import { computed } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  employee: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

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
    max-width="600"
    @update:model-value="closeDialog"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-5">
        <VIcon icon="tabler-pill" size="24" class="text-success" />
        <span class="text-h6">Productos Asignados</span>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-5">
        <!-- Información del Empleado -->
        <div class="d-flex align-center gap-3 mb-4 pa-4 rounded bg-surface">
          <VAvatar color="primary" variant="tonal" size="48">
            <span class="text-base">
              {{
                props.employee.employee_name
                  ?.split(" ")
                  .map((n) => n[0])
                  .join("")
                  .substring(0, 2) || "N/A"
              }}
            </span>
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-h6 font-weight-medium">
              {{ props.employee.employee_name }}
            </span>
            <span class="text-sm text-disabled">
              {{ props.employee.identification }}
            </span>
          </div>
          <VSpacer />
          <VChip
            :color="props.employee.products_count > 0 ? 'success' : 'default'"
            variant="tonal"
          >
            {{ props.employee.products_count }}
            {{ props.employee.products_count === 1 ? "producto" : "productos" }}
          </VChip>
        </div>

        <!-- Lista de Productos -->
        <div v-if="hasProducts">
          <VCard variant="outlined" class="overflow-hidden">
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
                      size="38"
                    >
                      <VIcon icon="tabler-pill" size="20" />
                    </VAvatar>
                  </template>

                  <VListItemTitle>
                    <span class="text-body-1 font-weight-medium">
                      {{ product.name }}
                    </span>
                  </VListItemTitle>

                  <template #append>
                    <VChip
                      :color="getProductColor(index)"
                      size="small"
                      variant="tonal"
                    >
                      ID: {{ product.id }}
                    </VChip>
                  </template>
                </VListItem>
                <VDivider v-if="index < props.employee.products.length - 1" />
              </template>
            </VList>
          </VCard>
        </div>

        <!-- Mensaje cuando no hay productos -->
        <VAlert v-else type="info" variant="tonal">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-info-circle" />
            <span>Este empleado no tiene productos asignados</span>
          </div>
        </VAlert>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-5">
        <VSpacer />
        <VBtn color="primary" @click="closeDialog"> Cerrar </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
