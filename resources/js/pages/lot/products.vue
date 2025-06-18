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

watchEffect(fetchProductLots);
onMounted(fetchProductLots);

const isEditModalOpen = ref(false);
const editedLot = ref({ stock: 0, quantity: 0 });
const snackbar = ref(false);
const snackbarMessage = ref("");
const snackbarColor = ref("success");

const openEditModal = (lot) => {
  editedLot.value = { ...lot }; // Cargar datos del lote seleccionado
  isEditModalOpen.value = true; // Abrir modal
};

const showSnackbar = (message, color = "success") => {
  snackbarMessage.value = message;
  snackbarColor.value = color;
  snackbar.value = true;
};

const updateLot = async () => {
  try {
    const updatedData = {
        lot_number: editedLot.value.lot_number,
        expiration_date: editedLot.value.expiration_date,
        cost_price: editedLot.value.cost_price,
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

const isCreateModalOpen = ref(false);
const newLot = ref({ product_id: null, quantity: 0, expiration_date: "", lot_number: "", cost_price: 0, location: "", supplier_id: null});
const availableProducts = ref([]);
const availableSuppliers = ref([]);

const openCreateModal = async () => {
  try {
    const response = await fetch("/api/products-without-lots");
    const response2 = await fetch("/api/available-suppliers");
    const data = await response.json();
    const data2 = await response2.json();
    availableProducts.value = data.data;
    availableSuppliers.value = data2.data;
    isCreateModalOpen.value = true;
  } catch (error) {
    console.error("Error al obtener productos sin lote:", error);
  }
};

const createLot = async () => {
  try {
    const response = await fetch(`/api/product-lots/`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify(newLot.value),
    });

    const result = await response.json();
    if (!response.ok) throw result;

    newLot.value = { product_id: null, lot_number: "", expiration_date: "", quantity: 0, cost_price: 0, location: "", supplier_id: null };
    showSnackbar(result.message, "success"); // Mostrar mensaje de éxito
    isCreateModalOpen.value = false;
    fetchProductLots(); // Recargar lista de lotes
  } catch (error) {
    console.error("Error al crear el lote:", error);
    const errorMessage = error.message || "Error desconocido";
    showSnackbar(errorMessage, "error"); // Mostrar mensaje de error
  }
};

const isDeleteModalOpen = ref(false);
const selectedLot = ref(null);

const confirmDelete = (lot) => {
  selectedLot.value = lot;
  isDeleteModalOpen.value = true;
};

const deleteLot = async () => {
  try {
    const response = await fetch(`/api/product-lots/${selectedLot.value.id}`, {
      method: "DELETE",
      headers: {
        "Accept": "application/json",
      },
    });

    const result = await response.json();
    if (!response.ok) throw result;

    showSnackbar(result.message, "success"); // Mostrar mensaje de éxito
    isDeleteModalOpen.value = false;
    fetchProductLots(); // Recargar lista de lotes
  } catch (error) {
    console.error("Error al eliminar el lote:", error);
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
                <VBtn color="primary" @click="openCreateModal()">
                    <VIcon icon="tabler-plus" class="mr-2" />
                    Agregar Lote
                </VBtn>
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

            <template #item.product.stock="{ item }">
                <div class="d-flex justify-center">
                    <span v-if="item.product" class="font-weight-medium">{{ item.product.stock }}</span>
                </div>
            </template>

            <template #item.quantity="{ item }">
                <div class="d-flex justify-center">
                    <span class="font-weight-medium">{{ item.quantity }}</span>
                </div>
            </template>

            <template #item.expiration_date="{ item }">
                <div class="d-flex justify-center">
                    <span>{{ item.expiration_date }}</span>
                </div>
            </template>

            <template #item.actions="{ item }">
                <div class="d-flex justify-center">
                    <IconBtn @click="openEditModal(item)">
                        <VIcon icon="tabler-edit" />
                    </IconBtn>
                    <IconBtn color="error" @click="confirmDelete(item)">
                        <VIcon icon="tabler-trash" />
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
                <VTextField v-model="editedLot.product.stock" label="Stock Producto" type="number" disabled class="mb-4" />
                <VTextField v-model="editedLot.quantity" label="Cantidad Lote" type="number" class="mb-4" />
            </VCardText>
            <VCardActions>
                <VSpacer />
                <VBtn @click="isEditModalOpen = false">Cancelar</VBtn>
                <VBtn color="primary" @click="updateLot()">Guardar Cambios</VBtn>
            </VCardActions>
        </VCard>
    </VDialog>

    <VDialog v-model="isCreateModalOpen" width="500">
        <VCard>
            <VCardTitle>Crear Lote</VCardTitle>
            <VCardText>
                <AppSelect 
                    v-model="newLot.product_id" 
                    label="Seleccionar Producto"
                    :items="availableProducts"
                    item-title="name"
                    item-value="id"
                    class="mb-4"
                />
                <VTextField v-model="newLot.lot_number" label="Número de Lote" class="mb-4" />
                <VTextField v-model="newLot.quantity" label="Cantidad" type="number" class="mb-4" />
                <VTextField v-model="newLot.expiration_date" label="Fecha de Vencimiento" type="date" class="mb-4" />
                <VTextField v-model="newLot.cost_price" label="Precio de Costo" type="number" class="mb-4" />
                <VTextField v-model="newLot.location" label="Ubicación" class="mb-4" />
                <AppSelect 
                    v-model="newLot.supplier_id" 
                    label="Proveedor"
                    :items="availableSuppliers"
                    item-title="supplier_name"
                    item-value="id"
                />
            </VCardText>
            <VCardActions>
                <VSpacer />
                <VBtn @click="isCreateModalOpen = false">Cancelar</VBtn>
                <VBtn color="primary" @click="createLot()">Guardar Lote</VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
    <VDialog v-model="isDeleteModalOpen" width="400">
        <VCard>
            <VCardTitle>Eliminar Lote</VCardTitle>
            <VCardText>
                <p>¿Estás seguro de que deseas eliminar este lote?</p>
            </VCardText>
            <VCardActions>
                <VSpacer />
                <VBtn @click="isDeleteModalOpen = false">Cancelar</VBtn>
                <VBtn color="error" @click="deleteLot()">Eliminar</VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
  </div>
</template>
