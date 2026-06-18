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
            'email' => 'admin@indofilter.com',
            'password' => 'password',
        ]);

        $company1 = Company::create([
            'name' => 'PT Indofilter Utama',
            'alias' => 'Indofilter Utama',
            'address' => 'Jl. Raya Industri No. 1, Jakarta',
            'phone' => '021-12345678',
            'email' => 'utama@indofilter.com',
            'npwp' => '01.234.567.8-901.000',
            'is_active' => true,
        ]);
        $company1->phones()->createMany([
            ['phone' => '021-12345678', 'label' => 'Kantor'],
            ['phone' => '0812-34567890', 'label' => 'Mobile'],
        ]);
        $company1->bankAccounts()->createMany([
            ['bank_name' => 'Bank Mandiri', 'account_name' => 'PT Indofilter Utama', 'account_number' => '123-00-1234567-8'],
            ['bank_name' => 'BNI', 'account_name' => 'PT Indofilter Utama', 'account_number' => '456-78-9012345-6'],
        ]);

        $company2 = Company::create([
            'name' => 'PT Indofilter Sukses',
            'alias' => 'Indofilter Sukses',
            'address' => 'Jl. Raya Industri No. 2, Bandung',
            'phone' => '022-87654321',
            'email' => 'sukses@indofilter.com',
            'npwp' => '09.876.543.2-109.000',
            'is_active' => true,
        ]);
        $company2->phones()->createMany([
            ['phone' => '022-87654321', 'label' => 'Kantor'],
            ['phone' => '0813-98765432', 'label' => 'Mobile'],
        ]);
        $company2->bankAccounts()->createMany([
            ['bank_name' => 'BCA', 'account_name' => 'PT Indofilter Sukses', 'account_number' => '987-65-4321098-7'],
            ['bank_name' => 'Bank Mandiri', 'account_name' => 'PT Indofilter Sukses', 'account_number' => '888-99-7654321-0'],
        ]);

        $partner1 = Partner::create([
            'company_id' => $company1->id,
            'type' => 'customer',
            'name' => 'CV Maju Jaya',
            'address' => 'Jl. Merdeka No. 10, Jakarta',
            'phone' => '021-55556666',
            'email' => 'info@majujaya.com',
            'npwp' => '12.345.678.9-012.000',
            'contact_person' => 'Budi Santoso',
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
            'contact_person' => 'Agus Wijaya',
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
            'contact_person' => 'Dewi Lestari',
            'is_active' => true,
        ]);

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
