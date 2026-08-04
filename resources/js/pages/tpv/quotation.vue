<script setup>
import QuotationCard from "@/components/cards/QuotationCard.vue";
import QuotationProducts from "@/components/cards/QuotationProducts.vue";
import QuotationFilters from "@/components/QuotationFilters.vue";
import QuotationTable from "@/components/QuotationTable.vue";
import QuotationTicket from "@/components/QuotationTicket.vue";
import RegisterClientModal from "@/components/dialogs/ClientFormDialoge.vue";
import axios from "@/plugins/axios";
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { toast } from "@/plugins/sweetalert";
import { useQuotationClient } from "@/composables/useQuotationClient";
import { useBrandingStore } from "@/stores/useBrandingStore";

const {
  selectedClient,
  clientSearchQuery,
  clientIdentification,
  activeDoctorOffers,
  activePrescriptionOffers,
  activeCompanyOffers,
  loadingDoctorOffers,
  fetchDoctorOffers,
  fetchPrescriptionOffers,
  fetchCompanyOffers,
  verifyClient: verifyClientComposable,
  fetchSearchedClient
} = useQuotationClient();

const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);
const isLoadingFilters = ref(false);
const isSaving = ref(false);

const selectedDiscountType = ref(null);
const selectedDoctorOffer = ref(null);

const prescriptionFile = ref(null);
const selectedCompanyId = ref(null);
const selectedCompany = ref(null);

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

const currentPrescriptionDiscountPercentage = computed(() => {
  if (
    selectedDiscountType.value === "Recipe" &&
    activePrescriptionOffers.value.length > 0
  ) {
    return parseFloat(activePrescriptionOffers.value[0].discount_percentage);
  }
  return 0;
});

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const showRegisterClientModal = ref(false);

// Persistencia de Cotización
const quotationItems = ref([]);

const saveQuotationToLocalStorage = () => {
  localStorage.setItem("tpv_current_quotation", JSON.stringify(quotationItems.value));
};

const loadQuotationFromLocalStorage = () => {
  const saved = localStorage.getItem("tpv_current_quotation");
  if (saved) {
    try {
      quotationItems.value = JSON.parse(saved);
    } catch (e) {
      console.error("Error al cargar cotización guardada:", e);
    }
  }
};

watch(quotationItems, () => {
  saveQuotationToLocalStorage();
}, { deep: true });

const barcodeSearchQuery = ref("");
const filterSearchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);

const laboratories = ref([]);
const origins = ref([]);
const selectedGroupId = ref(null);

const brandingStore = useBrandingStore();

const availableCurrencies = computed(() => {
  const defaults = ["USD", "BS", "COP"];
  const configured = brandingStore.settings?.tpv_payment_methods;
  if (!configured) return defaults;
  return defaults.filter(currency => configured[currency] && configured[currency].enabled !== false);
});

const defaultCurrency = computed(() => {
  return brandingStore.settings?.default_currency || "USD";
});

const selectedDisplayCurrency = ref(defaultCurrency.value);

watch(
  defaultCurrency,
  (newVal) => {
    if (newVal && availableCurrencies.value.includes(newVal)) {
      selectedDisplayCurrency.value = newVal;
    }
  },
  { immediate: true }
);

const quotationDetails = ref(null);
const isPrinting = ref(false);

let barcodeInputTimer;
const BARCODE_LENGTH_THRESHOLD = 10;

const getItemPriceByCurrency = (item, currency) => {
  if (currency === "BS" || currency === "Bs") {
    return item.price_bs || 0;
  } else if (currency === "COP") {
    return item.price_cop || 0;
  } else {
    // Por defecto o si es 'USD'
    return item.price || 0;
  }
};

// Computed: porcentaje de descuento global activo según tipo seleccionado
const globalDiscountPercentage = computed(() => {
  if (selectedDiscountType.value === "Empresa" && selectedCompanyId.value) {
    const offer = activeCompanyOffers.value.find(
      (o) => o.value === selectedCompanyId.value,
    );
    return parseFloat(offer?.current_discount || 0);
  } else if (
    selectedDiscountType.value === "Medico" &&
    selectedDoctorOffer.value
  ) {
    return parseFloat(selectedDoctorOffer.value.percentage || 0);
  } else if (selectedDiscountType.value === "Recipe") {
    return parseFloat(currentPrescriptionDiscountPercentage.value || 0);
  }
  return 0;
});

// Compatibilidad con código que llama la función directamente
const getGlobalDiscountPercentage = () => globalDiscountPercentage.value;

const calculateItemDiscountedPrice = (item, basePrice) => {
  const itemDiscount = parseFloat(item.discount_percentage || 0);
  const globalDiscount = getGlobalDiscountPercentage();
  const prescriptionDiscount = parseFloat(
    currentPrescriptionDiscountPercentage.value || 0,
  );

  const bestDiscount = Math.max(
    itemDiscount,
    globalDiscount,
    prescriptionDiscount,
  );

  if (bestDiscount > 0) {
    return basePrice * (1 - bestDiscount / 100);
  }
  return basePrice;
};

const totalAmountBs = computed(() => {
  let total = 0;
  quotationItems.value.forEach((item) => {
    const basePriceBs = item.price_bs || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;

    const discountedPrice = calculateItemDiscountedPrice(item, basePriceBs);
    total += discountedPrice * quantity * (1 + taxRate);
  });
  return total;
});

const totalAmountUsd = computed(() => {
  let total = 0;
  quotationItems.value.forEach((item) => {
    const basePriceUsd = item.price || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;

    const discountedPrice = calculateItemDiscountedPrice(item, basePriceUsd);
    total += discountedPrice * quantity * (1 + taxRate);
  });
  return total;
});

const totalAmountCop = computed(() => {
  let total = 0;
  quotationItems.value.forEach((item) => {
    const basePriceCop = item.price_cop || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;

    let discountedPrice = calculateItemDiscountedPrice(item, basePriceCop);
    // Apply rounding for COP calculation if needed before or after?
    // Usually rounding happens on final price. QuotationProducts rounds priceWithIva.
    // Let's mimic QuotationProducts logic: base -> discount -> +tax -> round.

    let priceWithIva = discountedPrice * (1 + taxRate);
    priceWithIva = Math.ceil(priceWithIva / 100) * 100; // Manual round up to nearest hundred as per logic

    total += priceWithIva * quantity;
  });
  return total;
});

const totalProductsAmount = computed(() => {
  let total = 0;
  quotationItems.value.forEach((item) => {
    const price = getItemPriceByCurrency(item, selectedDisplayCurrency.value);
    const quantity = item.selectedQuantity || 0;
    total += price * quantity;
  });
  return total;
});

const totalEligibleAmount = computed(() => {
  let total = 0;
  quotationItems.value.forEach((item) => {
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
      (o) => o.value === selectedCompanyId.value,
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
      currentPrescriptionDiscountPercentage.value || 0,
    );
    if (porcentaje > 0) {
      return totalEligibleAmount.value * (porcentaje / 100);
    }
  }
  return 0;
});

// Computed: payload listo para enviar al backend (evita duplicación entre save/print)
const buildPayload = computed(() => {
  let productsUsd  = 0;
  let eligibleBase = 0;
  let ivaUsd       = 0;

  quotationItems.value.forEach((item) => {
    const p = item.price || 0;
    const q = item.selectedQuantity || 0;
    const t = item.taxRate || 0;
    productsUsd  += p * q;
    ivaUsd       += p * q * t;
    if (item.discount_type !== "expiration") {
      eligibleBase += p * q;
    }
  });

  const discountAmount = eligibleBase * (globalDiscountPercentage.value / 100);
  const grandTotal     = productsUsd + ivaUsd - discountAmount;

  return {
    total_amount_usd: productsUsd,
    total_iva_usd:    ivaUsd,
    grand_total_usd:  grandTotal,
    currency:         selectedDisplayCurrency.value,
    client_id:        selectedClient.value?.id || null,
    products:         quotationItems.value.map((item) => ({
      id:       item.id      || null,
      dish_id:  item.dish_id || null,
      quantity: item.selectedQuantity,
    })),
  };
});

const totalIVAAmount = computed(() => {
  let totalIVA = 0;
  quotationItems.value.forEach((item) => {
    const price = getItemPriceByCurrency(item, selectedDisplayCurrency.value);
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;
    totalIVA += price * quantity * taxRate;
  });
  return totalIVA;
});

const totalQuotationAmount = computed(() => {
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


const fetchProducts = async () => {
  loading.value = true;
  const params = {
    q: filterSearchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    groupId: selectedGroupId.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );
  try {
    const response = await axios.get("/tpv/quotation", { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loading.value = false;
  }
};

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
    selectedGroupId,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true },
);

const handlePrescriptionFileSelected = (file) => {
  prescriptionFile.value = file;
  if (file && activePrescriptionOffers.value.length > 0) {
    const offer = activePrescriptionOffers.value[0];
    toast.success(
      `Descuento de receta del ${offer.discount_percentage}% detectado.`,
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

const handleCompanyDiscountSelected = async (companyId) => {
  selectedCompanyId.value = companyId;
  if (companyId) {
    await fetchCompanyOffers(companyId);
  }
  validateAndApplyCompanyDiscount();
};

const validateAndApplyCompanyDiscount = () => {
  if (activeCompanyOffers.value.length === 0) {
    return;
  }

  if (!selectedCompanyId.value) {
    if (selectedDiscountType.value === "Empresa") {
      selectedDiscountType.value = null;
    }
    return;
  }

  const offer = activeCompanyOffers.value.find(
    (o) => o.value === selectedCompanyId.value,
  );

  if (!offer) {
    selectedCompanyId.value = null;
    return;
  }

  const porcentaje = parseFloat(offer.current_discount || 0);
  if (porcentaje > 0) {
    toast.success(
      `Descuento de empresa ${porcentaje}% habilitado para esta cotización.`,
    );
  } else {
    selectedCompanyId.value = null;
    toast.info(
      `Esta empresa no cuenta con un descuento activo para el periodo actual.`,
    );
  }
};

watch(
  () => selectedClient.value,
  async (newCliente, oldCliente) => {
    if (!newCliente) {
      selectedCompany.value = null;
      await fetchCompanyOffers();
      return;
    }
    if (newCliente?.id === oldCliente?.id) {
      return;
    }

    try {
      if (newCliente.company_id) {
        await fetchCompanyOffers(newCliente.company_id);
        selectedDiscountType.value = "Empresa";
        selectedCompanyId.value = newCliente.company_id;
        validateAndApplyCompanyDiscount();
      } else {
        selectedCompanyId.value = null;
        await fetchCompanyOffers();
      }
    } catch (error) {
      console.error("[QUOTATION] Error en watcher de selectedClient:", error);
    }
  },
  { deep: true },
);

watch(selectedDiscountType, (newValue) => {
  if (newValue !== "Medico") {
    selectedDoctorOffer.value = null;
  }
  if (newValue !== "Recipe") {
    prescriptionFile.value = null;
  }
  if (newValue !== "Empresa") {
    selectedCompanyId.value = null;
  }
});

watch(
  [filterSearchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter, selectedGroupId],
  () => {
    page.value = 1;
  },
);

watch(barcodeSearchQuery, (newValue) => {
  clearTimeout(barcodeInputTimer);
  if (!newValue) {
    return;
  }
  if (newValue.length >= BARCODE_LENGTH_THRESHOLD) {
    barcodeInputTimer = setTimeout(async () => {
      await addProductToQuotationByBarcode(newValue);
      barcodeSearchQuery.value = "";
    }, 300);
  }
});

// --- Estado para Restaurante / Menú de Platos ---
const isRestaurant = ref(false);
const dishes = ref([]);
const dishesLoading = ref(false);
const dishFilterQuery = ref("");
const selectedDishCategory = ref(null);
const activeTab = ref("products");

const dishCategories = computed(() => {
  const categories = new Set();
  dishes.value.forEach((d) => {
    if (d.category && d.category.name) {
      categories.add(d.category.name);
    }
  });
  return Array.from(categories);
});

// Computed: platos filtrados por búsqueda local y categoría
const filteredDishes = computed(() => {
  let list = dishes.value;
  if (selectedDishCategory.value) {
    list = list.filter((d) => d.category && d.category.name === selectedDishCategory.value);
  }
  if (dishFilterQuery.value) {
    const q = dishFilterQuery.value.toLowerCase();
    list = list.filter((d) => d.name.toLowerCase().includes(q));
  }
  return list;
});

const enableDishes = ref(false);

const fetchGeneralSettings = async () => {
  try {
    const { data } = await axios.get("/general-settings");
    const settings = data.data || data;
    enableDishes.value = settings.enable_dishes !== undefined ? !!settings.enable_dishes : true;
    isRestaurant.value = settings.quotation_style === "restaurant";
    if (isRestaurant.value && enableDishes.value) {
      activeTab.value = "menu";
    }
  } catch (error) {
    console.error("Error al cargar configuración", error);
    toast.error("Error al cargar configuración");
  }
};

const fetchDishes = async () => {
  // Solo cargar platos si el interruptor "Habilitar Platos" está activo en la configuración
  if (!enableDishes.value) return;

  dishesLoading.value = true;
  try {
    const { data } = await axios.get("/dishes", {
      params: { status: 1, q: dishFilterQuery.value || undefined },
    });
    dishes.value = Array.isArray(data.data) ? data.data : data;
  } catch (error) {
    console.error("[TPV] Error al cargar platos:", error);
  } finally {
    dishesLoading.value = false;
  }
};

onMounted(async () => {
  await fetchGeneralSettings();
  fetchSelectOptions();
  fetchProducts();
  fetchDoctorOffers();
  fetchPrescriptionOffers();
  fetchCompanyOffers();
  loadQuotationFromLocalStorage();
});



const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const addProductToQuotationByBarcode = async (barcode) => {
  try {
    const response = await axios.get(`/barcode/${barcode}`);
    const productDetails = response.data;
    await addProductToQuotation({ productId: productDetails.id, quantity: 1 });
  } catch (error) {
    console.error(
      "Error al agregar producto por código de barras:",
      error.response ? error.response.data : error.message,
    );
    toast.error(
      "Producto no encontrado o error al agregar por código de barras.",
    );
  }
};

const handleAddDishToQuotation = async ({ dish, quantity }) => {
  if (quantity <= 0) return;

  const existingItemIndex = quotationItems.value.findIndex(
    (item) => item.dish_id === dish.id && item.is_dish
  );

  if (existingItemIndex !== -1) {
    quotationItems.value[existingItemIndex].selectedQuantity += quantity;
    toast.success(
      `Cantidad de "${dish.name}" incrementada a ${quotationItems.value[existingItemIndex].selectedQuantity}.`
    );
  } else {
    const unitPrice = parseFloat(dish.designated_price) || parseFloat(dish.sale_price) || 0;
    const itemToAdd = {
      id: null,
      dish_id: dish.id,
      title: dish.name,
      active_ingredient: dish.active_ingredient || (dish.category?.name || "Plato"),
      itemCode: null,
      price: unitPrice,
      price_bs: parseFloat(dish.price_bs) || unitPrice,
      price_cop: parseFloat(dish.price_cop) || unitPrice,
      availableQuantity: 9999, // Los platos no tienen stock directo
      selectedQuantity: quantity,
      laboratory: dish.laboratory_name || (dish.category?.name || "Plato"),
      taxRate: 0,
      pack_id: null,
      discount_percentage: 0,
      discount_type: null,
      discount_source_id: null,
      is_dish: true,
    };
    quotationItems.value.push(itemToAdd);
    toast.success(`"${itemToAdd.title}" agregado a la cotización.`);
  }
};

const addProductToQuotation = async ({ productId, quantity }) => {
  if (quantity <= 0) {
    toast.error("La cantidad a agregar debe ser mayor que cero.");
    return;
  }

  try {
    const response = await axios.get(`/tpv/quotation/${productId}`);
    const productDetails = response.data;
    const availableQuantity = productDetails.valid_stock_sum;
    if (quantity > availableQuantity) {
      toast.error(
        `No hay suficiente stock para "${productDetails.name}". Disponible: ${availableQuantity}. Solicitado: ${quantity}.`,
      );
      return;
    }

    const existingItemIndex = quotationItems.value.findIndex(
      (item) => item.id === productId,
    );
    if (existingItemIndex !== -1) {
      const currentSelectedQuantity =
        quotationItems.value[existingItemIndex].selectedQuantity;
      const newTotalSelectedQuantity = currentSelectedQuantity + quantity;

      if (newTotalSelectedQuantity > availableQuantity) {
        toast.warning(
          `Ya se agrego la cantidad maxima disponible de "${productDetails.name}"`,
        );
        quotationItems.value[existingItemIndex].selectedQuantity =
          availableQuantity;
      } else {
        quotationItems.value[existingItemIndex].selectedQuantity =
          newTotalSelectedQuantity;
        toast.success(
          `Cantidad de "${productDetails.name}" incrementada a ${newTotalSelectedQuantity}.`,
        );
      }
    } else {
      // Validar que todos los campos necesarios estén presentes
      if (!productDetails.id || !productDetails.name) {
        toast.error("El producto no tiene la información completa necesaria.");
        return;
      }

      const itemToAdd = {
        id: productDetails.id,
        title: productDetails.name || "Producto sin nombre",
        active_ingredient: productDetails.active_ingredient || null,
        itemCode: productDetails.barcode || null,
        price: productDetails.sale_price || 0,
        price_bs: productDetails.price_bs || 0,
        price_cop: productDetails.price_cop || 0,
        availableQuantity: availableQuantity || 0,
        selectedQuantity: quantity,
        laboratory: productDetails.laboratory
          ? productDetails.laboratory.name
          : "N/A",
        taxRate: productDetails.iva == 1 ? 0.16 : 0,
        discount_percentage: parseFloat(
          productDetails.discount_percentage || 0,
        ),
        discount_type: productDetails.discount_type || null,
        discount_source_id: productDetails.discount_source_id || null,
      };
      quotationItems.value.push(itemToAdd);
      toast.success(`"${itemToAdd.title}" agregado a la cotización.`);
    }
  } catch (error) {
    console.error(
      "Error al obtener o agregar el producto a la cotización:",
      error.response ? error.response.data : error.message,
    );
    
    // Mostrar errores de validación si existen
    if (error.response?.data?.errors) {
      const errorMessages = Object.values(error.response.data.errors).flat();
      toast.error(`Error: ${errorMessages.join(", ")}`);
    } else if (error.response?.data?.message) {
      toast.error(error.response.data.message);
    } else {
      toast.error(
        "Error al agregar el producto a la cotización. Inténtalo de nuevo.",
      );
    }
  }
};

const removeQuotationItem = (productId) => {
  quotationItems.value = quotationItems.value.filter(
    (item) => item.id !== productId,
  );
  toast.success("Producto eliminado exitosamente");
};

const removeQuotation = () => {
  quotationItems.value = [];
};

const handleClearFilters = () => {
  filterSearchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value = null;
  selectedGroupId.value = null;
  stockStatusFilter.value = null;
  sortBy.value = undefined;
  orderBy.value = undefined;
};

const handleClearSortOrder = () => {
  sortBy.value = undefined; // Reinicia el orden de la tabla
  orderBy.value = undefined; // Reinicia el orden de la tabla
};

const handleCurrencyChanged = (newCurrency) => {
  selectedDisplayCurrency.value = newCurrency;
};

const saveQuotation = async () => {
  if (quotationItems.value.length === 0) {
    throw new Error("No hay productos en la cotización para guardar.");
  }

  isSaving.value = true;
  try {
    const response = await axios.post("/tpv/quotations", buildPayload.value);
    quotationDetails.value = response.data.quotation;
    return response.data.quotation;
  } catch (error) {
    console.error("Error al guardar la cotización:", error);
    throw error;
  } finally {
    isSaving.value = false;
  }
};

const saveAndPrintQuotation = async () => {
  if (quotationItems.value.length === 0) {
    toast.error("No hay productos en la cotización para guardar e imprimir.");
    return;
  }

  isSaving.value = true;
  try {
    const response = await axios.post("/tpv/quotations", buildPayload.value);
    quotationDetails.value = response.data.quotation;
    toast.success("Cotización guardada exitosamente. Preparando impresión...");
    isPrinting.value = true;
    await nextTick();
    const printContents = document.getElementById("orderInvoicePrintArea");
    if (printContents) {
      const printWindow = window.open("", "", "height=600,width=800");

      printWindow.document.write(`
        <html>
          <head>
            <title>Farmacia Barrio Sucre - Cotización</title>
            <style>
              @media print {
                @page {
                  size: 54mm auto;
                  margin: 0;
                  padding: 0;
                }
                
                body {
                  width: 54mm !important;
                  max-width: 54mm !important;
                  margin: 0 !important;
                  padding: 2mm !important;
                  font-family: 'Courier New', monospace !important;
                  font-size: 10px !important;
                  line-height: 1.2 !important;
                }
                
                * {
                  max-width: 50mm !important;
                  box-sizing: border-box !important;
                  word-wrap: break-word !important;
                }
                
                .no-print, button, .actions {
                  display: none !important;
                }
                
                table {
                  width: 100% !important;
                  border-collapse: collapse !important;
                }
                
                td, th {
                  padding: 1px 0 !important;
                  font-size: 9px !important;
                  }
                
                .break-word {
                  word-break: break-word !important;
                  overflow-wrap: break-word !important;
                }
              }
              
              @media screen {
                body {
                  width: 54mm;
                  border: 1px dashed #ccc;
                  margin: 0;
                  padding: 2mm;
                  font-family: 'Courier New', monospace;
                  font-size: 10px;
                  line-height: 1.2;
                }
              }
            </style>
      `);

      const styleSheets = document.styleSheets;
      for (let i = 0; i < styleSheets.length; i++) {
        const sheet = styleSheets[i];
        try {
          if (sheet.cssRules) {
            let cssText = "";
            for (let j = 0; j < sheet.cssRules.length; j++) {
              cssText += sheet.cssRules[j].cssText;
            }
            printWindow.document.write(`<style>${cssText}</style>`);
          } else if (sheet.href) {
            printWindow.document.write(
              `<link rel="stylesheet" href="${sheet.href}">`,
            );
          }
        } catch (e) {
          console.warn(
            "No se pudo acceder a la hoja de estilo:",
            sheet.href || sheet,
            e,
          );
        }
      }

      printWindow.document.write(`
          </head>
          <body>
      `);
      printWindow.document.write(printContents.innerHTML);
      printWindow.document.write(`
          </body>
        </html>
      `);

      printWindow.document.close();
      printWindow.focus();

      printWindow.onload = function () {
        setTimeout(() => {
          printWindow.print();
          printWindow.close();
        }, 100);
      };
    } else {
      console.warn(
        "Elemento #orderInvoicePrintArea no encontrado para impresión tipo ticket. Imprimiendo toda la página.",
      );
      window.print();
    }

    setTimeout(() => {
      removeQuotation();
      quotationDetails.value = null;
      isPrinting.value = false;
    }, 500);
  } catch (error) {
    console.error(
      "Error al guardar o imprimir la cotización:",
      error.response ? error.response.data : error.message,
    );
    toast.error(
      "Error al guardar o imprimir la cotización. Inténtalo de nuevo.",
    );
    isPrinting.value = false;
  } finally {
    isSaving.value = false;
  }
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const fetchGroupProducts = async (groupId) => {
  if (!groupId) {
    toast.info("Este producto no pertenece a un grupo.");
    return;
  }
  selectedGroupId.value = groupId;
  page.value = 1;
  fetchProducts();
};

const clearGroupFilter = () => {
  selectedGroupId.value = null;
  page.value = 1;
  fetchProducts();
};

const fetchFailuresProducts = async (productId) => {
  try {
    const response = await axios.post("/tpv/product-failure", {
      product_id: productId,
    });
    toast.success("Reporte de falla guardado correctamente.");
  } catch (error) {
    if (error.response) {
      console.error("Errores de validación:", error.response.data.errors);
      toast.error("Hubo un problema al procesar su reporte de falla.");
    } else {
      console.error("Error de conexión:", error.message);
    }
  }
};

const verifyClient = async (identification) => {
  await verifyClientComposable(identification, newClientFormData, selectedDiscountType, selectedCompanyId);
};

const handleCleanAfterSave = () => {
  handleClearFilters();
  handleClearSortOrder();
  removeQuotation();

  page.value = 1;
  selectedClient.value = null;
  clientIdentification.value = "";
};

// Handler extraído del template para evitar lógica inline
const handleClientRegistered = (client) => {
  selectedClient.value = client;
  showRegisterClientModal.value = false;
  toast.success("Cliente registrado exitosamente.");
};

onUnmounted(() => {
  clearTimeout(barcodeInputTimer);
});
</script>

<template>
  <div>
    <VRow class="mb-4">
      <VCol cols="12" sm="12" md="6">
        <QuotationCard
          :total-products-amount="totalProductsAmount"
          :total-iva-amount="totalIVAAmount"
          :total-quotation-amount="totalQuotationAmount"
          :quotation-items="quotationItems"
          :selected-display-currency="selectedDisplayCurrency"
          :company-discount-total="totalCompanyDiscountAmount"
          :doctor-discount-total="totalDoctorDiscountAmount"
          :recipe-discount-total="totalRecipeDiscountAmount"
          :selected-discount-type="selectedDiscountType"
          @currency-changed="handleCurrencyChanged"
        />
      </VCol>
      <VCol cols="12" sm="12" md="6">
        <QuotationProducts
          v-model:searchQuery="barcodeSearchQuery"
          v-model:client-identification="clientSearchQuery"
          :quotation-products="quotationItems"
          :quotation-details="quotationDetails"
          :selected-display-currency="selectedDisplayCurrency"
          :total-amount-bs="totalAmountBs"
          :total-amount-usd="totalAmountUsd"
          :total-amount-cop="totalAmountCop"
          :on-save-quotation="saveQuotation"
          :selected-client="selectedClient"
          :is-saving="isSaving"
          v-model:selected-discount-type="selectedDiscountType"
          :active-doctor-offers="activeDoctorOffers"
          :prescription-discount-percentage="currentPrescriptionDiscountPercentage"
          :active-company-offers="activeCompanyOffers"
          :global-discount-percentage="globalDiscountPercentage"
          :is-restaurant="isRestaurant"
          @doctor-discount-selected="handleDoctorDiscountSelected"
          @prescription-file-selected="handlePrescriptionFileSelected"
          @company-discount-selected="handleCompanyDiscountSelected"
          @remove-quotation-product="removeQuotationItem"
          @remove="removeQuotation"
          @print-quotation="saveAndPrintQuotation"
          @search-client="fetchSearchedClient"
          @clean-post-save="handleCleanAfterSave"
        />
      </VCol>
    </VRow>

    <QuotationFilters
      v-model:searchQuery="filterSearchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      v-model:stockStatusFilter="stockStatusFilter"
      :laboratories="laboratories"
      :origins="origins"
      :is-restaurant="isRestaurant"
      v-model:selectedCategory="selectedDishCategory"
      :categories="dishCategories.map(cat => ({ id: cat, name: cat }))"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
      @sort="handleSort"
      @clear-sort="handleClearSortOrder"
    >
    </QuotationFilters>

    <div v-if="selectedGroupId" class="d-flex align-center gap-2 mb-4 animate__animated animate__fadeIn">
      <VBtn
        variant="tonal"
        color="secondary"
        size="small"
        prepend-icon="tabler-arrow-left"
        class="rounded-lg font-weight-black"
        @click="clearGroupFilter"
      >
        VOLVER A LA LISTA GENERAL
      </VBtn>
      <VChip color="info" size="small" variant="flat" class="font-weight-black shadow-sm">
        VIENDO PRODUCTOS DEL GRUPO #{{ selectedGroupId }}
      </VChip>
    </div>

    <QuotationTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      :is-restaurant="isRestaurant"
      v-model:active-tab="activeTab"
      :dishes="dishes"
      :dishes-loading="dishesLoading"
      v-model:dish-filter-query="dishFilterQuery"
      v-model:selected-dish-category="selectedDishCategory"
      :dish-categories="dishCategories"
      :filtered-dishes="filteredDishes"
      @update:options="updateTableOptions"
      @add-product="addProductToQuotation"
      @add-dish="handleAddDishToQuotation"
      @view-group-products="fetchGroupProducts"
      @failures-products="fetchFailuresProducts"
    />

    <div
      id="orderInvoicePrintArea"
      :class="{ 'd-none': !isPrinting, 'print-container': true }"
    >
      <QuotationTicket
        :quotation-details="quotationDetails"
        :quotation-items="quotationItems"
        :total-products-amount="totalProductsAmount"
        :total-iva-amount="totalIVAAmount"
        :total-quotation-amount="totalQuotationAmount"
        :selected-display-currency="selectedDisplayCurrency"
      />
    </div>

    <RegisterClientModal
      v-model="showRegisterClientModal"
      @client-registered="handleClientRegistered"
    />
  </div>
</template>
