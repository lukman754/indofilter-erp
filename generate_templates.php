<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Shared\Converter;

class TemplateGenerator
{
    private PhpWord $phpWord;
    private array $styles;

    public function __construct()
    {
        $this->phpWord = new PhpWord();
        $this->setupStyles();
    }

    private function setupStyles(): void
    {
        $blue = '1A56DB';
        $darkBlue = '0E3B8A';
        $lightBlue = 'E8EFFD';

        $this->phpWord->setDefaultFontName('Arial');
        $this->phpWord->setDefaultFontSize(10);

        $this->styles = compact('blue', 'darkBlue', 'lightBlue');

        $this->phpWord->addParagraphStyle('pLeft', ['alignment' => Jc::START, 'spaceAfter' => 80]);
        $this->phpWord->addParagraphStyle('pRight', ['alignment' => Jc::END, 'spaceAfter' => 80]);
        $this->phpWord->addParagraphStyle('pCenter', ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);
        $this->phpWord->addParagraphStyle('pTitle', ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'spaceBefore' => 0]);
        $this->phpWord->addParagraphStyle('pAddress', ['spaceAfter' => 0, 'lineHeight' => 1.0]);
        $this->phpWord->addParagraphStyle('pSmall', ['spaceAfter' => 40, 'lineHeight' => 1.0]);

        $this->phpWord->addFontStyle('fTitle', ['name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => $darkBlue]);
        $this->phpWord->addFontStyle('fDocType', ['name' => 'Arial', 'size' => 14, 'bold' => true, 'color' => $blue]);
        $this->phpWord->addFontStyle('fCompany', ['name' => 'Arial', 'size' => 14, 'bold' => true, 'color' => $darkBlue]);
        $this->phpWord->addFontStyle('fLabel', ['name' => 'Arial', 'size' => 10, 'bold' => true]);
        $this->phpWord->addFontStyle('fValue', ['name' => 'Arial', 'size' => 10]);
        $this->phpWord->addFontStyle('fSmall', ['name' => 'Arial', 'size' => 10, 'color' => '666666']);
        $this->phpWord->addFontStyle('fSmallBold', ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => '666666']);
        $this->phpWord->addFontStyle('fTableHeader', ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => 'FFFFFF']);
        $this->phpWord->addFontStyle('fTableCell', ['name' => 'Arial', 'size' => 10]);
        $this->phpWord->addFontStyle('fTotalLabel', ['name' => 'Arial', 'size' => 10, 'bold' => true]);
        $this->phpWord->addFontStyle('fTotalValue', ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => $darkBlue]);
        $this->phpWord->addFontStyle('fAddressLabel', ['name' => 'Arial', 'size' => 10, 'bold' => true]);
        $this->phpWord->addFontStyle('fAddressName', ['name' => 'Arial', 'size' => 10, 'bold' => true]);
        $this->phpWord->addFontStyle('fAddressText', ['name' => 'Arial', 'size' => 10]);
    }

    private function addHeader(Section $section, string $docType = 'QUOTATION'): void
    {
        $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 40]);
        $table->addRow(600);

        $c1 = $table->addCell(Converter::cmToTwip(2.5));
        $c1->addImage('resources/logo_placeholder.png', ['width' => Converter::cmToTwip(2), 'height' => Converter::cmToTwip(2), 'align' => Jc::CENTER]);

        $c2 = $table->addCell(Converter::cmToTwip(12), ['alignment' => Jc::CENTER, 'valign' => 'center']);
        $c2->addText('${company_name}', 'fCompany', 'pTitle');
        $c2->addText('${company_address}', 'fValue', 'pCenter');
        $c2->addText('Telp: ${company_phone} | Email: ${company_email}', 'fSmall', 'pCenter');
        $c2->addText('NPWP: ${company_npwp}', 'fSmall', 'pCenter');

        $c3 = $table->addCell(Converter::cmToTwip(3.5), ['alignment' => Jc::END, 'valign' => 'center']);
        $c3->addText($docType, 'fDocType', 'pRight');

        $section->addLine(['weight' => 2, 'color' => $this->styles['blue'], 'spaceAfter' => 200]);
    }

    private function addDocInfo(Section $section, string $numberLabel = 'No. Quotation', bool $showDueDate = true, bool $showPartnerNpwp = true): void
    {
        $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 30]);

        $left = [[$numberLabel, '${doc_number}'], ['Tanggal', '${doc_date}']];
        if ($showDueDate) {
            $left[] = ['Jatuh Tempo', '${doc_due_date}'];
        }

        $right = [['Kepada', '${partner_name}'], ['Alamat', '${partner_address}'], ['Up.', '${partner_contact}'], ['Telp.', '${partner_phone}']];
        if ($showPartnerNpwp) {
            $right[] = ['NPWP', '${partner_npwp}'];
        }

        $row = $table->addRow();
        $cl = $row->addCell(Converter::cmToTwip(6));
        foreach ($left as $item) {
            $tr = $cl->addTextRun(['spaceAfter' => 40]);
            $tr->addText($item[0] . '  :  ', 'fLabel');
            $tr->addText($item[1], 'fValue');
        }

        $row->addCell(Converter::cmToTwip(1));

        $cr = $row->addCell(Converter::cmToTwip(10.5));
        foreach ($right as $item) {
            $tr = $cr->addTextRun(['spaceAfter' => 40]);
            $tr->addText($item[0] . '  :  ', 'fLabel');
            $tr->addText($item[1], 'fValue');
        }
    }

    private function addItemsTable(Section $section, bool $showPrice = true): void
    {
        $headers = $showPrice
            ? ['No', 'Nama Barang', 'Deskripsi', 'Qty', 'Satuan', 'Harga Satuan', 'Total']
            : ['No', 'Nama Barang', 'Deskripsi', 'Qty', 'Satuan', 'Keterangan'];
        $widths = $showPrice
            ? [0.8, 4.5, 3.5, 1.2, 1, 2.5, 2]
            : [0.8, 5, 4.5, 1.2, 1, 3];

        $table = $section->addTable(['borderSize' => 1, 'borderColor' => '999999', 'cellMargin' => 30]);
        $table->addRow();
        foreach ($headers as $i => $h) {
            $cell = $table->addCell(Converter::cmToTwip($widths[$i]), ['bgColor' => $this->styles['blue'], 'alignment' => Jc::CENTER, 'valign' => 'center']);
            $cell->addText($h, 'fTableHeader', 'pCenter');
        }

        $table->addRow();
        for ($i = 0; $i < count($headers); $i++) {
            $cell = $table->addCell(Converter::cmToTwip($widths[$i]));
            if ($i == 0)
                $cell->addText('${no}', 'fTableCell', 'pCenter');
            elseif ($i == 1)
                $cell->addText('${product_name}', 'fTableCell');
            elseif ($i == 2)
                $cell->addText('${description}', 'fTableCell');
            elseif ($i == 3)
                $cell->addText('${qty}', 'fTableCell', 'pCenter');
            elseif ($i == 4)
                $cell->addText('${uom}', 'fTableCell', 'pCenter');
            elseif ($i == 5)
                $cell->addText($showPrice ? '${unit_price}' : '${note}', 'fTableCell', 'pRight');
            elseif ($i == 6)
                $cell->addText('${total}', 'fTableCell', 'pRight');
        }
    }

    private function addTotals(Section $section): void
    {
        $masterTable = $section->addTable(['borderSize' => 0, 'cellMargin' => 0]);
        $row = $masterTable->addRow();

        // Left Cell: Bank Info
        $cLeft = $row->addCell(Converter::cmToTwip(9.5), ['valign' => 'top']);
        $cLeft->addText('INFORMASI PEMBAYARAN', 'fLabel', 'pLeft');
        $cLeft->addText('Bank: ${bank_name}', 'fValue', 'pAddress');
        $cLeft->addText('No. Rekening: ${bank_account_number}', 'fValue', 'pAddress');
        $cLeft->addText('Atas Nama: ${bank_account_name}', 'fValue', 'pAddress');

        // Right Cell: Totals Table
        $cRight = $row->addCell(Converter::cmToTwip(8.0), ['valign' => 'top']);
        $totalsTable = $cRight->addTable(['borderSize' => 0, 'cellMargin' => 40, 'alignment' => Jc::END]);
        $lW = Converter::cmToTwip(4.5);
        $vW = Converter::cmToTwip(3.5);

        $rows = [
            ['Subtotal', '${subtotal}'],
            ['Diskon', '${discount}'],
            ['PPN (11%)', '${tax}'],
            ['Total Tagihan', '${total_tagihan}'],
            ['${dp_label}', '${dp_value}'],
            ['${rem_label}', '${rem_value}'],
            ['${payment_type_label}', '${grand_total}'],
        ];
        foreach ($rows as $i => $r) {
            $totalsTable->addRow();
            $c = $totalsTable->addCell($lW, ['alignment' => Jc::END]);
            $c->addText($r[0], $i == 6 ? 'fTotalLabel' : 'fLabel', 'pRight');
            $c = $totalsTable->addCell($vW, [
                'alignment' => Jc::END,
                'bgColor' => $i == 6 ? $this->styles['lightBlue'] : null,
                'borderSize' => $i == 6 ? 1 : null,
                'borderColor' => $i == 6 ? $this->styles['blue'] : null,
            ]);
            $c->addText($r[1], $i == 6 ? 'fTotalValue' : 'fValue', 'pRight');
        }
    }

    private function addTerms(Section $section): void
    {
        $section->addText(' ');
        $section->addLine(['weight' => 1, 'color' => 'CCCCCC', 'spaceAfter' => 100]);

        $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 40]);
        $row = $table->addRow();

        $cl = $row->addCell(Converter::cmToTwip(17.5));
        $cl->addText('SYARAT DAN KETENTUAN', 'fLabel', 'pLeft');
        $cl->addText('${terms}', 'fSmall', 'pAddress');
        $cl->addText(' ', 'fValue', 'pAddress');
        $cl->addText('Catatan:', 'fLabel', 'pLeft');
        $cl->addText('${notes}', 'fSmall', 'pAddress');
    }

    private function addQuotationTerms(Section $section): void
    {
        $section->addText(' ');
        $section->addLine(['weight' => 1, 'color' => 'CCCCCC', 'spaceAfter' => 100]);

        $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 40]);
        $row = $table->addRow();

        $cl = $row->addCell(Converter::cmToTwip(17.5));
        $cl->addText('KETENTUAN PENAWARAN', 'fLabel', 'pLeft');

        $tr1 = $cl->addTextRun(['spaceAfter' => 20]);
        $tr1->addText('Kondisi Stok: ', 'fSmallBold');
        $tr1->addText('${stock_conditions}', 'fSmall');

        $tr2 = $cl->addTextRun(['spaceAfter' => 20]);
        $tr2->addText('Syarat Pembayaran: ', 'fSmallBold');
        $tr2->addText('${term_of_payment}', 'fSmall');

        $tr3 = $cl->addTextRun(['spaceAfter' => 20]);
        $tr3->addText('Kondisi Harga: ', 'fSmallBold');
        $tr3->addText('${price_conditions}', 'fSmall');

        $tr4 = $cl->addTextRun(['spaceAfter' => 20]);
        $tr4->addText('Standard Packing: ', 'fSmallBold');
        $tr4->addText('${standard_packing}', 'fSmall');

        $tr5 = $cl->addTextRun(['spaceAfter' => 40]);
        $tr5->addText('Masa Berlaku: ', 'fSmallBold');
        $tr5->addText('${offer_validity}', 'fSmall');

        $cl->addText('Catatan / Syarat Lain:', 'fLabel', 'pLeft');
        $cl->addText('${terms}', 'fSmall', 'pAddress');
        $cl->addText('${notes}', 'fSmall', 'pAddress');
    }

    private function addSignature(Section $section, array $rows = [['Hormat Kami', '']]): void
    {
        $section->addText(' ');
        $section->addText(' ');
        $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 40]);
        $row = $table->addRow();
        foreach ($rows as $r) {
            $cell = $row->addCell(Converter::cmToTwip(9.25));
            $cell->addText($r[0], 'fLabel', 'pCenter');
            $cell->addText(' ', 'fValue');
            $cell->addText(' ', 'fValue');
            $cell->addText(' ', 'fValue');
            $cell->addText('( ' . ($r[1] ?: '${signature_name}') . ' )', 'fValue', 'pCenter');
        }
    }

    private function addFooter(Section $section): void
    {
        $section->addText(' ');
        $section->addLine(['weight' => 1, 'color' => $this->styles['blue'], 'spaceBefore' => 200]);
        $section->addText('Dokumen ini dibuat secara terkomputerisasi - ${company_name} - ${doc_number}', 'fSmall', 'pCenter');
    }

    private function addDeliveryInfo(Section $section): void
    {
        $section->addText(' ');

        // Pengirim Section
        $tablePengirim = $section->addTable(['borderSize' => 0, 'cellMargin' => 40]);

        $row = $tablePengirim->addRow();
        $row->addCell(Converter::cmToTwip(2.5))->addText('Pengirim', 'fAddressLabel');
        $row->addCell(Converter::cmToTwip(0.5))->addText(':', 'fAddressLabel');
        $row->addCell(Converter::cmToTwip(13))->addText('${company_name}', 'fAddressName');

        $row = $tablePengirim->addRow();
        $row->addCell(Converter::cmToTwip(2.5))->addText('No HP', 'fAddressLabel');
        $row->addCell(Converter::cmToTwip(0.5))->addText(':', 'fAddressLabel');
        $row->addCell(Converter::cmToTwip(13))->addText('${company_phone}', 'fAddressText');

        $row = $tablePengirim->addRow();
        $row->addCell(Converter::cmToTwip(2.5))->addText('Alamat', 'fAddressLabel');
        $row->addCell(Converter::cmToTwip(0.5))->addText(':', 'fAddressLabel');
        $row->addCell(Converter::cmToTwip(13))->addText('${company_address}', 'fAddressText');

        $section->addText(' ');
        $section->addLine(['weight' => 1.5, 'color' => '000000', 'spaceBefore' => 200, 'spaceAfter' => 200]);
        $section->addText(' ');

        // Penerima Section
        $tablePenerima = $section->addTable(['borderSize' => 0, 'cellMargin' => 40]);

        $row = $tablePenerima->addRow();
        $row->addCell(Converter::cmToTwip(2.5))->addText('Penerima', 'fAddressLabel');
        $row->addCell(Converter::cmToTwip(0.5))->addText(':', 'fAddressLabel');
        $row->addCell(Converter::cmToTwip(13))->addText('${partner_pic}', 'fAddressName');

        $row = $tablePenerima->addRow();
        $row->addCell(Converter::cmToTwip(2.5))->addText('No HP', 'fAddressLabel');
        $row->addCell(Converter::cmToTwip(0.5))->addText(':', 'fAddressLabel');
        $row->addCell(Converter::cmToTwip(13))->addText('${partner_phone}', 'fAddressText');

        $row = $tablePenerima->addRow();
        $row->addCell(Converter::cmToTwip(2.5))->addText('Alamat', 'fAddressLabel');
        $row->addCell(Converter::cmToTwip(0.5))->addText(':', 'fAddressLabel');
        $cellPartnerAddress = $row->addCell(Converter::cmToTwip(13));
        $cellPartnerAddress->addText('${partner_name}', 'fAddressName');
        $cellPartnerAddress->addText('${partner_address}', 'fAddressText');
    }

    public function generateQuotation(): void
    {
        $section = $this->phpWord->addSection([
            'marginTop' => Converter::cmToTwip(1.5),
            'marginBottom' => Converter::cmToTwip(1.5),
            'marginLeft' => Converter::cmToTwip(2),
            'marginRight' => Converter::cmToTwip(2),
        ]);
        $this->addHeader($section, 'QUOTATION');
        $this->addDocInfo($section, 'No. Quotation', true, true);
        $section->addText('Dengan hormat,', 'fValue', 'pLeft');
        $section->addText('Bersama ini kami sampaikan penawaran harga untuk barang-barang sebagai berikut:', 'fValue', 'pLeft');
        $this->addItemsTable($section, true, false);
        $this->addTotals($section);
        $section->addText(' ');
        $section->addText('Demikian penawaran ini kami sampaikan. Harga di atas belum termasuk PPN 11%. Penawaran ini berlaku selama 14 (empat belas) hari sejak tanggal surat.', 'fValue', 'pLeft');
        $this->addQuotationTerms($section);
        $this->addSignature($section, [['Hormat Kami', '${signature_name}'], ['Mengetahui', '${signature_name2}']]);
        $this->addFooter($section);
        $this->save('quotation.docx');
    }

    public function generateProformaInvoice(): void
    {
        $section = $this->phpWord->addSection([
            'marginTop' => Converter::cmToTwip(1.5),
            'marginBottom' => Converter::cmToTwip(1.5),
            'marginLeft' => Converter::cmToTwip(2),
            'marginRight' => Converter::cmToTwip(2),
        ]);
        $this->addHeader($section, 'PROFORMA INVOICE');
        $this->addDocInfo($section, 'No. Proforma', true, true);
        $section->addText('Kepada Yth,', 'fValue', 'pLeft');
        $section->addText('Bersama ini kami sampaikan Proforma Invoice untuk barang-barang sebagai berikut:', 'fValue', 'pLeft');
        $this->addItemsTable($section, true, false);
        $this->addTotals($section);
        $section->addText(' ');
        $section->addText('Pembayaran harus dilakukan sebelum barang dikirim. Proforma ini berlaku selama 7 (tujuh) hari sejak tanggal diterbitkan.', 'fValue', 'pLeft');
        $this->addTerms($section);
        $this->addSignature($section, [['Hormat Kami', '${signature_name}']]);
        $section->addText(' ');
        $section->addText('${po_image}');
        $this->addFooter($section);
        $this->save('proforma_invoice.docx');
    }

    public function generateInvoice(): void
    {
        $section = $this->phpWord->addSection([
            'marginTop' => Converter::cmToTwip(1.5),
            'marginBottom' => Converter::cmToTwip(1.5),
            'marginLeft' => Converter::cmToTwip(2),
            'marginRight' => Converter::cmToTwip(2),
        ]);
        $this->addHeader($section, 'INVOICE');
        $this->addDocInfo($section, 'No. Invoice', true, true);
        $section->addText('Kepada Yth,', 'fValue', 'pLeft');
        $section->addText('Mohon dilakukan pembayaran atas tagihan berikut ini:', 'fValue', 'pLeft');
        $this->addItemsTable($section, true, false);
        $this->addTotals($section);
        $section->addText(' ');
        $section->addText('Pembayaran paling lambat pada tanggal jatuh tempo. Keterlambatan pembayaran akan dikenakan denda sebesar 2% per bulan.', 'fValue', 'pLeft');
        $this->addTerms($section);
        $this->addSignature($section, [['Hormat Kami', '${signature_name}']]);
        $section->addText(' ');
        $section->addText('${po_image}');
        $this->addFooter($section);
        $this->save('invoice.docx');
    }

    public function generateDeliverySlip(): void
    {
        $section = $this->phpWord->addSection([
            'marginTop' => Converter::cmToTwip(1.5),
            'marginBottom' => Converter::cmToTwip(1.5),
            'marginLeft' => Converter::cmToTwip(2),
            'marginRight' => Converter::cmToTwip(2),
        ]);
        $this->addHeader($section, 'SURAT JALAN');
        $this->addDocInfo($section, 'No. Surat Jalan', false, false);

        $section->addText(' ');
        $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 30]);
        $row = $table->addRow();
        $cl = $row->addCell(Converter::cmToTwip(8.5));
        $cl->addText('Kepada Yth.', 'fLabel', 'pAddress');
        $cl->addText('${partner_name}', 'fValue', 'pAddress');
        $cl->addText('${partner_address}', 'fSmall', 'pAddress');
        $cl->addText('${partner_phone}', 'fSmall', 'pAddress');

        $cr = $row->addCell(Converter::cmToTwip(8.5));
        foreach ([['No. Referensi', '${customer_po_number}'], ['Tanggal', '${doc_date}'], ['No. Invoice', '${invoice_number}']] as $ii) {
            $tr = $cr->addTextRun(['spaceAfter' => 40]);
            $tr->addText($ii[0] . '  :  ', 'fLabel');
            $tr->addText($ii[1], 'fValue');
        }

        $section->addText('Dengan ini dikirimkan barang-barang sebagai berikut:', 'fValue', 'pLeft');
        $this->addItemsTable($section, false, false);

        $section->addText(' ');
        $section->addText(' ');

        $sigTable = $section->addTable(['borderSize' => 0, 'cellMargin' => 40]);
        $sigRow = $sigTable->addRow();
        foreach ([['Pengirim', '${signature_name}'], ['Sopir', '${driver_name}'], ['Penerima', '${receiver_name}']] as $s) {
            $cell = $sigRow->addCell(Converter::cmToTwip(6.17));
            $cell->addText($s[0], 'fLabel', 'pCenter');
            $cell->addText(' ', 'fValue');
            $cell->addText(' ', 'fValue');
            $cell->addText(' ', 'fValue');
            $cell->addText('( ' . $s[1] . ' )', 'fValue', 'pCenter');
        }
        $this->addFooter($section);
        $this->save('delivery_slip.docx');
    }

    public function generateDeliveryAddress(): void
    {
        $section = $this->phpWord->addSection([
            'pageSizeW' => Converter::cmToTwip(23),
            'pageSizeH' => Converter::cmToTwip(11),
            'marginTop' => Converter::cmToTwip(1.5),
            'marginBottom' => Converter::cmToTwip(1.5),
            'marginLeft' => Converter::cmToTwip(1.5),
            'marginRight' => Converter::cmToTwip(1.5),
        ]);

        $this->addDeliveryInfo($section);
        $this->save('delivery_address.docx');
    }

    public function generatePurchaseOrder(): void
    {
        $section = $this->phpWord->addSection([
            'marginTop' => Converter::cmToTwip(1.5),
            'marginBottom' => Converter::cmToTwip(1.5),
            'marginLeft' => Converter::cmToTwip(2),
            'marginRight' => Converter::cmToTwip(2),
        ]);
        $this->addHeader($section, 'PURCHASE ORDER');
        $this->addDocInfo($section, 'No. PO', true, false);

        $section->addText('Kepada Yth. ${partner_name}', 'fValue', 'pLeft');
        $section->addText('di ${partner_address}', 'fValue', 'pLeft');
        $section->addText(' ', 'fValue');
        $section->addText('Dengan ini kami memesan barang-barang sebagai berikut:', 'fValue', 'pLeft');
        $this->addItemsTable($section, true, false);
        $this->addTotals($section);

        $section->addText(' ');
        $section->addText('Mohon barang dipersiapkan sesuai dengan spesifikasi di atas. Pengiriman barang paling lambat pada tanggal yang telah disepakati.', 'fValue', 'pLeft');

        $section->addText(' ');
        $section->addLine(['weight' => 1, 'color' => 'CCCCCC', 'spaceAfter' => 100]);

        $poTable = $section->addTable(['borderSize' => 0, 'cellMargin' => 40]);
        $poRow = $poTable->addRow();
        $cl = $poRow->addCell(Converter::cmToTwip(9));
        $cl->addText('INFORMASI PENGIRIMAN', 'fLabel', 'pLeft');
        $cl->addText('Dikirim ke: ${shipping_address}', 'fValue', 'pAddress');
        $cl->addText('Metode Kirim: ${shipping_method}', 'fValue', 'pAddress');
        $cl->addText('Tanggal Kirim: ${delivery_date}', 'fValue', 'pAddress');

        $cl->addText(' ', 'fValue');
        $cl->addText('REKENING PERUSAHAAN (KAMI)', 'fLabel', 'pLeft');
        $cl->addText('Bank: ${bank_name}', 'fValue', 'pAddress');
        $cl->addText('Atas Nama: ${bank_account_name}', 'fValue', 'pAddress');
        $cl->addText('No. Rekening: ${bank_account_number}', 'fValue', 'pAddress');

        $cr = $poRow->addCell(Converter::cmToTwip(8.5));
        $cr->addText('REKENING VENDOR (TUJUAN)', 'fLabel', 'pLeft');
        $cr->addText('Bank: ${vendor_bank_name}', 'fValue', 'pAddress');
        $cr->addText('Atas Nama: ${vendor_bank_account_name}', 'fValue', 'pAddress');
        $cr->addText('No. Rekening: ${vendor_bank_account_number}', 'fValue', 'pAddress');

        $cr->addText(' ', 'fValue');
        $cr->addText('SYARAT DAN KETENTUAN', 'fLabel', 'pLeft');
        $cr->addText('${terms}', 'fValue', 'pAddress');
        $cr->addText('Catatan:', 'fLabel', 'pLeft');
        $cr->addText('${notes}', 'fSmall', 'pAddress');

        $section->addText(' ');
        $section->addLine(['weight' => 1, 'color' => $this->styles['blue'], 'spaceBefore' => 400]);

        $authTable = $section->addTable(['borderSize' => 0, 'cellMargin' => 40]);
        $authRow = $authTable->addRow();
        foreach ([['Dibuat Oleh,', '${prepared_by}'], ['Diperiksa Oleh,', '${checked_by}'], ['Disetujui Oleh,', '${approved_by}']] as $a) {
            $cell = $authRow->addCell(Converter::cmToTwip(6.17));
            $cell->addText($a[0], 'fLabel', 'pCenter');
            $cell->addText(' ', 'fValue');
            $cell->addText(' ', 'fValue');
            $cell->addText(' ', 'fValue');
            $cell->addText('( ' . $a[1] . ' )', 'fValue', 'pCenter');
        }
        $this->addFooter($section);
        $this->save('purchase_order.docx');
    }

    private function save(string $filename): void
    {
        $path = __DIR__ . '/templates/' . $filename;
        $objWriter = IOFactory::createWriter($this->phpWord, 'Word2007');
        $objWriter->save($path);
        echo "  [OK] $filename\n";

        // Also save for prefixes (ifs and af)
        foreach (['ifs_', 'af_'] as $prefix) {
            $prefixedPath = __DIR__ . '/templates/' . $prefix . $filename;
            if ($prefix === 'ifs_' && in_array($filename, ['purchase_order.docx', 'invoice.docx', 'proforma_invoice.docx']) && file_exists($prefixedPath)) {
                echo "  [SKIP] " . $prefix . $filename . " (skipped to preserve user edits)\n";
                continue;
            }
            copy($path, $prefixedPath);
            echo "  [OK] " . $prefix . $filename . " (copied)\n";
        }

        $this->phpWord = new PhpWord();
        $this->setupStyles();
    }
}

echo "Indofilter ERP - DOCX Template Generator\n";
echo str_repeat('=', 45) . "\n\n";

if (!is_dir(__DIR__ . '/resources')) {
    mkdir(__DIR__ . '/resources', 0777, true);
}
$ph = __DIR__ . '/resources/logo_placeholder.png';
if (!file_exists($ph)) {
    file_put_contents($ph, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
}

$gen = new TemplateGenerator();
$gen->generateQuotation();
$gen->generateProformaInvoice();
$gen->generateInvoice();
$gen->generateDeliverySlip();
$gen->generateDeliveryAddress();
$gen->generatePurchaseOrder();

echo "\n" . str_repeat('=', 45) . "\n";
echo "6 templates created successfully in /templates/\n";
