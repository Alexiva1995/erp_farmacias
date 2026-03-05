<script setup lang="js">
import dayjs from 'dayjs';



const props= defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  statuModule: { type: Object, required: true },
  // search: { type: String, required: true },
})

const emit = defineEmits(['update:options', 'approve']);

function verImagne(item){
  window.open(item.url_file, "_blank");
}

const headers = [
  { title: 'ID',             key: 'id',            sortable: true,  width: '70px' },
  { title: 'Descripción',     key: 'name',          sortable: true,  width: '250px' },
  { title: 'Categoría',       key: 'category.name', sortable: false, width: '150px' },
  { title: 'Monto',           key: 'amount',        sortable: true,  align: 'end', width: '150px' },
  { title: 'Estado',          key: 'status',        sortable: false, align: 'center', width: '120px' },
  { title: 'Fecha',           key: 'created_at',    sortable: true,  width: '100px' },
  { title: 'Acciones',        key: 'acciones',      sortable: false, align: 'center', width: '100px' },
];
</script>
<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items-per-page="props.itemsPerPage"
      :items="props.items"
      :items-length="props.total"
      :loading="loading"
      :page="props.page"
      hover
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <!-- ID -->
      <template #item.id="{ item }">
        <span class="font-weight-bold text-primary">#{{ item.id }}</span>
      </template>

      <!-- Nombre / Descripción -->
      <template #item.name="{ item }">
        <div class="d-flex flex-column" style="max-inline-size: 250px;">
          <span class="text-body-2 font-weight-medium text-high-emphasis text-truncate" :title="item.name">
            {{ item.name }} {{ item.last_name || '' }}
          </span>
          <span v-if="item.description" class="text-caption text-medium-emphasis text-truncate" :title="item.description">
            {{ item.description }}
          </span>
          <span class="text-caption text-disabled">{{ item.user?.username || 'S/U' }} • {{ item.count || 'N/A' }}</span>
        </div>
      </template>

      <!-- Categoría con Avatar -->
      <template #item.category.name="{ item }">
        <div class="d-flex align-center gap-2">
          <VAvatar
            size="28"
            color="primary"
            variant="tonal"
          >
            <VIcon icon="tabler-tag" size="16" />
          </VAvatar>
          <span class="text-body-2">{{ item.category?.name || 'S/C' }}</span>
        </div>
      </template>

      <!-- Monto Formateado -->
      <template #item.amount="{ item }">
        <div class="d-flex flex-column align-end">
          <span class="text-body-1 font-weight-bold text-error">
            {{ item.currency === 'USD' ? '$' : item.currency === 'BS' ? 'Bs' : 'COP' }}
            {{ Number(item.amount).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
          </span>
          <span v-if="item.currency !== 'USD'" class="text-caption text-medium-emphasis">
            ≈ ${{ Number(item.total_usd || 0).toFixed(2) }}
          </span>
        </div>
      </template>

      <!-- Deducible -->
      <template #item.is_deductible="{ item }">
        <VIcon
          v-if="item.is_deductible == '1'"
          icon="tabler-check"
          color="success"
          size="20"
        />
        <VIcon
          v-else-if="item.is_deductible == '0'"
          icon="tabler-x"
          color="error"
          size="20"
        />
        <span v-else>-</span>
      </template>

      <!-- Estado con Chips Premium -->
      <template #item.status="{ item }">
        <VChip
          size="small"
          :color="
            item.status === 'Approved'
              ? 'success'
              : item.status === 'Cancelled'
              ? 'error'
              : 'warning'
          "
          variant="tonal"
          class="font-weight-bold"
        >
          <template #prepend>
            <VIcon
              size="12"
              start
              :icon="
                item.status === 'Approved'
                  ? 'tabler-circle-check'
                  : item.status === 'Cancelled'
                  ? 'tabler-circle-x'
                  : 'tabler-clock'
              "
            />
          </template>
          {{ 
            item.status === 'Pending' ? 'Pendiente' : 
            item.status === 'Approved' ? 'Aprobado' : 
            item.status === 'Cancelled' ? 'Cancelado' : item.status 
          }}
        </VChip>
      </template>

      <!-- Fecha -->
      <template #item.created_at="{ item }">
        <span class="text-body-2 text-medium-emphasis">
          {{ dayjs(item.created_at.replace('Z', '')).format('DD/MM/YYYY') }}
        </span>
      </template>

      <!-- Acciones -->
      <template #item.acciones="{ item }">
        <div class="d-flex align-center justify-center gap-1">
          <!-- OJITO: Color info si tiene archivo (url_file), sino gris -->
          <IconBtn
            title="Ver comprobante"
            @click="() => verImagne(item)"
            :disabled="!item.url_file"
            size="small"
          >
            <VIcon 
              icon="tabler-eye" 
              :color="item.url_file ? 'info' : 'grey-lighten-1'" 
              size="20"
            />
          </IconBtn>
          
          <!-- BOTÓN APROBAR (Solo si está pendiente) -->
          <IconBtn
            v-if="item.status === 'Pending' || item.status === 'Pendiente'"
            title="Aprobar Gasto"
            color="success"
            size="small"
            :disabled="statuModule.loadingItems.has(item.id)"
            @click="() => emit('approve', item.id)"
          >
            <VProgressCircular
              v-if="statuModule.loadingItems.has(item.id)"
              indeterminate
              size="20"
              width="2"
            />
            <VIcon v-else icon="tabler-check" size="20" />
          </IconBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
