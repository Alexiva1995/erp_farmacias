<script setup>

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Nombre', key: 'product.name' },
  { title: 'Stock Producto', key: 'product.stock' },
  { title: 'Cantidad Lote', key: 'quantity' },
  { title: 'Exp', key: 'expiration_date' },
  { title: 'Acciones', key: 'actions', sortable: false },
];

const searchQuery = ref('')
const selectedRows = ref([])

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

const updateOptions = options => {
  sortBy.value = options.sortBy[0]?.key || 'id'
  orderBy.value = options.sortBy[0]?.order || 'desc'
}

const productLotsData = ref([]);
const totalProductLots = ref(0);

const fetchProductLots = async () => {
  try {
    const url = `/api/product-without-lots?q=${searchQuery.value}&page=${page.value}&itemsPerPage=${itemsPerPage.value}&sortBy=${sortBy.value}&orderBy=${orderBy.value}`;
    const response = await fetch(url);
    const data = await response.json();

    productLotsData.value = data?.data?.data || [];
    totalProductLots.value = data?.data?.total || 0;

    console.log('Datos finales en Vue:', productLotsData.value);
  } catch (error) {
    console.error('Error al obtener los lotes:', error);
  }
};

onMounted(fetchProductLots);

const isEditModalOpen = ref(false);
const editedLot = ref({ stock: 0, quantity: 0 });

const openEditModal = (lot) => {
  editedLot.value = { ...lot }; // Cargar datos del lote seleccionado
  isEditModalOpen.value = true; // Abrir modal
};

const updateLot = async () => {
  try {
    const updatedData = {
      quantity: editedLot.value.quantity,
      stock: editedLot.value.quantity, // Actualizar stock con el mismo valor de quantity
    };

    const response = await fetch(`/api/product-lots/${editedLot.value.id}`, {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
      },
      body: JSON.stringify(updatedData),
    });

    const result = await response.json();

    if (!response.ok) {
      throw result; // Capturar error del servidor
    }

    showSnackbar(result.message, "success"); // Mostrar mensaje de éxito
    isEditModalOpen.value = false;
    fetchProductLots(); // Recargar la lista después de la actualización
  } catch (error) {
    console.error("Error al actualizar el lote:", error);
    const errorMessage = error.message || "Error desconocido";
    showSnackbar(errorMessage, "error"); // Mostrar mensaje de error
  }
};
</script>

<template>
  <div>
    <VSnackbar v-model="snackbar" :color="snackbarColor">
        {{ snackbarMessage }}
    </VSnackbar>

    <VCard title="Listado de lotes" class="mb-6">
        <VDivider />

        <div class="d-flex flex-wrap gap-4 ma-6">
            <div class="d-flex align-center">
                <AppTextField
                    v-model="searchQuery"
                    placeholder="Buscar Lote"
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
            </div>
        </div>

        <VDivider class="mt-4" />

        <VDataTableServer
            v-model:items-per-page="itemsPerPage"
            v-model:model-value="selectedRows"
            v-model:page="page"
            :headers="headers"
            show-select
            :items="productLotsData"
            :items-length="totalProductLots"
            class="text-no-wrap"
            @update:options="updateOptions"
        >
            <template #item.id="{ item }">
                <span>{{ item.id }}</span>
            </template>

            <template #item.product.name="{ item }">
                <div class="d-flex align-center gap-x-4">
                    <VAvatar
                    v-if="item.product.photo_url"
                    size="38"
                    variant="tonal"
                    rounded
                    :image="item.product.photo_url"
                    />
                    <div class="d-flex flex-column">
                        <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.product.name }}</span>
                        <span class="text-body-2">{{ item.product.formatted_details }}</span> 
                    </div>
                </div>
            </template>

            <template #item.supplier.name="{ item }">
                <span v-if="item.supplier" class="text-body-1 text-high-emphasis">{{ item.supplier.name }}</span>
            </template>

            <template #item.cost_price="{ item }">
                <span class="font-weight-medium">${{ item.cost_price }}</span>
            </template>

            <template #item.expiration_date="{ item }">
                <span>{{ item.expiration_date }}</span>
            </template>

            <template #item.actions="{ item }">
                <div class="d-flex justify-center">
                    <IconBtn @click="openEditModal(item)">
                        <VIcon icon="tabler-edit" />
                    </IconBtn>
                </div>
            </template>

            <template #bottom>
                <TablePagination
                    v-model:page="page"
                    :items-per-page="itemsPerPage"
                    :total-items="totalProductLots"
                />
            </template>
        </VDataTableServer>
    </VCard>

    <VDialog v-model="isEditModalOpen" width="500">
        <VCard>
            <VCardTitle>Ajustar Stock</VCardTitle>
            <VCardText>
                <VTextField v-model="editedLot.stock" label="Stock Producto" type="number" disabled class="mb-4" />
                <VTextField v-model="editedLot.quantity" label="Cantidad Lote" type="number" class="mb-4" />
            </VCardText>
            <VCardActions>
                <VSpacer />
                <VBtn @click="isEditModalOpen = false">Cancelar</VBtn>
                <VBtn color="primary" @click="updateLot()">Guardar Cambios</VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
  </div>
</template>
