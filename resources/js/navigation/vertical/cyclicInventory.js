export default [
  {
    title: "Inventario Ciclicos",
    icon: {
      is: "font-awesome-icon",
      props: {
        icon: ["fas", "boxes-stacked"],
        size: "sm",
      },
    },
    children: [
      {
        title: "Historial",
        to: "cyclics-history",
        action: "manage",
        subject: "cyclic-history",
      },
      {
        title: "Cierre de Inventario",
        to: "cyclics-closing",
        action: "manage",
        subject: "closing-cyclics",
      },
      {
        title: "Pendientes",
        to: "cyclics-cyclic",
        action: "manage",
        subject: "pending-cyclics",
      },
      {
        title: "Inventarios Users",
        to: "cyclics-users",
        action: "manage",
        subject: "user",
      },
    ],
  },
];
