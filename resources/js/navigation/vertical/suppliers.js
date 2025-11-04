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
    action: 'manage', 
    subject: 'suppliers',
    children: [
      {
        title: "Lista",
        to: "suppliers-list",
      },
      {
        title: "Ordenes de Compra",
        to: "suppliers-purchase-orders-list",
      },
      {
        title: "Historial de Ordenes de Compra",
        to: "suppliers-purchase-orders-history-list",
      },
      {
        title: "IA Assistence",
        children: [
      {
        title: 'Pedidos',
        to: "suppliers-supplieriaorderassistant",
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Reporte',
        to: 'suppliers-supplieriaorderassistantreport',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Comparador',
        to: 'suppliers-product-comparator-list',
      }
    ],
      }
    ],
  },
];
