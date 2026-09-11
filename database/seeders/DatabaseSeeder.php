<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Partner;
use App\Models\Product;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Admin Indofilter',
            'email' => 'arthawa@onesulution.com',
            'password' => '72onevif',
        ]);

        User::factory()->create([
            'name' => 'Admin Arthawa',
            'email' => 'indo@arthawa.com',
            'password' => 'Rone199023#',
        ]);

        $company1 = Company::create([
            'name' => 'PT. INDO FILTER SEMESTA',
            'alias' => 'IFS',
            'address' => 'Komp Ruko Palem Ganda Asri, Jl. Raden Saleh No.3 Blok A5, RT.001/RW.016, Karang Tengah, Kec. Karang Tengah, Kota Tangerang, Banten 15157',
            'phone' => '0811-1881-234',
            'email' => 'sales@indofilter.com',
            'npwp' => '01.234.567.8-901.000',
            'is_active' => true,
        ]);
        $company1->phones()->createMany([
            ['phone' => '0811-1881-234', 'label' => 'No HP 1'],
            ['phone' => '087777-331-330', 'label' => 'No HP 2'],
        ]);
        $company1->bankAccounts()->createMany([
            [
                'bank_name' => 'BCA',
                'account_name' => 'INDO FILTER SEMESTA PT',
                'account_number' => '7010-1887-89',
                'is_default' => true
            ],
            [
                'bank_name' => 'BCA',
                'account_name' => 'SRI MURNININGSIH',
                'account_number' => '2290431541',
                'is_default' => false
            ],
        ]);

        $company2 = Company::create([
            'name' => 'PT. INDO ARTHAWA FILTER',
            'alias' => 'AF',
            'address' => 'Komp Ruko Palem Ganda Asri, Jl. Raden Saleh No.3 Blok A5, RT.001/RW.016, Karang Tengah, Kec. Karang Tengah, Kota Tangerang, Banten 15157',
            'phone' => '087777-331-330',
            'email' => 'indo@arthawa.com',
            'npwp' => '09.876.543.2-109.000',
            'is_active' => true,
        ]);
        $company2->phones()->createMany([
            ['phone' => '087777-331-330', 'label' => 'No HP 1'],
            ['phone' => '0811-1881-234', 'label' => 'No HP 2'],
        ]);
        $company2->bankAccounts()->createMany([
            [
                'bank_name' => 'MANDIRI',
                'account_name' => 'PT. INDO ARTHAWA FILTER',
                'account_number' => '165-000-299-2593',
                'is_default' => true
            ],
            [
                'bank_name' => 'BCA',
                'account_name' => 'Mochamad Ridwan',
                'account_number' => '2481511781',
                'is_default' => false
            ],
        ]);

        $partner1 = Partner::create([
            'company_id' => $company1->id,
            'type' => 'customer',
            'name' => 'CV Maju Jaya',
            'address' => 'Jl. Merdeka No. 10, Jakarta',
            'phone' => '021-55556666',
            'email' => 'info@majujaya.com',
            'npwp' => '12.345.678.9-012.000',
            'contact_person' => 'Bpk. Budi Santoso',
            'is_active' => true,
        ]);

        $partner2 = Partner::create([
            'company_id' => $company1->id,
            'type' => 'vendor',
            'name' => 'PT Bahan Baku Sejahtera',
            'address' => 'Jl. Industri Raya No. 5, Tangerang',
            'phone' => '021-77778888',
            'email' => 'sales@bahanbaku.com',
            'npwp' => '98.765.432.1-098.000',
            'contact_person' => 'Bpk. Agus Wijaya',
            'is_active' => true,
        ]);

        $partner3 = Partner::create([
            'company_id' => $company2->id,
            'type' => 'customer',
            'name' => 'Toko Filter Abadi',
            'address' => 'Jl. Diponegoro No. 22, Bandung',
            'phone' => '022-44443333',
            'email' => 'abadi@tokofilter.com',
            'npwp' => '45.678.901.2-345.000',
            'contact_person' => 'Ibu Dewi Lestari',
            'is_active' => true,
        ]);

        $newPartners = [
            [
                'company_id' => $company1->id,
                'type' => 'customer',
                'name' => 'PT. TEMPO NATURAL PRODUCT',
                'address' => 'EJIP Industrial Park Plot 2 G2, Cikarang Selatan, Bekasi 17550',
                'phone' => '0815-6320-4062',
                'contact_person' => 'Ibu Eulis',
                'is_active' => true,
                'alias' => 'TNP',
            ],
            [
                'company_id' => $company1->id,
                'type' => 'customer',
                'name' => 'PT. BINTANG ANUGRAH SEHATI',
                'address' => 'Jl. Jembatan Batu No. 82-83 Pinangsia, Taman Sari, Jakarta Barat 11110',
                'phone' => '0851-7416-4745',
                'contact_person' => 'Bpk. Muslihin',
                'is_active' => true,
                'alias' => 'BAS',
            ],
            [
                'company_id' => $company1->id,
                'type' => 'customer',
                'name' => 'PT. GERASI HENOKH SEJAHTERA',
                'address' => null,
                'phone' => '0823-1234-4864',
                'contact_person' => 'Bpk. Indra',
                'is_active' => true,
                'alias' => 'GHS',
            ],
            [
                'company_id' => $company1->id,
                'type' => 'customer',
                'name' => 'PT. KOSMETIKA GLOBAL HEALTH',
                'address' => 'Jl. Ciujung Kawasan EJIP Pintu II, Sukaresmi, Cikarang, Bekasi',
                'phone' => null,
                'contact_person' => 'Bpk. Asep',
                'is_active' => true,
                'alias' => 'KGH',
            ],
            [
                'company_id' => $company1->id,
                'type' => 'customer',
                'name' => 'PT. KRIDA HANGULINDO UTAMA',
                'address' => null,
                'phone' => null,
                'contact_person' => 'Ibu Risma',
                'is_active' => true,
                'alias' => 'KHU',
            ],
            [
                'company_id' => $company1->id,
                'type' => 'customer',
                'name' => 'PT. MENARA MAS',
                'address' => null,
                'phone' => null,
                'contact_person' => 'Ibu Rina',
                'is_active' => true,
                'alias' => 'MM',
            ],
            [
                'company_id' => $company1->id,
                'type' => 'customer',
                'name' => 'PT. GLOBAL PAPUA ABADI',
                'address' => null,
                'phone' => '0816-967-744',
                'contact_person' => 'Bpk. Djoko',
                'is_active' => true,
                'alias' => 'GPA',
            ],
            [
                'company_id' => $company1->id,
                'type' => 'customer',
                'name' => 'Bapak Salim',
                'address' => 'Jl. Sukadana No. 8, Pintu Belakang ITC Roxy Mas',
                'phone' => '0896-0300-2428',
                'contact_person' => 'Bpk. Salim',
                'is_active' => true,
                'alias' => 'SALIM',
            ],
            [
                'company_id' => $company1->id,
                'type' => 'customer',
                'name' => 'PT. PRIMATAMA UNGGUL MAKMUR',
                'address' => 'Rukan Galleria West Blok H1A/5, Citra Garden 6, Jakarta Barat',
                'phone' => null,
                'contact_person' => 'Bpk. Aan',
                'is_active' => true,
                'alias' => 'PUM',
            ],
            [
                'company_id' => $company1->id,
                'type' => 'customer',
                'name' => 'PT. TRINITI MURNI JAYA',
                'address' => 'Galeri Niaga Mediterania I No.A8L, Penjaringan, Jakarta Utara 14460',
                'phone' => '081280305767',
                'contact_person' => 'Bpk. Ricky',
                'is_active' => true,
                'alias' => 'TMJ',
            ],
            [
                'company_id' => $company1->id,
                'type' => 'customer',
                'name' => 'PT. MULTI SURYA MAKMUR GEMILANG',
                'address' => 'Jl. Meruya Utara Raya No.3, RT.1/RW.1, Meruya Utara, Kembangan, Jakarta Barat 11620',
                'phone' => null,
                'contact_person' => 'Ibu Natasha',
                'is_active' => true,
                'alias' => 'MSMG',
            ],
            [
                'company_id' => $company1->id,
                'type' => 'customer',
                'name' => 'PT. TIRTA TEKNOSYS',
                'address' => null,
                'phone' => null,
                'contact_person' => 'Ibu April',
                'is_active' => true,
                'alias' => 'TT',
            ],
            [
                'company_id' => $company1->id,
                'type' => 'customer',
                'name' => 'PT. SURAYA MEGAH CEMERLANG',
                'address' => 'Pertokoan Bestland Unit B-10 Jl. Dupak No. 61, Surabaya',
                'phone' => '0857-3514-0799',
                'contact_person' => 'Bpk. Vicky',
                'is_active' => true,
                'alias' => 'SMC',
            ],
            [
                'company_id' => $company1->id,
                'type' => 'customer',
                'name' => 'CV SARI SEDANA',
                'address' => 'Jl. Danau Tondano No.44, Sanur, Denpasar Selatan, Kota Denpasar, Bali 80228',
                'phone' => '0812-3844-316',
                'contact_person' => 'Bpk. Christian',
                'is_active' => true,
                'alias' => 'SS',
            ],
        ];

        foreach ($newPartners as $p) {
            Partner::create($p);
        }


        $product1 = Product::create([
            'company_id' => $company1->id,
            'code' => 'F-OIL-001',
            'name' => 'Oil Filter AF-100',
            'description' => 'Oil filter for heavy duty engine',
            'uom' => 'PCS',
            'price' => 150000,
            'is_active' => true,
        ]);

        $product2 = Product::create([
            'company_id' => $company1->id,
            'code' => 'F-AIR-001',
            'name' => 'Air Filter AF-200',
            'description' => 'Air filter for industrial compressor',
            'uom' => 'PCS',
            'price' => 250000,
            'is_active' => true,
        ]);

        $product3 = Product::create([
            'company_id' => $company2->id,
            'code' => 'F-HYD-001',
            'name' => 'Hydraulic Filter HF-50',
            'description' => 'Hydraulic filter for heavy machinery',
            'uom' => 'PCS',
            'price' => 350000,
            'is_active' => true,
        ]);

        $product4 = Product::create([
            'company_id' => $company2->id,
            'code' => 'F-FUEL-001',
            'name' => 'Fuel Filter FF-75',
            'description' => 'Fuel filter for diesel engine',
            'uom' => 'PCS',
            'price' => 175000,
            'is_active' => true,
        ]);

        $documentService = app(DocumentNumberService::class);

        $doc1 = Document::create([
            'company_id' => $company1->id,
            'partner_id' => $partner1->id,
            'type' => 'quotation',
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
            'status' => 'draft',
            'subtotal' => 400000,
            'discount' => 0,
            'tax' => 44000,
            'grand_total' => 444000,
            'stock_conditions' => 'Barang tersedia minimal 100 pcs',
            'term_of_payment' => '30% DP, 70% setelah barang diterima',
            'price_conditions' => 'Harga belum termasuk PPN 11%',
            'standard_packing' => 'Kardus + bubble wrap',
            'offer_validity' => '30 hari sejak tanggal penawaran',
        ]);
        $doc1->document_number = $documentService->generate($doc1);
        $doc1->save();
        $doc1->items()->createMany([
            ['product_name' => 'Oil Filter AF-100', 'qty' => 1, 'unit_price' => 150000, 'total' => 150000],
            ['product_name' => 'Air Filter AF-200', 'qty' => 1, 'unit_price' => 250000, 'total' => 250000],
        ]);

        $doc2 = Document::create([
            'company_id' => $company2->id,
            'partner_id' => $partner3->id,
            'type' => 'invoice',
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'status' => 'confirmed',
            'subtotal' => 525000,
            'discount' => 25000,
            'tax' => 55000,
            'grand_total' => 555000,
        ]);
        $doc2->document_number = $documentService->generate($doc2);
        $doc2->save();
        $doc2->items()->createMany([
            ['product_name' => 'Hydraulic Filter HF-50', 'qty' => 1, 'unit_price' => 350000, 'total' => 350000],
            ['product_name' => 'Fuel Filter FF-75', 'qty' => 1, 'unit_price' => 175000, 'total' => 175000],
        ]);
    }
}
