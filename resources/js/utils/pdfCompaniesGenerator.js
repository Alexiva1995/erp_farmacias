import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { applyPremiumHeader, applyFooter } from './pdfBaseStyles';

export default function pdfCompaniesGenerator(data) {
    const doc = new jsPDF();
    
    // Aplicar Encabezado Premium
    const startY = applyPremiumHeader(doc, 'Reporte de Empresas / Clínicas');
    
    // Configuración de la tabla
    const headers = [
        [
            { content: '#', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Identificación', styles: { fontStyle: 'bold' } },
            { content: 'Nombre', styles: { fontStyle: 'bold' } },
            { content: 'Tipo', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Dirección', styles: { fontStyle: 'bold' } }
        ]
    ];
    
    const rows = data.map((company, index) => [
        { content: index + 1, styles: { halign: 'center' } },
        company.identification,
        company.name,
        { 
            content: company.type_company === 'Empresa' ? 'Empresa' : 'Clínica',
            styles: { halign: 'center' }
        },
        company.address || 'N/A'
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
            1: { cellWidth: 35 },
            2: { cellWidth: 45 },
            3: { cellWidth: 25, halign: 'center' },
            4: { cellWidth: 'auto' }
        },
        margin: { left: 15, right: 15 }
    });
    
    // Aplicar Pie de Página
    applyFooter(doc);
    
    // Guardar PDF
    const todayStr = new Date().toISOString().slice(0, 10);
    doc.save(`Empresas_${todayStr}.pdf`);
}
