import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { applyPremiumHeader, applyFooter } from './pdfBaseStyles';

export default function pdfClienstGenerator(data) {
    const doc = new jsPDF();
    
    // Aplicar Encabezado Premium
    const startY = applyPremiumHeader(doc, 'Reporte de Clientes');
    
    // Configuración de la tabla
    const headers = [
        [
            { content: '#', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Identificación', styles: { fontStyle: 'bold' } },
            { content: 'Tipo', styles: { fontStyle: 'bold' } },
            { content: 'Nombre Completo', styles: { fontStyle: 'bold' } },
            { content: 'Teléfono', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Email', styles: { fontStyle: 'bold' } }
        ]
    ];
    
    const rows = data.map((client, index) => [
        { content: index + 1, styles: { halign: 'center' } },
        client.identification,
        client.identification_type,
        `${client.name} ${client.last_name || ''}`.trim(),
        { content: client.phone || 'N/A', styles: { halign: 'center' } },
        client.email || 'N/A'
    ]);
    
    // Generar tabla
    autoTable(doc, {
        startY: startY,
        head: headers,
        body: rows,
        theme: 'grid',
        headStyles: { 
            fillColor: [240, 240, 240],
            textColor: 0,
            halign: 'center',
            fontStyle: 'bold'
        },
        styles: {
            fontSize: 8,
            cellPadding: 3,
            overflow: 'linebreak'
        },
        columnStyles: {
            0: { cellWidth: 10, halign: 'center' },
            1: { cellWidth: 25 },
            2: { cellWidth: 15 },
            3: { cellWidth: 40 },
            4: { cellWidth: 25, halign: 'center' },
            5: { cellWidth: 'auto' }
        },
        margin: { left: 15, right: 15 }
    });
    
    // Aplicar Pie de Página
    applyFooter(doc);
    
    // Guardar PDF
    const todayStr = new Date().toISOString().slice(0, 10);
    doc.save(`Clientes_Reporte_${todayStr}.pdf`);
}
