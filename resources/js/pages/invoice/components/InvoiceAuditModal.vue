<script setup>
// Modal de Auditoría de Factura y Trazabilidad de Ciclo de Vida
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  invoice: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['update:modelValue'])

const steps = [
  {
    step: 1,
    title: 'Subida / Registro Inicial',
    description: 'Creación del registro de la factura en el sistema.',
    icon: 'tabler-cloud-upload',
    userKey: 'uploaded_by_user',
    defaultUser: 'Sistema / N/A',
    color: 'success',
    isDone: (inv) => Boolean(inv?.uploaded_by_user || inv?.id),
  },
  {
    step: 2,
    title: 'Carga / Registro de Productos',
    description: 'Ingreso y homologación de renglones, cantidades y costos.',
    icon: 'tabler-list-check',
    userKey: 'registered_by_user',
    defaultUser: 'No asignado',
    color: 'info',
    isDone: (inv) => Boolean(inv?.registered_by_user),
  },
  {
    step: 3,
    title: 'Verificación y Ubicación Física',
    description: 'Asignación de ubicaciones de almacén y validación de lotes.',
    icon: 'tabler-box-margin',
    userKey: 'loaded_by_user',
    defaultUser: 'No asignado',
    color: 'warning',
    isDone: (inv) => Boolean(inv?.loaded_by_user),
  },
  {
    step: 4,
    title: 'Aprobación y Orden de Compra',
    description: 'Validación final financiera y aprobación administrativa.',
    icon: 'tabler-circle-check',
    userKey: 'ordered_by_user',
    defaultUser: 'No asignado',
    color: 'primary',
    isDone: (inv) => Boolean(inv?.ordered_by_user),
  },
]
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="580"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-xl overflow-hidden shadow-lg border-0 d-flex flex-column">
      <!-- Cabecera con Gradiente Institucional del Sistema -->
      <VCardTitle class="pa-0 flex-shrink-0">
        <div class="px-5 py-4 bg-primary d-flex align-center justify-space-between text-white" style="background: linear-gradient(135deg, #7A0099, #E20074) !important;">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-1 flex-shrink-0">
              <VIcon color="primary" size="22">tabler-shield-check</VIcon>
            </VAvatar>
            <div class="d-flex flex-column">
              <h2 class="text-subtitle-1 font-weight-black text-white leading-tight mb-0.5" style="color: white !important;">
                Historial de Auditoría — Factura #{{ invoice?.invoice_number || 'N/A' }}
              </h2>
              <span class="text-caption text-white opacity-90 font-weight-medium" style="color: white !important; font-size: 11px;">
                Trazabilidad del ciclo de vida y operadores responsables
              </span>
            </div>
          </div>
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="x-small"
            class="rounded-lg"
            @click="emit('update:modelValue', false)"
          />
        </div>
      </VCardTitle>

      <!-- Resumen Compacto de la Factura -->
      <div v-if="invoice" class="px-5 py-2.5 bg-surface border-b d-flex align-center justify-space-between flex-wrap gap-2">
        <div class="d-flex align-center gap-2">
          <span class="text-caption font-weight-bold text-medium-emphasis">Proveedor:</span>
          <span class="text-caption font-weight-black text-high-emphasis">{{ invoice?.supplier?.name || 'N/A' }}</span>
        </div>
        <div class="d-flex align-center gap-2">
          <VChip size="x-small" variant="tonal" color="primary" class="font-weight-black">
            Control: {{ invoice?.control_number || 'S/N' }}
          </VChip>
          <VChip
            size="x-small"
            variant="tonal"
            :color="invoice?.status === 'approved' ? 'success' : invoice?.status === 'rejected' ? 'error' : 'warning'"
            class="font-weight-black text-uppercase"
          >
            {{ invoice?.status || 'pending' }}
          </VChip>
        </div>
      </div>

      <!-- Contenido del Stepper / Timeline -->
      <VCardText class="pa-5 bg-light flex-grow-1">
        <div class="d-flex flex-column gap-3">
          <div
            v-for="(st, idx) in steps"
            :key="st.step"
            class="audit-step-card pa-3.5 bg-white rounded border transition-all position-relative"
            :class="{
              'step-completed': st.isDone(invoice),
              'step-pending': !st.isDone(invoice),
            }"
            style="border-radius: 5px !important;"
          >
            <div class="d-flex align-start gap-3">
              <!-- Avatar / Icono del Paso -->
              <VAvatar
                size="36"
                :color="st.isDone(invoice) ? st.color : 'secondary'"
                variant="tonal"
                class="flex-shrink-0"
              >
                <VIcon
                  :icon="st.isDone(invoice) ? st.icon : 'tabler-clock'"
                  size="20"
                  :class="st.isDone(invoice) ? `text-${st.color}` : 'text-disabled'"
                />
              </VAvatar>

              <!-- Detalles del Paso -->
              <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-center justify-space-between gap-2 mb-1">
                  <span class="font-weight-black text-body-2 text-high-emphasis leading-tight">
                    {{ st.step }}. {{ st.title }}
                  </span>
                  <VChip
                    size="x-small"
                    :color="st.isDone(invoice) ? 'success' : 'secondary'"
                    variant="tonal"
                    class="font-weight-black px-2 flex-shrink-0"
                    style="height: 20px; font-size: 10px;"
                  >
                    <VIcon start size="12">
                      {{ st.isDone(invoice) ? 'tabler-check' : 'tabler-hourglass-empty' }}
                    </VIcon>
                    {{ st.isDone(invoice) ? 'Completado' : 'Pendiente' }}
                  </VChip>
                </div>

                <p class="text-caption text-medium-emphasis mb-2 leading-relaxed" style="font-size: 11px;">
                  {{ st.description }}
                </p>

                <!-- Información del Operador -->
                <div class="d-flex align-center gap-1.5 py-1 px-2.5 rounded bg-surface border text-caption">
                  <VIcon icon="tabler-user" size="14" class="text-medium-emphasis" />
                  <span class="text-medium-emphasis font-weight-medium">Operador:</span>
                  <strong class="text-high-emphasis ms-0.5">
                    {{ invoice?.[st.userKey]?.name || invoice?.[st.userKey]?.username || (st.step === 1 ? 'admin' : st.defaultUser) }}
                  </strong>
                </div>
              </div>
            </div>
          </div>
        </div>
      </VCardText>

      <!-- Pie con Botón Outlined 100% de Ancho -->
      <VCardActions class="pa-4 bg-white border-t">
        <VBtn
          color="primary"
          variant="outlined"
          block
          size="default"
          class="font-weight-bold tracking-wide text-uppercase"
          style="border-radius: 5px !important;"
          @click="emit('update:modelValue', false)"
        >
          Cerrar Ventana
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.audit-step-card {
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.step-completed {
  border-left: 3px solid rgb(var(--v-theme-success)) !important;
}

.step-pending {
  border-left: 3px solid rgba(var(--v-theme-on-surface), 0.2) !important;
  opacity: 0.85;
}
</style>
