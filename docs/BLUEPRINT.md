# Indofilter ERP — Blueprint Sistem

## 1. Gambaran Umum

Sistem ERP untuk mengelola 2 PT (PT Indofilter Utama & PT Indofilter Sukses) dengan modul:

| Modul | Dokumen |
|-------|---------|
| Sales | Quotation → Proforma Invoice → Invoice |
| Inventory | Delivery Slip, Delivery Address (Resi) |
| Purchasing | Purchase Order (PO Keluar) |

## 2. Arsitektur Sistem

```
┌─────────────────────────────────────────────────────┐
│                   Frontend (Odoo-style UI)           │
│  Vue.js 3 + Vite + Tailwind CSS (Biru Indofilter)   │
├─────────────────────────────────────────────────────┤
│                   REST API                           │
│              Laravel 11 Sanctum                      │
├─────────────────────────────────────────────────────┤
│                   Database                           │
│              MySQL 8 (Laragon)                       │
├─────────────────────────────────────────────────────┤
│                   DOCX Engine                        │
│              PHPWord — Template Processor            │
└─────────────────────────────────────────────────────┘
```

## 3. Modul & Flow Dokumen

### Sales Flow
```
Quotation ──(disetujui)──→ Proforma Invoice ──(pembayaran)──→ Invoice
```

### Inventory Flow
```
Invoice ──→ Delivery Slip (Surat Jalan) + Delivery Address (Resi)
```

### Purchasing Flow
```
Permintaan ──→ Purchase Order (PO ke Vendor)
```

## 4. Database Schema (Core Tables)

```sql
-- Perusahaan (Multi-company)
companies: id, name, alias, logo, address, phone, email, npwp, bank_account

-- Relasi (Customer / Vendor)
partners: id, company_id, type(customer|vendor), name, address, phone, email, npwp, contact_person

-- Dokumen Header
documents: id, type(quotation|proforma|invoice|delivery|address|po),
    company_id, partner_id, document_number, date, due_date,
    terms, notes, status(draft|confirmed|canceled),
    subtotal, discount, tax, grand_total

-- Item Baris
document_items: id, document_id, product_name, description, qty, uom, unit_price, total

-- Alamat Pengiriman (untuk Delivery Address)
delivery_addresses: id, document_id, partner_id, label, address, city, province, postal_code, phone, contact_person

-- Produk
products: id, company_id, code, name, uom, price, description
```

## 5. Format Nomor Dokumen

| Dokumen | Format |
|----------|--------|
| Quotation | QTN-{MM}/{YYYY}-{0001} |
| Proforma Invoice | PFI-{MM}/{YYYY}-{0001} |
| Invoice | INV-{MM}/{YYYY}-{0001} |
| Delivery Slip | SJ-{MM}/{YYYY}-{0001} |
| Delivery Address | RESI-{MM}/{YYYY}-{0001} |
| Purchase Order | PO-{MM}/{YYYY}-{0001} |

## 6. UI Design (Odoo-style)

### Color Palette — Indofilter Blue
```
Primary:   #1A56DB (Biru Indofilter)
Secondary: #0E3B8A (Biru Tua)
Accent:    #3B82F6 (Biru Terang)
BG Light:  #F0F4FF (Latar Biru Muda)
Success:   #10B981
Warning:   #F59E0B
Danger:    #EF4444
```

### Layout — Odoo Kanban / List View
```
┌────────────────────────────────────────────┐
│  [LOGO] Indofilter ERP        [2 PT] [User]│ ← Navbar
├────────────────────────────────────────────┤
│  Sales │ Inventory │ Purchasing │ Reports   │ ← Menu
├────┬───────────────────────────────────────┤
│    │  ┌─────┬──────┬──────┬──────┬──────┐ │
│    │  │ QTN │ PFI  │ INV  │ SJ   │ PO   │ │ ← Dashboard Cards
│    │  ├─────┴──────┴──────┴──────┴──────┤ │
│  S │  │ Filter: [All] [Draft] [Confirm] │ │
│  I │  ├─────────────────────────────────┤ │
│  D │  │ # │ Doc Num │ Partner │ Total │ │ │ ← List View
│  E │  │ 1 │ QTN-... │ PT ABC  │ 5.000K │ │
│  B │  │ 2 │ QTN-... │ PT XYZ  │ 3.200K │ │
│  A │  └─────────────────────────────────┘ │
│  R │                                       │
└────┴───────────────────────────────────────┘
```

## 7. Struktur Template DOCX

Setiap template menggunakan **PHPWord Template Processor** dengan placeholder `{VARIABLE}`.

### Daftar Placeholder Umum
```
{company_name}       — Nama PT
{company_logo}       — Logo perusahaan
{company_address}    — Alamat PT
{company_phone}      — Telepon
{company_email}      — Email
{company_npwp}       — NPWP
{company_bank}       — Rekening Bank
{doc_number}         — Nomor dokumen
{doc_date}           — Tanggal
{doc_due_date}       — Tanggal jatuh tempo
{partner_name}       — Nama customer/vendor
{partner_address}    — Alamat customer/vendor
{partner_phone}      — Telepon customer/vendor
{partner_npwp}       — NPWP customer/vendor
{terms}              — Syarat pembayaran
{notes}              — Catatan
```

### Placeholder Tabel Item (Block Clone)
```
{items}
{product_name}  {description}  {qty}  {uom}  {unit_price}  {total}
{/items}

{subtotal}  {discount}  {tax}  {grand_total}
```

## 8. Template Documents (6 files)

Setiap dokumen di atas dapat dikustomisasi per-perusahaan dengan menambahkan prefiks alias perusahaan yang di-lowercase (contoh: `ifs_quotation.docx`, `af_quotation.docx`). Jika berkas spesifik perusahaan tidak ditemukan, sistem akan otomatis menggunakan template default.

| File | Deskripsi |
|------|-----------|
| `templates/quotation.docx` | Surat Penawaran Harga (Default) |
| `templates/proforma_invoice.docx` | Proforma Invoice (PI) (Default) |
| `templates/invoice.docx` | Invoice / Faktur Pajak (Default) |
| `templates/delivery_slip.docx` | Surat Jalan (Default) |
| `templates/delivery_address.docx` | Alamat Pengiriman (seperti Resi) (Default) |
| `templates/purchase_order.docx` | Purchase Order (PO ke Vendor) (Default) |

## 9. Tech Stack

| Layer | Teknologi |
|-------|-----------|
| Backend | Laravel 11 + PHP 8.2 |
| Frontend | Vue.js 3 + Vite + Tailwind CSS |
| Database | MySQL 8 (via Laragon) |
| DOCX Engine | PHPWord |
| Auth | Laravel Sanctum |
| Multi-company | Database column `company_id` |

## 10. Milestone

1. ✅ Database schema & migration
2. ✅ DOCX templates generation (6 templates)
3. ⏳ CRUD master data (companies, partners, products)
4. ⏳ Modul Sales (Quotation → PI → Invoice)
5. ⏳ Modul Inventory (Delivery Slip, Address)
6. ⏳ Modul Purchasing (PO)
7. ⏳ DOCX Export & Print
8. ⏳ Multi-company switcher

---

*Dibuat: Juni 2026 — Indofilter ERP v1.0*
