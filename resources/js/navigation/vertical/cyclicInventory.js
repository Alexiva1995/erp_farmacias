export default [
  {
    title: "Inventario Ciclicos",
    icon: {
      is: "font-awesome-icon",
      props: {
        icon: ["fas", "arrows-rotate"],
        size: "sm",
      },
    },
    action: "manage",
    subject: "cyclic-menu",
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
        title: "Inventario de Usuario",
        to: "cyclics-users",
        action: "manage",
        subject: "cycli-user",
      },
      {
        title: "Cuota",
        to: "cyclics-quota",
        action: "manage",
        subject: "admin",
      },
    ],
  },
];
