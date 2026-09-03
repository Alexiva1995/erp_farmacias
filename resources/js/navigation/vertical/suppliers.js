export default [
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
        // action: "manage",
        // subject: "suppliers-list",
      },
      {
        title: "Ordenes de Compra",
        to: "suppliers-purchase-orders-list",
        // action: "manage",
        // subject: "suppliers-purchase-orders-list",
      },
      {
        title: "Órdenes por Laboratorio",
        to: "suppliers-purchase-orders-laboratory-list",
        // action: "manage",
        // subject: "suppliers-purchase-orders-laboratory-list",
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

