import { ref } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";

export function useQuotationClient() {
  const selectedClient = ref(null);
  const clientSearchQuery = ref("");
  const clientIdentification = ref("");
  const activeDoctorOffers = ref([]);
  const activePrescriptionOffers = ref([]);
  const activeCompanyOffers = ref([]);
  const loadingDoctorOffers = ref(false);

  const fetchDoctorOffers = async () => {
    loadingDoctorOffers.value = true;
    try {
      const response = await axios.get("/tpv/promotions/doctor-offer", {
        params: { status: "active" }
      });
      activeDoctorOffers.value = (response.data?.data || response.data || []).map(offer => ({
        id: offer.id,
        name: offer.doctor?.name || "Sin Nombre",
        percentage: offer.discount_percent
      }));
    } catch (error) {
      console.error("Error al obtener ofertas de médico:", error);
    } finally {
      loadingDoctorOffers.value = false;
    }
  };

  const fetchPrescriptionOffers = async () => {
    try {
      const response = await axios.get("/tpv/promotions/prescription-offer", {
        params: { status: "active" }
      });
      activePrescriptionOffers.value = response.data?.data || response.data || [];
    } catch (error) {
      console.error("Error al obtener ofertas de récipe:", error);
    }
  };

  const fetchCompanyOffers = async () => {
    try {
      const response = await axios.get("/tpv/promotions/company-offer", {
        params: { status: "active" }
      });
      activeCompanyOffers.value = (response.data?.data || response.data || []).map(offer => ({
        value: offer.company_id,
        title: offer.company?.name || "Sin Nombre",
        current_discount: offer.discount_percent
      }));
    } catch (error) {
      console.error("Error al obtener ofertas de convenios:", error);
    }
  };

  const verifyClient = async (identification, newClientFormData, selectedDiscountType, selectedCompanyId) => {
    clientIdentification.value = identification;

    if (!identification || !identification.trim()) {
      selectedClient.value = null;
      selectedCompanyId.value = null;
      selectedDiscountType.value = null;
      return;
    }

    try {
      const response = await axios.get(`/tpv/order/client/${identification}`);
      const responseData = response.data.data;

      if (responseData.found === false) {
        toast.info("Cliente no encontrado. Puede continuar sin cliente o registrarlo.");
        newClientFormData.value = {
          ...newClientFormData.value,
          identification: identification,
        };
        selectedClient.value = null;
      } else {
        const clientData = responseData.client;
        selectedClient.value = clientData;
        toast.success(`Cliente ${clientData.name} ${clientData.last_name} encontrado.`);
      }
    } catch (error) {
      console.error("Error al verificar cliente:", error);
      toast.error("Error al verificar el cliente.");
      selectedClient.value = null;
    }
  };

  const fetchSearchedClient = async () => {
    try {
      const { data } = await axios.get(`/crm/clients/identification/${clientSearchQuery.value}`);
      selectedClient.value = data.data;
      clientSearchQuery.value = "";
    } catch (error) {
      if (error.response?.status == 404) {
        toast.error("No se encontró ningún cliente con esa cédula");
      }
    }
  };

  return {
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
    verifyClient,
    fetchSearchedClient
  };
}
