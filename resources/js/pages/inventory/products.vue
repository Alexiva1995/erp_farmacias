<script setup>

const headers = [
  { title: 'Producto', key: 'name' }, // 'name' es la columna en tu BD
  { title: 'Categoría', key: 'category.name' }, // Accede a la relación
  { title: 'Laboratorio', key: 'laboratory.name' }, // Accede a la relación
  { title: 'Precio Venta', key: 'sale_price' },
  { title: 'Código de Barras', key: 'barcode' }, // 'barcode' en lugar de SKU
  { title: 'Acciones', key: 'actions', sortable: false },
]

const selectedStatus = ref()
const selectedCategory = ref()
const selectedStock = ref()
const searchQuery = ref('')
const selectedRows = ref([])

const status = ref([
  {
    title: 'Scheduled',
    value: 'Scheduled',
  },
  {
    title: 'Publish',
    value: 'Published',
  },
  {
    title: 'Inactive',
    value: 'Inactive',
  },
])

const categories = ref([
  {
    title: 'Accessories',
    value: 'Accessories',
  },
  {
    title: 'Home Decor',
    value: 'Home Decor',
  },
  {
    title: 'Electronics',
    value: 'Electronics',
  },
  {
    title: 'Shoes',
    value: 'Shoes',
  },
  {
    title: 'Office',
    value: 'Office',
  },
  {
    title: 'Games',
    value: 'Games',
  },
])

const stockStatus = ref([
  {
    title: 'In Stock',
    value: true,
  },
  {
    title: 'Out of Stock',
    value: false,
  },
])

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

const updateOptions = options => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const resolveCategory = category => {
  if (category === 'Accessories')
    return {
      color: 'error',
      icon: 'tabler-device-watch',
    }
  if (category === 'Home Decor')
    return {
      color: 'info',
      icon: 'tabler-home',
    }
  if (category === 'Electronics')
    return {
      color: 'primary',
      icon: 'tabler-device-imac',
    }
  if (category === 'Shoes')
    return {
      color: 'success',
      icon: 'tabler-shoe',
    }
  if (category === 'Office')
    return {
      color: 'warning',
      icon: 'tabler-briefcase',
    }
  if (category === 'Games')
    return {
      color: 'primary',
      icon: 'tabler-device-gamepad-2',
    }
}

const resolveStatus = statusMsg => {
  if (statusMsg === 'Scheduled')
    return {
      text: 'Scheduled',
      color: 'warning',
    }
  if (statusMsg === 'Published')
    return {
      text: 'Publish',
      color: 'success',
    }
  if (statusMsg === 'Inactive')
    return {
      text: 'Inactive',
      color: 'error',
    }
}

const {
  data: productsData,
  execute: fetchProducts,
} = useApi(createUrl('/products', {
  query: {
    q: searchQuery,
    page,
    itemsPerPage,
    sortBy,
    orderBy,
  },
}));
console.log(productsData);

const products = computed(() => productsData.value?.data || [])
const totalProduct = computed(() => productsData.value?.total || 0)

const deleteProduct = async id => {
  await $api(`apps/ecommerce/products/${ id }`, { method: 'DELETE' })

  // Delete from selectedRows
  const index = selectedRows.value.findIndex(row => row === id)
  if (index !== -1)
    selectedRows.value.splice(index, 1)

  // Refetch products
  fetchProducts()
}
</script>

<template>
  <div>
    <!-- 👉 products -->
    <VCard
      title="Filters"
      class="mb-6"
    >
      <VCardText>
        <VRow>
          <!-- 👉 Select Status -->
          <VCol
            cols="12"
            sm="4"
          >
            <AppSelect
              v-model="selectedStatus"
              placeholder="Status"
              :items="status"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>

          <!-- 👉 Select Category -->
          <VCol
            cols="12"
            sm="4"
          >
            <AppSelect
              v-model="selectedCategory"
              placeholder="Category"
              :items="categories"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>

          <!-- 👉 Select Stock Status -->
          <VCol
            cols="12"
            sm="4"
          >
            <AppSelect
              v-model="selectedStock"
              placeholder="Stock"
              :items="stockStatus"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <div class="d-flex flex-wrap gap-4 ma-6">
        <div class="d-flex align-center">
          <!-- 👉 Search  -->
          <AppTextField
            v-model="searchQuery"
            placeholder="Search Product"
            style="inline-size: 200px;"
            class="me-3"
          />
        </div>

        <VSpacer />
        <div class="d-flex gap-4 flex-wrap align-center">
          <AppSelect
            v-model="itemsPerPage"
            :items="[5, 10, 20, 25, 50]"
          />
          <!-- 👉 Export button -->
          <VBtn
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-upload"
          >
            Export
          </VBtn>

          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="$router.push('/apps/ecommerce/product/add')"
          >
            Add Product
          </VBtn>
        </div>
      </div>

      <VDivider class="mt-4" />

      <!-- 👉 Datatable  -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:model-value="selectedRows"
        v-model:page="page"
        :headers="headers"
        show-select
        :items="products"
        :items-length="totalProduct"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- product  -->
        <template #item.name="{ item }"> <!-- usa key: 'name' -->
  <div class="d-flex align-center gap-x-4">
    <VAvatar
      v-if="item.photo_url"
      size="38"
      variant="tonal"
      rounded
      :image="item.photo_url"
    />
    <div class="d-flex flex-column">
      <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.name }}</span>
      <!-- Puedes usar el campo 'active_ingredient' aquí -->
      <span class="text-body-2">{{ item.active_ingredient }}</span> 
    </div>
  </div>
        </template>

        <!-- Categoría -->
        <template #item.category.name="{ item }"> <!-- usa key: 'category.name' -->
          <!-- Puedes mantener la lógica del avatar o simplificarla -->
          <span v-if="item.category" class="text-body-1 text-high-emphasis">{{ item.category.name }}</span>
        </template>

        <!-- Laboratorio -->
        <template #item.laboratory.name="{ item }">
          <span v-if="item.laboratory" class="text-body-1 text-high-emphasis">{{ item.laboratory.name }}</span>
        </template>

        <!-- Precio Venta -->
        <template #item.sale_price="{ item }">
          <span class="font-weight-medium">${{ item.sale_price }}</span>
        </template>

        <!-- Código de Barras -->
        <template #item.barcode="{ item }">
          <span>{{ item.barcode }}</span>
        </template>

        <!-- Acciones (esto puede quedar igual por ahora) -->
        <template #item.actions="{ item }">
          <IconBtn>
            <VIcon icon="tabler-edit" />
          </IconBtn>
          <IconBtn @click="deleteProduct(item.id)">
            <VIcon icon="tabler-trash" />
          </IconBtn>
        </template>

        <!-- pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalProduct"
          />
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
