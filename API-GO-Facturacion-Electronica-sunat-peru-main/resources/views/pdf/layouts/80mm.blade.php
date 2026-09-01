@extends('pdf.layouts.base')

@section('format-styles')
    <style>
        /* ==================== CONFIGURACIÓN PÁGINA ==================== */
        @page {
            size: 80mm auto;
            margin: 0;
        }

        /* ==================== RESET Y BASE ==================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 80mm;
            max-width: 80mm;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.2;
        }

        .container {
            width: 76mm;
            max-width: 76mm;
            margin: 0 auto;
            padding: 0;
        }

        /* ==================== SECCIONES GENERALES ==================== */
        .header,
        .client-section,
        .items-section,
        .totals-section,
        .qr-section,
        .payment-methods-ticket,
        .additional-contacts-ticket,
        .footer-message-ticket {
            width: 100%;
            max-width: 100%;
            overflow: hidden;
            word-wrap: break-word;
        }

        /* ==================== HEADER ==================== */
        .header {
            text-align: center;
            margin-bottom: 3px;
            padding-bottom: 3px;
            border-bottom: 1px dashed #000;
        }

        .company-name {
            font-size: 12px;
            font-weight: bold;
            margin: 2px 0;
        }

        .company-ruc {
            font-size: 11px;
            font-weight: bold;
            margin: 1px 0;
        }

        .company-details {
            font-size: 10px;
            margin: 1px 0;
        }

        .logo-img-ticket {
            max-width: 100px;
            height: auto;
            margin: 2px auto;
        }

        /* ==================== TÍTULO DOCUMENTO ==================== */
        .document-title {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            margin: 3px 0;
            padding: 2px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
        }

        .document-number {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            margin: 2px 0;
        }

        /* ==================== CLIENTE ==================== */
        .client-section {
            margin: 3px 0;
            padding: 2px 0;
            font-size: 10px;
            border-bottom: 1px dashed #000;
        }

        .client-name {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            margin: 2px 0;
        }

        .client-details {
            font-size: 10px;
            text-align: center;
            margin: 1px 0;
        }

        /* ==================== ITEMS ==================== */
        .items-section {
            margin: 3px 0;
            padding: 3px 0;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        /* ==================== TOTALES ==================== */
        .totals-section {
            margin: 3px 0;
            padding: 2px 0;
            font-size: 11px;
            border-top: 1px solid #000;
        }

        .total-line {
            width: 100%;
            max-width: 100%;
            margin: 1px 0;
            font-size: 11px;
            overflow: hidden;
            text-overflow: clip;
        }

        .total-final {
            border-top: 1px solid #000;
            margin-top: 2px;
            padding-top: 2px;
            font-size: 12px;
            font-weight: bold;
        }

        .total-letras {
            font-size: 11px;
            font-weight: bold;
            margin: 2px 0;
        }

        /* ==================== CÓDIGO HASH Y QR ==================== */
        .payment-info {
            font-size: 10px;
            margin: 2px 0;
            padding: 2px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
        }

        .qr-section {
            text-align: center;
            margin: 3px 0;
            padding: 2px 0;
        }

        .qr-code img {
            max-width: 80px;
            height: auto;
        }

        .footer-text,
        .footer-url,
        .footer-auth {
            font-size: 10px;
            text-align: center;
            margin: 1px 0;
        }

        /* ==================== MÉTODOS DE PAGO ==================== */
        .payment-methods-ticket {
            margin: 3px 0;
            padding: 2px 0;
            font-size: 10px;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
        }

        .section-title-ticket {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            margin: 2px 0;
        }

        .payment-item-ticket {
            font-size: 10px;
            margin: 1px 0;
        }

        /* ==================== CONTACTOS ==================== */
        .additional-contacts-ticket {
            margin: 3px 0;
            padding: 2px 0;
            font-size: 10px;
            border-top: 1px dashed #000;
            text-align: center;
        }

        /* ==================== FOOTER MESSAGE ==================== */
        .footer-message-ticket {
            margin: 3px 0;
            padding: 2px 0;
            font-size: 10px;
            border-top: 1px dashed #000;
            text-align: center;
        }

        .mensaje-ticket {
            font-size: 11px;
            font-weight: bold;
            margin: 2px 0;
        }

        .terminos-ticket {
            font-size: 10px;
            margin: 1px 0;
        }

        /* ==================== UTILIDADES ==================== */
        .text-bold { font-weight: bold; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }

        /* ==================== PRINT ==================== */
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }

            body {
                width: 80mm;
                margin: 0;
                padding: 0;
            }

            .container {
                width: 76mm;
                margin: 0 auto;
            }

            .no-print {
                display: none;
            }
        }

        /* ==================== BOTONES ==================== */
        .actions {
            text-align: center;
            margin: 10px 0;
        }

        .btn {
            background-color: #fff;
            border: 1px solid #000;
            color: #000;
            padding: 5px 10px;
            font-size: 10px;
            margin: 2px;
            cursor: pointer;
        }
    </style>
@endsection

@section('body-content')
    <div class="container">
        @yield('content')
    </div>
@endsection
