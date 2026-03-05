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
    children: [
      {
        title: 'Facturas',
        to: 'fiscal-history',
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
        subject: 'admin',
      },
    ],
  },
]
