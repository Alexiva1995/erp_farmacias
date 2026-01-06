<script setup>
import OrderFilters from "@/components/OrderFilters.vue";
import OrderPacksTable from "@/components/OrderPacksTable.vue";
import OrderProductsTable from "@/components/OrderProductsTable.vue";
import OrderTicket from "@/components/OrderTicket.vue";
import OpenOrderCard from "@/components/cards/OpenOrderCard.vue";
import OrderClienteCard from "@/components/cards/OrderClienteCard.vue";
import BuysModal from "@/components/dialogs/BuysModal.vue";
import RegisterClientModal from "@/components/dialogs/ClientFormDialoge.vue";
import PackDetailsModal from "@/components/dialogs/PackDetailsModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import Swal from "sweetalert2";
import { onMounted, ref, watch, computed, reactive, nextTick } from "vue";

const activeTab = ref("products");
const packs = ref([]);
const totalPacks = ref(0);
const loadingPacks = ref(false);
const packsPage = ref(1);
const packsItemsPerPage = ref(10);
const showPackDetailsModal = ref(false);
const selectedPack = ref(null);

const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const filterSearchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);
const isStrictSearch = ref(false);
const discount = ref(0);
const discountMinProducts = ref(0);
const discountMaxProducts = ref(0);

const laboratories = ref([]);
const origins = ref([]);
const isLoadingFilters = ref(false);

const barcodeSearchQuery = ref("");
let barcodeInputTimer;
const BARCODE_LENGTH_THRESHOLD = 10;

const clientIdentification = ref("");
const showRegisterClientModal = ref(false);
const selectedClient = ref(null);
const isLoadingInitialOrder = ref(true);
const isPrinting = ref(false);

const selectedDisplayCurrency = ref("COP");

const recipeDiscountForPrint = ref(0);
const doctorDiscountForPrint = ref(0);
const companyDiscountForPrint = ref(0);
const discountTypeForPrint = ref(null);
const expirationDiscountForPrint = ref(0);
const speSurchargeAmount = ref(0);

const isFinishingOrder = ref(false);


const isSpecialTaxpayer = ref(false)


const newClientFormData = ref({
  id: null,
  identification_type: "",
  identification: "",
  name: "",
  last_name: "",
  email: "",
  phone: "",
  birthdate: "",
  company_id: null,
  address: "",
  is_spe: false,
});

const newClientFormErrors = reactive({
  id: "",
  identification: "",
  identification_type: "",
  name: "",
  last_name: "",
  email: "",
  phone: "",
  address: "",
  birthdate: "",
  company_id: "",
  is_spe: "",
});


const tableOptions = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
});

const companies = ref([]);

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const hasOpenOrder = ref(false);
const openOrderData = ref(null);
const orderData = ref(null);

const reservedOrderData = ref(null);

const orderItems = ref([]);
const itemsToPrint = ref([]);
const TotalToPrint = ref(0);
const speSurchargeAmountPrint = ref(0);

const showBuysModal = ref(false);

const paymentsForPrint = ref([]);
const changeAmountForPrint = ref(0);
const creditAmountForPrint = ref(0);
const creditForPrint = ref(false);

const selectedDiscountType = ref(null);
const activeDoctorOffers = ref([]);
const selectedDoctorOffer = ref(null);
const loadingDoctorOffers = ref(false);

const activePrescriptionOffers = ref([]);
const prescriptionFile = ref(null);
const activeCompanyOffers = ref([]);
const selectedCompanyId = ref(null);

const selectedCompany = ref(null); 
const selectedDoctor = ref(null);

const handleDoctorSelected = (id) => {
  selectedDoctor.value = id;
};

const currentPrescriptionDiscountPercentage = computed(() => {
  if (activePrescriptionOffers.value.length > 0) {
    return parseFloat(activePrescriptionOffers.value[0].discount_percentage);
  }
  return 0;
});

const currentGroupId = ref(null);

const fetchGeneralSettings = async () => {
  try {
    const { data } = await axios.get('/general-settings')
    isSpecialTaxpayer.value = data.special_taxpayer_status === 'activa'
  } catch (error) {
    console.error("Error al cargar configuración", error)
    toast.error("Error al cargar configuración");
  }
}


const fetchProducts = async () => {
  loading.value = true;
  const params = {
    q: filterSearchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    ...(currentGroupId.value !== null && {
      groupId: currentGroupId.value,
    }),
    isStrictSearch: isStrictSearch.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/tpv/order", { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loading.value = false;
  }
};

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchDoctorOffers = async () => {
  loadingDoctorOffers.value = true;
  try {
    const response = await axios.get("/tpv/promotions/doctor-offer", {
      params: {
        per_page: 100,
        sort_by: "id",
        sort_order: "desc",
      },
    });

    if (response.data.success) {
      activeDoctorOffers.value = response.data.data.map((offer) => ({
        id: offer.id,
        title: `${offer.doctor.name} - ${offer.discount}%`,
        value: offer.id,
        percentage: parseFloat(offer.discount),
        doctor_id: offer.doctor_id,
      }));
    }
  } catch (error) {
    console.error("Error fetching doctor offers:", error);
    toast.error("Error al cargar las ofertas de médicos.");
  } finally {
    loadingDoctorOffers.value = false;
  }
};

const fetchPrescriptionOffers = async () => {
  try {
    const response = await axios.get("/tpv/promotions/prescription-offer", {
      params: {
        is_active: true,
        per_page: 1,
        sort_by: "discount_percentage",
        sort_order: "desc",
      },
    });
    if (response.data.success) {
      activePrescriptionOffers.value = response.data.data;
    }
  } catch (error) {
    console.error("Error fetching prescription offers:", error);
  }
};

const fetchCompanyOffers = async (companyId = null) => {
  try {

  const params = {
      is_active: true,
      per_page: 100,
      sort_by: "id",
      order_by: "desc", // Cambiado de sort_order a order_by para tu Laravel
    };

    if (companyId) {
      params.search = companyId; 
    }

const response = await axios.get("/tpv/promotions/company-offer", { params });

    if (response.data && response.data.data) {
      activeCompanyOffers.value = response.data.data.map((offer) => {
        const scales = offer.scales || [];
        // Calculate min and max percentage for display
        let discountText = "";
        if (scales.length > 0) {
          const percentages = scales.map((s) =>
            parseFloat(s.discount_percentage)
          );
          const minP = Math.min(...percentages);
          const maxP = Math.max(...percentages);
          discountText = minP === maxP ? `${minP}%` : `${minP}-${maxP}%`;
        }

        return {
          title: `${offer.company?.name || "N/A"} ${
            discountText ? "- " + discountText : ""
          }`,
          value: offer.company_id,
          scales: scales,
          id: offer.id,
          current_discount: offer.company?.current_discount || 0,
        };
      });
    }
  } catch (error) {
    console.error("Error fetching company offers:", error);
  }
};

/*const handlePrescriptionFileSelected = (file) => {
  prescriptionFile.value = file;
  if (file && activePrescriptionOffers.value.length > 0) {
    // Apply discount from the first active offer (highest discount due to sort)
    const offer = activePrescriptionOffers.value[0];

    orderItems.value = orderItems.value.map((item) => {
      if (!item.originalPrice) {
        item.originalPrice = item.price;
        item.originalPriceBs = item.price_bs;
        item.originalPriceCop = item.price_cop;
      }

      const discountFactor = 1 - offer.discount_percentage / 100;

      return {
        ...item,
        price: item.originalPrice * discountFactor,
        price_bs: item.originalPriceBs * discountFactor,
        price_cop: item.originalPriceCop * discountFactor,

        discountApplied: true, // Standardize flag
        discountSource: "prescription",
        discountSourceId: offer.id,
        appliedDiscountPercentage: offer.discount_percentage,
      };
    });
    toast.success(
      `Descuento de receta del ${offer.discount_percentage}% aplicado.`
    );
  } else {
    // Revert changes if file removed or no offer
    handleDoctorDiscountSelected(null); // Reuse revert logic or make a shared revert function
    // Since handleDoctorDiscountSelected(null) reverts to originalPrice, it works for this too
    // BUT we should be careful if we want to support stacking (likely not based on UI)
    // For now, assuming mutually exclusive discounts based on selectedDiscountType
  }
};*/

const handlePrescriptionFileSelected = (file) => {
  prescriptionFile.value = file;
  if (file && activePrescriptionOffers.value.length > 0) {
    const offer = activePrescriptionOffers.value[0];
    toast.success(
      `Descuento de receta del ${offer.discount_percentage}% detectado.`
    );
  }
};

const handleDoctorDiscountSelected = (offerId) => {
  const offer = activeDoctorOffers.value.find((o) => o.value === offerId);
  selectedDoctorOffer.value = offer;
  if (offer) {
    toast.success(`Descuento de médico ${offer.percentage}% seleccionado.`);
  } else {
    selectedDoctorOffer.value = null;
    toast.info("Descuento de médico removido.");
  }
};

watch(() => selectedClient.value, async (newCliente, oldCliente) => {
  if (!newCliente) return;
  if (newCliente?.id === oldCliente?.id) return;

  if (newCliente.company_id) {
    await fetchCompanyOffers(newCliente.company_id);
    selectedDiscountType.value = "Empresa";
    selectedCompany.value = newCliente.company_id;
  } else {
    selectedCompany.value = null;
    await fetchCompanyOffers(); 
  }
}, { immediate: true });


const currentGlobalDiscountDetails = computed(() => {
  if (selectedDiscountType.value === "Empresa" && selectedCompany.value) {
    const offer = activeCompanyOffers.value.find(
      (o) => o.value === selectedCompany.value
    );
    if (offer && offer.current_discount > 0) {

      return {
        type: "Empresa",
        percentage: parseFloat(offer.current_discount),
        label: "Empresa",
      };
    }
  }

  if (selectedDiscountType.value === "Medico" && selectedDoctorOffer.value) {
    const offer = activeDoctorOffers.value.find(
      (o) => o.value === selectedDoctorOffer.value.value
    );

    if (offer && offer.percentage > 0) {
      return {
        type: "Medico",
        percentage: parseFloat(offer.percentage),
        label: "Médico",
      };
    }
  }

  if (
    selectedDiscountType.value === "Recipe" &&
    currentPrescriptionDiscountPercentage.value > 0
  ) {
    return {
      type: "Recipe",
      percentage: parseFloat(currentPrescriptionDiscountPercentage.value),
      label: "Recipe",
    };
  }
  return null;
});

const handleCompanyDiscountSelected = async (companyId) => {
  selectedCompanyId.value = companyId;
  if (companyId) {
    await fetchCompanyOffers(companyId); 
  }
  validateAndApplyCompanyDiscount();
};

const validateAndApplyCompanyDiscount = () => {

  if (activeCompanyOffers.value.length === 0) {
    console.warn("Validación abortada: No hay ofertas cargadas aún.");
    return;
  }

  if (!selectedCompanyId.value) {
    if (selectedDiscountType.value === "Empresa") {
      removeDiscount();
    }
    return;
  }

  const offer = activeCompanyOffers.value.find(
    (o) => o.value === selectedCompanyId.value
  );

  if (!offer) {
    console.warn('No se encontró oferta para el ID:', selectedCompanyId.value);
    selectedCompanyId.value = null;
    return;
  }

  const porcentaje = parseFloat(offer.current_discount || 0);
  if (porcentaje > 0) {
    toast.success(
      `Descuento de empresa ${porcentaje}% habilitado para esta orden.`
    );
  } else {
    selectedCompanyId.value = null;
    toast.info(
      `Esta empresa no cuenta con un descuento activo para el periodo actual.`
    );
  }
};

/*const validateAndApplyCompanyDiscount = () => {
  if (!selectedCompanyId.value) {
    if (selectedDiscountType.value === "Empresa") {
      removeDiscount();
    }
    return;
  }

  const offer = activeCompanyOffers.value.find(
    (o) => o.value === selectedCompanyId.value
  );
  if (!offer) return;

  // Calculate total quantity of items (excluding filtered items if any logic existed for that, but usually total quantity)
  // Assuming company discount applies to ALL items or requires total volume of order
  let totalQuantity = 0;
  orderItems.value.forEach((item) => {
    totalQuantity += item.selectedQuantity || 0;
  });

  // Find matching scale
  // scale: { min_volume, max_volume, discount_percentage }
  const validScale = offer.scales.find(
    (scale) =>
      totalQuantity >= scale.min_volume && totalQuantity <= scale.max_volume
  );

  if (validScale) {
    applyDiscount(parseFloat(validScale.discount_percentage), {
      type: "company",
      name: offer.title,
      id: offer.id,
    });
    // toast.success(`Descuento de empresa ${validScale.discount_percentage}% aplicado.`); // Optional: prevent spamming toasts on quantity change
  } else {
    removeDiscount();
    toast.warning(
      `La cantidad total (${totalQuantity}) no cumple con los rangos de volumen para el descuento de esta empresa.`
    );
  }
};*/

const applyDiscount = (percentage, source) => {
  orderItems.value = orderItems.value.map((item) => {
    // Exclude expiration items from global discounts
    if (item.discount_type === "expiration") {
      return item;
    }

    if (!item.originalPrice) {
      item.originalPrice = item.price;
      item.originalPriceBs = item.price_bs;
      item.originalPriceCop = item.price_cop;
    }

    const discountFactor = 1 - percentage / 100;

    return {
      ...item,
      price: item.originalPrice * discountFactor,
      price_bs: item.originalPriceBs * discountFactor,
      price_cop: item.originalPriceCop * discountFactor,
      discountApplied: true,
      discountSource: source.type,
      discountSourceId: source.id,
      appliedDiscountPercentage: percentage,
    };
  });
};

const removeDiscount = () => {
  orderItems.value = orderItems.value.map((item) => {
    // Exclude expiration items from global discount removal
    if (item.discount_type === "expiration") {
      return item;
    }

    if (item.originalPrice) {
      return {
        ...item,
        price: item.originalPrice,
        price_bs: item.originalPriceBs,
        price_cop: item.originalPriceCop,
        discountApplied: false,
        discountSource: null,
        discountSourceId: null,
        appliedDiscountPercentage: 0,
      };
    }
    return item;
  });
};

// Refactor handlePrescriptionFileSelected to use common apply/remove
// Leaving it separate for now as it was already implemented, but could reuse applyDiscount

watch(selectedDiscountType, (newValue) => {
  if (newValue !== "Medico") {
    selectedDoctorOffer.value = null;
    // Ensure we don't accidentally remove subscription/company discount if we just added one?
    // But here we switch types, so yes, clear others.
    // Since applyDiscount overwrites based on orderItems map logic using originalPrice, it should be fine to "remove" first
    // effectively resetting.
    if (selectedDiscountType.value === "Medico") removeDiscount();
  }
  if (newValue !== "Recipe") {
    prescriptionFile.value = null;
    if (selectedDiscountType.value === "Recipe") removeDiscount();
  }
  if (newValue !== "Empresa") {
    selectedCompanyId.value = null;
    if (selectedDiscountType.value === "Empresa") removeDiscount();
  }

  // Explicit removal when clearing type (newValue is null)
  if (!newValue) {
    removeDiscount();
  }
});

// Watch orderItems for quantity changes (mapped to string key) to re-validate company discount
// This avoids infinite loop since applying discount (changing prices) won't change the key
watch(
  () =>
    orderItems.value
      .map((i) => `${i.product_id}:${i.selectedQuantity}`)
      .join("|"),
  (newVal) => {
    if (selectedDiscountType.value === "Empresa" && selectedCompanyId.value) {
      validateAndApplyCompanyDiscount();
    }
  }
);

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    filterSearchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
    isStrictSearch,
    currentGroupId,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true }
);

const consultAllcomapanies = async () => {
  const companiesResponse = await axios.get("/crm/companies");
  companies.value = companiesResponse.data.data;
};

onMounted(() => {
  fetchSelectOptions();
  fetchProducts();
  consultAllcomapanies();
  fetchDoctorOffers();
  fetchPrescriptionOffers();
//  fetchCompanyOffers();
  fetchGeneralSettings();
});

const formatOrderItemForFrontend = (backendItem) => {
  const product = backendItem.product;
  const availableQuantity = product.lots_sum_quantity ?? 0;

  const discountFactor =
    backendItem.discount_type === "expiration" &&
    backendItem.discount_percentage
      ? 1 - backendItem.discount_percentage / 100
      : 1;

  return {
    order_detail_id: backendItem.id,
    product_id: product.id,
    title: product.name,
    active_ingredient: product.active_ingredient,
    itemCode: product.barcode,
    price:
      parseFloat(backendItem.unit_cost) ||
      (parseFloat(product.sale_price) || 0) * discountFactor,
    price_before_discount:
      parseFloat(backendItem.unit_cost) ||
      (parseFloat(product.sale_price) || 0),
    price_bs: (parseFloat(product.price_bs) || 0) * discountFactor,
    price_cop: (parseFloat(product.price_cop) || 0) * discountFactor,
    unitCost: parseFloat(product.unit_cost) || 0,
    basePrice: parseFloat(product.sale_price) || 0, // Store original base price
    original_price_usd: parseFloat(product.sale_price) || 0,
    original_price_bs: parseFloat(product.price_bs) || 0,
    original_price_cop: parseFloat(product.price_cop) || 0,
    availableQuantity:
      parseInt(product.valid_stock_sum) || parseInt(product.lots_sum_quantity),
    selectedQuantity: parseInt(backendItem.quantity) || 0,
    laboratory: product.laboratory ? product.laboratory.name : "N/A",
    taxRate: product.iva == 1 ? 0.16 : 0,
    pack_id: backendItem.pack_id || null,
    discount_percentage: parseFloat(backendItem.discount_percentage) || 0,
    discount_type: backendItem.discount_type || null,
    discount_source_id: backendItem.discount_source_id || null,
    original_pack_config: backendItem.pack_config || (backendItem.product?.pack_config) || null,
  };
};

const fetchOpenOrder = async () => {
  try {
    const response = await axios.get("/tpv/order/seller/my-open-order");
    if (
      response.data.data &&
      response.data.data.order &&
      response.data.data.order.pending_order
    ) {
      openOrderData.value = response.data.data.order.pending_order;
      reservedOrderData.value = response.data.data.order.reserved_order;
      selectedClient.value = response.data.data.order.pending_order.client;
      hasOpenOrder.value = true;
      if (openOrderData.value.currency) {
        selectedDisplayCurrency.value =
          openOrderData.value.currency.toUpperCase();
      }
      if (openOrderData.value.details) {
        orderItems.value = openOrderData.value.details.map((item) =>
          formatOrderItemForFrontend(item)
        );
      } else {
        orderItems.value = [];
      }
    } else {
      hasOpenOrder.value = false;
      openOrderData.value = null;
      reservedOrderData.value = null;
      selectedClient.value = null;
      orderItems.value = [];
    }
  } catch (error) {
    console.error("Error al verificar orden abierta del vendedor:", error);
    hasOpenOrder.value = false;
    openOrderData.value = null;
    selectedClient.value = null;
    orderItems.value = [];
  } finally {
    isLoadingInitialOrder.value = false;
  }
};

onMounted(async () => {
  await fetchOpenOrder();
  fetchSelectOptions();
  fetchProducts();
  consultAllcomapanies();
  fetchDoctorOffers();
  fetchPrescriptionOffers();
  //fetchCompanyOffers();
});


onMounted(async () => {
  try {
    const { data } = await axios.get('/user/config');
    if (data.config && data.config.sort_products_orders) {
      const [key, order] = data.config.sort_products_orders.split('|');
      sortBy.value = key;
      orderBy.value = order;
      tableOptions.value.sortBy = [{ key, order }];
    }
  } catch (error) {
    console.error("Error al cargar configuración");
  }
});


const totalOrderCost = computed(() => {
  let totalCost = 0;
  orderItems.value.forEach((item) => {
    const cost = item.unitCost || 0;
    const quantity = item.selectedQuantity || 0;
    totalCost += cost * quantity;
  });
  return parseFloat(totalCost.toFixed(2));
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  
  // Si la tabla cambia el orden por clic en columna
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  }
};

const addProductToQuotation = async ({ productId, quantity }) => {};

const handleClearFilters = () => {
  filterSearchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value = null;
  stockStatusFilter.value = null;
  isStrictSearch.value = false;
  sortBy.value = undefined;
  orderBy.value = undefined;
};

watch(
  [filterSearchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter],
  () => {
    page.value = 1;
  }
);

watch(barcodeSearchQuery, (newValueBar) => {
  clearTimeout(barcodeInputTimer);
  if (!newValueBar) {
    return;
  }
  if (newValueBar.length >= BARCODE_LENGTH_THRESHOLD) {
    barcodeInputTimer = setTimeout(async () => {
      await addProductToOrderByBarcode(newValueBar);
      barcodeSearchQuery.value = "";
    }, 300);
  }
});

const addProductToOrderByBarcode = async (barcode) => {
  try {
    const response = await axios.get(`/barcode/${barcode}`);
    const productDetails = response.data;

    await addProductToOrder({ productId: productDetails.id, quantity: 1 });
  } catch (error) {
    console.error(
      "Error al agregar producto por código de barras:",
      error.response ? error.response.data : error.message
    );
    toast.error(
      "Producto no encontrado o error al agregar por código de barras."
    );
  }
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const verifyClient = async (identification) => {
  clientIdentification.value = identification;

  if (!identification) {
    toast.warning("Por favor, ingrese un número de identificación.");
    return;
  }

  try {
    const response = await axios.get(`/tpv/order/client/${identification}`);
    const responseData = response.data.data;

    if (responseData.found === false) {
      toast.info("Cliente no encontrado. Por favor, regístrelo.");
      newClientFormData.value = {
        ...newClientFormData.value,
        identification: identification,
      };
      showRegisterClientModal.value = true;
    } else {
      const clientData = responseData.client;

      if (clientData.available_discount) {
        const { discount_percentage, max_volume, min_volume } =
          clientData.available_discount;
        discount.value = Number(discount_percentage);
        discountMinProducts.value = min_volume;
        discountMaxProducts.value = max_volume;
      } else {
        discount.value = 0;
        discountMinProducts.value = 0;
        discountMaxProducts.value = 0;
      }

      selectedClient.value = clientData;
      toast.success(
        `Cliente ${clientData.name} ${clientData.last_name} encontrado.`
      );

      if (responseData.found_open_order) {
        hasOpenOrder.value = true;
        openOrderData.value = responseData.order;

        if (openOrderData.value.currency) {
          selectedDisplayCurrency.value =
            openOrderData.value.currency.toUpperCase();
        }
      } else {
        hasOpenOrder.value = false;
        openOrderData.value = null;
        await addOrden(clientData.id);
      }
    }
  } catch (error) {
    console.error("Error al verificar cliente:", error);
    console.log(error.message);
    toast.error("Error al verificar el cliente.");
  }
};

const addOrden = async (id) => {
  const params = {
    client_id: id,
    seller_id: currentUser.value?.id || 3,
    currency: selectedDisplayCurrency.value,
  };
  try {
    const response = await axios.post("/tpv/orders", params);
    openOrderData.value = response.data.data.order;
    selectedClient.value = response.data.data.order.client;
    hasOpenOrder.value = true;
    toast.success("Orden creada exitosamente.");
    return response.data.data.order;
  } catch (error) {
    console.error("Error al agregar la orden:", error);
    toast.error("Error al agregar la orden.");
    return null;
  }
};

function cargarErrores(errores) {
  newClientFormErrors.id = errores.id ? errores.id.join(", ") : "";
  newClientFormErrors.identification = errores.identification
    ? errores.identification.join(", ")
    : "";
  newClientFormErrors.identification_type = errores.identification_type
    ? errores.identification_type.join(", ")
    : "";
  newClientFormErrors.name = errores.name ? errores.name.join(", ") : "";
  newClientFormErrors.last_name = errores.last_name
    ? errores.last_name.join(", ")
    : "";
  newClientFormErrors.email = errores.email ? errores.email.join(", ") : "";
  newClientFormErrors.phone = errores.phone ? errores.phone.join(", ") : "";
  newClientFormErrors.address = errores.address
    ? errores.address.join(", ")
    : "";
  newClientFormErrors.birthdate = errores.birthdate
    ? errores.birthdate.join(", ")
    : "";
  newClientFormErrors.company_id = errores.company_id
    ? errores.company_id.join(", ")
    : "";
  newClientFormErrors.is_spe = errores.is_spe ? errores.is_spe.join(", ") : ""; // Nuevo campo agregado
}

const handleSaveNewClient = async (formData) => {
  try {
    let respuesApi = await axios.post("/crm/clients", formData);
    if (respuesApi.status == 200) {
      toast.success("El cliente se ha guardado correctamente");
      handleCloseRegisterModal();
      addOrden(respuesApi.data.data.id);
    }
  } catch (error) {
    toast.error("Error al crear el cliente");
    let errores = { ...error.response.data.data.errors };
    cargarErrores(errores);
  }
};

const handleCloseRegisterModal = () => {
  showRegisterClientModal.value = false;
  limpiarDatosFormulario();
  limpiarErroresFormulario();
};

function limpiarDatosFormulario() {
  newClientFormData.id = null;
  newClientFormData.identification = "";
  newClientFormData.identification_type = "";
  newClientFormData.name = "";
  newClientFormData.last_name = "";
  newClientFormData.email = "";
  newClientFormData.phone = "";
  newClientFormData.address = "";
  newClientFormData.birthdate = null;
  newClientFormData.company_id = "";
  newClientFormData.is_spe = false; // Nuevo campo agregado
}

function limpiarErroresFormulario() {
  newClientFormErrors.id = "";
  newClientFormErrors.identification = "";
  newClientFormErrors.identification_type = "";
  newClientFormErrors.name = "";
  newClientFormErrors.last_name = "";
  newClientFormErrors.email = "";
  newClientFormErrors.phone = "";
  newClientFormErrors.address = "";
  newClientFormErrors.birthdate = "";
  newClientFormErrors.company_id = "";
  newClientFormErrors.is_spe = ""; // Nuevo campo agregado
}

const clearFormErrors = () => {
  newClientFormErrors.value = {};
};

const handleCurrencyChanged = async (newCurrency) => {
  try {
    if (hasOpenOrder.value && openOrderData.value?.id) {
      // Calculate totals for the new currency to satisfy backend validation
      let calculatedTotal = 0;
      let calculatedTotalUSD = 0;
      let calculatedTotalCost = 0;

      orderItems.value.forEach((item) => {
        const qty = item.selectedQuantity || 0;
        calculatedTotalCost += (item.unitCost || 0) * qty;

        // Calculate USD Total (Use basePrice if available, else fallback)
        const usdPrice =
          item.basePrice ||
          (selectedDisplayCurrency.value === "USD" ? item.price : 0);
        // Note: If current view is not USD, and basePrice missing, we can't easily guess USD price.
        // But basePrice should be present for all valid products now.
        calculatedTotalUSD += usdPrice * qty;

        // Calculate Target Currency Total
        if (newCurrency === "BS") {
          calculatedTotal += (item.price_bs || 0) * qty;
        } else if (newCurrency === "COP") {
          calculatedTotal += (item.price_cop || 0) * qty;
        } else {
          // USD
          calculatedTotal += usdPrice * qty;
        }
      });

      await axios.patch(`/tpv/orders/${openOrderData.value.id}`, {
        currency: newCurrency,
        total_amount: calculatedTotal,
        total_amount_usd: calculatedTotalUSD,
        total_cost: calculatedTotalCost,
      });
      await fetchOpenOrder();
    }
    selectedDisplayCurrency.value = newCurrency;
  } catch (error) {
    console.error("Error updating currency:", error);
    toast.error("Error al actualizar la moneda de la orden.");
  }
};

const totalEligibleAmount = computed(() => {
  let total = 0;
  orderItems.value.forEach((item) => {
    // Exclude items with expiration discount from the eligible base
    if (item.discount_type === "expiration") {
      return;
    }
    const price = getItemPriceByCurrency(item, selectedDisplayCurrency.value);
    const quantity = item.selectedQuantity || 0;
    total += price * quantity;
  });
  return total;
});

const totalCompanyDiscountAmount = computed(() => {
  if (selectedDiscountType.value === "Empresa" && selectedCompanyId.value) {
    const offer = activeCompanyOffers.value.find(
      (o) => o.value === selectedCompanyId.value
    );
    const porcentaje = parseFloat(offer?.current_discount || 0);
    if (porcentaje > 0) {
      return totalEligibleAmount.value * (porcentaje / 100);
    }
  }
  return 0;
});

const totalDoctorDiscountAmount = computed(() => {
  if (selectedDiscountType.value === "Medico" && selectedDoctorOffer.value) {
    const porcentaje = parseFloat(selectedDoctorOffer.value.percentage || 0);
    if (porcentaje > 0) {
      return totalEligibleAmount.value * (porcentaje / 100);
    }
  }
  return 0;
});

const totalRecipeDiscountAmount = computed(() => {
  if (selectedDiscountType.value === "Recipe") {
    const porcentaje = parseFloat(
      currentPrescriptionDiscountPercentage.value || 0
    );
    if (porcentaje > 0) {
      return totalEligibleAmount.value * (porcentaje / 100);
    }
  }
  return 0;
});

const totalExpirationDiscountAmount = computed(() => {
  let total = 0;
  orderItems.value.forEach((item) => {
    const percentage = parseFloat(item.discount_percentage || 0);
    if (item.discount_type === "expiration" && percentage > 0) {
      let originalPrice = item.basePrice || 0;
      if (selectedDisplayCurrency.value === "BS") {
        originalPrice = item.original_price_bs || 0;
      } else if (selectedDisplayCurrency.value === "COP") {
        originalPrice = item.original_price_cop || 0;
        // For COP, we typically round up price before calculating total
        if (typeof roundUpToNearestHundred === "function") {
          originalPrice = roundUpToNearestHundred(originalPrice);
        } else {
          // Quick inline round up if function not in scope here (it is imported usually)
          originalPrice = Math.ceil(originalPrice / 100) * 100;
        }
      }

      const quantity = item.selectedQuantity || 0;
      const discountAmount = originalPrice * quantity * (percentage / 100);
      total += discountAmount;
    }
  });
  return total;
});


const appliesSpecialTax = computed(() => {
  return isSpecialTaxpayer.value && (selectedDisplayCurrency.value === 'USD' || selectedDisplayCurrency.value === 'COP');
});

const specialTaxAmount = computed(() => {
  if (!appliesSpecialTax.value) return 0;
  let tax = totalOrderAmount.value * 0.03;
  if (selectedDisplayCurrency.value === "COP") {
    tax = Math.ceil(tax / 100) * 100;
  }
  return tax;
});

const totalOrderAmountWithspecialTaxAmount = computed(() => {
  const base = totalOrderAmount.value || 0;
   if (appliesSpecialTax.value) {
    return base + specialTaxAmount.value;
  }
  return base;
});

const totalOrderAmount = computed(() => {
  const baseTotal = totalProductsAmount.value + totalIVAAmount.value;
  let discountToSubtract = 0;
  if (selectedDiscountType.value === "Empresa") {
    discountToSubtract = totalCompanyDiscountAmount.value;
  } else if (selectedDiscountType.value === "Medico") {
    discountToSubtract = totalDoctorDiscountAmount.value;
  } else if (selectedDiscountType.value === "Recipe") {
    discountToSubtract = totalRecipeDiscountAmount.value;
  }
  return baseTotal - discountToSubtract;
});

const totalOrderAmountSinDiscount = computed(() => {
  const baseTotal = totalProductsAmount.value + totalIVAAmount.value;
  let discountToSubtract = 0;
  return baseTotal;
});


const totalSPESavings = computed(() => {
  if (!selectedClient.value?.is_spe) return 0;

  let totalOriginalIVA = 0;
  orderItems.value.forEach((item) => {
    const price = getItemPriceByCurrency(item, selectedDisplayCurrency.value);
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;
    totalOriginalIVA += price * quantity * taxRate;
  });

  // El ahorro es el 75% del IVA original
  const savings = totalOriginalIVA * 0.75;
  if (selectedDisplayCurrency.value === "COP") {
    return roundUpToNearestHundred(savings);
  }
  return parseFloat(savings.toFixed(2));
});
const totalIVAAmount = computed(() => {
  let totalIVA = 0;
  orderItems.value.forEach((item) => {
    const price = getItemPriceByCurrency(item, selectedDisplayCurrency.value);
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;

    let ivaAmount = price * quantity * taxRate;

    // Si el cliente es SPE, aplicar solo el 25% del IVA (descuento del 75%)
    if (selectedClient.value?.is_spe) {
      ivaAmount = ivaAmount * 0.25;
    }

    totalIVA += ivaAmount;
  });
  return totalIVA;
});

const totalProductsAmount = computed(() => {
  let total = 0;
  orderItems.value.forEach((item) => {
    const price = getItemPriceByCurrency(item, selectedDisplayCurrency.value);
    const quantity = item.selectedQuantity || 0;
    total += price * quantity;
  });
  return total;
});
const totalAmountBs = computed(() => {
  let total = 0;
  orderItems.value.forEach((item) => {
    const basePriceBs = item.price_bs || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;

    // Aplicar descuento SPE si corresponde
    let effectiveTaxRate = taxRate;
    if (selectedClient.value?.is_spe) {
      effectiveTaxRate = taxRate * 0.25;
    }

    total += basePriceBs * quantity * (1 + effectiveTaxRate);
  });
  return total;
});

const totalAmountUsd = computed(() => {
  let total = 0;
  let subtotalProductosUSD = 0;

  orderItems.value.forEach((item) => {
    const basePriceUsd = item.original_price_usd || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;

    // Aplicar descuento SPE si corresponde
    let effectiveTaxRate = taxRate;
    if (selectedClient.value?.is_spe) {
      effectiveTaxRate = taxRate * 0.25;
    }
    subtotalProductosUSD += basePriceUsd * quantity;
    total += basePriceUsd * quantity * (1 + effectiveTaxRate);
  });

  // return total;

  let porcentaje = 0;

  if (selectedDiscountType.value === "Empresa" && selectedCompanyId.value) {
    const offer = activeCompanyOffers.value.find(
      (o) => o.value === selectedCompanyId.value
    );
    porcentaje = parseFloat(offer?.current_discount || 0);
  } else if (
    selectedDiscountType.value === "Medico" &&
    selectedDoctorOffer.value
  ) {
    porcentaje = parseFloat(selectedDoctorOffer.value.percentage || 0);
  } else if (selectedDiscountType.value === "Recipe") {
    porcentaje = parseFloat(currentPrescriptionDiscountPercentage.value || 0);
  }


  const descuentoUSD = subtotalProductosUSD * (porcentaje / 100);
  let finalTotalUSD = total - descuentoUSD;
  
  if (appliesSpecialTax.value) {
    let tax = finalTotalUSD * 0.03;
    finalTotalUSD += tax;
  }

  return finalTotalUSD;

});

const totalAmountCop = computed(() => {
  let total = 0;
  orderItems.value.forEach((item) => {
    const basePriceCop = item.price_cop || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;

    // Aplicar descuento SPE si corresponde
    let effectiveTaxRate = taxRate;
    if (selectedClient.value?.is_spe) {
      effectiveTaxRate = taxRate * 0.25;
    }

    total += basePriceCop * quantity * (1 + effectiveTaxRate);
  });
  return total;
});

const updateOrderTotalsInBackend = async () => {
  if (!openOrderData.value || !openOrderData.value.id) {
    return;
  }

  let total =
    selectedDisplayCurrency.value == "COP"
      ? roundUpToNearestHundred(totalOrderAmountWithspecialTaxAmount.value)
      : totalOrderAmountWithspecialTaxAmount.value.toFixed(2);

  try {
    const payload = {
      total_amount: total,
      total_amount_usd: totalAmountUsd.value,
      total_cost: totalOrderCost.value,
      currency: selectedDisplayCurrency.value,
      discount_type: selectedDiscountType.value,
    };

    await axios.patch(`/tpv/orders/${openOrderData.value.id}`, payload);
  } catch (error) {
    toast.error("Error al actualizar los totales de la orden.");
  }
};
/*
const updateOrderItemQuantity = async ({
  productId,
  quantity,
  orderDetailId,
}) => {
  if (quantity <= 0) {
    return;
  }

  if (!hasOpenOrder.value || !openOrderData.value || !openOrderData.value.id) {
    toast.error("Debe haber una orden abierta para modificar productos.");
    return;
  }

  try {
    let currentItem = null;
    let computedTotalQuantity = quantity;

    if (orderDetailId) {
      currentItem = orderItems.value.find(
        (item) => item.order_detail_id === orderDetailId
      );

      // Calculate cumulative quantity for split items (non-packs)
      if (currentItem && !currentItem.pack_id) {
        // Sum quantity of OTHER matching items + NEW quantity for THIS item
        const otherItemsQuantity = orderItems.value
          .filter(
            (item) =>
              item.product_id === productId &&
              !item.pack_id &&
              item.order_detail_id !== orderDetailId
          )
          .reduce((sum, item) => sum + item.selectedQuantity, 0);

        computedTotalQuantity = otherItemsQuantity + quantity;
      }
    }

    // Fallback if no orderDetailId or item not found (legacy behavior)
    if (!currentItem) {
      currentItem = orderItems.value.find(
        (item) => item.product_id === productId
      );
    }

    if (!currentItem) {
      toast.error(
        "Producto no encontrado en la orden para actualizar su cantidad."
      );
      return;
    }
    const payload = {
      product_id: productId,
      quantity: computedTotalQuantity,
      price_usd_unit: currentItem.basePrice || currentItem.price,
      price_at_product:
        currentItem.basePrice || currentItem.orderPrice || currentItem.price,
      currency_at_order: selectedDisplayCurrency.value,
    };

    const backendResponse = await axios.post(
      `/tpv/orders/${openOrderData.value.id}/items`,
      payload
    );
    // Force refresh of the order to reflect any backend-side logical splits (e.g. expiration offers)
    await fetchOpenOrder();
    toast.success("Cantidad actualizada correctamente.");
  } catch (error) {
    const errorMessage =
      error.response?.data?.message ||
      "Error al actualizar el producto en la orden. Inténtalo de nuevo.";
    toast.error(errorMessage);
    if (
      error.response &&
      error.response.status === 400 &&
      error.response.data.data
    ) {
      const { available_stock, requested_quantity, product_name } =
        error.response.data.data;
      toast.error(
        `Stock insuficiente para "${product_name}". Disponible: ${available_stock}. Solicitado: ${requested_quantity}.`
      );
    }
  }
};*/


const updateOrderItemQuantity = async ({
  productId,
  quantity,
  orderDetailId,
}) => {
  if (quantity <= 0) return;

  if (!hasOpenOrder.value || !openOrderData.value || !openOrderData.value.id) {
    toast.error("Debe haber una orden abierta para modificar productos.");
    return;
  }

  try {
    // 1. Buscamos el ítem específico usando el orderDetailId
    let currentItem = orderItems.value.find(
      (item) => item.order_detail_id === orderDetailId
    );

    // Fallback por seguridad
    if (!currentItem) {
      currentItem = orderItems.value.find((item) => item.product_id === productId);
    }

    if (!currentItem) {
      toast.error("Producto no encontrado en la orden.");
      return;
    }

    // 2. Lógica de cantidad acumulada
    let computedTotalQuantity = quantity;

    // Solo acumulamos si NO es un pack (los packs se manejan por líneas únicas)
    if (!currentItem.pack_id && orderDetailId) {
      const otherItemsQuantity = orderItems.value
        .filter(
          (item) =>
            item.product_id === productId &&
            !item.pack_id &&
            item.order_detail_id !== orderDetailId
        )
        .reduce((sum, item) => sum + item.selectedQuantity, 0);

      computedTotalQuantity = otherItemsQuantity + quantity;
    }

    // 3. Construcción del Payload con pack_id
    const payload = {
      product_id: productId,
      quantity: computedTotalQuantity,
      price_usd_unit: currentItem.basePrice || currentItem.price,
      price_at_product: currentItem.basePrice || currentItem.orderPrice || currentItem.price,
      currency_at_order: selectedDisplayCurrency.value,
      // --- CAMBIO CRUCIAL ---
      // Enviamos el pack_id si el ítem lo tiene para que el backend mantenga la relación
      pack_id: currentItem.pack_id || null, 
    };

    await axios.post(
      `/tpv/orders/${openOrderData.value.id}/items`,
      payload
    );
    await fetchOpenOrder();
    toast.success("Cantidad actualizada.");
  } catch (error) {
    // ... tu manejo de errores actual
    console.error("Error al actualizar cantidad:", error);
    toast.error(error.response?.data?.message || "Error al actualizar");
  }
};

const addProductToOrder = async ({
  productId,
  quantity,
  packId = null,
  customPrice = null,
}) => {
  if (quantity <= 0) {
    toast.error("La cantidad a agregar debe ser mayor que cero.");
    return;
  }

  if (!hasOpenOrder.value || !openOrderData.value || !openOrderData.value.id) {
    toast.error("Debe haber una orden abierta para agregar productos.");
    return;
  }

  try {
    const response = await axios.get(`/product/${productId}`);
    const productDetails = response.data;

    const availableQuantity = productDetails.lots_sum_quantity;

    const currentItemInOrder = orderItems.value.find(
      (item) => item.product_id === productId
    );
    const currentQuantityInOrder = currentItemInOrder
      ? currentItemInOrder.selectedQuantity
      : 0;
    const newTotalQuantity = currentQuantityInOrder + quantity;

    if (quantity > availableQuantity) {
      toast.error(
        `No hay suficiente stock para "${productDetails.name}". Disponible: ${availableQuantity}. Solicitado: ${quantity}.`
      );
      return;
    }

    const priceInSelectedCurrency =
      customPrice !== null
        ? parseFloat(customPrice)
        : getItemPriceByCurrency(productDetails, selectedDisplayCurrency.value);

    const payload = {
      product_id: productDetails.id,
      quantity: newTotalQuantity,
      price_usd_unit:
        customPrice !== null
          ? parseFloat(customPrice)
          : productDetails.sale_price,
      price_at_product: priceInSelectedCurrency,
      tax_rate_at_order: productDetails.iva == 1 ? 0.16 : 0,
      currency_at_order: selectedDisplayCurrency.value,
      pack_id: packId,
    };

    const backendResponse = await axios.post(
      `/tpv/orders/${openOrderData.value.id}/items`,
      payload
    );
    const backendOrderItem = backendResponse.data.data.order_item;
    const existingItemIndex = orderItems.value.findIndex(
      (item) => item.product_id === backendOrderItem.product_id
    );

    if (existingItemIndex !== -1) {
      orderItems.value[existingItemIndex] =
        formatOrderItemForFrontend(backendOrderItem);
      toast.success(
        `Cantidad de "${productDetails.name}" incrementada a ${backendOrderItem.quantity}.`
      );
    } else {
      const itemToAdd = formatOrderItemForFrontend(backendOrderItem);
      orderItems.value.push(itemToAdd);
      toast.success(`"${itemToAdd.title}" agregado a la orden.`);
    }
  } catch (error) {
    console.error(
      "Error al obtener o agregar el producto a la orden:",
      error.response ? error.response.data : error.message
    );
    const errorMessage =
      error.response?.data?.message ||
      "Error al agregar el producto a la orden. Inténtalo de nuevo.";
    toast.error(errorMessage);
    if (
      error.response &&
      error.response.status === 400 &&
      error.response.data.data
    ) {
      const { available_stock, requested_quantity, product_name } =
        error.response.data.data;
      toast.error(
        `Stock insuficiente para "${product_name}". Disponible: ${available_stock}. Solicitado: ${requested_quantity}.`
      );
    }
  }
};


watch(
  [totalOrderAmount, selectedDisplayCurrency],
  async (newValue, oldValue) => {
    if (!isFinishingOrder.value) {
      if (newValue[0] !== oldValue[0] || newValue[1] !== oldValue[1]) {
        if (hasOpenOrder.value && openOrderData.value?.id) {
          await updateOrderTotalsInBackend();
        }
      }
    }
  },
  { deep: false }
);

const getItemPriceByCurrency = (item, currency) => {
  if (currency === "BS") {
    return item.price_bs || 0;
  } else if (currency === "COP") {
    return item.price_cop || 0;
  } else {
    return item.price || item.sale_price || 0;
  }
};

const removeOrderItem = async (productIdToRemove) => {
 /* Swal.fire({
    title: "¿Estás seguro?",
    text: "¡Desea eliminar el producto!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Continuar",
    cancelButtonText: "Cancelar",
  }).then(async (result) => {
    if (result.isConfirmed) {*/
      if (
        !hasOpenOrder.value ||
        !openOrderData.value ||
        !openOrderData.value.id
      ) {
        toast.error("No hay una orden abierta para eliminar productos.");
        return;
      }
      try {
        const itemToRemove = orderItems.value.find(
          (item) => item.product_id === productIdToRemove
        );
        if (!itemToRemove || !itemToRemove.order_detail_id) {
          toast.error(
            "No se encontró el detalle del producto en la orden para eliminar."
          );
          return;
        }
        await axios.delete(
          `/tpv/orders/${openOrderData.value.id}/items/${itemToRemove.order_detail_id}`
        );
        orderItems.value = orderItems.value.filter(
          (item) => item.product_id !== productIdToRemove
        );
        toast.success("Producto eliminado de la orden.");
      } catch (error) {
        toast.error("Error al eliminar el producto de la orden.");
      }
    //}
  //});
};

const cancelarOrder = async () => {
  try {
    await axios.patch(`/tpv/orders/${openOrderData.value.id}/abandon`);
    toast.success("Orden abandonada exitosamente.");
    hasOpenOrder.value = false;
    openOrderData.value = null;
    selectedClient.value = null;
    orderItems.value = [];
  } catch (error) {
    console.error(
      "Error al abandonar la orden:",
      error.response ? error.response.data : error.message
    );
    const errorMessage =
      error.response?.data?.message ||
      "Error al abandonar la orden. Inténtalo de nuevo.";
    toast.error(errorMessage);
  }
};

const reserverOrder = async () => {
  try {
    const response = await axios.patch(
      `/tpv/order/${openOrderData.value.id}/reserve`
    );
    hasOpenOrder.value = false;
    openOrderData.value = null;
    selectedClient.value = null;
    orderItems.value = [];
    reservedOrderData.value = response.data.data.reserved_order;
    toast.success("Orden reservada exitosamente.");
  } catch (error) {
    console.error(
      "Error al reservar la orden:",
      error.response ? error.response.data : error.message
    );

    if (
      error.response?.data?.message.includes("Ya tienes una orden reservada")
    ) {
      try {
        const checkResponse = await axios.get(
          "/tpv/order/seller/my-open-order"
        );
        if (checkResponse.data.data) {
          openOrderData.value = checkResponse.data.data.order.pending_order;
          reservedOrderData.value =
            checkResponse.data.data.order.reserved_order;
          toast.info(
            "Ya hay una orden reservada. La orden abierta se mantiene."
          );
          return;
        }
      } catch (checkError) {
        console.error(
          "Error al verificar el estado de las órdenes:",
          checkError
        );
      }
    }
    console.error(
      "Error al reservar la orden:",
      error.response ? error.response.data : error.message
    );
    toast.error(errorMessage);
  }
};

const openBuysModal = () => {
  if (selectedDiscountType.value === "Recipe" && !prescriptionFile.value) {
    toast.error(
      "Debe adjuntar la foto de la receta para aplicar este descuento."
    );
    return;
  }
  showBuysModal.value = true;
};

const closeBuysModal = () => {
  showBuysModal.value = false;
};

/*const handleBuysCompletion = async (
  orderId,
  paymentsData,
  credit,
  changeAmount,
  changeAmountUSD,
  switchStates
) => {
  try {
    const balanceUsed = paymentsData.some(
      (payment) => payment.type === "balance"
    );

    let currentPercentage = 0;
    let currentSourceId = null;
    let currentTypeName = null;

  if (selectedDiscountType.value === 'Empresa' && selectedCompanyId.value) {
      const offer = activeCompanyOffers.value.find(o => o.value === selectedCompanyId.value);
      currentPercentage = parseFloat(offer?.current_discount || 0);
      currentSourceId = selectedCompanyId.value;
      currentTypeName = 'company';
    } else if (selectedDiscountType.value === 'Medico' && selectedDoctorOffer.value) {
      currentPercentage = parseFloat(selectedDoctorOffer.value.percentage || 0);
      currentSourceId = selectedDoctorOffer.value.id;
      currentTypeName = 'doctor';
    } else if (selectedDiscountType.value === 'Recipe' && prescriptionFile.value) {
      currentPercentage = parseFloat(currentPrescriptionDiscountPercentage.value)
      currentSourceId = activePrescriptionOffers.value[0]?.id;
      currentTypeName = 'recipe';
    }

    const payload = {
      order_id: orderId,
      payments: paymentsData,
      total_amount: totalOrderAmountWithspecialTaxAmount.value,
      currency: selectedDisplayCurrency.value,
      client_id: selectedClient.value?.id,
      seller_id: currentUser.value?.id,
      balance_used: balanceUsed,
      generate_invoice: switchStates.invoice_switch,
      credit: credit,
      changeAmount: changeAmount,
      changeAmountUSD: changeAmountUSD,
      spe: switchStates.spe,
      items: orderItems.value.map((item) => ({
        order_detail_id: item.order_detail_id,
        quantity: item.selectedQuantity,
        price: item.price,
        discount_percentage: currentPercentage > 0 ? currentPercentage : null,
        discount_type: currentPercentage > 0 ? currentTypeName : null,
        discount_source_id: currentPercentage > 0 ? currentSourceId : null,
      })),
    };

    const response = await axios.post(
      `/tpv/orders/${orderId}/complete`,
      payload
    );
    if (response.status === 200 || response.status === 201) {
      toast.success("¡Compra finalizada y registrada con éxito!");
      paymentsForPrint.value = [...paymentsData];
      changeAmountForPrint.value = changeAmount;
      creditAmountForPrint.value = totalOrderAmountWithspecialTaxAmount.value;
      creditForPrint.value = credit;
      creditAmountForPrint.value = totalOrderAmountWithspecialTaxAmount.value;
      creditForPrint.value = credit;

      // Manual calculation to ensure accuracy
      let manualExpTotal = 0;
      orderItems.value.forEach(item => {
         const p = parseFloat(item.discount_percentage || 0);
         if (item.discount_type === 'expiration' && p > 0) {
            const price = item.basePrice || 0;
            const qty = item.selectedQuantity || 0;
            manualExpTotal += price * qty * (p / 100);
         }
      });
      console.log("Manual Expiration Total:", manualExpTotal);
      expirationDiscountForPrint.value = manualExpTotal;
      speSurchargeAmount.value = specialTaxAmount.value;
      clientIdentification.value = "";
      await fetchProducts();
      showBuysModal.value = false;
      isPrinting.value = true;

      await nextTick();
      const printContents = document.getElementById("orderPrint");
      if (printContents) {
        const printWindow = window.open("", "", "height=600,width=800");
        printWindow.document.write(
          "<html><head><title>Farmacia Barrio Sucre</title>"
        );
        const styleSheets = document.styleSheets;
        for (let i = 0; i < styleSheets.length; i++) {
          const sheet = styleSheets[i];
          try {
            if (sheet.cssRules) {
              let cssText = "";
              for (let j = 0; j < sheet.cssRules.length; j++) {
                cssText += sheet.cssRules[j].cssText;
              }
              printWindow.document.write("<style>" + cssText + "</style>");
            } else if (sheet.href) {
              printWindow.document.write(
                '<link rel="stylesheet" href="' + sheet.href + '">'
              );
            }
          } catch (e) {
            console.warn(
              "No se pudo acceder a la hoja de estilo:",
              sheet.href || sheet,
              e
            );
          }
        }
        printWindow.document.write("</head><body>");
        printWindow.document.write(printContents.innerHTML);
        printWindow.document.write("</body></html>");
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
      } else {
        console.warn(
          "Elemento #orderPrint no encontrado para impresión tipo ticket. Imprimiendo toda la página."
        );
        window.print();
      }

      if (response.data.data.order) {
        hasOpenOrder.value = true;
        openOrderData.value = response.data.data.order;
        selectedClient.value = openOrderData.value.client;
        reservedOrderData.value = null;
        orderItems.value = openOrderData.value.details.map((item) =>
          formatOrderItemForFrontend(item)
        );
      } else {
        hasOpenOrder.value = false;
        openOrderData.value = null;
        selectedClient.value = null;
        orderItems.value = [];
        reservedOrderData.value = null;
        clientIdentification.value = "";
      }
    } else {
      toast.error(
        `Error inesperado al finalizar la compra: ${
          response.data.message || "Intente de nuevo."
        }`
      );
    }

    setTimeout(() => {
      isPrinting.value = false;
    }, 500);
  } catch (error) {
    console.error(
      "Error al finalizar la compra:",
      error.response ? error.response.data : error.message
    );
    const errorMessage =
      error.response?.data?.message ||
      "Hubo un problema al procesar su compra. Por favor, intente de nuevo.";
    toast.error(errorMessage);
    isPrinting.value = false;
    paymentsForPrint.value = [];
    changeAmountForPrint.value = 0;
    creditAmountForPrint.value = 0;
    creditForPrint.value = false;
  }
};*/

const resetFormSelectors = () => {
  selectedDiscountType.value = null;
  selectedCompanyId.value = null;
  selectedDoctorOffer.value = null;
  prescriptionFile.value = null;
  currentPrescriptionDiscountPercentage.value = 0;
};

const handleBuysCompletion = async (
  orderId,
  paymentsData,
  credit,
  changeAmount,
  changeAmountUSD,
  switchStates
) => {
  try {
    isFinishingOrder.value = true;
    const balanceUsed = paymentsData.some(
      (payment) => payment.type === "balance"
    );

    let currentPercentage = 0;
    let currentSourceId = null;
    let currentTypeName = null;

    if (selectedDiscountType.value === "Empresa" && selectedCompanyId.value) {
      const offer = activeCompanyOffers.value.find(
        (o) => o.value === selectedCompanyId.value
      );
      currentPercentage = parseFloat(offer?.current_discount || 0);
      currentSourceId = selectedCompanyId.value;
      currentTypeName = "company";
    } else if (
      selectedDiscountType.value === "Medico" &&
      selectedDoctorOffer.value
    ) {
      currentPercentage = parseFloat(selectedDoctorOffer.value.percentage || 0);
      currentSourceId = selectedDoctorOffer.value.id;
      currentTypeName = "doctor";
    } else if (
      selectedDiscountType.value === "Recipe" &&
      prescriptionFile.value
    ) {
      currentPercentage = parseFloat(
        currentPrescriptionDiscountPercentage.value
      );
      currentSourceId = activePrescriptionOffers.value[0]?.id;
      currentTypeName = "recipe";
    }

    let taxable_base = appliesSpecialTax.value ? totalOrderAmount.value : 0.00;
    let spe_surcharge_rate = appliesSpecialTax.value ? 3.00 : 0.00;
    let spe_surcharge_amount = appliesSpecialTax.value ? specialTaxAmount.value : 0.00;

    const formData = new FormData();
    formData.append("order_id", orderId);
    formData.append("total_amount", totalOrderAmountWithspecialTaxAmount.value);
    formData.append("currency", selectedDisplayCurrency.value);
    formData.append("client_id", selectedClient.value?.id || "");
    formData.append("seller_id", currentUser.value?.id || "");
    formData.append("balance_used", balanceUsed ? 1 : 0);
    formData.append("generate_invoice", switchStates.invoice_switch ? 1 : 0);
    formData.append("credit", credit ? 1 : 0);
    formData.append("changeAmount", changeAmount);
    formData.append("changeAmountUSD", changeAmountUSD);
    formData.append("spe", switchStates.spe ? 1 : 0);
    formData.append("payments", JSON.stringify(paymentsData));
    formData.append("taxable_base", taxable_base);
    formData.append("spe_surcharge_rate", spe_surcharge_rate);
    formData.append("spe_surcharge_amount", spe_surcharge_amount);

    const mappedItems = orderItems.value.map((item) => {
      // 1. Determine Base Price in Current Currency
      let finalPrice = getItemPriceByCurrency(
        item,
        selectedDisplayCurrency.value
      );

      let finalPriceBeforeDiscount = getItemPriceByCurrency(
        item,
        selectedDisplayCurrency.value
      );

      // 2. Determine Discount Details
      let dType = null;
      let dPercent = 0;
      let dSourceId = null;

      if (item.discount_type === "expiration") {
        // Priority: Expiration Discount (Item specific)
        dType = "expiration";
        dPercent = parseFloat(item.discount_percentage || 0);
        dSourceId = item.discount_source_id || null;
      } else if (currentPercentage > 0) {
        // Global Discount (Company/Doctor/Recipe)
        dType = currentTypeName;
        dPercent = currentPercentage;
        dSourceId = currentSourceId;
      }

      // 3. Apply Discount to Price
      // Note: Expiration items already have their price discounted in the view model.
      // Global discount items do not, so we calculate it here.
      if (dPercent > 0 && dType !== "expiration") {
        finalPrice = finalPrice * (1 - dPercent / 100);
      }

      // 4. Rounding for specific currencies if needed (e.g. COP)
      // getItemPriceByCurrency usually handles base rounding, but discount might introduce decimals.
      /*if (selectedDisplayCurrency.value === 'COP') {
          // finalPrice = roundUpToNearestHundred(finalPrice); 
          // Depending on if backend expects strict rounding or specific value
      }*/

      return {
        order_detail_id: item.order_detail_id,
        price: finalPrice,
        price_before_discount: finalPriceBeforeDiscount,
        discount_percentage: dPercent > 0 ? dPercent : null,
        discount_type: dType,
        discount_source_id: dSourceId,
      };
    });
    formData.append("items", JSON.stringify(mappedItems));

    // ADJUNTO DE IMAGEN: Solo si es tipo Recipe y hay archivo
    if (selectedDiscountType.value === "Recipe" && prescriptionFile.value) {
      formData.append("prescription_image", prescriptionFile.value);
    }
    const response = await axios.post(
      `/tpv/orders/${orderId}/complete`,
      formData,
      {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      }
    );

    if (response.status === 200 || response.status === 201) {
      toast.success("¡Compra finalizada y registrada con éxito!");
      prescriptionFile.value = null;
      changeAmountForPrint.value = changeAmount;
      creditAmountForPrint.value = totalOrderAmountWithspecialTaxAmount.value;
      creditForPrint.value = credit;
      expirationDiscountForPrint.value = totalExpirationDiscountAmount.value;
      speSurchargeAmount.value = specialTaxAmount.value;
      clientIdentification.value = "";
      await fetchProducts();
      recipeDiscountForPrint.value = totalRecipeDiscountAmount.value;
      doctorDiscountForPrint.value = totalDoctorDiscountAmount.value;
      companyDiscountForPrint.value = totalCompanyDiscountAmount.value;
      discountTypeForPrint.value = selectedDiscountType.value;
      const orderCompletada = response.data.data.orderCompletada;
      orderData.value = orderCompletada;
      itemsToPrint.value = JSON.parse(JSON.stringify(orderItems.value))
      TotalToPrint.value = parseFloat(orderData.value.total_amount);
      paymentsForPrint.value = orderData.value.payment_methods.filter(p => {
        const name = (p.method || "").toString().toUpperCase();
        return name !== 'N/A' && name !== '' && name !== 'UNDEFINED' && name !== 'NULL';
      });

      if (response.data.data.order) {
        hasOpenOrder.value = true;
        openOrderData.value = response.data.data.order;
        selectedClient.value = openOrderData.value.client;
        reservedOrderData.value = null;
        orderItems.value = openOrderData.value.details.map((item) =>
          formatOrderItemForFrontend(item)
        );
      } 
    }
  } catch (error) {
    console.error("Error al finalizar la compra:", error);
    toast.error("Hubo un problema al procesar su compra.");
      hasOpenOrder.value = false;
        openOrderData.value = null;
        selectedClient.value = null;
        orderItems.value = [];
        reservedOrderData.value = null;
        clientIdentification.value = "";
  }
};

const printTickeCompletion = async () => {
  if (!orderData.value) {
    console.error("No hay datos de orden para imprimir");
    return;
  }
  speSurchargeAmountPrint.value = parseFloat(orderData.value.spe_surcharge_amount || 0);
  isSpecialTaxpayer.value = parseFloat(orderData.value.spe_surcharge_amount) > 0;
  isPrinting.value = true;
  await nextTick();
  await new Promise(resolve => setTimeout(resolve, 600)); 
  const printContents = document.getElementById("orderPrint");

  if (printContents && printContents.innerHTML.trim() !== "") {
    const printWindow = window.open("", "", "height=600,width=800");
    printWindow.document.write("<html><head><title>Farmacia Barrio Sucre</title>");

    // Copiar estilos (el bloque try/catch que ya tienes)
    Array.from(document.styleSheets).forEach(sheet => {
      try {
        if (sheet.cssRules) {
          const css = Array.from(sheet.cssRules).map(r => r.cssText).join("");
          printWindow.document.write("<style>" + css + "</style>");
        } else if (sheet.href) {
          printWindow.document.write('<link rel="stylesheet" href="' + sheet.href + '">');
        }
      } catch (e) {
        if (sheet.href) printWindow.document.write('<link rel="stylesheet" href="' + sheet.href + '">');
      }
    });

    printWindow.document.write("</head><body>");
    printWindow.document.write(printContents.innerHTML);
    printWindow.document.write("</body></html>");
    
    printWindow.document.close();
    setTimeout(() => {
      printWindow.focus();
      printWindow.print();
      printWindow.close();
      isPrinting.value = false;
    }, 500);
  } else {
    alert("Error: El ticket está vacío. Intente de nuevo.");
    isPrinting.value = false;
  }
};

const fetchGroupProducts = async (groupId) => {
  if (!groupId) {
    toast.info("Este producto no pertenece a un grupo.");
    if (currentGroupId.value !== null) {
      currentGroupId.value = null;
    }
    return;
  }
  currentGroupId.value = groupId;
};

const fetchFailuresProducts = async (productId) => {
  try {
    const response = await axios.post('/tpv/product-failure', {
      product_id: productId,
    })
    toast.info("Reporte de falla guardado correctamente.");
  } catch (error) {
    if (error.response) {
      console.error('Errores de validación:', error.response.data.errors)
      toast.error("Hubo un problema al procesar su reporte de falla.");
    } else {
      console.error('Error de conexión:', error.message)
    }
  }
};

const handleBackFromGroupView = () => {
  currentGroupId.value = null;
};

const handleAddQuotationProducts = async (productsFromQuotation) => {
  if (!productsFromQuotation || productsFromQuotation.length === 0) {
    toast.info("La cotización no tiene productos o está vacía.");
    return;
  }

  for (const product of productsFromQuotation) {
    try {
      await addProductToOrder({
        productId: product.product_id,
        quantity: product.units,
      });
    } catch (error) {
      console.error(
        `Error al agregar el producto con ID ${product.product_id}:`,
        error
      );
    }
  }

  toast.success("Productos de la cotización agregados al pedido.");
  await fetchProducts();
};

const addReserverOrder = async () => {
  try {
    const response = await axios.patch(
      `/tpv/order/${reservedOrderData.value.id}/reserveAdd`
    );
    const { pending_order, reserved_order } = response.data.data;

    openOrderData.value = pending_order;
    reservedOrderData.value = reserved_order;
    selectedClient.value = pending_order.client;


    if (openOrderData.value.details) {
      orderItems.value = openOrderData.value.details.map((item) =>
        formatOrderItemForFrontend(item)
      );
    } else {
      orderItems.value = [];
    }
    hasOpenOrder.value = true;
    await nextTick();
    toast.success("Orden agregada exitosamente.");
    return response.data.data.order;
  } catch (error) {
    const errorMessage =
      error.response?.data?.message ||
      "Error al reservar la orden. Inténtalo de nuevo.";
    toast.error(errorMessage);
  }
};

const fetchPacks = async () => {
  loadingPacks.value = true;
  try {
    const response = await axios.get("/tpv/promotions/product-packs", {
      params: {
        page: packsPage.value,
        itemsPerPage: packsItemsPerPage.value,
      },
    });
    if (response.data.data) {
      packs.value = response.data.data;
      totalPacks.value = response.data.total || response.data.data.length;
    }
  } catch (error) {
    console.error("Error fetching packs:", error);
    toast.error("Error al cargar los packs.");
  } finally {
    loadingPacks.value = false;
  }
};

const updatePacksOptions = (options) => {
  packsPage.value = options.page;
  packsItemsPerPage.value = options.itemsPerPage;
  fetchPacks();
};

const handleViewPackDetails = async (item) => {
  try {
    const response = await axios.get(`/tpv/promotions/product-packs/${item.id}`);
    const packCompleto = response.data;
    selectedPack.value = packCompleto.data;
    showPackDetailsModal.value = true;
  } catch (error) {
    console.error("Error al obtener los detalles del pack:", error);
  }
};

/*
const handleAddPackToOrder = async ({ pack, quantity }) => {

  //if (!pack || !pack.pack_config) return;
  

  const productsToAdd = JSON.parse(pack.pack_config)
  if (Object.keys(productsToAdd).length === 0) {
    toast.warning("El pack no contiene configuración de productos.");
    return;
  }

  loading.value = true;
  let addedCount = 0;

  for (let i = 0; i < quantity; i++) {
    for (const [productId, config] of Object.entries(productsToAdd)) {
      try {
        // Handle both object {quantity: 1, sale_price: 1.2} and direct number formats
        const productQty =
          typeof config === "object" && config !== null
            ? config.quantity || 1
            : config;

        const productPrice =
          typeof config === "object" && config !== null
            ? config.sale_price !== undefined
              ? config.sale_price
              : null
            : null;

        await addProductToOrder({
          productId: parseInt(productId),
          quantity: parseInt(productQty),
          packId: pack.id,
          customPrice: productPrice,
          original_pack_config: pack.pack_config
        });
        addedCount++;
      } catch (e) {
        console.error(`Error adding product ${productId} from pack`, e);
      }
    }
  }
  loading.value = false;

  if (addedCount > 0) {
    toast.success(`Pack agregado (x${quantity}).`);
  }
};*/


const handleAddPackToOrder = async ({ pack, quantity }) => {



 // if (!pack || !pack.pack_config) return;



   let configStr = pack.pack_config;
  
  if (!configStr) {
    const itemWithConfig = orderItems.value.find(i => i.pack_id === pack.id && i.original_pack_config);
    configStr = itemWithConfig?.original_pack_config;
  }

  if (!configStr) {
    console.error("No se encontró la configuración del pack ID:", pack.id);
    return;
  }


  const productsToAdd = JSON.parse(pack.pack_config);
  loading.value = true;

  try {
  const itemsInOrderBelongingToPack = orderItems.value.filter(
      (item) => item.pack_id === pack.id
    );



if (itemsInOrderBelongingToPack.length > 0) {
      // CASO: EL PACK YA EXISTE EN LA ORDEN
      // Iteramos sobre los productos que ya están en el carrito

      for (const item of itemsInOrderBelongingToPack) {

        // Buscamos en la configuración cuánto debe aumentar este producto específico
        const unitsPerPack = productsConfig[item.product_id]?.quantity || productsConfig[item.product_id] || 1;
        const totalToAdd = unitsPerPack * quantity;

        // Llamamos a actualizar con la cantidad acumulada
        await updateOrderItemQuantity({
          productId: item.product_id,
          quantity: item.selectedQuantity + totalToAdd,
          orderDetailId: item.order_detail_id,
          packId: pack.id // IMPORTANTE: pasar el packId para que no se pierda
        });
      }
    } else {
      // CASO: EL PACK ES NUEVO (No hay productos con ese pack_id aún)
      for (const [productId, config] of Object.entries(productsToAdd)) {
        const unitsPerPack = typeof config === "object" ? (config.quantity || 1) : config;
        const productPrice = typeof config === "object" ? config.sale_price : null;

        await addProductToOrder({
          productId: parseInt(productId),
          quantity: unitsPerPack * quantity,
          packId: pack.id,
          customPrice: productPrice,
        });
      }
    }

    // Recorremos cada producto definido en la configuración del pack
    /*for (const [productId, config] of Object.entries(productsToAdd)) {
      const idNumeric = parseInt(productId);
      
      // 1. Obtenemos cuántas unidades de este producto vienen en 1 solo pack
      const unitsPerPack = typeof config === "object" && config !== null 
        ? parseInt(config.quantity || 1) 
        : parseInt(config);

      // 2. Calculamos cuánto vamos a añadir (unidades_del_pack * cantidad_de_packs_a_sumar)
      // Si quantity es 1 y unitsPerPack es 2, sumaremos 2 unidades.
      const amountToAdd = unitsPerPack * quantity;

      // 3. Buscamos el producto en la orden actual que pertenezca a este pack
      const existingItem = orderItems.value.find(
        (item) => item.product_id === idNumeric && item.pack_id === pack.id
      );

      if (existingItem) {
   
        await updateOrderItemQuantity({
          productId: idNumeric,
          quantity: existingItem.selectedQuantity + amountToAdd,
          orderDetailId: existingItem.order_detail_id,
        });
      } else {
        const productPrice = typeof config === "object" && config !== null ? config.sale_price : null;
        await addProductToOrder({
          productId: idNumeric,
          quantity: amountToAdd,
          packId: pack.id,
          customPrice: productPrice,
        });
      }
    }*/
    
    // Sincronizamos la interfaz con el backend
    await fetchOpenOrder();
    toast.success(`Pack actualizado: +${quantity} unidad(es) de pack.`);

  } catch (e) {
    console.error("Error al procesar el pack:", e);
    toast.error("Error al actualizar las cantidades del pack.");
  } finally {
    loading.value = false;
  }
};


watch(activeTab, (val) => {
  if (val === "packs" && packs.value.length === 0 && totalPacks.value === 0) {
    fetchPacks();
  }
});

/*
const handleAddPackToOrder = async ({ pack, quantity }) => {
  if (!pack || !pack.pack_config) return;

  const productsInPack = JSON.parse(pack.pack_config);
  if (Object.keys(productsInPack).length === 0) {
    toast.warning("El pack no tiene productos configurados.");
    return;
  }

  loading.value = true;
  
  try {
    // 1. Agrupamos todos los productos del pack para enviarlos de forma eficiente
    for (const [productId, config] of Object.entries(productsInPack)) {
      const idNumeric = parseInt(productId);
      
      // Calculamos cuántas unidades totales se van a agregar de este producto
      // (Cantidad base en el pack * cantidad de packs solicitados)
      const baseQtyInPack = typeof config === "object" ? parseInt(config.quantity || 1) : parseInt(config);
      const totalNewQty = baseQtyInPack * quantity;

      const productPrice = typeof config === "object" ? config.sale_price : null;

      // Buscamos si ya existe en la orden LOCALMENTE
      const existingItem = orderItems.value.find(
        (item) => Number(item.product_id) === idNumeric && Number(item.pack_id) === Number(pack.id)
      );

      if (existingItem) {
        // Si existe, actualizamos cantidad sumando la nueva
        await updateOrderItemQuantity({
          productId: idNumeric,
          quantity: existingItem.selectedQuantity + totalNewQty,
          orderDetailId: existingItem.order_detail_id,
        });
      } else {
        // Si no existe, lo creamos
        await addProductToOrder({
          productId: idNumeric,
          quantity: totalNewQty,
          pack_id: pack.id,
          customPrice: productPrice,
          original_pack_config: pack.pack_config
        });
      }
      
      // CRUCIAL: Refrescamos la orden después de cada producto del pack 
      // para que el siguiente producto vea la lista actualizada y no duplique.
      await fetchOpenOrder();
    }

    toast.success(`Pack "${pack.name}" procesado correctamente.`);
  } catch (e) {
    console.error("Error al procesar el pack:", e);
    toast.error("Error al agregar algunos productos del pack.");
  } finally {
    loading.value = false;
  }
};

*/
const itemsForTicket = computed(() => {
  const globalDiscount = currentGlobalDiscountDetails.value;
  
  // Si no hay nada que imprimir, retornamos array vacío
  if (!itemsToPrint.value || itemsToPrint.value.length === 0) return [];

  return itemsToPrint.value.map(item => {
    // Si el item ya tiene descuento por vencimiento, lo dejamos igual
    if (item.discount_type === 'expiration') {
      return { ...item };
    }

    // Normalizamos la cantidad (Laravel usa 'quantity', el carrito local 'selectedQuantity')
    const qty = item.selectedQuantity || item.quantity || 1;
    
    if (globalDiscount && globalDiscount.percentage > 0) {
      const factor = 1 - (globalDiscount.percentage / 100);
      
      return {
        ...item,
        // Usamos el precio base y lo multiplicamos por el factor y la cantidad
        price: (item.price_before_discount * factor) * qty,
        price_bs: (item.original_price_bs * factor) * qty,
        price_cop: (item.original_price_cop * factor) * qty,
        price_before_discount: item.price_before_discount * qty,
        selectedQuantity: qty // Aseguramos que el ticket vea la cantidad
      };
    }

    // Si no hay descuento global, devolvemos el item con su cantidad normalizada
    return { 
      ...item, 
      selectedQuantity: qty 
    };
  });
});
const handleExternalSort = async (sortData) => {
  sortBy.value = sortData.key;
  orderBy.value = sortData.order;
  tableOptions.value.sortBy = [{ key: sortData.key, order: sortData.order }];
  try {
    await axios.post('/user/update-sort-config', {
      sortBy: sortData.key,
      orderBy: sortData.order
    });
    toast.success("Orden guardada como preferida");
  } catch (error) {
    console.error("Error al guardar preferencia:", error);
  }
};
</script>
<template>
  <div>
    <div v-if="isLoadingInitialOrder">
      <p>Cargando sesión de orden...</p>
    </div>

    <div v-else-if="hasOpenOrder">
      <OpenOrderCard
        v-model:searchQuery="barcodeSearchQuery"
        :order-products="orderItems"
        :order="openOrderData"
        :order-reserved="reservedOrderData"
        :total-products-amount="totalProductsAmount"
        :total-iva-amount="totalIVAAmount"
        :total-order-amount="totalOrderAmount"
        :company-discount-total="totalCompanyDiscountAmount"
        :doctor-discount-total="totalDoctorDiscountAmount"
        :recipe-discount-total="totalRecipeDiscountAmount"
        :expiration-discount-total="totalExpirationDiscountAmount"
        :cliente="selectedClient"
        :selected-display-currency="selectedDisplayCurrency"
        @currency-changed="handleCurrencyChanged"
        @update-quantity="updateOrderItemQuantity"
        @remove-item="removeOrderItem"
        @cancelar-order="cancelarOrder"
        @reserve-order="reserverOrder"
        @open-buys-modal="openBuysModal"
        @add-quotation-products="handleAddQuotationProducts"
        @add-reserved-order="addReserverOrder"
        v-model:selected-discount-type="selectedDiscountType"
        :active-doctor-offers="activeDoctorOffers"
        :prescription-discount-percentage="currentPrescriptionDiscountPercentage"
        :active-company-offers="activeCompanyOffers"
        @doctor-discount-selected="handleDoctorDiscountSelected"
        @prescription-file-selected="handlePrescriptionFileSelected"
        @company-discount-selected="handleCompanyDiscountSelected"
        :global-discount="currentGlobalDiscountDetails"
        @add-pack="handleAddPackToOrder"
        :is-special-taxpayer="isSpecialTaxpayer"
      />
    </div>
    <div v-else>
      <OrderClienteCard
        v-model="clientIdentification"
        @verify-client="verifyClient"
      />
    </div>

        <OrderFilters
          v-model:searchQuery="filterSearchQuery"
          v-model:selectedLaboratory="selectedLaboratory"
          v-model:selectedOrigin="selectedOrigin"
          v-model:stockStatusFilter="stockStatusFilter"
          v-model:isStrictSearch="isStrictSearch"
          :laboratories="laboratories"
          :origins="origins"
          :loading="isLoadingFilters"
          @clear="handleClearFilters"
          @sort="handleExternalSort"
          @back="handleBackFromGroupView"
        >
        </OrderFilters>

        <OrderProductsTable
          :products="products"
          :loading="loading"
          :total-product="totalProduct"
          v-model:items-per-page="itemsPerPage"
          v-model:page="page""
          :discount-min-products="discountMinProducts"
          :discount-max-products="discountMaxProducts"
          :current-discount="discount"
          :order-items="orderItems"
          :options="tableOptions"
          @update:options="updateTableOptions"
          @add-product="addProductToOrder"
          @view-group-products="fetchGroupProducts"
          @failures-products="fetchFailuresProducts"
          @view-pack-details="handleViewPackDetails"
          @add-pack="handleAddPackToOrder"
        />

    <!--   <OrderPacksTable
          :packs="packs"
          :loading="loadingPacks"
          :total-packs="totalPacks"
          :items-per-page="packsItemsPerPage"
          :page="packsPage"
          @update:options="updatePacksOptions"
          @add-pack="handleAddPackToOrder"
          @view-pack-details="handleViewPackDetails"
        />-->

    <PackDetailsModal
      v-model:isDialogVisible="showPackDetailsModal"
      :pack="selectedPack"
    />

    <RegisterClientModal
      :companies="companies"
      :modalFormulario="showRegisterClientModal"
      titulo="Registrar Nuevo Cliente"
      :formData="newClientFormData"
      :formError="newClientFormErrors"
      @modalClose="handleCloseRegisterModal"
      @save="handleSaveNewClient"
      @clearErrorForm="clearFormErrors"
    />

    <BuysModal
      v-model:is-dialog-visible="showBuysModal"
      :order-products="orderItems"
      :order-data="openOrderData"
      :total-amount="totalOrderAmount"
      :selected-currency="selectedDisplayCurrency"
      @modal-closed="closeBuysModal"
      @purchase-completed="handleBuysCompletion"
      :company-discount-total="totalCompanyDiscountAmount"
      :selected-discount-type="selectedDiscountType"
      :doctor-discount-total="totalDoctorDiscountAmount"
      :recipe-discount-total="totalRecipeDiscountAmount"
      :expiration-discount-total="totalExpirationDiscountAmount"
      :active-doctor-offers="activeDoctorOffers"
      :prescription-discount-percentage="currentPrescriptionDiscountPercentage"
      :active-company-offers="activeCompanyOffers"
      :global-discount="currentGlobalDiscountDetails"
      :is-special-taxpayer="isSpecialTaxpayer"
      @printTicke-completed="printTickeCompletion"
    />

   <div
  id="orderPrint"
  :style="isPrinting ? 'position: fixed; left: 0; top: 0; z-index: 9999; background: white; width: 80mm;' : 'position: absolute; left: -9999px;'"
>
      <OrderTicket
        v-if="orderData"
        :order-data="orderData"
        :order-products="itemsForTicket"
        :total-amount="TotalToPrint"
        :selected-currency="selectedDisplayCurrency"
        :payments="paymentsForPrint"
        :change-amount="changeAmountForPrint"
        :credit-amount="creditAmountForPrint"
        :credit="creditForPrint"
        :company-discount-total="companyDiscountForPrint"
        :selected-discount-type="discountTypeForPrint"
        :doctor-discount-total="doctorDiscountForPrint"
        :recipe-discount-total="recipeDiscountForPrint"
        :expiration-discount-total="expirationDiscountForPrint"
        :is-special-taxpayer="isSpecialTaxpayer"
        :spe-surcharge-amount="speSurchargeAmountPrint"
      />
    </div>
  </div>
</template>
