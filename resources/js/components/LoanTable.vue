<script setup>
import { useDisplay } from "vuetify";
import AppEmptyState from "@/components/AppEmptyState.vue";

const props = defineProps({
  loans: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalLoans: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit-loan", "delete-loan", "add-payment"]);

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Préstamo", key: "loan_date", sortable: true, width: "15%" },
  { title: "C. Mensual", key: "monthly_payment", sortable: true },
  { title: "Cuotas", key: "total_installments", sortable: true },
  { title: "Monto Total", key: "total_amount", sortable: true },
  { title: "Saldo Pendiente", key: "remaining_balance", sortable: true },
  { title: "Estado", key: "status", sortable: false },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString("es-ES", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    });
  } catch (error) {
    return "Fecha inválida";
  }
};
</script>

<template>
  <div class="loan-table-container">
    <!-- Vista de Escritorio (Tabla) -->
    <VDataTableServer
      v-if="!mobile"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.loans"
      :items-length="props.totalLoans"
      :loading="props.loading"
      class="text-no-wrap premium-table-loan"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #no-data>
        <AppEmptyState
          title="No hay préstamos"
          message="No se encontraron préstamos asignados a este empleado."
          icon="tabler-coin-off"
        />
      </template>

      <template #item.id="{ item }">
        <span class="font-weight-black text-primary">{{ item.id }}</span>
      </template>

      <template #item.loan_date="{ item }">
        <div class="d-flex align-center gap-3 py-2">
          <VAvatar
            color="purple"
            variant="tonal"
            rounded
            size="36"
            class="rounded-lg"
          >
            <VIcon icon="tabler-calendar-dollar" size="18" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span
              class="text-body-2 font-weight-bold text-high-emphasis leading-tight"
            >
              {{ formatDate(item.loan_date) }}
            </span>
            <span class="text-xs text-disabled">
              Inició hace {{ item.months_passed }} meses
            </span>
          </div>
        </div>
      </template>

      <template #item.monthly_payment="{ item }">
        <span class="text-body-2 font-weight-semibold text-primary">{{
          formatCurrency(item.monthly_payment)
        }}</span>
      </template>

      <template #item.total_installments="{ item }">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-hash" size="14" color="disabled" />
          <span class="text-body-2 font-weight-medium"
            >{{ item.total_installments }} cuotas</span
          >
        </div>
      </template>

      <template #item.total_amount="{ item }">
        <span class="text-body-2 font-weight-medium">{{
          formatCurrency(item.total_amount)
        }}</span>
      </template>

      <template #item.remaining_balance="{ item }">
        <div class="d-flex flex-column" style="min-inline-size: 140px">
          <div class="d-flex justify-space-between align-center mb-1">
            <span class="text-body-2 font-weight-black">{{
              formatCurrency(item.remaining_balance)
            }}</span>
            <span class="text-xs font-weight-bold"
              >{{ item.progress_percentage.toFixed(0) }}%</span
            >
          </div>
          <VProgressLinear
            :model-value="item.progress_percentage"
            :color="item.status.color"
            height="6"
            rounded
            class="rounded-pill"
          />
          <span class="text-xs text-disabled mt-1 font-weight-medium">
            {{ item.remaining_months }} meses rest.
          </span>
        </div>
      </template>

      <template #item.status="{ item }">
        <VChip
          :color="item.status.color"
          variant="tonal"
          size="small"
          class="font-weight-bold rounded-lg"
        >
          <template #prepend>
            <VIcon :icon="item.status.icon" size="14" class="mr-1" />
          </template>
          {{ item.status.text }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex justify-center gap-2">
          <VBtn
            icon
            size="32"
            variant="tonal"
            color="warning"
            class="rounded-circle shadow-sm"
            @click="emit('edit-loan', item)"
          >
            <VIcon icon="tabler-edit" size="18" />
            <VTooltip activator="parent" location="top">Editar</VTooltip>
          </VBtn>

          <VBtn
            v-if="item.remaining_balance > 0"
            icon
            size="32"
            variant="tonal"
            color="success"
            class="rounded-circle shadow-sm"
            @click="emit('add-payment', item)"
          >
            <VIcon icon="tabler-currency-dollar" size="18" />
            <VTooltip activator="parent" location="top">Añadir Abono</VTooltip>
          </VBtn>

          <VBtn
            icon
            size="32"
            variant="tonal"
            color="error"
            class="rounded-circle shadow-sm"
            @click="emit('delete-loan', item.id)"
          >
            <VIcon icon="tabler-trash" size="18" />
            <VTooltip activator="parent" location="top">Eliminar</VTooltip>
          </VBtn>
        </div>
      </template>

      <template #loading>
        <VSkeletonLoader type="table-row@10" />
      </template>
    </VDataTableServer>

    <!-- Vista Móvil (Cards) -->
    <div v-else class="mobile-cards-container pa-4">
      <VRow v-if="props.loading">
        <VCol v-for="n in 5" :key="n" cols="12">
          <VSkeletonLoader type="card" class="rounded-lg" />
        </VCol>
      </VRow>
      <VRow v-else-if="props.loans.length > 0">
        <VCol v-for="item in props.loans" :key="item.id" cols="12">
          <VCard class="rounded-lg border shadow-sm loan-card" variant="flat">
            <VCardText class="pa-4">
              <div class="d-flex justify-space-between align-start mb-3">
                <div class="d-flex align-center gap-3">
                  <VAvatar
                    color="purple"
                    variant="tonal"
                    rounded
                    size="40"
                    class="rounded-lg"
                  >
                    <VIcon icon="tabler-calendar-dollar" size="20" />
                  </VAvatar>
                  <div>
                    <div
                      class="text-subtitle-1 font-weight-black text-high-emphasis leading-none"
                    >
                      #{{ item.id }}
                    </div>
                    <div
                      class="text-caption text-disabled font-weight-bold uppercase mt-1"
                    >
                      Iniciado el {{ formatDate(item.loan_date) }}
                    </div>
                  </div>
                </div>
                <div class="d-flex gap-2">
                  <VBtn
                    icon="tabler-edit"
                    variant="tonal"
                    color="warning"
                    size="36"
                    class="rounded-lg"
                    @click="emit('edit-loan', item)"
                  />
                  <VBtn
                    v-if="item.remaining_balance > 0"
                    icon="tabler-currency-dollar"
                    variant="tonal"
                    color="success"
                    size="36"
                    class="rounded-lg"
                    @click="emit('add-payment', item)"
                  />
                  <VBtn
                    icon="tabler-trash"
                    variant="tonal"
                    color="error"
                    size="36"
                    class="rounded-lg"
                    @click="emit('delete-loan', item.id)"
                  />
                </div>
              </div>

              <VDivider class="my-3 border-dashed" />

              <VRow no-gutters class="mb-4">
                <VCol cols="6">
                  <div
                    class="text-caption text-disabled uppercase font-weight-bold mb-1"
                  >
                    Cuota Mensual
                  </div>
                  <div class="text-body-2 font-weight-black text-primary">
                    {{ formatCurrency(item.monthly_payment) }}
                  </div>
                </VCol>
                <VCol cols="6" class="text-right border-l-dashed pl-4">
                  <div
                    class="text-caption text-disabled uppercase font-weight-bold mb-1"
                  >
                    Saldo Pendiente
                  </div>
                  <div class="text-body-2 font-weight-black">
                    {{ formatCurrency(item.remaining_balance) }}
                  </div>
                </VCol>
              </VRow>

              <div class="mb-4">
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-xs text-disabled uppercase font-weight-bold"
                    >Progreso de Pago</span
                  >
                  <span class="text-xs font-weight-black text-primary"
                    >{{ item.progress_percentage.toFixed(1) }}%</span
                  >
                </div>
                <VProgressLinear
                  :model-value="item.progress_percentage"
                  :color="item.status.color"
                  height="10"
                  rounded
                  class="rounded-pill"
                />
                <div class="d-flex justify-space-between mt-1">
                  <span class="text-xs text-disabled font-weight-medium"
                    >{{ item.total_installments }} cuotas tot.</span
                  >
                  <span
                    class="text-xs text-disabled font-weight-medium text-uppercase"
                    >{{ item.remaining_months }} meses rest.</span
                  >
                </div>
              </div>

              <div
                class="d-flex justify-space-between align-center px-4 py-2 bg-light-surface rounded-lg border"
              >
                <div class="d-flex flex-column">
                  <span class="text-xs text-disabled uppercase font-weight-bold"
                    >Estado</span
                  >
                  <div class="d-flex align-center gap-1">
                    <VIcon
                      :icon="item.status.icon"
                      :color="item.status.color"
                      size="14"
                    />
                    <span
                      :class="`text-body-2 font-weight-black text-${item.status.color}`"
                    >
                      {{ item.status.text }}
                    </span>
                  </div>
                </div>
                <div class="text-right">
                  <span class="text-xs text-disabled uppercase font-weight-bold"
                    >Total Préstamo</span
                  >
                  <div class="text-body-2 font-weight-bold">
                    {{ formatCurrency(item.total_amount) }}
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- No data state -->
      <div v-else class="text-center pa-12">
        <VIcon
          icon="tabler-credit-card-off"
          size="64"
          class="mb-4 text-disabled opacity-20"
        />
        <div class="text-h6 font-weight-bold mb-1">Sin resultados</div>
        <div class="text-body-2 text-disabled">
          No se encontraron préstamos con esos filtros
        </div>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-6">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalLoans / props.itemsPerPage)"
          density="comfortable"
          variant="tonal"
          @update:model-value="
            (val) =>
              emit('update:options', {
                page: val,
                itemsPerPage: props.itemsPerPage,
              })
          "
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(.v-data-table-header) {
  background-color: rgba(var(--v-theme-on-surface), 2%) !important;
}

.premium-table :deep(.v-data-table-header th) {
  block-size: 48px !important;
  color: rgba(var(--v-theme-on-surface), 50%) !important;
  font-size: 0.65rem !important;
  font-weight: 900 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.loan-card {
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.loan-card:active {
  transform: scale(0.98);
}

.border-dashed {
  border-style: dashed !important;
  opacity: 0.15;
}

.border-l-dashed {
  border-inline-start: 1px dashed rgba(var(--v-border-color), 0.15);
}

.bg-light-surface {
  background-color: rgba(var(--v-theme-on-surface), 2%);
}

.leading-none {
  line-height: 1 !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.opacity-20 {
  opacity: 0.2;
}
</style>
