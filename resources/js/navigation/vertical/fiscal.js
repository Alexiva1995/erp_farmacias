export default [
  {
    title: 'Fiscal',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'barcode'],
        size: 'sm',
      },
    },
    action: 'manage',
    subject: 'supervisor-or-admin',
    children: [
      {
        title: 'Home',
        to: 'fiscal-home',
        action: 'manage',
        subject: 'supervisor-or-admin',
      },
      {
        title: 'Facturas',
        to: 'fiscal-history',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Máquina Fiscal',
        to: 'fiscal-control',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'IVA',
        to: 'iva-general',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'ISRL',
        to: 'islr-general',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Retenciones',
        to: 'fiscal-retenciones',
        action: 'manage',
        subject: 'supervisor-or-admin',
      },
    ],
  },
]
