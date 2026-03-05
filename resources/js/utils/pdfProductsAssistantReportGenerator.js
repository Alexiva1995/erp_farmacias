import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

export default function pdfProductsAssistantReportGenerator(data) {
    const doc = new jsPDF({
        orientation: 'landscape',
        unit: 'mm',
        format: [350, 215.9] // Formato Oficio/Letter extendido landscape
    });
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const today = new Date();
    const dateStr = `${today.getDate()}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`;
    
    // --- 1. MEMBRETE OFICIAL ---
    try {
        const logoSrc = '/images/logoDonative.png';
        const logoWidth = 50;
        const logoHeight = 20;
        doc.addImage(logoSrc, 'PNG', (pageWidth - logoWidth) / 2, 8, logoWidth, logoHeight);
    } catch (error) {
        console.warn("No se pudo cargar el logo", error);
    }
    
    doc.setFontSize(11);
    doc.setFont('helvetica', 'bold');
    doc.text('FARMACIA BARRIO SUCRE 2024, C.A.', pageWidth / 2, 34, { align: 'center' });
    doc.setFontSize(9);
    doc.text('R.I.F. Nº J-50540695-7', pageWidth / 2, 38, { align: 'center' });
    
    // --- 2. TÍTULO PRINCIPAL CON BORDES ---
    doc.setDrawColor(0);
    doc.setLineWidth(0.4);
    doc.line(10, 42, pageWidth - 10, 42);
    
    doc.setFontSize(11);
    doc.text('REPORTE ASISTENTE DE PEDIDO IA', pageWidth / 2, 47, { align: 'center' });
    
    doc.line(10, 50, pageWidth - 10, 50);
    
    // --- 3. CUADRO DE INFORMACIÓN ---
    doc.setFontSize(8.5);
    doc.setFont('helvetica', 'normal');
    doc.text('Ciudad: LA FRIA', 12, 56);
    doc.text(`Fecha de Emisión: ${dateStr}`, 12, 60);
    
    doc.setFont('helvetica', 'bold');
    doc.text('Nº Reporte: IA-' + today.getTime().toString().slice(-6), pageWidth - 12, 56, { align: 'right' });
    doc.text('Tipo: Análisis de Demanda y Costos', pageWidth - 12, 60, { align: 'right' });

    // --- 4. CONFIGURACIÓN DE TABLA ---
    const headers = [
        [
            { content: '#', styles: { halign: 'center' } },
            { content: 'ID', styles: { halign: 'center' } },
            { content: 'PRODUCTO' },
            { content: 'LABORATORIO' },
            { content: 'C. MIN', styles: { halign: 'center' } },
            { content: 'C. MAX', styles: { halign: 'center' } },
            { content: 'C. ACTUAL', styles: { halign: 'center' } },
            { content: 'MEJOR OFERTA', styles: { halign: 'center' } },
            { content: 'VTAS', styles: { halign: 'center' } },
            { content: 'STOCK', styles: { halign: 'center' } },
            { content: 'PROM.', styles: { halign: 'center' } },
            { content: 'DEMANDA', styles: { halign: 'center' } },
            { content: 'ANÁLISIS', styles: { halign: 'center' } }
        ]
    ];
    
    const rows = data.map((product, index) => [
        { content: index + 1, styles: { halign: 'center' } },
        { content: product.id, styles: { halign: 'center' } },
        product.name,
        product.laboratory?.name || 'N/A',
        { 
            content: product.cost_min ? parseFloat(product.cost_min).toFixed(2) : '0.00',
            styles: { halign: 'right' }
        },
        { 
            content: product.cost_max ? parseFloat(product.cost_max).toFixed(2) : '0.00',
            styles: { halign: 'right' }
        },
        { 
            content: product.unit_cost ? parseFloat(product.unit_cost).toFixed(2) : '0.00',
            styles: { halign: 'right' }
        },
        {
            content: product.product_suppliers?.length > 0 
                ? `$${parseFloat(product.product_suppliers[0].unit_cost_usd_with_discount).toFixed(2)}\n(${product.product_suppliers[0].supplier.name})`
                : '---',
            styles: { halign: 'center', fontSize: 6.5 }
        },
        { 
            content: product.total_sold_completed ? parseInt(product.total_sold_completed) : 0,
            styles: { halign: 'center' }
        },
        { 
            content: product.lote_quantity ? parseInt(product.lote_quantity) : 0,
            styles: { halign: 'center' }
        },
        { 
            content: product.promedio_calculado ? parseFloat(product.promedio_calculado).toFixed(1) : '0.0',
            styles: { halign: 'right' }
        },
        { 
            content: product.demanda_ponderada ? parseFloat(product.demanda_ponderada).toFixed(1) : '0.0',
            styles: { halign: 'right', fontStyle: 'bold' }
        },
        { 
            content: product.solicitar ? parseFloat(product.solicitar).toFixed(1) : '0.0',
            styles: { halign: 'right', fontStyle: 'bold' }
        }
    ]);
    
    autoTable(doc, {
        startY: 65,
        head: headers,
        body: rows,
        theme: 'grid',
        headStyles: { 
            fillColor: [245, 245, 245], 
            textColor: 0,
            halign: 'center',
            fontSize: 7.5,
            fontStyle: 'bold',
            lineWidth: 0.1,
            lineColor: [0, 0, 0]
        },
        styles: {
            fontSize: 7,
            cellPadding: 1.5,
            lineWidth: 0.1,
            lineColor: [0, 0, 0],
            textColor: 0,
            valign: 'middle'
        },
        columnStyles: {
            0: { cellWidth: 8 },  // #
            1: { cellWidth: 12 }, // ID
            2: { cellWidth: 50 }, // Producto
            3: { cellWidth: 35 }, // Laboratorio
            4: { cellWidth: 18 }, // C. Min
            5: { cellWidth: 18 }, // C. Max
            6: { cellWidth: 18 }, // Costo actual
            7: { cellWidth: 40 }, // Mejor oferta
            8: { cellWidth: 12 }, // Ventas
            9: { cellWidth: 12 }, // Stock
            10: { cellWidth: 15 }, // Prom
            11: { cellWidth: 15 }, // Demanda
            12: { cellWidth: 15 }  // Análisis
        },
        margin: { left: 10, right: 10 },
        pageBreak: 'auto'
    });
    
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.text(
            `Página ${i} de ${pageCount}`,
            pageWidth / 2,
            pageHeight - 8,
            { align: 'center' }
        );
    }
    
    doc.save(`Reporte_IA_${today.toISOString().slice(0, 10)}.pdf`);
}
