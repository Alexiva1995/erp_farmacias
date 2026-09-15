<script setup>
import { ref, watch, onMounted } from 'vue'
import AppFilterBase from '@/components/AppFilterBase.vue'
import EditProductConsumptionDialog from './EditProductConsumptionDialog.vue'
import { $api } from '@/utils/api'
import { toast } from '@/plugins/sweetalert'

const emit = defineEmits(['updated'])

const productsLoading = ref(false)
const productsConfigList = ref([])
const totalProducts = ref(0)
const productPage = ref(1)
const productPerPage = ref(15)
const productSearchQuery = ref('')
const productConsumptionFilter = ref('unclassified')

const editDialog = ref(false)
const selectedProduct = ref({})

const productConsumptionFilterOptions = [
  { title: 'Todos los Tipos', value: 'all' },
  { title: 'Sin Clasificar (Pendientes)', value: 'unclassified' },
  { title: 'Crónico (Uso Continuo)', value: 'chronic' },
  { title: 'Tratamiento Único / Ciclo', value: 'single_treatment' },
  { title: 'Sin Alerta / Insumos', value: 'no_alert' },
  { title: 'Esporádico / Ocasional', value: 'sporadic' },
]

const productConfigHeaders = [
  { title: 'ID', key: 'id', sortable: false, cellClass: 'font-weight-black text-primary' },
  { title: 'Producto', key: 'name', sortable: false },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center' },
]

const fetchProductsConfig = async () => {
  productsLoading.value = true
  try {
    const params = {
      page: productPage.value,
      itemsPerPage: productPerPage.value,
      search: productSearchQuery.value || undefined,
      consumption_type: productConsumptionFilter.value !== 'all' ? productConsumptionFilter.value : undefined,
    }
    const res = await $api('/crm/chronic-clients/products-config', { params })
    if (res?.data) {
      productsConfigList.value = res.data.items || []
      totalProducts.value = res.data.total || 0
    }
  } catch (e) {
    console.error('Error fetchProductsConfig:', e)
  } finally {
    productsLoading.value = false
  }
}

const openEditProduct = (item) => {
  selectedProduct.value = { ...item }
  editDialog.value = true
}

const handleProductSaved = (updatedItem) => {
  const targetIdx = productsConfigList.value.findIndex(p => p.id === updatedItem.id)
  if (targetIdx !== -1) {
    productsConfigList.value[targetIdx] = {
      ...productsConfigList.value[targetIdx],
      ...updatedItem,
    }
  }
  fetchProductsConfig()
  emit('updated')
}

const markAsNoAlert = async (item) => {
  try {
    const payload = {
      consumption_type: 'no_alert',
      treatment_duration_days: null,
    }

    const res = await $api(`/crm/chronic-clients/products-config/${item.id}`, {
      method: 'PUT',
      body: payload,
      data: payload,
    })

    if (res?.data) {
      const targetIdx = productsConfigList.value.findIndex(p => p.id === item.id)
      if (targetIdx !== -1) {
        productsConfigList.value[targetIdx] = {
          ...productsConfigList.value[targetIdx],
          ...res.data,
        }
      }
    }

    toast.success(`"${item.name}" marcado como Sin Alerta / Insumos.`)
    await fetchProductsConfig()
    emit('updated')
  } catch (e) {
    console.error('Error markAsNoAlert:', e)
    const errorMsg = e?.response?._data?.message || e?.message || 'No se pudo marcar el producto.'
    toast.error(errorMsg)
  }
}

const resetProductFilters = () => {
  productSearchQuery.value = ''
  productConsumptionFilter.value = 'all'
  productPage.value = 1
  fetchProductsConfig()
}

let productSearchTimeout = null
watch(productSearchQuery, () => {
  clearTimeout(productSearchTimeout)
  productSearchTimeout = setTimeout(() => {
    productPage.value = 1
    fetchProductsConfig()
  }, 400)
})

watch(productConsumptionFilter, () => {
  productPage.value = 1
  fetchProductsConfig()
})

watch([productPage, productPerPage], fetchProductsConfig)

onMounted(() => {
  fetchProductsConfig()
})

defineExpose({
  fetchProductsConfig,
})
</script>

<template>
  <div class="d-flex flex-column gap-y-4">
    <AppFilterBase
      :search="productSearchQuery"
      :has-advanced-filters="productConsumptionFilter !== 'all'"
      search-placeholder="Buscar medicamento por nombre, código o principio activo..."
      class="py-1"
      @update:search="productSearchQuery = $event"
      @clear="resetProductFilters"
    >
      <template #advanced-filters>
        <VCol cols="12" sm="6" md="4">
          <VSelect
            v-model="productConsumptionFilter"
            :items="productConsumptionFilterOptions"
            item-title="title"
            item-value="value"
            label="Filtrar por Tipo de Consumo"
            density="compact"
            hide-details
            prepend-inner-icon="tabler-category"
          />
        </VCol>
      </template>
    </AppFilterBase>

    <VCard variant="flat" border class="rounded-xl">
      <VDataTableServer
        v-model:page="productPage"
        v-model:items-per-page="productPerPage"
        :headers="productConfigHeaders"
        :items="productsConfigList"
        :items-length="totalProducts"
        :loading="productsLoading"
        density="comfortable"
        class="elevation-0"
        @update:options="fetchProductsConfig"
      >
        <!-- ID -->
        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.id"
            target="_blank"
            class="text-decoration-none font-weight-bold text-primary"
          >
            {{ item.id }}
          </a>
        </template>

        <!-- Producto -->
        <template #item.name="{ item }">
          <div class="d-flex flex-column min-width-0 py-2">
            <span
              class="text-sm font-weight-semibold text-high-emphasis text-truncate"
              style="max-inline-size: 420px;"
              :title="item.name"
            >
              {{ item.name || "—" }}
            </span>
            <div class="d-flex align-center flex-wrap gap-1 text-caption text-medium-emphasis mt-0.5">
              <span v-if="item.active_ingredient">{{ item.active_ingredient }}</span>
              <span v-if="item.active_ingredient && (item.laboratory?.name || item.category?.name)" class="text-disabled mx-0.5">•</span>
              <span class="text-secondary font-weight-medium">
                {{ item.laboratory?.name || item.category?.name || 'S/L' }}
              </span>
              <template v-if="item.barcode">
                <span class="text-disabled mx-0.5">•</span>
                <span class="text-disabled text-caption">Cód: {{ item.barcode }}</span>
              </template>
            </div>
          </div>
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <IconBtn
              size="small"
              color="error"
              @click="markAsNoAlert(item)"
            >
              <VIcon icon="tabler-x" size="18" />
              <VTooltip activator="parent">Sin Alerta / Insumo</VTooltip>
            </IconBtn>

            <IconBtn
              size="small"
              color="warning"
              @click="openEditProduct(item)"
            >
              <VIcon icon="tabler-edit" size="18" />
              <VTooltip activator="parent">Configurar Consumo</VTooltip>
            </IconBtn>
          </div>
        </template>

        <template #no-data>
          <div class="py-8 text-center">
            <VAvatar color="primary" variant="tonal" size="56" class="mb-3">
              <VIcon icon="tabler-pill" size="32" />
            </VAvatar>
            <div class="text-h6 font-weight-bold">No hay productos encontrados</div>
            <div class="text-caption text-medium-emphasis mb-4">
              No se encontraron productos con los términos de búsqueda.
            </div>
            <VBtn variant="tonal" color="primary" @click="resetProductFilters">
              Limpiar Filtros
            </VBtn>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <EditProductConsumptionDialog
      v-model="editDialog"
      :product="selectedProduct"
      @saved="handleProductSaved"
    />
  </div>
</template>
