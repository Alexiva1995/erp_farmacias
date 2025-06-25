<script setup>
import { ref, computed, watch } from "vue";

// --- PROPS ---
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  color: {
    type: String,
    required: false,
    default: "primary",
  },
  icon: {
    type: String,
    required: false,
  },
  stats: {
    type: String,
    required: false,
  },
  height: {
    type: Number,
    required: false,
  },
  series: {
    type: Array,
    required: false,
  },
  chartOptions: {
    type: null,
    required: false,
  },
  searchQuery: {
    type: String,
    default: "",
  },
});

const productCost = ref(28);

const emit = defineEmits(["update:searchQuery"]);

const products = ref([
  {
    id: 1,
    title: "Apple iPhone 13",
    itemCode: "#FXZ-4567",
    price: 999.29,
    quantity: 1,
  },
  {
    id: 2,
    title: "Nike Air Jordan",
    itemCode: "#FXZ-3456",
    price: 72.4,
    quantity: 1,
  },
  {
    id: 3,
    title: "Beats Studio 2",
    itemCode: "#FXZ-9485",
    price: 99.0,
    quantity: 1,
  },
  {
    id: 4,
    title: "Apple Watch Series 7",
    itemCode: "#FXZ-2345",
    price: 249.99,
    quantity: 1,
  },
  {
    id: 5,
    title: "Amazon Echo Dot",
    itemCode: "#FXZ-8959",
    price: 79.4,
    quantity: 1,
  },
  {
    id: 6,
    title: "Play Station Console",
    itemCode: "#FXZ-7892",
    price: 129.48,
    quantity: 1,
  },
  {
    id: 7,
    title: "Google Home Mini",
    itemCode: "#GHM-007",
    price: 49.99,
    quantity: 1,
  },
  {
    id: 8,
    title: "Razer DeathAdder V2",
    itemCode: "#RDV-008",
    price: 499.0,
    quantity: 1,
  },
  {
    id: 10,
    title: "Logitech Mouse MX Master 3",
    itemCode: "#LMX-1010",
    price: 99.0,
    quantity: 1,
  },
  {
    id: 11,
    title: "GoPro HERO11",
    itemCode: "#GPH-011",
    price: 399.0,
    quantity: 1,
  },
  {
    id: 12,
    title: "DJI Mini 3 Pro",
    itemCode: "#DMI-012",
    price: 759.0,
    quantity: 1,
  },
]);

const incrementQuantity = (productId) => {
  const product = products.value.find((p) => p.id === productId);
  if (product) {
    product.quantity++;
  }
};

const decrementQuantity = (productId) => {
  const product = products.value.find((p) => p.id === productId);
  if (product && product.quantity > 1) {
    product.quantity--;
  }
};

const removeProduct = (productId) => {
  products.value = products.value.filter((p) => p.id !== productId);
};

const formatCurrency = (value) => {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
  }).format(value);
};

const chipColor = "primary";
</script>

<template>
  <VCard>
    <VCardText class="d-flex flex-column pb-0 mb-4">
      <VRow>
        <VCol cols="12" sm="12" md="12">
          <div class="d-flex align-center gap-4 flex-wrap">
            <AppTextField
              :model-value="props.searchQuery"
              placeholder="Código de Barra"
              clearable
              @update:model-value="emit('update:searchQuery', $event)"
              class="flex-grow-1"
            />

            <VChip
              label
              :color="chipColor"
              variant="tonal"
              density="default"
              size="small"
              draggable="false"
              class="ms-auto"
            >
              <span class="font-weight-medium">{{ products.length }}</span>
            </VChip>
          </div>
        </VCol>
      </VRow>
    </VCardText>

    <VCardText class="d-flex flex-column pb-0">
      <div
        class="scrollable-list-container"
        :class="{ 'show-scroll': products.length > 2 }"
      >
        <VList class="card-list" density="compact" nav>
          <VListItem v-if="products.length === 0">
            <VListItemTitle class="text-center text-medium-emphasis"
              >No hay productos en la lista.</VListItemTitle
            >
          </VListItem>

          <VListItem
            v-for="product in products"
            :key="product.id"
            class="rounded-0"
          >
            <template #prepend>
              <VCol cols="6" class="pa-0">
              <VTextField
                  v-model.number="product.cost"
                  type="number"
                  variant="outlined"
                  density="compact"
                  hide-details
                  single-line
                  class="cost-input-field text-center"
                >
                </VTextField>
              </VCol>
            </template>

            <VListItemTitle class="font-weight-medium me-4">{{
              product.title
            }}</VListItemTitle>
            <VListItemSubtitle class="me-4"
              >Item: {{ product.itemCode }}</VListItemSubtitle
            >

            <template #append>
              <div class="d-flex align-center">
                <span class="text-body-1 me-2">{{
                  formatCurrency(product.price * product.quantity)
                }}</span>
                <VBtn
                  icon="tabler-trash"
                  size="x-small"
                  variant="text"
                  color="error"
                  @click="removeProduct(product.id)"
                />
              </div>
            </template>
          </VListItem>
        </VList>
      </div>
    </VCardText>

    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined"> Cancelar </VBtn>
      <VBtn color="primary" variant="flat"> Imprimir </VBtn>
      <VBtn color="success" variant="flat"> Compartir </VBtn>
    </VCardActions>
  </VCard>
</template>

<style scoped>
/* Contenedor del scroll */
.scrollable-list-container {
  max-height: 100px; /* Ajusta esta altura según tus necesidades */
  overflow-y: hidden; /* Oculto por defecto */
  transition: overflow-y 0.3s ease-in-out; /* Transición suave si quieres */
}

/* Mostrar scroll solo cuando la clase 'show-scroll' esté presente */
.scrollable-list-container.show-scroll {
  overflow-y: auto; /* Muestra el scroll vertical */
}

/* Estilos de la lista y los botones */
.card-list .v-list-item {
  padding-inline: 0px !important;
}

/* Ajustes específicos para los botones de cantidad dentro del prepend */
.v-list-item__prepend .v-btn {
  min-width: unset !important;
  padding: 0 4px;
}

/* Ajuste para el texto de cantidad */
.v-list-item__prepend .text-body-2 {
  line-height: 1; /* Para que la altura del texto no afecte el espaciado entre botones */
}

/* Alineación vertical del contenido si es necesario */
.v-list-item__content {
  justify-content: center; /* Centra verticalmente el título/subtítulo */
}
</style>
