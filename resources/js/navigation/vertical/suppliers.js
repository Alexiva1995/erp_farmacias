export default [
  {
    title: "Auditoría Farmacias",
    icon: { icon: "tabler-chart-histogram" },
    to: "configuration-suppliers-tenant-audit",
  },
  {
    title: "Proveedores",
    icon: {
      is: "font-awesome-icon",
      props: {
        icon: ["fas", "address-book"],
        size: "sm",
      },
    },
    children: [
      {
        title: "Lista",
        to: "suppliers-list",
      },
      {
        title: "Auditoría por Farmacias",
        to: "configuration-suppliers-tenant-audit",
      },
      {
        title: "Ordenes de Compra",
        to: "suppliers-purchase-orders-list",
      },
      {
        title: "Órdenes por Laboratorio",
        to: "suppliers-purchase-orders-laboratory-list",
      },
    ],
  },
  {
    title: "IA Assistence",
    icon: { icon: "tabler-brain" },
    children: [
      {
        title: 'Pedidos',
        to: "suppliers-supplieriaorderassistant",
        action: 'manage',
        subject: 'ia-pedidos',
      },
      {
        title: 'Reporte',
        to: 'suppliers-supplieriaorderassistantreport',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Oportunidad de Mercado',
        to: 'suppliers-market-opportunities',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Comparador',
        to: 'suppliers-product-comparator-list',
        action: 'manage',
        subject: 'comparadorAssistence',
      },
      {
        title: 'Automatización',
        to: 'suppliers-auto-replenishment',
        action: 'manage',
        subject: 'admin',
      },
    ],
  },
];
