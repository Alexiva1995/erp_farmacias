<script setup>
const props = defineProps({
  loans: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalLoans: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit-loan", "delete-loan"]);

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Fecha Préstamo", key: "loan_date", sortable: true, width: "15%" },
  { title: "Cuota Mensual", key: "monthly_payment", sortable: true },
  { title: "Total Cuotas", key: "total_installments", sortable: true },
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

const calculateTotalAmount = (item) => {
  return item.monthly_payment * item.total_installments;
};

const calculateRemainingBalance = (item) => {
  const currentDate = new Date();
  const loanDate = new Date(item.loan_date);
  const monthsPassed = Math.max(
    0,
    Math.floor((currentDate - loanDate) / (1000 * 60 * 60 * 24 * 30.44))
  );
  const installmentsPaid = Math.min(monthsPassed, item.total_installments);
  const remainingInstallments = Math.max(
    0,
    item.total_installments - installmentsPaid
  );

  return item.monthly_payment * remainingInstallments;
};

const getLoanStatus = (item) => {
  const currentDate = new Date();
  const loanDate = new Date(item.loan_date);
  const monthsPassed = Math.floor(
    (currentDate - loanDate) / (1000 * 60 * 60 * 24 * 30.44)
  );
  const remainingBalance = calculateRemainingBalance(item);

  if (remainingBalance <= 0) {
    return { text: "Completado", color: "success" };
  } else if (monthsPassed >= item.total_installments) {
    return { text: "Vencido", color: "error" };
  } else if (item.total_installments - monthsPassed <= 3) {
    return { text: "Por Vencer", color: "warning" };
  } else {
    return { text: "Activo", color: "info" };
  }
};

const getProgressPercentage = (item) => {
  const currentDate = new Date();
  const loanDate = new Date(item.loan_date);
  const monthsPassed = Math.max(
    0,
    Math.floor((currentDate - loanDate) / (1000 * 60 * 60 * 24 * 30.44))
  );
  const installmentsPaid = Math.min(monthsPassed, item.total_installments);

  return (installmentsPaid / item.total_installments) * 100;
};

const getRemainingMonths = (item) => {
  const currentDate = new Date();
  const loanDate = new Date(item.loan_date);
  const monthsPassed = Math.floor(
    (currentDate - loanDate) / (1000 * 60 * 60 * 24 * 30.44)
  );

  return Math.max(0, item.total_installments - monthsPassed);
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.loans"
      :items-length="props.totalLoans"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-medium">#{{ item.id }}</span>
      </template>

      <template #item.loan_date="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar size="38" variant="tonal" color="purple" rounded>
            <VIcon icon="tabler-calendar-dollar" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">
              {{ formatDate(item.loan_date) }}
            </span>
            <span class="text-sm text-disabled">
              Hace
              {{
                Math.floor(
                  (new Date() - new Date(item.loan_date)) /
                    (1000 * 60 * 60 * 24 * 30.44)
                )
              }}
              meses
            </span>
          </div>
        </div>
      </template>

      <template #item.monthly_payment="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium">{{
            formatCurrency(item.monthly_payment)
          }}</span>
          <span class="text-xs text-disabled">Mensual</span>
        </div>
      </template>

      <template #item.total_installments="{ item }">
        <div class="text-center">
          <VChip color="primary" variant="tonal" size="small">
            {{ item.total_installments }} cuotas
          </VChip>
        </div>
      </template>

      <template #item.total_amount="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium">{{
            formatCurrency(calculateTotalAmount(item))
          }}</span>
          <span class="text-xs text-disabled">Total del préstamo</span>
        </div>
      </template>

      <template #item.remaining_balance="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium">{{
            formatCurrency(calculateRemainingBalance(item))
          }}</span>
          <div class="mt-1">
            <VProgressLinear
              :model-value="getProgressPercentage(item)"
              :color="getLoanStatus(item).color"
              height="4"
              rounded
            />
          </div>
          <span class="text-xs text-disabled mt-1">
            {{ getRemainingMonths(item) }} meses restantes
          </span>
        </div>
      </template>

      <template #item.status="{ item }">
        <VChip :color="getLoanStatus(item).color" variant="tonal" size="small">
          {{ getLoanStatus(item).text }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <IconBtn @click="emit('edit-loan', item)" color="warning">
            <VIcon icon="tabler-edit" />
            <VTooltip activator="parent" location="top">
              Editar préstamo
            </VTooltip>
          </IconBtn>

          <IconBtn @click="emit('delete-loan', item.id)" color="error">
            <VIcon icon="tabler-trash" />
            <VTooltip activator="parent" location="top">
              Eliminar préstamo
            </VTooltip>
          </IconBtn>
        </div>
      </template>

      <!-- Loading state -->
      <template #loading>
        <VSkeletonLoader type="table-row@10" />
      </template>

      <!-- No data state -->
      <template #no-data>
        <div class="text-center pa-4">
          <VIcon
            icon="tabler-credit-card-off"
            size="48"
            class="mb-2 text-disabled"
          />
          <div class="text-body-1 font-weight-medium mb-1">
            No hay préstamos
          </div>
          <div class="text-body-2 text-disabled">
            No se encontraron préstamos con los filtros aplicados
          </div>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
