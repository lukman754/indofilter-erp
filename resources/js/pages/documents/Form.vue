<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
    documents as api,
    partners as partnersApi,
    companies as companiesApi,
    products as productsApi,
} from "../../api/index.js";
import { useAppStore } from "../../stores/app.js";

const route = useRoute();
const router = useRouter();
const appStore = useAppStore();

function showConfirm(title, message, onConfirm, onCancel = null) {
    appStore.showConfirm(title, message, onConfirm, onCancel);
}

function showNotification(title, message, type = "success") {
    appStore.showNotification(title, message, type);
}

const isEdit = computed(() => !!route.params.id);
const isLoaded = ref(!isEdit.value);
const loading = ref(false);
const saving = ref(false);
const error = ref("");
const partnersList = ref([]);
const companiesList = ref([]);
const productsList = ref([]);
const documentsList = ref([]);

const isOpenPartnerModal = ref(false);
const isSavingPartner = ref(false);
const partnerForm = ref({
    name: "",
    alias: "",
    type: "customer",
    address: "",
    phone: "",
    email: "",
    npwp: "",
    contact_person: "",
    company_id: "",
});
const selectedRefDocumentId = ref("");
const refSearch = ref("");
const isOpenRefDropdown = ref(false);
const partnerSearch = ref("");
const isOpenPartnerDropdown = ref(false);
const filteredPartners = computed(() => {
    const q = partnerSearch.value.toLowerCase().trim();
    if (!q) return partnersList.value;
    return partnersList.value.filter(
        (p) =>
            p.name.toLowerCase().includes(q) ||
            (p.alias && p.alias.toLowerCase().includes(q)),
    );
});
const displayPartnerName = computed(() => {
    const p = partnersList.value.find((p) => p.id === form.value.partner_id);
    return p ? p.name : "";
});

const parsingPdf = ref(false);
const uploadedPdfName = ref("");
const pdfFileInput = ref(null);

const filteredRefDocuments = computed(() => {
    const q = refSearch.value.toLowerCase().trim();
    if (!q) return documentsList.value;
    return documentsList.value.filter((doc) => {
        const typeStr = (doc.type || "")
            .toUpperCase()
            .replace("_", " ")
            .toLowerCase();
        const numberStr = (doc.document_number || "").toLowerCase();
        const partnerStr = (doc.partner?.name || "").toLowerCase();
        const dateStr = (doc.date || "").toLowerCase();
        return (
            typeStr.includes(q) ||
            numberStr.includes(q) ||
            partnerStr.includes(q) ||
            dateStr.includes(q)
        );
    });
});

function selectRefDocument(doc) {
    selectedRefDocumentId.value = doc.id;
    form.value.reference_id = doc.id;
    refSearch.value = `${doc.document_number || "(Draft)"} - ${doc.partner?.name || "No Partner"}`;
    isOpenRefDropdown.value = false;

    if (!isEdit.value) {
        handleCopyFromDocument();
    } else {
        showConfirm(
            "Salin Referensi",
            "Apakah Anda ingin menyalin data (barang, partner, dll.) dari dokumen referensi ini? Klik Batal jika hanya ingin menghubungkan referensi saja.",
            () => {
                handleCopyFromDocument();
            },
        );
    }
}

watch(refSearch, (newVal) => {
    if (!newVal) {
        selectedRefDocumentId.value = "";
        form.value.reference_id = "";
    }
});

const form = ref({
    company_id: "",
    partner_id: "",
    document_type: "Quotation",
    number: "",
    date: new Date().toISOString().split("T")[0],
    due_date: "",
    terms: "",
    notes: "",
    stock_conditions: "",
    term_of_payment: "",
    price_conditions: "",
    standard_packing: "",
    offer_validity: "",
    discount: 0,
    tax: 0,
    is_ppn: true,
    bank_account_id: "",
    payment_type: "full",
    dp_percent: 30,
    vendor_bank_name: "",
    vendor_bank_account_name: "",
    vendor_bank_account_number: "",
    items: [],
    status: "draft",
    reference_id: "",
    sender_name: "",
    sender_phone: "",
    sender_address: "",
    recipient_name: "",
    recipient_phone: "",
    recipient_address: "",
    recipient_pic: "",
    customer_po_number: "",
    customer_po_date: "",
    customer_po_file: "",
});

const selectedPoFile = ref(null);
function handlePoFileChange(e) {
    const file = e.target.files[0];
    if (file) {
        selectedPoFile.value = file;
    }
}
async function handleViewPoFile() {
    // Jika dokumen baru (belum punya ID), coba pakai reference document (PI) sebagai sumber file
    const docId = route.params.id || selectedRefDocumentId.value;
    if (!docId) return;
    try {
        const response = await api.downloadPo(docId);
        const blob = new Blob([response.data], {
            type: response.headers["content-type"],
        });
        const url = window.URL.createObjectURL(blob);
        window.open(url, "_blank");
    } catch (e) {
        showNotification(
            "Gagal Membuka PO",
            "Gagal menampilkan file PO: " +
                (e.response?.data?.message || e.message),
            "error",
        );
    }
}

const quotationDefaults = {
    stock_conditions: "Ready Stock",
    term_of_payment: "Cash Before Delivery",
    price_conditions: "Grand Total Include PPN and Exclude Delivery",
    standard_packing: "Cardboard Box",
    offer_validity: "Valid for 14 days",
};

watch(
    () => form.value.document_type,
    (val) => {
        if (val === "Delivery Slip") {
            form.value.due_date = "";
        }
        if (val === "Quotation" && !isEdit.value) {
            Object.assign(form.value, quotationDefaults);
        }
    },
);

const isNumberManuallyEdited = ref(false);

async function autoGenerateNumber() {
    if (isEdit.value && form.value.number) return;
    if (isEdit.value && !isLoaded.value) return;
    if (isNumberManuallyEdited.value) return;
    if (
        !form.value.company_id ||
        !form.value.partner_id ||
        !form.value.date ||
        !form.value.document_type
    )
        return;

    try {
        const firstItem = form.value.items[0];

        let refProductCode = "";
        if (selectedRefDocumentId.value) {
            const refDoc = documentsList.value.find(
                (d) => d.id === selectedRefDocumentId.value,
            );
            if (refDoc && refDoc.document_number) {
                const parts = refDoc.document_number.split("-");
                if (parts[5]) {
                    refProductCode = parts[5];
                }
            }
        }

        const params = {
            type: typeMap[form.value.document_type] || form.value.document_type,
            company_id: form.value.company_id,
            partner_id: form.value.partner_id,
            date: form.value.date,
            product_name: firstItem ? firstItem.product_name : "",
            product_code: refProductCode || undefined,
        };
        const res = await api.generateNumber(params);
        form.value.number = res.data.number;
    } catch (e) {
        // ignore error
    }
}

function handleNumberInput() {
    if (form.value.number === "") {
        isNumberManuallyEdited.value = false;
        autoGenerateNumber();
    } else {
        isNumberManuallyEdited.value = true;
    }
}

const handlePdfUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    uploadedPdfName.value = file.name;
    parsingPdf.value = true;
    error.value = "";

    const formData = new FormData();
    formData.append("pdf", file);

    try {
        const res = await api.parsePdf(formData);
        if (res.data && res.data.success) {
            const data = res.data.data;

            if (data.partner_id) {
                form.value.partner_id = data.partner_id;
            } else if (data.partner_name) {
                showNotification(
                    "Partner Tidak Ditemukan",
                    `Partner "${data.partner_name}" tidak ditemukan di database. Silakan pilih partner secara manual.`,
                    "warning",
                );
            }

            if (data.document_number) form.value.number = data.document_number;
            if (data.date) form.value.date = data.date;
            if (data.due_date) form.value.due_date = data.due_date;
            if (data.notes) form.value.notes = data.notes;
            if (data.terms) form.value.terms = data.terms;
            if (data.discount !== undefined)
                form.value.discount = data.discount;
            if (data.is_ppn !== undefined) form.value.is_ppn = data.is_ppn;

            if (data.items && data.items.length > 0) {
                form.value.items = data.items.map((item) => ({
                    product_name: item.product_name,
                    description: item.description || "",
                    qty: item.qty || 1,
                    uom: item.uom || "PCS",
                    unit_price: item.unit_price || 0,
                    total: (item.qty || 1) * (item.unit_price || 0),
                }));
            }

            showNotification(
                "Analisis PDF Sukses",
                "PDF berhasil dibaca! Data form telah diisi otomatis.",
                "success",
            );
        } else {
            error.value = res.data.message || "Gagal menganalisis PDF.";
        }
    } catch (e) {
        error.value =
            e.response?.data?.message || e.message || "Gagal menganalisis PDF.";
    } finally {
        parsingPdf.value = false;
        if (event.target) event.target.value = "";
    }
};

watch(
    [
        () => form.value.document_type,
        () => form.value.company_id,
        () => form.value.partner_id,
        () => form.value.date,
        () => selectedRefDocumentId.value,
    ],
    (
        [newType, newCompany, newPartner, newDate],
        [oldType, oldCompany, oldPartner, oldDate],
    ) => {
        // If partner or company changed explicitly by user, allow number regeneration
        if (newPartner !== oldPartner || newCompany !== oldCompany) {
            isNumberManuallyEdited.value = false;
        }
        autoGenerateNumber();
    },
);

watch(
    () => form.value.items,
    () => {
        autoGenerateNumber();
    },
    { deep: true },
);

watch(
    () => form.value.company_id,
    (newCompanyId, oldCompanyId) => {
        if (!newCompanyId) {
            form.value.bank_account_id = "";
            return;
        }

        const shouldAutoSelect =
            !isEdit.value ||
            (oldCompanyId !== undefined && oldCompanyId !== "");
        const company = companiesList.value.find((c) => c.id === newCompanyId);

        if (shouldAutoSelect) {
            if (
                company &&
                company.bank_accounts &&
                company.bank_accounts.length > 0
            ) {
                const defaultAcc = company.bank_accounts.find(
                    (b) => b.is_default,
                );
                form.value.bank_account_id = defaultAcc
                    ? defaultAcc.id
                    : company.bank_accounts[0].id;
            } else {
                form.value.bank_account_id = "";
            }
        }

        if (!isEdit.value || !form.value.sender_name) {
            form.value.sender_name = company?.name || "";
            form.value.sender_phone = company?.phone || "";
            form.value.sender_address = company?.address || "";
        }
    },
);

watch(
    () => form.value.partner_id,
    (newPartnerId) => {
        if (!newPartnerId) {
            if (!isEdit.value) {
                form.value.vendor_bank_name = "";
                form.value.vendor_bank_account_name = "";
                form.value.vendor_bank_account_number = "";
                form.value.recipient_name = "";
                form.value.recipient_phone = "";
                form.value.recipient_address = "";
                form.value.recipient_pic = "";
            }
            return;
        }
        const partner = partnersList.value.find((p) => p.id === newPartnerId);
        if (partner && partner.type === "vendor") {
            if (!form.value.vendor_bank_name)
                form.value.vendor_bank_name = partner.bank_name || "";
            if (!form.value.vendor_bank_account_name)
                form.value.vendor_bank_account_name =
                    partner.bank_account_name || "";
            if (!form.value.vendor_bank_account_number)
                form.value.vendor_bank_account_number =
                    partner.bank_account_number || "";
        }
        if (!isEdit.value || !form.value.recipient_name) {
            form.value.recipient_name = partner?.name || "";
            form.value.recipient_address = partner?.address || "";
            form.value.recipient_pic = partner?.contact_person || "";
            form.value.recipient_phone = partner?.phone || "";
        }
    },
);

const documentTypes = [
    "Quotation",
    "Proforma Invoice",
    "Invoice",
    "Delivery Slip",
    "Delivery Address",
    "Purchase Order",
];

const typeMap = {
    Quotation: "quotation",
    "Proforma Invoice": "proforma_invoice",
    Invoice: "invoice",
    "Delivery Slip": "delivery_slip",
    "Delivery Address": "delivery_address",
    "Purchase Order": "purchase_order",
};

function addItem() {
    form.value.items.push({
        product_name: "",
        description: "",
        qty: 1,
        uom: "PCS",
        unit_price: 0,
        total: 0,
        variations: [],
        has_variations: false,
    });
}

function removeItem(index) {
    form.value.items.splice(index, 1);
}

function calcItem(item) {
    item.total =
        (parseFloat(item.qty) || 0) * (parseFloat(item.unit_price) || 0);
}

function toggleVariations(item) {
    item.has_variations = !item.has_variations;
    if (item.has_variations) {
        if (!item.variations) {
            item.variations = [];
        }
        if (item.variations.length === 0) {
            item.variations.push({ name: "", qty: 1, unit_price: 0, total: 0 });
        }
        recalcParentFromVariations(item);
    } else {
        calcItem(item);
    }
}

function addVariation(item) {
    if (!item.variations) {
        item.variations = [];
    }
    item.variations.push({ name: "", qty: 1, unit_price: 0, total: 0 });
    recalcParentFromVariations(item);
}

function removeVariation(item, index) {
    item.variations.splice(index, 1);
    recalcParentFromVariations(item);
}

function onVariationQtyInput(item, v, e) {
    const val = parseInt(e.target.value, 10);
    v.qty = isNaN(val) ? 0 : val;
    v.total = v.qty * (v.unit_price || 0);
    recalcParentFromVariations(item);
}

function onVariationPriceInput(item, v, e) {
    const raw = e.target.value.replace(/[^\d]/g, "");
    v.unit_price = raw ? parseInt(raw, 10) : 0;
    e.target.value = raw
        ? new Intl.NumberFormat("id-ID").format(parseInt(raw, 10))
        : "";
    v.total = (v.qty || 0) * v.unit_price;
    recalcParentFromVariations(item);
}

function recalcParentFromVariations(item) {
    if (!item.has_variations || !item.variations || !item.variations.length)
        return;
    let totalQty = 0;
    let totalAmount = 0;
    item.variations.forEach((v) => {
        totalQty += parseInt(v.qty, 10) || 0;
        totalAmount += parseInt(v.total, 10) || 0;
    });
    item.qty = totalQty;
    item.total = totalAmount;
    item.unit_price = totalQty > 0 ? Math.round(totalAmount / totalQty) : 0;
}

function formatRupiah(val) {
    if (!val && val !== 0) return "";
    const num =
        typeof val === "string" ? val.replace(/[^\d]/g, "") : String(val);
    if (!num) return "";
    return new Intl.NumberFormat("id-ID").format(parseInt(num, 10));
}

function onQtyInput(item, e) {
    const raw = e.target.value.replace(/[^\d]/g, "");
    item.qty = raw ? parseInt(raw, 10) : "";
    calcItem(item);
}

function onPriceInput(item, e) {
    const raw = e.target.value.replace(/[^\d]/g, "");
    item.unit_price = raw ? parseInt(raw, 10) : "";
    e.target.value = raw
        ? new Intl.NumberFormat("id-ID").format(parseInt(raw, 10))
        : "";
    calcItem(item);
}

function togglePaymentType(type) {
    if (form.value.payment_type === type) {
        form.value.payment_type = "full";
    } else {
        form.value.payment_type = type;
    }
}

const subtotal = computed(() =>
    form.value.items.reduce((sum, item) => sum + (item.total || 0), 0),
);

const tax = computed(() => {
    return form.value.is_ppn ? Math.round(subtotal.value * 0.11) : 0;
});

const invoiceTotal = computed(() => {
    return subtotal.value + tax.value;
});

const dpAmount = computed(() => {
    return Math.round(
        invoiceTotal.value * ((parseFloat(form.value.dp_percent) || 0) / 100),
    );
});

const pelunasanAmount = computed(() => {
    return invoiceTotal.value - dpAmount.value;
});

const grandTotal = computed(() => {
    if (form.value.payment_type === "dp") {
        return dpAmount.value;
    } else if (form.value.payment_type === "pelunasan") {
        return pelunasanAmount.value;
    }
    return invoiceTotal.value;
});

const activeCompany = computed(() => {
    return companiesList.value.find((c) => c.id === form.value.company_id);
});

const companyBankAccounts = computed(() => {
    return activeCompany.value ? activeCompany.value.bank_accounts || [] : [];
});

const companyProducts = computed(() => {
    if (!form.value.company_id) return [];
    return productsList.value.filter(
        (p) => p.company_id === form.value.company_id,
    );
});

function terbilangJS(number) {
    number = Math.abs(Math.round(number));
    const words = [
        "",
        "satu",
        "dua",
        "tiga",
        "empat",
        "lima",
        "enam",
        "tujuh",
        "delapan",
        "sembilan",
        "sepuluh",
        "sebelas",
    ];
    let temp = "";
    if (number < 12) {
        temp = " " + words[number];
    } else if (number < 20) {
        temp = terbilangJS(number - 10) + " belas";
    } else if (number < 100) {
        temp =
            terbilangJS(Math.floor(number / 10)) +
            " puluh" +
            terbilangJS(number % 10);
    } else if (number < 200) {
        temp = " seratus" + terbilangJS(number - 100);
    } else if (number < 1000) {
        temp =
            terbilangJS(Math.floor(number / 100)) +
            " ratus" +
            terbilangJS(number % 100);
    } else if (number < 2000) {
        temp = " seribu" + terbilangJS(number - 1000);
    } else if (number < 1000000) {
        temp =
            terbilangJS(Math.floor(number / 1000)) +
            " ribu" +
            terbilangJS(number % 1000);
    } else if (number < 1000000000) {
        temp =
            terbilangJS(Math.floor(number / 1000000)) +
            " juta" +
            terbilangJS(number % 1000000);
    } else if (number < 1000000000000) {
        temp =
            terbilangJS(Math.floor(number / 1000000000)) +
            " milyar" +
            terbilangJS(number % 1000000000);
    } else if (number < 1000000000000000) {
        temp =
            terbilangJS(Math.floor(number / 1000000000000)) +
            " trilyun" +
            terbilangJS(number % 1000000000000);
    }
    return temp;
}

function spellNumber(number) {
    if (!number || number === 0) return "NOL RUPIAH";
    const spelled = terbilangJS(number).trim();
    return (spelled + " rupiah").toUpperCase();
}

function onProductNameInput(item) {
    if (!form.value.company_id) return;
    const prod = productsList.value.find(
        (p) =>
            p.company_id === form.value.company_id &&
            p.name === item.product_name,
    );
    if (prod) {
        item.description = prod.description || "";
        item.uom = prod.uom || "PCS";
        item.unit_price = prod.price || 0;

        if (prod.variations && prod.variations.length > 0) {
            item.variations = JSON.parse(JSON.stringify(prod.variations));
            item.has_variations = true;
            recalcParentFromVariations(item);
        } else {
            item.variations = [];
            item.has_variations = false;
            calcItem(item);
        }
    }
}

async function loadDeps() {
    try {
        const res = await api.getFormDependencies();
        const data = res.data;
        partnersList.value = data.partners || [];
        companiesList.value = data.companies || [];
        productsList.value = data.products || [];
        documentsList.value = data.documents || [];
    } catch (e) {
        console.error("Gagal memuat dependencies", e);
    }
}

function openAddPartnerModal() {
    const isPO = form.value.document_type === "Purchase Order";
    partnerForm.value = {
        name: "",
        alias: "",
        type: isPO ? "vendor" : "customer",
        address: "",
        phone: "",
        email: "",
        npwp: "",
        contact_person: "",
        company_id: form.value.company_id || "",
    };
    isOpenPartnerModal.value = true;
}

async function handleSavePartner() {
    if (!partnerForm.value.name) {
        showNotification("Validasi", "Nama partner wajib diisi!", "warning");
        return;
    }
    if (!partnerForm.value.company_id) {
        showNotification(
            "Validasi",
            "Perusahaan harus dipilih terlebih dahulu di form utama!",
            "warning",
        );
        return;
    }
    isSavingPartner.value = true;
    try {
        const res = await partnersApi.create(partnerForm.value);
        const newPartner = res.data;

        const partnersRes = await partnersApi.list();
        partnersList.value = partnersRes.data.data || partnersRes.data || [];

        form.value.partner_id = newPartner.id;

        form.value.recipient_name = newPartner.name || "";
        form.value.recipient_phone = newPartner.phone || "";
        form.value.recipient_address = newPartner.address || "";
        form.value.recipient_pic = newPartner.contact_person || "";

        isOpenPartnerModal.value = false;
    } catch (e) {
        const errData = e.response?.data;
        if (errData?.errors) {
            const msgs = Object.values(errData.errors).flat();
            showNotification(
                "Gagal Tambah Partner",
                "Gagal menambah partner: " + msgs.join("\n"),
                "error",
            );
        } else {
            showNotification(
                "Gagal Tambah Partner",
                errData?.message || "Gagal menambah partner",
                "error",
            );
        }
    } finally {
        isSavingPartner.value = false;
    }
}

async function handleCopyFromDocument() {
    if (!selectedRefDocumentId.value) return;

    loading.value = true;
    try {
        const res = await api.get(selectedRefDocumentId.value);
        const doc = res.data.data || res.data;

        form.value.company_id = doc.company_id || "";
        form.value.partner_id = doc.partner_id || "";
        form.value.terms = doc.terms || "";
        form.value.notes = doc.notes || "";
        form.value.stock_conditions = doc.stock_conditions || "";
        form.value.term_of_payment = doc.term_of_payment || "";
        form.value.price_conditions = doc.price_conditions || "";
        form.value.standard_packing = doc.standard_packing || "";
        form.value.offer_validity = doc.offer_validity || "";
        form.value.sender_name = doc.sender_name || "";
        form.value.sender_phone = doc.sender_phone || "";
        form.value.sender_address = doc.sender_address || "";
        form.value.recipient_name = doc.recipient_name || "";
        form.value.recipient_phone = doc.recipient_phone || "";
        form.value.recipient_address = doc.recipient_address || "";
        form.value.recipient_pic = doc.recipient_pic || "";
        form.value.discount = parseFloat(doc.discount) || 0;
        form.value.tax = parseFloat(doc.tax) || 0;
        form.value.is_ppn = doc.is_ppn !== undefined ? !!doc.is_ppn : true;
        form.value.bank_account_id = doc.bank_account_id || "";
        form.value.payment_type = doc.payment_type || "full";
        form.value.dp_percent =
            doc.dp_percent !== null ? parseFloat(doc.dp_percent) : 30;
        form.value.vendor_bank_name = doc.vendor_bank_name || "";
        form.value.vendor_bank_account_name =
            doc.vendor_bank_account_name || "";
        form.value.vendor_bank_account_number =
            doc.vendor_bank_account_number || "";
        form.value.due_date = doc.due_date ? doc.due_date.split("T")[0] : "";
        form.value.customer_po_number = doc.customer_po_number || "";
        form.value.customer_po_date = doc.customer_po_date
            ? doc.customer_po_date.split("T")[0]
            : "";
        form.value.customer_po_file = doc.customer_po_file || "";

        form.value.items = (doc.items || []).map((i) => {
            const hasVars = i.variations && i.variations.length > 0;
            return {
                product_name: i.product_name || "",
                description: i.description || "",
                qty: parseFloat(i.qty) || 1,
                uom: i.uom || "PCS",
                unit_price: parseFloat(i.unit_price) || 0,
                total: parseFloat(i.total) || 0,
                variations: i.variations || [],
                has_variations: hasVars,
            };
        });

        if (form.value.document_type === "Delivery Address") {
            form.value.items = [];
        }
    } catch (e) {
        error.value = "Gagal menyalin data dokumen";
    } finally {
        loading.value = false;
    }
}

async function loadDocument() {
    if (!isEdit.value) return;
    loading.value = true;
    try {
        const res = await api.get(route.params.id);
        const doc = res.data.data || res.data;
        const revMap = Object.fromEntries(
            Object.entries(typeMap).map(([k, v]) => [v, k]),
        );
        form.value = {
            company_id: doc.company_id || "",
            partner_id: doc.partner_id || "",
            document_type: revMap[doc.type] || doc.type,
            number: doc.number || "",
            date: doc.date?.split("T")[0] || "",
            due_date: doc.due_date?.split("T")[0] || "",
            terms: doc.terms || "",
            notes: doc.notes || "",
            stock_conditions: doc.stock_conditions || "",
            term_of_payment: doc.term_of_payment || "",
            price_conditions: doc.price_conditions || "",
            standard_packing: doc.standard_packing || "",
            offer_validity: doc.offer_validity || "",
            discount: doc.discount || 0,
            tax: doc.tax || 0,
            is_ppn: doc.is_ppn !== undefined ? !!doc.is_ppn : true,
            bank_account_id: doc.bank_account_id || "",
            payment_type: doc.payment_type || "full",
            dp_percent:
                doc.dp_percent !== null ? parseFloat(doc.dp_percent) : 30,
            vendor_bank_name: doc.vendor_bank_name || "",
            vendor_bank_account_name: doc.vendor_bank_account_name || "",
            vendor_bank_account_number: doc.vendor_bank_account_number || "",
            items: (doc.items || []).map((i) => {
                const hasVars = i.variations && i.variations.length > 0;
                return {
                    product_name: i.product_name || "",
                    description: i.description || "",
                    qty: i.qty || 1,
                    uom: i.uom || "PCS",
                    unit_price: i.unit_price || 0,
                    total: i.total || 0,
                    variations: i.variations || [],
                    has_variations: hasVars,
                };
            }),
            status: doc.status || "draft",
            reference_id: doc.reference_id || "",
            sender_name: doc.sender_name || "",
            sender_phone: doc.sender_phone || "",
            sender_address: doc.sender_address || "",
            recipient_name: doc.recipient_name || "",
            recipient_phone: doc.recipient_phone || "",
            recipient_address: doc.recipient_address || "",
            recipient_pic: doc.recipient_pic || "",
            customer_po_number: doc.customer_po_number || "",
            customer_po_date: doc.customer_po_date
                ? doc.customer_po_date.split("T")[0]
                : "",
            customer_po_file: doc.customer_po_file || "",
        };
        if (doc.reference) {
            selectedRefDocumentId.value = doc.reference.id;
            refSearch.value = `${doc.reference.document_number || "(Draft)"} - ${doc.reference.partner?.name || "No Partner"}`;
        }
        if (!route.query.type) {
            router.replace({
                query: { ...route.query, type: form.value.document_type },
            });
        }
        await nextTick();
        isLoaded.value = true;
    } catch (e) {
        error.value = "Gagal memuat dokumen";
    } finally {
        loading.value = false;
    }
}

async function executeSave(confirm, overwrite) {
    try {
        const payload = {
            ...form.value,
            status: confirm ? "confirmed" : "draft",
            type: typeMap[form.value.document_type] || form.value.document_type,
            document_type: undefined,
            number: form.value.number || null,
            due_date: form.value.due_date || null,
            reference_id: form.value.reference_id || null,
            bank_account_id: form.value.bank_account_id || null,
            discount: 0,
            tax: tax.value,
            subtotal: subtotal.value,
            grand_total: grandTotal.value,
            dp_percent:
                form.value.payment_type !== "full"
                    ? parseFloat(form.value.dp_percent) || 0
                    : 0,
            dp_amount: dpAmount.value,
            payment_type: form.value.payment_type,
            overwrite: overwrite,
            items: form.value.items.map((item) => ({
                product_name: item.product_name,
                description: item.description || null,
                qty: item.qty,
                uom: item.uom,
                unit_price: item.unit_price,
                total: item.total,
                variations: item.has_variations ? item.variations : null,
            })),
        };
        if (
            form.value.document_type !== "Invoice" &&
            form.value.document_type !== "Proforma Invoice"
        ) {
            delete payload.due_date;
        }

        let savedDocId = route.params.id;
        if (isEdit.value) {
            await api.update(route.params.id, payload);
        } else {
            const res = await api.create(payload);
            const createdDoc = res.data.data || res.data;
            savedDocId = createdDoc.id;
        }

        if (selectedPoFile.value && savedDocId) {
            const formData = new FormData();
            formData.append("customer_po_file", selectedPoFile.value);
            await api.uploadPo(savedDocId, formData);
        }

        router.push({
            path: "/documents",
            query: { type: form.value.document_type },
        });
    } catch (e) {
        const errData = e.response?.data;
        if (errData?.errors) {
            const msgs = Object.values(errData.errors).flat();
            error.value = msgs.join(" | ");
        } else {
            error.value = errData?.message || "Gagal menyimpan dokumen";
        }
    } finally {
        saving.value = false;
    }
}

async function handleSave(confirm = false) {
    saving.value = true;
    error.value = "";
    if (confirm && form.value.number) {
        try {
            const checkRes = await api.checkLocalFile({
                number: form.value.number,
                company_id: form.value.company_id,
                type: form.value.type,
            });
            if (
                checkRes.data &&
                checkRes.data.path_configured &&
                checkRes.data.exists
            ) {
                showConfirm(
                    "Berkas Sudah Ada",
                    `Berkas "${checkRes.data.filename}" sudah ada di folder penyimpanan lokal. Apakah Anda ingin menimpanya?`,
                    async () => {
                        await executeSave(confirm, true);
                    },
                    () => {
                        saving.value = false;
                    },
                );
                return;
            }
        } catch (e) {
            console.error("Gagal memeriksa berkas lokal", e);
        }
    }
    await executeSave(confirm, false);
}

async function handleDelete() {
    showConfirm(
        "Hapus Dokumen",
        "Apakah Anda yakin ingin menghapus dokumen ini? Tindakan ini tidak dapat dibatalkan.",
        async () => {
            saving.value = true;
            try {
                await api.delete(route.params.id);
                router.push({
                    path: "/documents",
                    query: { type: form.value.document_type },
                });
            } catch (e) {
                error.value =
                    e.response?.data?.message || "Gagal menghapus dokumen";
            } finally {
                saving.value = false;
            }
        },
    );
}

watch(
    () => form.value.document_type,
    (newVal) => {
        if (!isEdit.value && newVal) {
            if (route.query.type !== newVal) {
                router.replace({ query: { ...route.query, type: newVal } });
            }
        }
    },
);

watch(
    () => route.query.type,
    (newVal) => {
        if (!isEdit.value && newVal && form.value.document_type !== newVal) {
            form.value.document_type = newVal;
            if (newVal === "Quotation") {
                Object.assign(form.value, quotationDefaults);
            }
        }
    },
);

const handleFormKeydown = (e) => {
    if (e.ctrlKey && e.key.toLowerCase() === "s") {
        e.preventDefault();
        handleSave(false);
    }
};

onMounted(async () => {
    window.addEventListener("keydown", handleFormKeydown);
    await loadDeps();
    if (isEdit.value) {
        await loadDocument();
    } else {
        if (appStore.activeCompanyId) {
            form.value.company_id = appStore.activeCompanyId;
        }
        if (route.query.type) {
            form.value.document_type = route.query.type;
        }
        if (form.value.document_type === "Quotation") {
            Object.assign(form.value, quotationDefaults);
        }
        if (route.query.reference_id) {
            selectedRefDocumentId.value = Number(route.query.reference_id);
            form.value.reference_id = selectedRefDocumentId.value;
            await handleCopyFromDocument();
            if (
                form.value.document_type !== "Invoice" &&
                form.value.document_type !== "Proforma Invoice"
            ) {
                form.value.due_date = "";
            }
        }
    }
});

onUnmounted(() => {
    window.removeEventListener("keydown", handleFormKeydown);
});
</script>
<style>
input,
select,
textarea {
    border: none !important;
    border-radius: 0 !important;
    padding: 5px !important;
}
</style>

<template>
    <div class="min-h-full">
        <!-- Loading -->
        <div
            v-if="loading"
            class="min-h-[50vh] flex items-center justify-center"
        >
            <div class="flex items-center gap-2.5 text-sm text-gray-500">
                <svg
                    class="animate-spin h-4 w-4 text-indofilter"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="3"
                    ></circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                </svg>
                Memuat formulir...
            </div>
        </div>

        <template v-if="!loading">
            <!-- Page Header -->
            <div class="flex items-center gap-2.5 mb-4">
                <button
                    type="button"
                    @click="
                        router.push({
                            path: '/documents',
                            query: { type: form.document_type },
                        })
                    "
                    class="shrink-0 w-8 h-8 -ml-1.5 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 inline-flex items-center justify-center transition-colors"
                    title="Kembali"
                >
                    <svg
                        class="w-4.5 h-4.5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"
                        ></path>
                    </svg>
                </button>

                <h1 class="text-base font-semibold text-gray-900 truncate">
                    {{
                        isEdit
                            ? "Edit " + form.document_type
                            : "Buat " + form.document_type + " Baru"
                    }}
                </h1>
            </div>

            <!-- Error -->
            <div
                v-if="error"
                class="mb-4 flex items-start gap-2.5 rounded-md border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-700"
            >
                <svg
                    class="w-4 h-4 shrink-0 mt-0.5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 8v4m0 4h.01M10.29 3.86l-7.82 13a2 2 0 001.71 3h15.64a2 2 0 001.71-3l-7.82-13a2 2 0 00-3.42 0z"
                    ></path>
                </svg>

                <span>{{ error }}</span>
            </div>

            <!-- Main Form -->
            <div class="space-y-4">
                <!-- Import / Reference -->
                <section class="bg-white border border-gray-200 rounded-lg">
                    <div class="px-4 py-2.5 border-b border-gray-200">
                        <h2 class="text-[13px] font-semibold text-gray-900">
                            Data Awal
                        </h2>
                    </div>

                    <div class="p-4 space-y-3">
                        <!-- PDF Import -->
                        <div
                            v-if="!isEdit"
                            class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5"
                        >
                            <div class="text-xs text-gray-500">
                                Isi formulir otomatis dari PDF penawaran atau
                                invoice sebelumnya.
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <input
                                    type="file"
                                    ref="pdfFileInput"
                                    @change="handlePdfUpload"
                                    accept="application/pdf"
                                    class="hidden"
                                />

                                <button
                                    type="button"
                                    @click="pdfFileInput.click()"
                                    :disabled="parsingPdf"
                                    class="inline-flex items-center justify-center gap-1.5 px-3 h-8 border border-gray-300 rounded-md bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 hover:border-gray-400 transition-colors disabled:opacity-50 whitespace-nowrap"
                                >
                                    <svg
                                        v-if="parsingPdf"
                                        class="animate-spin h-3.5 w-3.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="3"
                                        ></circle>
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        ></path>
                                    </svg>

                                    <svg
                                        v-else
                                        class="w-3.5 h-3.5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.8"
                                            d="M12 16V4m0 0L8 8m4-4l4 4M5 16v1a3 3 0 003 3h8a3 3 0 003-3v-1"
                                        ></path>
                                    </svg>

                                    {{
                                        parsingPdf
                                            ? "Membaca PDF..."
                                            : "Import PDF"
                                    }}
                                </button>

                                <span
                                    v-if="uploadedPdfName"
                                    class="max-w-[160px] truncate text-xs text-gray-500"
                                    :title="uploadedPdfName"
                                >
                                    {{ uploadedPdfName }}
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="!isEdit"
                            class="border-t border-gray-100"
                        ></div>

                        <!-- Reference Document -->
                        <div>
                            <label
                                class="block text-xs font-medium text-gray-600 mb-1.5"
                            >
                                Salin dari Dokumen
                            </label>

                            <div class="relative">
                                <div class="relative">
                                    <input
                                        type="text"
                                        v-model="refSearch"
                                        @focus="isOpenRefDropdown = true"
                                        placeholder="Cari nomor dokumen, partner, atau tipe..."
                                        class="w-full h-9 px-3 pr-9 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    />

                                    <div
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center"
                                    >
                                        <button
                                            v-if="refSearch"
                                            type="button"
                                            @click="
                                                refSearch = '';
                                                selectedRefDocumentId = '';
                                            "
                                            class="text-gray-400 hover:text-gray-700"
                                            title="Hapus pencarian"
                                        >
                                            <svg
                                                class="w-3.5 h-3.5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12"
                                                ></path>
                                            </svg>
                                        </button>

                                        <svg
                                            class="w-3.5 h-3.5 ml-1 text-gray-400 transition-transform"
                                            :class="
                                                isOpenRefDropdown
                                                    ? 'rotate-180'
                                                    : ''
                                            "
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 9l-7 7-7-7"
                                            ></path>
                                        </svg>
                                    </div>
                                </div>

                                <div
                                    v-if="isOpenRefDropdown"
                                    class="fixed inset-0 z-10"
                                    @click="isOpenRefDropdown = false"
                                ></div>

                                <div
                                    v-if="isOpenRefDropdown"
                                    class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-56 overflow-y-auto"
                                >
                                    <div
                                        v-if="filteredRefDocuments.length === 0"
                                        class="px-3.5 py-5 text-center text-xs text-gray-500"
                                    >
                                        Tidak ada dokumen yang cocok.
                                    </div>

                                    <button
                                        v-for="doc in filteredRefDocuments"
                                        :key="doc.id"
                                        type="button"
                                        @click="selectRefDocument(doc)"
                                        class="w-full text-left px-3.5 py-2.5 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-0"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <span
                                                class="text-sm font-medium text-gray-800 truncate"
                                            >
                                                {{
                                                    doc.document_number ||
                                                    "(Draft)"
                                                }}
                                            </span>

                                            <span
                                                class="shrink-0 text-[10px] font-medium text-gray-500"
                                            >
                                                {{
                                                    doc.type
                                                        .toUpperCase()
                                                        .replace("_", " ")
                                                }}
                                            </span>
                                        </div>

                                        <div
                                            class="flex items-center justify-between gap-3 mt-0.5"
                                        >
                                            <span
                                                class="text-xs text-gray-500 truncate"
                                            >
                                                {{
                                                    doc.partner?.name ||
                                                    "No Partner"
                                                }}
                                            </span>

                                            <span
                                                class="text-xs text-gray-400 shrink-0"
                                            >
                                                {{ doc.date }}
                                            </span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Document Information -->
                <section class="bg-white border border-gray-200">
                    <div class="px-3 py-2.5 border-b border-gray-200">
                        <h2 class="text-[13px] font-semibold text-gray-900">
                            Informasi Dokumen
                        </h2>
                    </div>

                    <div class="grid grid-cols-[180px_1fr] text-sm">
                        <!-- Company -->
                        <div
                            class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                        >
                            Perusahaan
                        </div>

                        <div class="border-b border-gray-200">
                            <select
                                v-model="form.company_id"
                                disabled
                                id="company"
                                class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-500 cursor-not-allowed"
                            >
                                <option value="">Pilih Perusahaan</option>
                                <option
                                    v-for="c in companiesList"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Partner -->
                        <div
                            class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                        >
                            Partner
                            <span class="text-red-500">*</span>
                        </div>

                        <div class="border-b border-gray-200">
                            <div class="flex items-center">
                                <div class="relative flex-1 min-w-0">
                                    <input
                                        type="text"
                                        :value="
                                            isOpenPartnerDropdown
                                                ? partnerSearch
                                                : displayPartnerName
                                        "
                                        @input="
                                            partnerSearch = $event.target.value;
                                            isOpenPartnerDropdown = true;
                                            form.partner_id = '';
                                        "
                                        @focus="
                                            isOpenPartnerDropdown = true;
                                            partnerSearch = '';
                                        "
                                        placeholder="Cari partner..."
                                        id="partner"
                                        class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800 placeholder:text-gray-400"
                                    />

                                    <div
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2"
                                    >
                                        <button
                                            v-if="
                                                form.partner_id || partnerSearch
                                            "
                                            type="button"
                                            @click="
                                                partnerSearch = '';
                                                form.partner_id = '';
                                                isOpenPartnerDropdown = true;
                                            "
                                            class="text-gray-400 hover:text-gray-700"
                                        >
                                            <svg
                                                class="w-3.5 h-3.5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12"
                                                ></path>
                                            </svg>
                                        </button>
                                    </div>

                                    <div
                                        v-if="isOpenPartnerDropdown"
                                        class="fixed inset-0 z-10"
                                        @click="isOpenPartnerDropdown = false"
                                    ></div>

                                    <div
                                        v-if="isOpenPartnerDropdown"
                                        class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 shadow-lg max-h-56 overflow-y-auto"
                                    >
                                        <div
                                            v-if="filteredPartners.length === 0"
                                            class="px-3.5 py-5 text-center text-xs text-gray-500"
                                        >
                                            Tidak ada partner.
                                        </div>

                                        <button
                                            v-for="p in filteredPartners"
                                            :key="p.id"
                                            type="button"
                                            @click="
                                                form.partner_id = p.id;
                                                isOpenPartnerDropdown = false;
                                            "
                                            class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 border-b border-gray-100 last:border-0 truncate"
                                        >
                                            {{ p.name }}
                                        </button>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    @click="openAddPartnerModal"
                                    class="px-3 py-2.5 border-l border-gray-200 text-gray-500 hover:text-gray-900 hover:bg-gray-50 shrink-0"
                                    title="Tambah Partner Baru"
                                >
                                    <svg
                                        class="w-4 h-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 4v16m8-8H4"
                                        ></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Document Number -->
                        <div
                            class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                        >
                            No. Dokumen
                        </div>

                        <div class="border-b border-gray-200">
                            <input
                                v-model="form.number"
                                @input="handleNumberInput"
                                id="doc-no"
                                placeholder="Otomatis digenerate"
                                class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800 placeholder:text-gray-400"
                            />
                        </div>

                        <!-- Date -->
                        <div
                            class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                        >
                            Tanggal
                            <span class="text-red-500">*</span>
                        </div>

                        <div class="border-b border-gray-200">
                            <input
                                v-model="form.date"
                                type="date"
                                id="date"
                                class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800"
                            />
                        </div>

                        <!-- Due Date (Invoice & Proforma Invoice only) -->
                        <template
                            v-if="
                                form.document_type === 'Invoice' ||
                                form.document_type === 'Proforma Invoice'
                            "
                        >
                            <div
                                class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                            >
                                Jatuh Tempo
                            </div>

                            <div class="border-b border-gray-200">
                                <input
                                    v-model="form.due_date"
                                    type="date"
                                    id="due-date"
                                    :min="form.date || undefined"
                                    class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800"
                                />
                            </div>
                        </template>

                        <!-- Delivery Slip PO Number -->
                        <template v-if="form.document_type === 'Delivery Slip'">
                            <div
                                class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                            >
                                Nomor PO Customer
                            </div>

                            <div class="border-b border-gray-200">
                                <input
                                    v-model="form.customer_po_number"
                                    type="text"
                                    id="po-no"
                                    placeholder="Masukkan No. PO Customer..."
                                    class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800 placeholder:text-gray-400"
                                />
                            </div>
                        </template>

                        <!-- Delivery Slip PO Date -->
                        <template v-if="form.document_type === 'Delivery Slip'">
                            <div
                                class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                            >
                                Tanggal PO
                            </div>

                            <div class="border-b border-gray-200">
                                <input
                                    v-model="form.customer_po_date"
                                    type="date"
                                    id="po-date"
                                    class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800"
                                />
                            </div>
                        </template>

                        <!-- Customer Reference -->
                        <template
                            v-if="
                                form.document_type === 'Invoice' ||
                                form.document_type === 'Proforma Invoice'
                            "
                        >
                            <div
                                class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                            >
                                Customer Reff
                            </div>

                            <div class="border-b border-gray-200">
                                <input
                                    v-model="form.customer_po_number"
                                    type="text"
                                    id="cust-reff"
                                    placeholder="Customer Reff / No. PO..."
                                    class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800 placeholder:text-gray-400"
                                />
                            </div>
                        </template>

                        <!-- Customer PO Date (Invoice & Proforma Invoice only) -->
                        <template
                            v-if="
                                form.document_type === 'Invoice' ||
                                form.document_type === 'Proforma Invoice'
                            "
                        >
                            <div
                                class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                            >
                                Tanggal PO Customer
                            </div>

                            <div class="border-b border-gray-200">
                                <input
                                    v-model="form.customer_po_date"
                                    type="date"
                                    id="cust-po-date"
                                    class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800"
                                />
                            </div>
                        </template>

                        <!-- PO File -->
                        <template
                            v-if="
                                form.document_type === 'Invoice' ||
                                form.document_type === 'Proforma Invoice' ||
                                form.document_type === 'Delivery Slip'
                            "
                        >
                            <div
                                class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                            >
                                File PO Customer
                            </div>

                            <div class="border-b border-gray-200">
                                <div class="flex items-center">
                                    <input
                                        type="file"
                                        accept=".pdf,image/*"
                                        id="po-file"
                                        @change="handlePoFileChange"
                                        class="flex-1 min-w-0 px-3 py-2 text-xs text-gray-500 file:mr-2.5 file:px-2.5 file:py-1.5 file:border file:border-gray-300 file:bg-white file:text-xs file:font-medium file:text-gray-700 hover:file:bg-gray-50"
                                    />

                                    <button
                                        v-if="form.customer_po_file"
                                        type="button"
                                        @click="handleViewPoFile"
                                        class="px-3 py-2.5 border-l border-gray-200 text-xs font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 whitespace-nowrap"
                                    >
                                        Lihat PO
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Payment Terms -->
                        <div
                            class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                        >
                            Syarat Pembayaran
                        </div>

                        <div class="border-b border-gray-200">
                            <textarea
                                v-model="form.terms"
                                id="payment-terms"
                                rows="2"
                                placeholder="Masukkan syarat pembayaran..."
                                class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800 placeholder:text-gray-400 resize-none"
                            ></textarea>
                        </div>

                        <!-- Notes -->
                        <div
                            class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                        >
                            Catatan
                        </div>

                        <div class="border-b border-gray-200">
                            <textarea
                                v-model="form.notes"
                                id="notes"
                                rows="2"
                                placeholder="Catatan internal..."
                                class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800 placeholder:text-gray-400 resize-none"
                            ></textarea>
                        </div>
                    </div>
                </section>
                <!-- Delivery Address -->
                <section
                    v-if="form.document_type === 'Delivery Address'"
                    class="bg-white border border-gray-200 rounded-lg"
                >
                    <div class="px-4 py-2.5 border-b border-gray-200">
                        <h2 class="text-[13px] font-semibold text-gray-900">
                            Alamat Pengiriman
                        </h2>
                    </div>

                    <div
                        class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-5"
                    >
                        <!-- Sender -->
                        <div>
                            <h3
                                class="text-[11px] font-semibold text-gray-500 mb-2.5"
                            >
                                Pengirim
                            </h3>

                            <div class="space-y-3">
                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-600 mb-1.5"
                                    >
                                        Nama Perusahaan
                                    </label>

                                    <input
                                        v-model="form.sender_name"
                                        placeholder="Nama perusahaan pengirim"
                                        class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-600 mb-1.5"
                                    >
                                        No. HP
                                    </label>

                                    <input
                                        v-model="form.sender_phone"
                                        placeholder="No. telepon perusahaan"
                                        class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-600 mb-1.5"
                                    >
                                        Alamat
                                    </label>

                                    <textarea
                                        v-model="form.sender_address"
                                        rows="3"
                                        placeholder="Alamat lengkap pengirim"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm resize-none focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Recipient -->
                        <div>
                            <h3
                                class="text-[11px] font-semibold text-gray-500 mb-2.5"
                            >
                                Penerima
                            </h3>

                            <div class="space-y-3">
                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-600 mb-1.5"
                                    >
                                        Nama / Perusahaan
                                    </label>

                                    <input
                                        v-model="form.recipient_name"
                                        placeholder="Nama penerima atau perusahaan"
                                        class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-600 mb-1.5"
                                    >
                                        No. HP
                                    </label>

                                    <input
                                        v-model="form.recipient_phone"
                                        placeholder="No. telepon penerima"
                                        class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-600 mb-1.5"
                                    >
                                        Alamat
                                    </label>

                                    <textarea
                                        v-model="form.recipient_address"
                                        rows="3"
                                        placeholder="Alamat lengkap penerima"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm resize-none focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    ></textarea>
                                </div>

                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-600 mb-1.5"
                                    >
                                        PIC
                                    </label>

                                    <input
                                        v-model="form.recipient_pic"
                                        placeholder="Nama contact person"
                                        class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Company Bank Account -->
                <section
                    v-if="
                        (form.document_type === 'Invoice' ||
                            form.document_type === 'Proforma Invoice') &&
                        form.company_id &&
                        companyBankAccounts.length > 0
                    "
                    class="bg-white border border-gray-200 rounded-lg"
                >
                    <div class="px-4 py-2.5 border-b border-gray-200">
                        <h2 class="text-[13px] font-semibold text-gray-900">
                            Rekening Bank
                        </h2>
                    </div>

                    <div class="p-4">
                        <div
                            class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2.5"
                        >
                            <label
                                v-for="acc in companyBankAccounts"
                                :key="acc.id"
                                class="relative block p-3 border rounded-md cursor-pointer transition-colors"
                                :class="
                                    form.bank_account_id === acc.id
                                        ? 'border-blue-500 bg-blue-50/30'
                                        : 'border-gray-200 hover:border-gray-300'
                                "
                            >
                                <div class="flex items-start gap-2.5">
                                    <input
                                        type="radio"
                                        :value="acc.id"
                                        v-model="form.bank_account_id"
                                        class="mt-0.5 text-blue-600 focus:ring-blue-500"
                                    />

                                    <div class="min-w-0">
                                        <div
                                            class="flex items-center gap-1.5 flex-wrap"
                                        >
                                            <span
                                                class="text-sm font-semibold text-gray-800"
                                            >
                                                {{ acc.bank_name }}
                                            </span>

                                            <span
                                                v-if="acc.is_default"
                                                class="text-[10px] font-medium text-blue-700"
                                            >
                                                Utama
                                            </span>
                                        </div>

                                        <p class="text-xs text-gray-600 mt-0.5">
                                            {{ acc.account_name }}
                                        </p>

                                        <p
                                            class="text-xs font-medium text-gray-800"
                                        >
                                            {{ acc.account_number }}
                                        </p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </section>

                <!-- Vendor Bank -->
                <section
                    v-if="form.document_type === 'Purchase Order'"
                    class="bg-white border border-gray-200 rounded-lg"
                >
                    <div class="px-4 py-2.5 border-b border-gray-200">
                        <h2 class="text-[13px] font-semibold text-gray-900">
                            Detail Bank Vendor
                        </h2>
                    </div>

                    <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label
                                class="block text-xs font-medium text-gray-600 mb-1.5"
                            >
                                Nama Bank
                            </label>

                            <input
                                v-model="form.vendor_bank_name"
                                placeholder="Contoh: Bank Mandiri"
                                class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-xs font-medium text-gray-600 mb-1.5"
                            >
                                Nama Rekening
                            </label>

                            <input
                                v-model="form.vendor_bank_account_name"
                                placeholder="Contoh: PT Sumber Jaya"
                                class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-xs font-medium text-gray-600 mb-1.5"
                            >
                                Nomor Rekening
                            </label>

                            <input
                                v-model="form.vendor_bank_account_number"
                                placeholder="Contoh: 12345678"
                                class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            />
                        </div>
                    </div>
                </section>

                <!-- Quotation Terms -->
                <section
                    v-if="form.document_type === 'Quotation'"
                    class="bg-white border border-gray-200 rounded-lg"
                >
                    <div class="px-4 py-2.5 border-b border-gray-200">
                        <h2 class="text-[13px] font-semibold text-gray-900">
                            Ketentuan Penawaran
                        </h2>
                    </div>

                    <div class="w-full text-sm">
                        <div
                            class="grid grid-cols-[180px_1fr] border-t border-gray-200"
                        >
                            <!-- Stock Conditions -->
                            <div
                                class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                            >
                                Stock Conditions
                            </div>
                            <div class="border-b border-gray-200">
                                <input
                                    v-model="form.stock_conditions"
                                    class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800 focus:bg-blue-50/30"
                                />
                            </div>

                            <!-- Term of Payment -->
                            <div
                                class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                            >
                                Term of Payment
                            </div>
                            <div class="border-b border-gray-200">
                                <input
                                    v-model="form.term_of_payment"
                                    class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800 focus:bg-blue-50/30"
                                />
                            </div>

                            <!-- Price Conditions -->
                            <div
                                class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                            >
                                Price Conditions
                            </div>
                            <div class="border-b border-gray-200">
                                <input
                                    v-model="form.price_conditions"
                                    class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800 focus:bg-blue-50/30"
                                />
                            </div>

                            <!-- Standard Packing -->
                            <div
                                class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                            >
                                Standard Packing
                            </div>
                            <div class="border-b border-gray-200">
                                <input
                                    v-model="form.standard_packing"
                                    class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800 focus:bg-blue-50/30"
                                />
                            </div>

                            <!-- Offer Validity -->
                            <div
                                class="px-3 py-2.5 bg-gray-50 border-b border-r border-gray-200 font-medium text-gray-600"
                            >
                                Offer Validity
                            </div>
                            <div class="border-b border-gray-200">
                                <input
                                    v-model="form.offer_validity"
                                    class="w-full px-3 py-2.5 bg-transparent border-0 outline-none text-gray-800 focus:bg-blue-50/30"
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Items -->
                <!-- Items -->
                <section
                    v-if="form.document_type !== 'Delivery Address'"
                    class="bg-white border border-gray-300"
                >
                    <!-- Header -->
                    <div
                        class="flex items-center justify-between px-3 py-1.5 border-b border-gray-300"
                    >
                        <h2 class="text-xs font-semibold text-gray-900">
                            Item Barang
                        </h2>

                        <span class="text-[11px] text-gray-500">
                            {{ form.items.length }} item
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse table-fixed">
                            <thead>
                                <tr
                                    class="bg-gray-100 border-b border-gray-300"
                                >
                                    <th
                                        class="h-7 px-2 text-left text-[10px] font-semibold text-gray-600 min-w-[170px]"
                                    >
                                        Nama Barang
                                    </th>

                                    <th
                                        class="h-7 px-2 text-left text-[10px] font-semibold text-gray-600 min-w-[160px]"
                                    >
                                        Deskripsi
                                    </th>

                                    <th
                                        class="h-7 px-1.5 text-right text-[10px] font-semibold text-gray-600 w-20"
                                    >
                                        Qty
                                    </th>

                                    <th
                                        class="h-7 px-1.5 text-left text-[10px] font-semibold text-gray-600 w-24"
                                    >
                                        Satuan
                                    </th>

                                    <th
                                        v-if="
                                            form.document_type !==
                                            'Delivery Slip'
                                        "
                                        class="h-7 px-1.5 text-right text-[10px] font-semibold text-gray-600 w-32"
                                    >
                                        Harga
                                    </th>

                                    <th
                                        v-if="
                                            form.document_type !==
                                            'Delivery Slip'
                                        "
                                        class="h-7 px-1.5 text-right text-[10px] font-semibold text-gray-600 w-32"
                                    >
                                        Total
                                    </th>

                                    <th class="w-12"></th>
                                </tr>
                            </thead>

                            <tbody>
                                <template
                                    v-for="(item, i) in form.items"
                                    :key="i"
                                >
                                    <!-- Item Row -->
                                    <tr
                                        class="border-b border-gray-200 hover:bg-gray-50"
                                    >
                                        <!-- Product -->
                                        <td
                                            class="px-2 py-1 border-r border-gray-100 align-top"
                                        >
                                            <textarea
                                                :ref="
                                                    (el) => {
                                                        if (el) {
                                                            el.style.height =
                                                                'auto';
                                                            el.style.height =
                                                                el.scrollHeight +
                                                                'px';
                                                        }
                                                    }
                                                "
                                                @input="
                                                    onProductNameInput(item);
                                                    $event.target.style.height =
                                                        'auto';
                                                    $event.target.style.height =
                                                        $event.target
                                                            .scrollHeight +
                                                        'px';
                                                "
                                                v-model="item.product_name"
                                                placeholder="Nama barang"
                                                rows="1"
                                                class="w-full p-0 border-0 outline-none bg-transparent text-xs text-gray-800 placeholder:text-gray-400 focus:ring-0 resize-none overflow-hidden leading-relaxed"
                                            ></textarea>
                                        </td>

                                        <!-- Description -->
                                        <td
                                            class="px-2 py-1 border-r border-gray-100 align-top"
                                        >
                                            <textarea
                                                :ref="
                                                    (el) => {
                                                        if (el) {
                                                            el.style.height =
                                                                'auto';
                                                            el.style.height =
                                                                el.scrollHeight +
                                                                'px';
                                                        }
                                                    }
                                                "
                                                @input="
                                                    $event.target.style.height =
                                                        'auto';
                                                    $event.target.style.height =
                                                        $event.target
                                                            .scrollHeight +
                                                        'px';
                                                "
                                                v-model="item.description"
                                                placeholder="Deskripsi"
                                                rows="1"
                                                class="w-full p-0 border-0 outline-none bg-transparent text-xs text-gray-700 placeholder:text-gray-400 focus:ring-0 resize-none overflow-hidden leading-relaxed"
                                            ></textarea>
                                        </td>

                                        <!-- Qty -->
                                        <td
                                            class="px-1.5 py-1 border-r border-gray-100 align-top"
                                        >
                                            <div
                                                class="h-full flex items-center"
                                            >
                                                <input
                                                    :value="item.qty"
                                                    @input="
                                                        onQtyInput(item, $event)
                                                    "
                                                    :disabled="
                                                        item.has_variations
                                                    "
                                                    inputmode="numeric"
                                                    placeholder="0"
                                                    :class="
                                                        item.has_variations
                                                            ? 'text-gray-400 cursor-not-allowed'
                                                            : 'text-gray-800'
                                                    "
                                                    class="w-full p-0 border-0 outline-none bg-transparent text-xs text-right placeholder:text-gray-400 focus:ring-0"
                                                />
                                            </div>
                                        </td>

                                        <!-- UOM -->
                                        <td
                                            class="px-1.5 py-1 border-r border-gray-100 align-top"
                                        >
                                            <div
                                                class="h-full flex items-center"
                                            >
                                                <select
                                                    v-model="item.uom"
                                                    class="w-full p-0 pr-2 border-0 outline-none bg-transparent text-xs text-gray-700 focus:ring-0 cursor-pointer"
                                                >
                                                    <option value="PCS">
                                                        PCS
                                                    </option>
                                                    <option value="KG">
                                                        KG
                                                    </option>
                                                    <option value="MTR">
                                                        MTR
                                                    </option>
                                                    <option value="LTR">
                                                        LTR
                                                    </option>
                                                    <option value="BOX">
                                                        BOX
                                                    </option>
                                                    <option value="ROL">
                                                        ROL
                                                    </option>
                                                    <option value="SET">
                                                        SET
                                                    </option>
                                                    <option value="UNIT">
                                                        UNIT
                                                    </option>
                                                </select>
                                            </div>
                                        </td>

                                        <!-- Price -->
                                        <td
                                            v-if="
                                                form.document_type !==
                                                'Delivery Slip'
                                            "
                                            class="px-1.5 py-1 border-r border-gray-100 align-top"
                                        >
                                            <div
                                                class="h-full flex items-center"
                                            >
                                                <input
                                                    :value="
                                                        formatRupiah(
                                                            item.unit_price,
                                                        )
                                                    "
                                                    @input="
                                                        onPriceInput(
                                                            item,
                                                            $event,
                                                        )
                                                    "
                                                    :disabled="
                                                        item.has_variations
                                                    "
                                                    inputmode="numeric"
                                                    placeholder="0"
                                                    :class="
                                                        item.has_variations
                                                            ? 'text-gray-400 cursor-not-allowed'
                                                            : 'text-gray-800'
                                                    "
                                                    class="w-full p-0 border-0 outline-none bg-transparent text-xs text-right placeholder:text-gray-400 focus:ring-0"
                                                />
                                            </div>
                                        </td>

                                        <!-- Total -->
                                        <td
                                            v-if="
                                                form.document_type !==
                                                'Delivery Slip'
                                            "
                                            class="px-1.5 py-1 text-right border-r border-gray-100 align-top"
                                        >
                                            <div
                                                class="h-full flex items-center justify-end"
                                            >
                                                <span
                                                    class="text-xs text-gray-800"
                                                >
                                                    {{
                                                        new Intl.NumberFormat(
                                                            "id-ID",
                                                        ).format(
                                                            item.total || 0,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Actions -->
                                        <td class="px-1 py-1 align-top">
                                            <div
                                                class="h-full flex items-center justify-end gap-0.5"
                                            >
                                                <!-- Variations -->
                                                <button
                                                    v-if="
                                                        form.document_type !==
                                                        'Delivery Slip'
                                                    "
                                                    @click="
                                                        toggleVariations(item)
                                                    "
                                                    type="button"
                                                    :class="
                                                        item.has_variations
                                                            ? 'text-blue-600'
                                                            : 'text-gray-400 hover:text-gray-700'
                                                    "
                                                    class="p-1 border-0 bg-transparent inline-flex items-center justify-center"
                                                    title="Kelola variasi"
                                                >
                                                    <svg
                                                        class="w-3.5 h-3.5"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="1.8"
                                                            d="M4 6h16M4 10h16M4 14h16M4 18h16"
                                                        ></path>
                                                    </svg>
                                                </button>

                                                <!-- Remove -->
                                                <button
                                                    @click="removeItem(i)"
                                                    type="button"
                                                    class="p-1 border-0 bg-transparent text-gray-400 hover:text-red-600 inline-flex items-center justify-center"
                                                    title="Hapus barang"
                                                >
                                                    <svg
                                                        class="w-3.5 h-3.5"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="1.8"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 01-1-1h-4a1 1 0 01-1 1v3M4 7h16"
                                                        ></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Variations -->
                                    <tr v-if="item.has_variations">
                                        <td
                                            :colspan="
                                                form.document_type ===
                                                'Delivery Slip'
                                                    ? 5
                                                    : 7
                                            "
                                            class="px-2 py-1.5 border-b border-gray-200 bg-gray-50"
                                        >
                                            <div
                                                class="ml-2 pl-2 border-l border-gray-300"
                                            >
                                                <div
                                                    class="flex items-center justify-between h-6"
                                                >
                                                    <span
                                                        class="text-[10px] font-semibold text-gray-500"
                                                    >
                                                        Variasi Barang
                                                    </span>

                                                    <button
                                                        @click="
                                                            addVariation(item)
                                                        "
                                                        type="button"
                                                        class="text-[10px] text-blue-600 hover:text-blue-700"
                                                    >
                                                        + Tambah variasi
                                                    </button>
                                                </div>

                                                <table
                                                    class="w-full border-collapse"
                                                >
                                                    <thead>
                                                        <tr
                                                            class="border-b border-gray-200"
                                                        >
                                                            <th
                                                                class="h-6 pr-2 text-left text-[9px] font-medium text-gray-400"
                                                            >
                                                                Nama / Jenis
                                                            </th>

                                                            <th
                                                                class="h-6 px-1.5 text-right text-[9px] font-medium text-gray-400 w-20"
                                                            >
                                                                Qty
                                                            </th>

                                                            <th
                                                                class="h-6 px-1.5 text-right text-[9px] font-medium text-gray-400 w-32"
                                                            >
                                                                Harga Satuan
                                                            </th>

                                                            <th
                                                                class="w-7"
                                                            ></th>
                                                        </tr>
                                                    </thead>

                                                    <tbody>
                                                        <tr
                                                            v-for="(
                                                                v, vi
                                                            ) in item.variations"
                                                            :key="vi"
                                                            class="border-b border-gray-100 last:border-0"
                                                        >
                                                            <td
                                                                class="h-7 pr-2"
                                                            >
                                                                <input
                                                                    v-model="
                                                                        v.name
                                                                    "
                                                                    type="text"
                                                                    placeholder="Nama variasi"
                                                                    class="w-full h-6 p-0 border-0 outline-none bg-transparent text-[11px] text-gray-700 placeholder:text-gray-400 focus:ring-0"
                                                                />
                                                            </td>

                                                            <td
                                                                class="h-7 px-1.5"
                                                            >
                                                                <input
                                                                    :value="
                                                                        v.qty
                                                                    "
                                                                    @input="
                                                                        onVariationQtyInput(
                                                                            item,
                                                                            v,
                                                                            $event,
                                                                        )
                                                                    "
                                                                    type="number"
                                                                    placeholder="0"
                                                                    class="w-full h-6 p-0 border-0 outline-none bg-transparent text-[11px] text-right text-gray-700 placeholder:text-gray-400 focus:ring-0"
                                                                />
                                                            </td>

                                                            <td
                                                                class="h-7 px-1.5"
                                                            >
                                                                <input
                                                                    :value="
                                                                        formatRupiah(
                                                                            v.unit_price,
                                                                        )
                                                                    "
                                                                    @input="
                                                                        onVariationPriceInput(
                                                                            item,
                                                                            v,
                                                                            $event,
                                                                        )
                                                                    "
                                                                    type="text"
                                                                    placeholder="0"
                                                                    class="w-full h-6 p-0 border-0 outline-none bg-transparent text-[11px] text-right text-gray-700 placeholder:text-gray-400 focus:ring-0"
                                                                />
                                                            </td>

                                                            <td
                                                                class="h-7 text-right"
                                                            >
                                                                <button
                                                                    @click="
                                                                        removeVariation(
                                                                            item,
                                                                            vi,
                                                                        )
                                                                    "
                                                                    type="button"
                                                                    class="p-0.5 border-0 bg-transparent text-gray-400 hover:text-red-600"
                                                                    title="Hapus variasi"
                                                                >
                                                                    <svg
                                                                        class="w-3 h-3"
                                                                        fill="none"
                                                                        stroke="currentColor"
                                                                        viewBox="0 0 24 24"
                                                                    >
                                                                        <path
                                                                            stroke-linecap="round"
                                                                            stroke-linejoin="round"
                                                                            stroke-width="1.8"
                                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 01-1-1h-4a1 1 0 01-1 1v3M4 7h16"
                                                                        ></path>
                                                                    </svg>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <!-- Empty -->
                                <tr v-if="form.items.length === 0">
                                    <td
                                        :colspan="
                                            form.document_type ===
                                            'Delivery Slip'
                                                ? 5
                                                : 7
                                        "
                                        class="py-4 text-center border-b border-gray-200"
                                    >
                                        <span class="text-xs text-gray-400">
                                            Belum ada item.
                                        </span>
                                    </td>
                                </tr>

                                <!-- Add Item -->
                                <tr>
                                    <td
                                        :colspan="
                                            form.document_type ===
                                            'Delivery Slip'
                                                ? 5
                                                : 7
                                        "
                                        class="h-8 px-2"
                                    >
                                        <button
                                            type="button"
                                            @click="addItem"
                                            class="p-0 border-0 bg-transparent text-xs text-blue-600 hover:text-blue-700"
                                        >
                                            + Tambah Item
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Summary -->
                <!-- Summary -->
                <section
                    v-if="
                        form.document_type !== 'Delivery Address' &&
                        form.document_type !== 'Delivery Slip'
                    "
                    class="bg-white border border-gray-300"
                >
                    <div class="px-3 py-1.5 border-b border-gray-300">
                        <h2 class="text-xs font-semibold text-gray-900">
                            Ringkasan
                        </h2>
                    </div>

                    <div class="px-3 py-2">
                        <div class="ml-auto w-full max-w-sm text-xs">
                            <!-- Subtotal -->
                            <div class="flex items-center justify-between py-1">
                                <span class="text-xs text-gray-600">
                                    Subtotal
                                </span>

                                <span class="text-xs font-medium text-gray-800">
                                    {{
                                        new Intl.NumberFormat("id-ID").format(
                                            subtotal,
                                        )
                                    }}
                                </span>
                            </div>

                            <!-- PPN -->
                            <div class="flex items-center justify-between py-1">
                                <label
                                    class="inline-flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer select-none"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="form.is_ppn"
                                        class="w-3 h-3 rounded-sm border-gray-300 text-blue-600 focus:ring-0"
                                    />

                                    <span>PPN (11%)</span>
                                </label>

                                <span class="text-xs font-medium text-gray-700">
                                    {{
                                        new Intl.NumberFormat("id-ID").format(
                                            tax,
                                        )
                                    }}
                                </span>
                            </div>

                            <!-- Payment -->
                            <div class="border-t border-gray-200 mt-1 pt-1.5">
                                <div
                                    class="text-[11px] font-medium text-gray-500 mb-1"
                                >
                                    Pembayaran
                                </div>

                                <div class="flex items-center gap-4">
                                    <label
                                        class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                form.payment_type === 'dp'
                                            "
                                            @change="togglePaymentType('dp')"
                                            class="w-3 h-3 rounded-sm border-gray-300 text-blue-600 focus:ring-0"
                                        />

                                        <span>Uang Muka (DP)</span>
                                    </label>

                                    <label
                                        class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                form.payment_type ===
                                                'pelunasan'
                                            "
                                            @change="
                                                togglePaymentType('pelunasan')
                                            "
                                            class="w-3 h-3 rounded-sm border-gray-300 text-blue-600 focus:ring-0"
                                        />

                                        <span>Pelunasan</span>
                                    </label>
                                </div>
                            </div>

                            <!-- DP Detail -->
                            <div
                                v-if="form.payment_type !== 'full'"
                                class="border-t border-gray-100 mt-1 pt-1.5"
                            >
                                <div
                                    class="flex items-center justify-between py-0.5"
                                >
                                    <span class="text-xs text-gray-500">
                                        Persentase DP
                                    </span>

                                    <div class="flex items-center gap-1">
                                        <input
                                            type="number"
                                            min="1"
                                            max="100"
                                            v-model.number="form.dp_percent"
                                            class="w-14 h-6 px-1.5 border-0 border-b border-gray-300 rounded-none bg-transparent text-xs text-right outline-none focus:border-blue-500 focus:ring-0"
                                        />

                                        <span class="text-[11px] text-gray-400">
                                            %
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center justify-between py-0.5"
                                >
                                    <span class="text-xs text-gray-500">
                                        Nominal DP ({{ form.dp_percent }}%)
                                    </span>

                                    <span
                                        class="text-xs font-medium text-gray-800"
                                    >
                                        {{
                                            new Intl.NumberFormat(
                                                "id-ID",
                                            ).format(dpAmount)
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="flex items-center justify-between py-0.5"
                                >
                                    <span class="text-xs text-gray-500">
                                        Pelunasan ({{ 100 - form.dp_percent }}%)
                                    </span>

                                    <span
                                        class="text-xs font-medium text-gray-800"
                                    >
                                        {{
                                            new Intl.NumberFormat(
                                                "id-ID",
                                            ).format(pelunasanAmount)
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- Grand Total -->
                            <div
                                class="border-t border-gray-300 mt-1.5 pt-1.5 flex items-center justify-between"
                            >
                                <span
                                    class="text-sm font-semibold text-gray-900"
                                >
                                    Grand Total
                                </span>

                                <span
                                    class="text-[15px] font-semibold"
                                    :class="
                                        form.payment_type !== 'full'
                                            ? 'text-blue-600'
                                            : 'text-gray-900'
                                    "
                                >
                                    {{
                                        new Intl.NumberFormat("id-ID").format(
                                            grandTotal,
                                        )
                                    }}
                                </span>
                            </div>

                            <!-- Terbilang -->
                            <div
                                v-if="
                                    (form.document_type === 'Invoice' ||
                                        form.document_type ===
                                            'Proforma Invoice') &&
                                    grandTotal > 0
                                "
                                class="text-right mt-1"
                            >
                                <span class="text-[10px] text-gray-400">
                                    Terbilang:
                                </span>

                                <span class="text-[10px] text-gray-500 italic">
                                    {{ spellNumber(grandTotal) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Actions -->
            <div
                class="sticky bottom-0 z-30 mt-5 -mx-4 px-4 py-3 bg-white/95 backdrop-blur-sm border-t border-gray-200 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2.5"
            >
                <div>
                    <button
                        v-if="isEdit"
                        @click="handleDelete"
                        :disabled="saving"
                        class="px-3 h-9 text-sm font-medium text-red-600 hover:bg-red-50 rounded-md transition-colors disabled:opacity-50"
                    >
                        Hapus Dokumen
                    </button>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        @click="
                            router.push({
                                path: '/documents',
                                query: { type: form.document_type },
                            })
                        "
                        class="px-3.5 h-9 text-sm font-medium text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors"
                    >
                        Batal
                    </button>

                    <button
                        @click="handleSave(false)"
                        :disabled="saving"
                        class="px-3.5 h-9 text-sm font-medium text-gray-700 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors disabled:opacity-50 inline-flex items-center gap-2"
                    >
                        <svg
                            v-if="saving"
                            class="animate-spin h-3.5 w-3.5"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="3"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>

                        {{ saving ? "Menyimpan..." : "Simpan Draft" }}
                    </button>

                    <button
                        @click="handleSave(true)"
                        :disabled="saving"
                        class="px-3.5 h-9 text-sm font-medium text-white bg-indofilter hover:bg-indofilter-dark rounded-md transition-colors disabled:opacity-50 inline-flex items-center gap-2"
                    >
                        <svg
                            class="w-3.5 h-3.5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M5 13l4 4L19 7"
                            ></path>
                        </svg>

                        {{ saving ? "Menyimpan..." : "Simpan & Konfirmasi" }}
                    </button>
                </div>
            </div>

            <!-- Product Datalist -->
            <datalist id="company-products">
                <option
                    v-for="prod in companyProducts"
                    :key="prod.id"
                    :value="prod.name"
                >
                    {{ prod.code ? `[${prod.code}] ` : ""
                    }}{{ prod.description ? ` - ${prod.description}` : "" }}
                </option>
            </datalist>

            <!-- Add Partner Modal -->
            <div
                v-if="isOpenPartnerModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
            >
                <div
                    class="bg-white w-full max-w-md rounded-lg border border-gray-200 shadow-xl overflow-hidden"
                >
                    <!-- Modal Header -->
                    <div
                        class="px-4 py-3 border-b border-gray-200 flex items-center justify-between"
                    >
                        <h3 class="text-sm font-semibold text-gray-900">
                            Tambah Partner
                        </h3>

                        <button
                            type="button"
                            @click="isOpenPartnerModal = false"
                            class="w-7 h-7 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 inline-flex items-center justify-center"
                        >
                            <svg
                                class="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M6 18L18 6M6 6l12 12"
                                ></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-4 space-y-3 max-h-[65vh] overflow-y-auto">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Type -->
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-600 mb-1.5"
                                >
                                    Tipe Partner
                                </label>

                                <select
                                    v-model="partnerForm.type"
                                    class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="customer">Customer</option>
                                    <option value="vendor">Vendor</option>
                                </select>
                            </div>

                            <!-- Alias -->
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-600 mb-1.5"
                                >
                                    Alias
                                </label>

                                <input
                                    v-model="partnerForm.alias"
                                    type="text"
                                    placeholder="Contoh: Maju Jaya"
                                    class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                />
                            </div>
                        </div>

                        <!-- Name -->
                        <div>
                            <label
                                class="block text-xs font-medium text-gray-600 mb-1.5"
                            >
                                Nama Partner / Perusahaan
                                <span class="text-red-500">*</span>
                            </label>

                            <input
                                v-model="partnerForm.name"
                                type="text"
                                placeholder="Nama Lengkap / Nama PT"
                                required
                                class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            />
                        </div>

                        <!-- Address -->
                        <div>
                            <label
                                class="block text-xs font-medium text-gray-600 mb-1.5"
                            >
                                Alamat Lengkap
                            </label>

                            <textarea
                                v-model="partnerForm.address"
                                rows="2"
                                placeholder="Alamat pengiriman / penagihan"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm resize-none focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            ></textarea>
                        </div>

                        <!-- Phone / PIC -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-600 mb-1.5"
                                >
                                    No. HP / Telepon
                                </label>

                                <input
                                    v-model="partnerForm.phone"
                                    type="text"
                                    placeholder="No. Telepon"
                                    class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-600 mb-1.5"
                                >
                                    Kontak Person (PIC)
                                </label>

                                <input
                                    v-model="partnerForm.contact_person"
                                    type="text"
                                    placeholder="Nama PIC"
                                    class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                />
                            </div>
                        </div>

                        <!-- Email / NPWP -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-600 mb-1.5"
                                >
                                    Email
                                </label>

                                <input
                                    v-model="partnerForm.email"
                                    type="email"
                                    placeholder="Alamat Email"
                                    class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-600 mb-1.5"
                                >
                                    NPWP
                                </label>

                                <input
                                    v-model="partnerForm.npwp"
                                    type="text"
                                    placeholder="Nomor NPWP"
                                    class="w-full h-9 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div
                        class="px-4 py-3 border-t border-gray-200 flex items-center justify-end gap-2"
                    >
                        <button
                            type="button"
                            @click="isOpenPartnerModal = false"
                            class="px-3.5 h-9 text-sm font-medium text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                        >
                            Batal
                        </button>

                        <button
                            type="button"
                            @click="handleSavePartner"
                            :disabled="isSavingPartner"
                            class="px-3.5 h-9 text-sm font-medium text-white bg-indofilter hover:bg-indofilter-dark rounded-md transition-colors flex items-center gap-2 disabled:opacity-50"
                        >
                            <svg
                                v-if="isSavingPartner"
                                class="animate-spin h-3.5 w-3.5"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="3"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>

                            {{
                                isSavingPartner
                                    ? "Menyimpan..."
                                    : "Simpan Partner"
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
