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
        title: "Listado de Proveedores",
        to: "suppliers-list",
      },
      {
        title: "IA Assistence de Pedidos",
        to: "suppliers-supplieriaorderassistant",
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
        title: "Comparador de Productos",
        to: "suppliers-product-comparator-list",
      },
    ],
  },
];
