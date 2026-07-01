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
        if (
            confirm(
                "Apakah Anda ingin menyalin data (barang, partner, dll.) dari dokumen referensi ini? Klik Batal jika hanya ingin menghubungkan referensi saja.",
            )
        ) {
            handleCopyFromDocument();
        }
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
});

const quotationDefaults = {
    stock_conditions: "Ready Stock",
    term_of_payment: "Cash Before Delivery",
    price_conditions: "Grand Total Include PPN and Delivery",
    standard_packing: "Cardboard Box",
    offer_validity: "Valid for 14 days",
};

watch(
    () => form.value.document_type,
    (val) => {
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
                alert(`Partner "${data.partner_name}" tidak ditemukan di database. Silakan pilih partner secara manual.`);
            }
            
            if (data.document_number) form.value.number = data.document_number;
            if (data.date) form.value.date = data.date;
            if (data.due_date) form.value.due_date = data.due_date;
            if (data.notes) form.value.notes = data.notes;
            if (data.terms) form.value.terms = data.terms;
            if (data.discount !== undefined) form.value.discount = data.discount;
            if (data.is_ppn !== undefined) form.value.is_ppn = data.is_ppn;
            
            if (data.items && data.items.length > 0) {
                form.value.items = data.items.map(item => ({
                    product_name: item.product_name,
                    description: item.description || "",
                    qty: item.qty || 1,
                    uom: item.uom || "PCS",
                    unit_price: item.unit_price || 0,
                    total: (item.qty || 1) * (item.unit_price || 0)
                }));
            }
            
            alert("PDF berhasil dibaca! Data form telah diisi otomatis.");
        } else {
            error.value = res.data.message || "Gagal menganalisis PDF.";
        }
    } catch (e) {
        error.value = e.response?.data?.message || e.message || "Gagal menganalisis PDF.";
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
    });
}

function removeItem(index) {
    form.value.items.splice(index, 1);
}

function calcItem(item) {
    item.total =
        (parseFloat(item.qty) || 0) * (parseFloat(item.unit_price) || 0);
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
    if (!number || number === 0) return "Nol Rupiah";
    const spelled = terbilangJS(number).trim();
    return spelled.replace(/\b\w/g, (c) => c.toUpperCase()) + " Rupiah";
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
        calcItem(item);
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
        alert("Nama partner wajib diisi!");
        return;
    }
    if (!partnerForm.value.company_id) {
        alert("Perusahaan harus dipilih terlebih dahulu di form utama!");
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
        alert(e.response?.data?.message || "Gagal menambah partner");
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

        form.value.items = (doc.items || []).map((i) => ({
            product_name: i.product_name || "",
            description: i.description || "",
            qty: parseFloat(i.qty) || 1,
            uom: i.uom || "PCS",
            unit_price: parseFloat(i.unit_price) || 0,
            total: parseFloat(i.total) || 0,
        }));

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
            items: (doc.items || []).map((i) => ({
                product_name: i.product_name || "",
                description: i.description || "",
                qty: i.qty || 1,
                uom: i.uom || "PCS",
                unit_price: i.unit_price || 0,
                total: i.total || 0,
            })),
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

async function handleSave(confirm = false) {
    saving.value = true;
    error.value = "";
    try {
        let overwrite = false;
        if (confirm && form.value.number) {
            try {
                const checkRes = await api.checkLocalFile({ 
                    number: form.value.number,
                    company_id: form.value.company_id,
                    type: form.value.type
                });
                if (checkRes.data && checkRes.data.path_configured && checkRes.data.exists) {
                    if (!window.confirm(`Berkas "${checkRes.data.filename}" sudah ada di folder penyimpanan lokal. Apakah Anda ingin menimpanya?`)) {
                        saving.value = false;
                        return;
                    }
                    overwrite = true;
                }
            } catch (e) {
                console.error("Gagal memeriksa berkas lokal", e);
            }
        }

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
            })),
        };

        if (isEdit.value) {
            await api.update(route.params.id, payload);
        } else {
            await api.create(payload);
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

async function handleDelete() {
    if (!confirm("Yakin ingin menghapus dokumen ini?")) return;
    saving.value = true;
    try {
        await api.delete(route.params.id);
        router.push({
            path: "/documents",
            query: { type: form.value.document_type },
        });
    } catch (e) {
        error.value = e.response?.data?.message || "Gagal menghapus dokumen";
    } finally {
        saving.value = false;
    }
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
    if (e.ctrlKey && e.key.toLowerCase() === 's') {
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
    }
});

onUnmounted(() => {
    window.removeEventListener("keydown", handleFormKeydown);
});
</script>

<template>
    <div>
        <div v-if="loading" class="flex items-center justify-center py-20">
            <svg
                class="animate-spin h-8 w-8 text-indofilter"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                ></circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
            </svg>
        </div>

        <template v-if="!loading">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold text-gray-800">
                    {{
                        isEdit
                            ? "Edit " + form.document_type
                            : "Buat " + form.document_type + " Baru"
                    }}
                </h2>
                <button
                    @click="
                        router.push({
                            path: '/documents',
                            query: { type: form.document_type },
                        })
                    "
                    class="text-sm text-gray-500 hover:text-gray-700 transition-colors flex items-center gap-1"
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
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"
                        ></path>
                    </svg>
                    Kembali
                </button>
            </div>

            <div
                v-if="error"
                class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3 mb-4"
            >
                {{ error }}
            </div>

            <div
                class="bg-white rounded-lg border border-gray-200 p-6 space-y-6"
            >
                <!-- Auto-fill AI (PDF) -->
                <div
                    v-if="!isEdit"
                    class="bg-purple-50/40 border border-purple-100 rounded-xl p-4 flex flex-col md:flex-row md:items-start gap-4 mb-4"
                >
                    <div class="flex-1">
                        <label class="block text-xs font-bold text-purple-800 mb-1">
                            Auto-fill Form dengan AI (Upload PDF)
                        </label>
                        <div class="flex items-center gap-2 mt-2">
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
                                class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 flex items-center gap-2 cursor-pointer"
                            >
                                <svg v-if="parsingPdf" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                {{ parsingPdf ? "Membaca PDF..." : "Pilih File PDF Penawaran / Invoice" }}
                            </button>
                            <span v-if="uploadedPdfName" class="text-xs text-gray-600 font-semibold truncate max-w-xs bg-purple-100/60 px-2.5 py-1 rounded-md">
                                {{ uploadedPdfName }}
                            </span>
                        </div>
                    </div>
                    <div class="text-xs text-purple-700 md:max-w-sm md:mt-2">
                        Punya file PDF penawaran/invoice sebelumnya? Unggah di sini dan AI akan otomatis membaca, mengekstrak tabel barang, harga, tanggal, nomor dokumen, dll. untuk mengisi form ini secara instan!
                    </div>
                </div>

                <!-- Referensi Dokumen -->
                <div
                    class="bg-blue-50/40 border border-blue-100 rounded-xl p-4 flex flex-col md:flex-row md:items-start gap-4"
                >
                    <div class="flex-1 relative">
                        <label
                            class="block text-xs font-semibold text-blue-800 mb-1"
                            >Salin Data / Referensi Dokumen</label
                        >

                        <!-- Custom Searchable Dropdown Wrapper -->
                        <div class="relative">
                            <!-- Toggle / Search Input -->
                            <div class="relative flex items-center">
                                <input
                                    type="text"
                                    v-model="refSearch"
                                    @focus="isOpenRefDropdown = true"
                                    placeholder="Cari No. Dokumen, Partner, atau Tipe Dokumen..."
                                    class="w-full pl-3 pr-16 py-2 border border-blue-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                                <div
                                    class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5 text-gray-400"
                                >
                                    <button
                                        v-if="refSearch"
                                        type="button"
                                        @click="
                                            refSearch = '';
                                            selectedRefDocumentId = '';
                                        "
                                        class="hover:text-red-500 cursor-pointer pointer-events-auto"
                                        title="Hapus pilihan"
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
                                                d="M6 18L18 6M6 6l12 12"
                                            ></path>
                                        </svg>
                                    </button>
                                    <svg
                                        class="w-4 h-4 transition-transform duration-200 pointer-events-none"
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

                            <!-- Dropdown Overlay Backdrop to close dropdown on click outside -->
                            <div
                                v-if="isOpenRefDropdown"
                                class="fixed inset-0 z-10"
                                @click="isOpenRefDropdown = false"
                            ></div>

                            <!-- Results List -->
                            <div
                                v-if="isOpenRefDropdown"
                                class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                            >
                                <div
                                    v-if="filteredRefDocuments.length === 0"
                                    class="px-4 py-3 text-sm text-gray-500 italic"
                                >
                                    Tidak ada dokumen yang cocok
                                </div>
                                <button
                                    v-for="doc in filteredRefDocuments"
                                    :key="doc.id"
                                    type="button"
                                    @click="selectRefDocument(doc)"
                                    class="w-full text-left px-4 py-2.5 hover:bg-blue-50 transition-colors text-sm border-b border-gray-50 last:border-0 flex flex-col"
                                >
                                    <div
                                        class="flex items-center justify-between font-semibold text-gray-800"
                                    >
                                        <span>{{
                                            doc.document_number || "(Draft)"
                                        }}</span>
                                        <span
                                            class="text-[10px] bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded uppercase font-bold"
                                        >
                                            {{
                                                doc.type
                                                    .toUpperCase()
                                                    .replace("_", " ")
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        class="flex items-center justify-between text-xs text-gray-500 mt-1"
                                    >
                                        <span>{{
                                            doc.partner?.name || "No Partner"
                                        }}</span>
                                        <span>{{ doc.date }}</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="text-xs text-blue-600 md:max-w-sm md:mt-6">
                        Ketik untuk menyaring dokumen referensi berdasarkan
                        tipe, partner, atau nomor dokumen. Memilih dokumen akan
                        menyalin data Perusahaan, Partner, Item Barang, Syarat,
                        dan Ketentuan.
                    </div>
                </div>

                <!-- Combined Odoo ERP Fields Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-2">
                    <!-- Left Column: Primary Fields -->
                    <div class="space-y-2">
                        <div
                            class="grid grid-cols-3 items-center gap-x-4 gap-y-2"
                        >
                            <label for="company" class="mb-0">Perusahaan</label>
                            <div class="col-span-2">
                                <select
                                    v-model="form.company_id"
                                    disabled
                                    class="w-full outline-none bg-gray-50 cursor-not-allowed"
                                    id="company"
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
                        </div>
                        <div
                            class="grid grid-cols-3 items-center gap-x-4 gap-y-2"
                        >
                            <label for="partner" class="mb-0">Partner *</label>
                            <div class="col-span-2 flex gap-2">
                                <select
                                    v-model="form.partner_id"
                                    class="w-full outline-none flex-1"
                                    id="partner"
                                >
                                    <option value="">Pilih Partner</option>
                                    <option
                                        v-for="p in partnersList"
                                        :key="p.id"
                                        :value="p.id"
                                    >
                                        {{ p.name }}
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    @click="openAddPartnerModal"
                                    class="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg text-xs font-semibold border border-blue-200 transition-colors flex items-center gap-1 shrink-0"
                                    title="Tambah Partner Baru"
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
                                            d="M12 4v16m8-8H4"
                                        ></path>
                                    </svg>
                                    <span>Tambah</span>
                                </button>
                            </div>
                        </div>
                        <div
                            class="grid grid-cols-3 items-center gap-x-4 gap-y-2"
                        >
                            <label for="doc-no" class="mb-0">No. Dokumen</label>
                            <div class="col-span-2">
                                <input
                                    v-model="form.number"
                                    @input="handleNumberInput"
                                    class="w-full doc-number-input outline-none"
                                    id="doc-no"
                                    placeholder="Otomatis digenerate"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Dates & References -->
                    <div class="space-y-2">
                        <div
                            class="grid grid-cols-3 items-center gap-x-4 gap-y-2"
                        >
                            <label for="date" class="mb-0">Tanggal *</label>
                            <div class="col-span-2">
                                <input
                                    v-model="form.date"
                                    type="date"
                                    class="w-full outline-none"
                                    id="date"
                                />
                            </div>
                        </div>
                        <div
                            class="grid grid-cols-3 items-center gap-x-4 gap-y-2"
                        >
                            <label for="due-date" class="mb-0"
                                >Jatuh Tempo</label
                            >
                            <div class="col-span-2">
                                <input
                                    v-model="form.due_date"
                                    type="date"
                                    class="w-full outline-none"
                                    id="due-date"
                                />
                            </div>
                        </div>

                        <!-- Customer PO (For Delivery Slip) -->
                        <div
                            v-if="form.document_type === 'Delivery Slip'"
                            class="grid grid-cols-3 items-center gap-x-4 gap-y-2"
                        >
                            <label for="po-no" class="mb-0"
                                >Nomor PO Customer</label
                            >
                            <div class="col-span-2">
                                <input
                                    v-model="form.customer_po_number"
                                    type="text"
                                    class="w-full outline-none"
                                    id="po-no"
                                    placeholder="Masukkan No. PO Customer..."
                                />
                            </div>
                        </div>
                        <div
                            v-if="form.document_type === 'Delivery Slip'"
                            class="grid grid-cols-3 items-center gap-x-4 gap-y-2"
                        >
                            <label for="po-date" class="mb-0">Tanggal PO</label>
                            <div class="col-span-2">
                                <input
                                    v-model="form.customer_po_date"
                                    type="date"
                                    class="w-full outline-none"
                                    id="po-date"
                                />
                            </div>
                        </div>

                        <!-- Customer Reff / PO (For Invoice and Proforma Invoice) -->
                        <div
                            v-if="
                                form.document_type === 'Invoice' ||
                                form.document_type === 'Proforma Invoice'
                            "
                            class="grid grid-cols-3 items-center gap-x-4 gap-y-2"
                        >
                            <label for="cust-reff" class="mb-0"
                                >Customer Reff</label
                            >
                            <div class="col-span-2">
                                <input
                                    v-model="form.customer_po_number"
                                    type="text"
                                    class="w-full outline-none"
                                    id="cust-reff"
                                    placeholder="Customer Reff / No. PO..."
                                />
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-3 items-start gap-x-4 gap-y-2"
                        >
                            <label class="mt-1.5" for="payment-terms"
                                >Syarat Pembayaran</label
                            >
                            <div class="col-span-2">
                                <textarea
                                    v-model="form.terms"
                                    class="w-full resize-none outline-none"
                                    id="payment-terms"
                                    placeholder="Masukkan syarat pembayaran..."
                                    rows="2"
                                ></textarea>
                            </div>
                        </div>
                        <div
                            class="grid grid-cols-3 items-start gap-x-4 gap-y-2"
                        >
                            <label class="mt-1.5" for="notes">Catatan</label>
                            <div class="col-span-2">
                                <textarea
                                    v-model="form.notes"
                                    class="w-full resize-none outline-none"
                                    id="notes"
                                    placeholder="Catatan internal..."
                                    rows="2"
                                ></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Address Fields -->
                <div
                    v-if="form.document_type === 'Delivery Address'"
                    class="border-t border-gray-200 pt-4"
                >
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">
                        Alamat Pengiriman
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <h4
                                class="text-xs font-semibold text-gray-500 uppercase tracking-wider"
                            >
                                Pengirim
                            </h4>
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-600 mb-1"
                                    >Nama Perusahaan</label
                                >
                                <input
                                    v-model="form.sender_name"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                    placeholder="Nama perusahaan pengirim"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-600 mb-1"
                                    >No. HP</label
                                >
                                <input
                                    v-model="form.sender_phone"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                    placeholder="No. telepon perusahaan"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-600 mb-1"
                                    >Alamat</label
                                >
                                <textarea
                                    v-model="form.sender_address"
                                    rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                    placeholder="Alamat lengkap pengirim"
                                ></textarea>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <h4
                                class="text-xs font-semibold text-gray-500 uppercase tracking-wider"
                            >
                                Penerima
                            </h4>
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-600 mb-1"
                                    >Nama / Perusahaan</label
                                >
                                <input
                                    v-model="form.recipient_name"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                    placeholder="Nama penerima atau perusahaan"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-600 mb-1"
                                    >No. HP</label
                                >
                                <input
                                    v-model="form.recipient_phone"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                    placeholder="No. telepon penerima"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-600 mb-1"
                                    >Alamat</label
                                >
                                <textarea
                                    v-model="form.recipient_address"
                                    rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                    placeholder="Alamat lengkap penerima"
                                ></textarea>
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-600 mb-1"
                                    >PIC (Up.)</label
                                >
                                <input
                                    v-model="form.recipient_pic"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                    placeholder="Nama contact person"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rekening Bank Perusahaan -->
                <div
                    v-if="
                        (form.document_type === 'Invoice' ||
                            form.document_type === 'Proforma Invoice') &&
                        form.company_id &&
                        companyBankAccounts.length > 0
                    "
                    class="border-t border-gray-100 pt-4"
                >
                    <label class="block text-xs font-medium text-gray-600 mb-2"
                        >Rekening Bank Perusahaan untuk Dokumen Ini *</label
                    >
                    <div
                        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3"
                    >
                        <label
                            v-for="acc in companyBankAccounts"
                            :key="acc.id"
                            class="relative flex flex-col p-3 border rounded-xl cursor-pointer hover:border-blue-500 transition-all select-none"
                            :class="
                                form.bank_account_id === acc.id
                                    ? 'border-blue-500 bg-blue-50/20 ring-1 ring-blue-500'
                                    : 'border-gray-200 bg-white'
                            "
                        >
                            <div class="flex items-center gap-2">
                                <input
                                    type="radio"
                                    :value="acc.id"
                                    v-model="form.bank_account_id"
                                    class="text-blue-600 focus:ring-blue-500"
                                />
                                <span class="text-sm font-bold text-gray-800">{{
                                    acc.bank_name
                                }}</span>
                                <span
                                    v-if="acc.is_default"
                                    class="text-[10px] bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded font-semibold"
                                    >Utama</span
                                >
                            </div>
                            <div class="mt-1.5 pl-5">
                                <p class="text-xs text-gray-700 font-medium">
                                    An: {{ acc.account_name }}
                                </p>
                                <p class="text-xs font-semibold text-blue-600">
                                    No. Rek: {{ acc.account_number }}
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Rekening Bank Vendor (Hanya untuk Purchase Order) -->
                <div
                    v-if="form.document_type === 'Purchase Order'"
                    class="border-t border-gray-100 pt-4 space-y-3"
                >
                    <label
                        class="block text-xs font-semibold text-gray-700 uppercase tracking-wider"
                        >Detail Bank Vendor (Untuk Pembayaran PO)</label
                    >
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label
                                class="block text-[11px] font-medium text-gray-500 mb-1"
                                >Nama Bank</label
                            >
                            <input
                                v-model="form.vendor_bank_name"
                                placeholder="Contoh: Bank Mandiri"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-[11px] font-medium text-gray-500 mb-1"
                                >Nama Rekening</label
                            >
                            <input
                                v-model="form.vendor_bank_account_name"
                                placeholder="Contoh: PT Sumber Jaya"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-[11px] font-medium text-gray-500 mb-1"
                                >Nomor Rekening</label
                            >
                            <input
                                v-model="form.vendor_bank_account_number"
                                placeholder="Contoh: 12345678"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Ketentuan Penawaran -->
                <div
                    v-if="form.document_type === 'Quotation'"
                    class="border-t border-gray-200 pt-3"
                >
                    <h3 class="text-xs font-semibold text-gray-700 mb-2">
                        Ketentuan Penawaran
                    </h3>
                    <div class="flex flex-col gap-1.5 max-w-2xl">
                        <div class="flex items-center gap-2">
                            <label
                                class="w-40 text-xs font-medium text-gray-600 flex-shrink-0 whitespace-nowrap"
                                >Stock Conditions</label
                            >
                            <input
                                v-model="form.stock_conditions"
                                class="flex-1 min-w-0 px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <label
                                class="w-40 text-xs font-medium text-gray-600 flex-shrink-0 whitespace-nowrap"
                                >Term of Payment</label
                            >
                            <input
                                v-model="form.term_of_payment"
                                class="flex-1 min-w-0 px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <label
                                class="w-40 text-xs font-medium text-gray-600 flex-shrink-0 whitespace-nowrap"
                                >Price Conditions</label
                            >
                            <input
                                v-model="form.price_conditions"
                                class="flex-1 min-w-0 px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <label
                                class="w-40 text-xs font-medium text-gray-600 flex-shrink-0 whitespace-nowrap"
                                >Standard Packing</label
                            >
                            <input
                                v-model="form.standard_packing"
                                class="flex-1 min-w-0 px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <label
                                class="w-40 text-xs font-medium text-gray-600 flex-shrink-0 whitespace-nowrap"
                                >Offer Validity</label
                            >
                            <input
                                v-model="form.offer_validity"
                                class="flex-1 min-w-0 px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Items -->
                <div v-if="form.document_type !== 'Delivery Address'">
                    <div class="mb-3">
                        <h3 class="text-sm font-semibold text-gray-700">
                            Item Barang
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                                    >
                                        Nama Barang
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                                    >
                                        Deskripsi
                                    </th>
                                    <th
                                        class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase"
                                    >
                                        Qty
                                    </th>
                                    <th
                                        class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                                    >
                                        Satuan
                                    </th>
                                    <th
                                        v-if="
                                            form.document_type !==
                                            'Delivery Slip'
                                        "
                                        class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase"
                                    >
                                        Harga
                                    </th>
                                    <th
                                        v-if="
                                            form.document_type !==
                                            'Delivery Slip'
                                        "
                                        class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase"
                                    >
                                        Total
                                    </th>
                                    <th
                                        class="px-1 py-2 text-center text-xs font-medium text-gray-500 uppercase"
                                    ></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(item, i) in form.items" :key="i">
                                    <td class="px-1.5 py-1">
                                        <input
                                            v-model="item.product_name"
                                            list="company-products"
                                            @input="onProductNameInput(item)"
                                            placeholder="Nama barang"
                                            class="w-full px-1.5 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                        />
                                    </td>
                                    <td class="px-1.5 py-1">
                                        <input
                                            v-model="item.description"
                                            placeholder="Deskripsi"
                                            class="w-full px-1.5 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                        />
                                    </td>
                                    <td class="px-1.5 py-1">
                                        <input
                                            :value="item.qty"
                                            @input="onQtyInput(item, $event)"
                                            inputmode="numeric"
                                            placeholder="0"
                                            class="w-full px-1.5 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 outline-none text-right"
                                        />
                                    </td>
                                    <td class="px-1.5 py-1">
                                        <select
                                            v-model="item.uom"
                                            class="w-full px-1.5 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                        >
                                            <option value="PCS">PCS</option>
                                            <option value="KG">KG</option>
                                            <option value="MTR">MTR</option>
                                            <option value="LTR">LTR</option>
                                            <option value="BOX">BOX</option>
                                            <option value="ROL">ROL</option>
                                            <option value="SET">SET</option>
                                            <option value="UNIT">UNIT</option>
                                        </select>
                                    </td>
                                    <td
                                        v-if="
                                            form.document_type !==
                                            'Delivery Slip'
                                        "
                                        class="px-1.5 py-1"
                                    >
                                        <input
                                            :value="
                                                formatRupiah(item.unit_price)
                                            "
                                            @input="onPriceInput(item, $event)"
                                            inputmode="numeric"
                                            placeholder="0"
                                            class="w-full px-1.5 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 outline-none text-right"
                                        />
                                    </td>
                                    <td
                                        v-if="
                                            form.document_type !==
                                            'Delivery Slip'
                                        "
                                        class="px-1.5 py-1 text-right text-sm font-medium text-gray-700"
                                    >
                                        {{
                                            new Intl.NumberFormat(
                                                "id-ID",
                                            ).format(item.total || 0)
                                        }}
                                    </td>
                                    <td class="px-1 py-1 text-center">
                                        <button
                                            @click="removeItem(i)"
                                            class="text-red-400 hover:text-red-600 transition-colors"
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
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                ></path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="form.items.length === 0">
                                    <td
                                        :colspan="
                                            form.document_type ===
                                            'Delivery Slip'
                                                ? 5
                                                : 7
                                        "
                                        class="px-3 py-4 text-center text-gray-400 text-sm"
                                    >
                                        Belum ada item. Tambahkan item
                                        menggunakan baris di bawah.
                                    </td>
                                </tr>
                                <!-- Add Item Row (Odoo style) -->
                                <tr
                                    class="hover:bg-gray-50/50 transition-colors"
                                >
                                    <td
                                        :colspan="
                                            form.document_type ===
                                            'Delivery Slip'
                                                ? 5
                                                : 7
                                        "
                                        class="px-3 py-2 border-t border-gray-100"
                                    >
                                        <button
                                            type="button"
                                            @click="addItem"
                                            class="text-blue-600 hover:text-blue-800 text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-1.5 py-1"
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
                                                    d="M12 4v16m8-8H4"
                                                ></path>
                                            </svg>
                                            Tambah Item (Add a line)
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Totals -->
                <div
                    v-if="
                        form.document_type !== 'Delivery Address' &&
                        form.document_type !== 'Delivery Slip'
                    "
                    class="flex justify-end"
                >
                    <div class="w-72 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Subtotal</span>
                            <span class="font-medium">{{
                                new Intl.NumberFormat("id-ID").format(subtotal)
                            }}</span>
                        </div>
                        <div class="flex justify-between text-sm items-center">
                            <label
                                class="inline-flex items-center text-gray-500 cursor-pointer select-none"
                            >
                                <input
                                    type="checkbox"
                                    v-model="form.is_ppn"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2"
                                />
                                Gunakan PPN (11%)
                            </label>
                            <span class="font-medium text-green-600"
                                >+{{
                                    new Intl.NumberFormat("id-ID").format(tax)
                                }}</span
                            >
                        </div>
                        <div
                            class="flex flex-col gap-1.5 border-t border-gray-100 pt-2 pb-1"
                        >
                            <div
                                class="flex justify-between items-center text-sm"
                            >
                                <label
                                    class="inline-flex items-center text-gray-500 cursor-pointer select-none"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="form.payment_type === 'dp'"
                                        @change="togglePaymentType('dp')"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2"
                                    />
                                    Uang Muka (DP)
                                </label>
                            </div>
                            <div
                                class="flex justify-between items-center text-sm"
                            >
                                <label
                                    class="inline-flex items-center text-gray-500 cursor-pointer select-none"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="
                                            form.payment_type === 'pelunasan'
                                        "
                                        @change="togglePaymentType('pelunasan')"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2"
                                    />
                                    Pelunasan
                                </label>
                            </div>
                        </div>
                        <div
                            v-if="form.payment_type !== 'full'"
                            class="space-y-2 border-t border-gray-100 pt-2 pb-1"
                        >
                            <div
                                class="flex justify-between text-sm items-center"
                            >
                                <span class="text-xs text-gray-400"
                                    >Persentase DP (%)</span
                                >
                                <input
                                    type="number"
                                    min="1"
                                    max="100"
                                    v-model.number="form.dp_percent"
                                    class="w-16 px-1.5 py-0.5 border border-gray-300 rounded text-xs text-right focus:ring-2 focus:ring-blue-500 outline-none"
                                />
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500"
                                    >Nominal DP ({{ form.dp_percent }}%)</span
                                >
                                <span class="font-medium text-blue-600">{{
                                    new Intl.NumberFormat("id-ID").format(
                                        dpAmount,
                                    )
                                }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500"
                                    >Nominal Pelunasan ({{
                                        100 - form.dp_percent
                                    }}%)</span
                                >
                                <span class="font-medium text-gray-700">{{
                                    new Intl.NumberFormat("id-ID").format(
                                        pelunasanAmount,
                                    )
                                }}</span>
                            </div>
                        </div>
                        <div
                            class="flex justify-between text-sm font-bold border-t border-gray-200 pt-2"
                        >
                            <span>Grand Total</span>
                            <span
                                :class="
                                    form.payment_type !== 'full'
                                        ? 'text-blue-600 font-bold'
                                        : ''
                                "
                                >{{
                                    new Intl.NumberFormat("id-ID").format(
                                        grandTotal,
                                    )
                                }}</span
                            >
                        </div>
                        <!-- Terbilang Display -->
                        <div
                            v-if="
                                (form.document_type === 'Invoice' ||
                                    form.document_type ===
                                        'Proforma Invoice') &&
                                grandTotal > 0
                            "
                            class="text-xs text-right text-gray-500 italic mt-1.5 font-medium bg-gray-50 p-2 rounded border border-gray-100"
                        >
                            Terbilang: {{ spellNumber(grandTotal) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 mt-6">
                <button
                    v-if="isEdit"
                    @click="handleDelete"
                    :disabled="saving"
                    class="px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-sm font-medium transition-colors disabled:opacity-50 flex items-center gap-2"
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
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                        ></path>
                    </svg>
                    Hapus
                </button>
                <button
                    @click="
                        router.push({
                            path: '/documents',
                            query: { type: form.document_type },
                        })
                    "
                    class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg transition-colors"
                >
                    Batal
                </button>
                <button
                    @click="handleSave(false)"
                    :disabled="saving"
                    class="px-4 py-2 bg-indofilter hover:bg-indofilter-dark text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 flex items-center gap-2"
                >
                    <svg
                        v-if="saving"
                        class="animate-spin h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                    {{ saving ? "Menyimpan..." : "Simpan sebagai Draft" }}
                </button>
                <button
                    @click="handleSave(true)"
                    :disabled="saving"
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 flex items-center gap-2"
                >
                    Simpan & Konfirmasi
                </button>
            </div>
        </template>

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
            class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
        >
            <div
                class="bg-white rounded-2xl max-w-lg w-full border border-gray-150 shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200"
            >
                <!-- Modal Header -->
                <div
                    class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50"
                >
                    <h3 class="text-base font-bold text-gray-800">
                        Tambah Partner Baru
                    </h3>
                    <button
                        type="button"
                        @click="isOpenPartnerModal = false"
                        class="text-gray-400 hover:text-gray-650 transition-colors"
                    >
                        <svg
                            class="w-5 h-5"
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

                <!-- Modal Body -->
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <!-- Type Selector -->
                    <div>
                        <label
                            class="block text-xs font-semibold text-gray-650 mb-1"
                            >Tipe Partner</label
                        >
                        <select
                            v-model="partnerForm.type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                        >
                            <option value="customer">Customer</option>
                            <option value="vendor">Vendor</option>
                        </select>
                    </div>

                    <!-- Name -->
                    <div>
                        <label
                            class="block text-xs font-semibold text-gray-650 mb-1"
                            >Nama Partner / Perusahaan *</label
                        >
                        <input
                            v-model="partnerForm.name"
                            type="text"
                            placeholder="Nama Lengkap / Nama PT"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                            required
                        />
                    </div>

                    <!-- Alias -->
                    <div>
                        <label
                            class="block text-xs font-semibold text-gray-650 mb-1"
                            >Alias (Singkatan)</label
                        >
                        <input
                            v-model="partnerForm.alias"
                            type="text"
                            placeholder="Contoh: Maju Jaya"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                        />
                    </div>

                    <!-- Address -->
                    <div>
                        <label
                            class="block text-xs font-semibold text-gray-650 mb-1"
                            >Alamat Lengkap</label
                        >
                        <textarea
                            v-model="partnerForm.address"
                            rows="3"
                            placeholder="Alamat pengiriman / penagihan"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                        ></textarea>
                    </div>

                    <!-- Phone and Contact Person (Parallel) -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-650 mb-1"
                                >No. HP / Telepon</label
                            >
                            <input
                                v-model="partnerForm.phone"
                                type="text"
                                placeholder="No. Telepon"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-650 mb-1"
                                >Kontak Person (PIC)</label
                            >
                            <input
                                v-model="partnerForm.contact_person"
                                type="text"
                                placeholder="Nama PIC"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                            />
                        </div>
                    </div>

                    <!-- Email and NPWP (Parallel - Rarely used) -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-650 mb-1"
                                >Email</label
                            >
                            <input
                                v-model="partnerForm.email"
                                type="email"
                                placeholder="Alamat Email"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-650 mb-1"
                                >NPWP</label
                            >
                            <input
                                v-model="partnerForm.npwp"
                                type="text"
                                placeholder="Nomor NPWP"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div
                    class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2 bg-gray-50/50"
                >
                    <button
                        type="button"
                        @click="isOpenPartnerModal = false"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="handleSavePartner"
                        :disabled="isSavingPartner"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors flex items-center gap-1.5 disabled:opacity-50"
                    >
                        <svg
                            v-if="isSavingPartner"
                            class="animate-spin h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        Simpan Partner
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
