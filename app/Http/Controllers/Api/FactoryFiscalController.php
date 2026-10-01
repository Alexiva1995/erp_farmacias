<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Fiscal\FactoryFiscalServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\PrintFactoryCreditNoteRequest;
use App\Http\Requests\Fiscal\PrintFactoryInvoiceRequest;
use App\Http\Requests\Fiscal\PrintFactoryReportRequest;
use App\Http\Requests\Fiscal\TestFactoryConnectionRequest;
use App\Http\Resources\Fiscal\FactoryFiscalResponseResource;
use Illuminate\Http\Request;

class FactoryFiscalController extends Controller
{
    public function __construct(
        protected FactoryFiscalServiceInterface $factoryService
    ) {}

    /**
     * Probar conexión y presencia de la impresora fiscal The Factory HKA.
     */
    public function testConnection(TestFactoryConnectionRequest $request): FactoryFiscalResponseResource
    {
        $result = $this->factoryService->checkConnection(
            $request->input('ip'),
            $request->has('port') ? (int) $request->input('port') : null
        );

        return new FactoryFiscalResponseResource($result);
    }

    /**
     * Imprimir factura fiscal en The Factory HKA.
     */
    public function printInvoice(PrintFactoryInvoiceRequest $request, int $id): FactoryFiscalResponseResource
    {
        $result = $this->factoryService->printInvoice(
            $id,
            $request->input('ip'),
            $request->has('port') ? (int) $request->input('port') : null
        );

        return new FactoryFiscalResponseResource($result);
    }

    /**
     * Imprimir nota de crédito fiscal en The Factory HKA.
     */
    public function printCreditNote(PrintFactoryCreditNoteRequest $request): FactoryFiscalResponseResource
    {
        $result = $this->factoryService->printCreditNote(
            $request->validated(),
            $request->input('ip'),
            $request->has('port') ? (int) $request->input('port') : null
        );

        return new FactoryFiscalResponseResource($result);
    }

    /**
     * Imprimir Reporte X en The Factory HKA.
     */
    public function printReportX(PrintFactoryReportRequest $request): FactoryFiscalResponseResource
    {
        $result = $this->factoryService->printReportX(
            $request->input('ip'),
            $request->has('port') ? (int) $request->input('port') : null
        );

        return new FactoryFiscalResponseResource($result);
    }

    /**
     * Imprimir Reporte Z en The Factory HKA.
     */
    public function printReportZ(PrintFactoryReportRequest $request): FactoryFiscalResponseResource
    {
        $result = $this->factoryService->printReportZ(
            $request->input('ip'),
            $request->has('port') ? (int) $request->input('port') : null
        );

        return new FactoryFiscalResponseResource($result);
    }

    /**
     * Obtener lectura de estado S1 de The Factory HKA.
     */
    public function getStatusS1(Request $request): FactoryFiscalResponseResource
    {
        $ip = $request->query('ip');
        $port = $request->has('port') ? (int) $request->query('port') : null;

        $result = $this->factoryService->getStatusS1($ip, $port);

        return new FactoryFiscalResponseResource($result);
    }
}
