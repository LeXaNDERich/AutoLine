document.addEventListener("DOMContentLoaded", () => {
    const brandNameEl = document.getElementById("brand-name");
    const brandLeadEl = document.getElementById("brand-lead");
    const brandModelsNameEl = document.getElementById("brand-models-name");
    const modelsGrid = document.getElementById("brand-models-grid");
    const autoGrid = document.getElementById("brand-auto-grid");
    const partsFilters = document.getElementById("brand-parts-filters");
    const partsGrid = document.getElementById("brand-parts-grid");

    const quickModal = document.getElementById("quick-modal");
    const quickModalImage = document.getElementById("quick-modal-image");
    const quickModalPartThumb = document.getElementById("quick-modal-part-thumb");
    const quickModalBadge = document.getElementById("quick-modal-badge");
    const quickModalTitle = document.getElementById("quick-modal-title");
    const quickModalPrice = document.getElementById("quick-modal-price");
    const quickModalMeta = document.getElementById("quick-modal-meta");
    const quickRequestForm = document.getElementById("quick-request-form");
    const quickServiceInput = document.getElementById("quick-service");
    const quickCommentInput = document.getElementById("quick-comment");
    const quickNameInput = document.getElementById("quick-name");
    const quickPhoneInput = document.getElementById("quick-phone");
    const quickNoteInput = document.getElementById("quick-note");
    const quickFormMessage = document.getElementById("quick-form-message");
    let loggedInUser = null;

    if (!brandNameEl || !brandLeadEl || !brandModelsNameEl || !modelsGrid || !autoGrid || !partsGrid || !partsFilters || !quickModal || !quickRequestForm) {
        return;
    }

    let activePartsModel = "";
    let brandModelsList = [];
    let catalogBrand = "";
    let catalogMultiplier = 1;
    let quickCardsBound = false;
    let lastModalPayload = null;
    let brandPartsBySlug = null;
    let brandPartsModels = [];

    const ACCENT = "#f39200";
    const SUCCESS = "#0f7a3f";
    const ERROR = "#b42318";

    function formatPhoneDisplay(phone) {
        if (!phone || !window.AutoLinePhone) return phone || "";
        return AutoLinePhone.formatRuPhone(phone);
    }

    function applyQuickAccountFields() {
        const accountApi = window.AutoLineAccount;
        if (accountApi && typeof accountApi.getUser === "function") {
            const shared = accountApi.getUser();
            if (shared && shared.loggedIn === true) {
                loggedInUser = shared;
            }
        }
        const qRow = quickNameInput && quickNameInput.closest(".quick-form-row");
        if (!loggedInUser || loggedInUser.loggedIn !== true) {
            if (qRow) qRow.style.display = "";
            return;
        }
        if (quickNameInput && loggedInUser.name) {
            quickNameInput.value = loggedInUser.name;
            quickNameInput.required = false;
        }
        if (quickPhoneInput && loggedInUser.phone) {
            quickPhoneInput.value = formatPhoneDisplay(loggedInUser.phone);
            quickPhoneInput.required = false;
        }
        if (qRow) qRow.style.display = "none";
    }

    function applyAccountFromSubmit(data) {
        if (!data || data.loggedIn !== true || !data.user) return;
        loggedInUser = {
            loggedIn: true,
            name: data.user.name,
            phone: data.user.phone,
        };
        if (window.AutoLineAccount && typeof window.AutoLineAccount.applyLoggedInUi === "function") {
            window.AutoLineAccount.applyLoggedInUi(loggedInUser);
        }
    }

    fetch("me.php", { cache: "no-store" })
        .then((r) => r.json().catch(() => null))
        .then((data) => {
            if (data && data.loggedIn === true) {
                loggedInUser = data;
                if (window.AutoLineAccount && typeof window.AutoLineAccount.applyLoggedInUi === "function") {
                    window.AutoLineAccount.applyLoggedInUi(data);
                }
            }
        })
        .catch(() => {});

    if (window.AutoLinePhone) {
        AutoLinePhone.bindAll();
    }

    const params = new URLSearchParams(window.location.search);
    const brandSlug = (params.get("brand") || "").trim().toLowerCase();

    const autoCatalogBase = [
        { key: "economy", title: "Эконом-класс", desc: "Городские и практичные автомобили с оптимальным бюджетом владения.", meta: "Срок поставки: 10-20 дней", basePrice: 1150000 },
        { key: "family", title: "Семейные кроссоверы", desc: "Просторные и безопасные модели для ежедневных поездок и путешествий.", meta: "Срок поставки: 14-25 дней", basePrice: 2350000 },
        { key: "business", title: "Бизнес-класс", desc: "Комфортные и статусные автомобили с расширенным оснащением.", meta: "Срок поставки: 20-30 дней", basePrice: 3800000 },
        { key: "premium", title: "Премиум-класс", desc: "Максимальные комплектации, полный пакет сопровождения и проверки.", meta: "Срок поставки: 25-40 дней", basePrice: 5900000 },
    ];

    const partNamesPool = [
        "Тормозные колодки передние",
        "Тормозные колодки задние",
        "Диск тормозной",
        "Воздушный фильтр",
        "Масляный фильтр",
        "Фильтр салона",
        "Свечи зажигания",
        "Амортизатор передний",
        "Амортизатор задний",
        "Стойка стабилизатора",
        "Рычаг подвески",
        "Ремень ГРМ",
        "Ремень генератора",
        "Термостат",
        "Датчик ABS",
        "Сайлентблок рычага",
    ];

    const partMetaPool = ["1–2 дня", "сегодня", "2–3 дня", "3–5 дней"];
    const partBasePrices = [480, 650, 920, 1250, 1750, 1950, 2300, 3200, 4600, 5200];

    const priceMultipliers = {
        audi: 1.35, avatr: 1.4, bmw: 1.55, byd: 1.2, changan: 1.18, chery: 1.15, ford: 1.1,
        geely: 1.15, genesis: 1.45, gmc: 1.6, honda: 1.25, hyundai: 1.15, infiniti: 1.5,
        jeep: 1.35, kia: 1.12, lada: 1, "land-rover": 1.7, lexus: 1.6, lixiang: 1.5, mazda: 1.2,
        mercedes: 1.65, mini: 1.35, mitsubishi: 1.15, nissan: 1.15, porsche: 1.9, skoda: 1.15,
        subaru: 1.25, tank: 1.28, toyota: 1.2, volkswagen: 1.2, volvo: 1.55, voyah: 1.45,
        xiaomi: 1.35, zeekr: 1.45, zhiji: 1.4,
    };

    function formatPrice(value) {
        return `${Math.round(value).toLocaleString("ru-RU")} ₽`;
    }

    function modelListForBrand(brandName) {
        const presets = {
            bmw: ["3 Series", "5 Series", "7 Series", "X1", "X3", "X5", "X6", "X7", "M3", "M5", "i4", "iX"],
            mercedes: ["A-Class", "C-Class", "E-Class", "S-Class", "GLA", "GLC", "GLE", "GLS", "AMG C 63", "AMG GT", "EQE", "EQS"],
            audi: ["A3", "A4", "A6", "A8", "Q3", "Q5", "Q7", "Q8", "S5", "RS6", "e-tron", "Q4 e-tron"],
            toyota: ["Corolla", "Camry", "RAV4", "Highlander", "Land Cruiser", "Prado", "Yaris", "C-HR", "Hilux", "Supra", "Prius", "Crown"],
            kia: ["Rio", "Ceed", "Cerato", "K5", "Sportage", "Sorento", "Seltos", "Stinger", "Carnival", "EV6", "Mohave", "Picanto"],
            hyundai: ["Solaris", "Elantra", "Sonata", "Tucson", "Santa Fe", "Palisade", "Creta", "Kona", "i30", "Staria", "IONIQ 5", "IONIQ 6"],
            volkswagen: ["Polo", "Jetta", "Passat", "Tiguan", "Touareg", "Golf", "Taos", "T-Roc", "Arteon", "ID.4", "Amarok", "Multivan"],
            lada: ["Granta", "Vesta", "Niva Legend", "Niva Travel", "Largus", "XRAY", "Vesta SW", "Vesta Cross", "Kalina", "Priora", "Samara", "Niva Sport"],
        };
        return presets[brandSlug] || [
            "City", "Sedan", "Comfort", "Business", "Crossover", "SUV", "Sport", "Premium", "Touring", "Executive", "Electric", "Performance",
        ].map((name) => `${brandName} ${name}`);
    }

    function localImage(fileName) {
        // Поддержка файлов с пробелами/кириллицей в именах.
        return `assets/images/${encodeURIComponent(fileName)}`;
    }

    function modelImageUrl(brandName, modelName, index) {
        const byExactModel = {
            "3 Series": "3 series.png",
            "5 Series": "5series.png",
            "7 Series": "7series.png",
            X1: "x1.png",
            X3: "x3.png",
            X5: "x5.png",
            X6: "x6.png",
            X7: "x7.png",
        };
        const byBrand = {
            BMW: "5series.png",
            Hyundai: "Hyundai.png",
            Kia: "kia.png",
            Toyota: "toyota corolla.png",
            Lada: "Lada Granta.jpg",
            Audi: "3series.png",
            Mercedes: "7series.png",
            Volkswagen: "toyota corolla.png",
        };

        if (byExactModel[modelName]) {
            return localImage(byExactModel[modelName]);
        }
        if (byBrand[brandName]) {
            return localImage(byBrand[brandName]);
        }
        const keyword = `${brandName} ${modelName} car`;
        return `https://source.unsplash.com/840x520/?${encodeURIComponent(keyword)}&sig=${index + 1}`;
    }

    function catalogClassImage(classTitle) {
        const byClass = {
            "Эконом-класс": "econom.png",
            "Семейные кроссоверы": "semeinui.jpeg",
            "Бизнес-класс": "buisness sedan.jpg",
            "Премиум-класс": "5series.png",
        };
        return byClass[classTitle] ? localImage(byClass[classTitle]) : localImage("car-placeholder.svg");
    }

    function partsForModel(modelName, modelIndex, multiplier) {
        const offset = modelIndex * 3;
        return Array.from({ length: 6 }, (_, i) => {
            const title = partNamesPool[(offset + i) % partNamesPool.length];
            const base = partBasePrices[(offset + i) % partBasePrices.length];
            return {
                title,
                meta: partMetaPool[(offset + i) % partMetaPool.length],
                price: formatPrice(base * multiplier),
            };
        });
    }

    function buildQuickComment() {
        const p = lastModalPayload;
        if (!p) return (quickNoteInput && quickNoteInput.value.trim()) || "";
        const lines = [`Марка: ${p.brand}`, `Позиция: ${p.title}`];
        if (p.meta) lines.push(p.meta);
        if (p.price) lines.push(`Цена: от ${p.price}`);
        const note = quickNoteInput && quickNoteInput.value.trim();
        if (note) lines.push(`Уточнение: ${note}`);
        return lines.join("\n");
    }

    function openQuickModal(payload) {
        lastModalPayload = payload;
        const isParts = payload.service === "parts";
        if (quickModalImage) {
            quickModalImage.hidden = isParts;
            if (!isParts) {
                quickModalImage.src = payload.image || "assets/images/car-placeholder.svg";
                quickModalImage.alt = payload.title || "Карточка";
                quickModalImage.onerror = () => {
                    quickModalImage.onerror = null;
                    quickModalImage.src = "assets/images/car-placeholder.svg";
                };
            }
        }
        if (quickModalPartThumb) quickModalPartThumb.hidden = !isParts;
        if (quickModalBadge) quickModalBadge.textContent = payload.brand || "";
        if (quickModalTitle) quickModalTitle.textContent = payload.title || "Заявка";
        if (quickModalPrice) quickModalPrice.textContent = payload.price ? `от ${payload.price}` : "";
        if (quickModalMeta) quickModalMeta.textContent = payload.meta || "";
        if (quickServiceInput) quickServiceInput.value = payload.service || "order";
        if (quickNoteInput) quickNoteInput.value = "";
        if (quickCommentInput) quickCommentInput.value = "";
        if (quickFormMessage) quickFormMessage.textContent = "";
        if (!loggedInUser || loggedInUser.loggedIn !== true) {
            if (quickNameInput) quickNameInput.value = "";
            if (quickPhoneInput) quickPhoneInput.value = "";
            const qEmail = document.getElementById("quick-email");
            if (qEmail) qEmail.value = "";
        }
        if (window.AutoLinePhone) {
            [quickNameInput, quickPhoneInput, document.getElementById("quick-email")].forEach((el) => {
                if (el) AutoLinePhone.clearFieldError(el);
            });
        }
        applyQuickAccountFields();

        quickModal.hidden = false;
        document.body.style.overflow = "hidden";
        const focusTarget = loggedInUser && loggedInUser.loggedIn === true ? quickNoteInput : quickNameInput;
        if (focusTarget) {
            requestAnimationFrame(() => focusTarget.focus());
        }
    }

    function closeQuickModal() {
        quickModal.hidden = true;
        document.body.style.overflow = "";
    }

    quickModal.addEventListener("click", (event) => {
        const closer = event.target.closest("[data-close-modal='true']");
        if (closer) {
            closeQuickModal();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && !quickModal.hidden) {
            closeQuickModal();
        }
    });

    let quickSubmitting = false;
    quickRequestForm.addEventListener("submit", (event) => {
        event.preventDefault();
        if (quickSubmitting) return;
        const validate = window.AutoLineFormValidate && window.AutoLineFormValidate.validateQuickFields;
        if (typeof validate === "function" && !validate()) {
            if (quickFormMessage) {
                quickFormMessage.textContent = "Проверьте имя, телефон и email.";
                quickFormMessage.style.color = ERROR;
            }
            return;
        }
        quickSubmitting = true;
        if (quickCommentInput) quickCommentInput.value = buildQuickComment();
        const body = new URLSearchParams(new FormData(quickRequestForm));

        if (quickFormMessage) {
            quickFormMessage.textContent = "Отправляем заявку...";
            quickFormMessage.style.color = ACCENT;
        }

        fetch(quickRequestForm.action, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body,
        })
            .then(async (res) => {
                const data = await res.json().catch(() => null);
                if (!res.ok || !data || data.ok !== true) {
                    throw new Error((data && data.error) || "Ошибка отправки. Попробуйте еще раз.");
                }
                return data;
            })
            .then((data) => {
                applyAccountFromSubmit(data);
                if (quickFormMessage) {
                    const qStatus = data.status_label ? ` Статус: ${data.status_label}.` : "";
                    const regHint = data.autoRegistered ? " Аккаунт создан." : "";
                    quickFormMessage.textContent = `Заявка принята. Номер: ${data.id}.${qStatus}${regHint}`;
                    quickFormMessage.style.color = SUCCESS;
                }
                quickRequestForm.reset();
                if (quickServiceInput && lastModalPayload) {
                    quickServiceInput.value = lastModalPayload.service || "order";
                }
                applyQuickAccountFields();
                setTimeout(closeQuickModal, 900);
            })
            .catch((err) => {
                if (quickFormMessage) {
                    quickFormMessage.textContent = err.message || "Ошибка отправки. Попробуйте еще раз.";
                    quickFormMessage.style.color = ERROR;
                }
            })
            .finally(() => {
                quickSubmitting = false;
            });
    });

    function renderBrandModels(brandName, multiplier) {
        const models = modelListForBrand(brandName);
        brandModelsList = models;
        brandModelsNameEl.textContent = brandName;

        modelsGrid.innerHTML = models
            .map((modelName, index) => {
                const base = 1250000 + index * 230000;
                const price = formatPrice(base * multiplier);
                const image = modelImageUrl(brandName, modelName, index);
                return `
                    <article class="car-card">
                        <a class="car-card-main js-quick-card" href="#" data-service="order" data-brand="${brandName}" data-title="${modelName}" data-price="${price}" data-meta="Категория: модель под заказ" data-image="${image}">
                            <img class="car-card-image" src="${image}" alt="${brandName} ${modelName}" loading="lazy" onerror="this.onerror=null;this.src='assets/images/car-placeholder.svg';">
                            <div class="car-card-content">
                                <h3>${modelName}</h3>
                                <p class="car-card-meta">${brandName}</p>
                                <div class="car-card-price">от ${price}</div>
                            </div>
                        </a>
                        <a class="car-card-parts-link" href="#brand-parts-catalog" data-model="${modelName}">Запчасти для ${modelName}</a>
                    </article>
                `;
            })
            .join("");
    }

    function renderPartsFilters(models, brandName, multiplier) {
        partsFilters.innerHTML = models
            .map((modelName) => {
                const active = modelName === activePartsModel ? " is-active" : "";
                return `<button type="button" class="parts-filter-btn${active}" data-model="${modelName}" role="tab" aria-selected="${modelName === activePartsModel}">${modelName}</button>`;
            })
            .join("");

        partsFilters.querySelectorAll(".parts-filter-btn").forEach((btn) => {
            btn.addEventListener("click", () => {
                setActivePartsModel(btn.dataset.model || "");
            });
        });
    }

    function formatPartPriceLabel(price) {
        const raw = String(price || "").trim();
        if (!raw) return "";
        return /^от\s/i.test(raw) ? raw : `от ${raw}`;
    }

    function slugifyPartKey(value) {
        return String(value || "")
            .trim()
            .toLowerCase()
            .replace(/ё/g, "е")
            .replace(/[^a-z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "");
    }

    function normalizeModelLabel(value) {
        return String(value || "")
            .trim()
            .toLowerCase()
            .replace(/\s+/g, " ");
    }

    function stripBrandFromModelLabel(modelName, brandName) {
        let base = String(modelName || "").trim();
        const brand = String(brandName || "").trim();
        if (!brand) return base;
        const brandLower = brand.toLowerCase();
        const baseLower = base.toLowerCase();
        if (baseLower.startsWith(`${brandLower} `)) {
            return base.slice(brand.length).trim();
        }
        return base;
    }

    function guessModelSlugFromPage(modelName) {
        const base = stripBrandFromModelLabel(modelName, catalogBrand);
        return slugifyPartKey(`${brandSlug}-${base}`);
    }

    function resolveServerModelRecord(pageModelName) {
        if (!Array.isArray(brandPartsModels) || brandPartsModels.length === 0) {
            return null;
        }
        const exact = brandPartsModels.find((m) => m.name === pageModelName);
        if (exact && exact.slug) return exact;

        const guessSlug = guessModelSlugFromPage(pageModelName);
        const bySlug = brandPartsModels.find((m) => m.slug === guessSlug);
        if (bySlug) return bySlug;
        if (brandPartsBySlug && Array.isArray(brandPartsBySlug[guessSlug]) && brandPartsBySlug[guessSlug].length > 0) {
            return { slug: guessSlug, name: pageModelName };
        }

        const pageNorm = normalizeModelLabel(pageModelName);
        const strippedPage = normalizeModelLabel(stripBrandFromModelLabel(pageModelName, catalogBrand));
        for (const m of brandPartsModels) {
            const modelNorm = normalizeModelLabel(m.name);
            const strippedModel = normalizeModelLabel(stripBrandFromModelLabel(m.name, catalogBrand));
            if (!modelNorm) continue;
            if (modelNorm === pageNorm || strippedModel === strippedPage) return m;
            if (strippedPage && strippedModel === strippedPage) return m;
            if (strippedPage && (strippedPage.includes(strippedModel) || strippedModel.includes(strippedPage))) return m;
        }
        return null;
    }

    function brandHasServerParts() {
        if (!brandPartsBySlug || typeof brandPartsBySlug !== "object") return false;
        return Object.keys(brandPartsBySlug).some((slug) => {
            const list = brandPartsBySlug[slug];
            return Array.isArray(list) && list.length > 0;
        });
    }

    function mapServerParts(list) {
        return (list || []).map((p) => ({
            title: p.title || "Запчасть",
            meta: p.meta || "",
            price: formatPartPriceLabel(p.price),
            desc: p.desc || "",
        }));
    }

    function partsFromServerForModel(modelName) {
        if (!brandPartsBySlug) return null;
        const model = resolveServerModelRecord(modelName);
        if (!model || !model.slug) return null;
        const list = brandPartsBySlug[model.slug];
        if (!Array.isArray(list) || list.length === 0) return null;
        return mapServerParts(list);
    }

    function buildPartsFilterModels() {
        const entries = [];
        const seen = new Set();
        const push = (name, priority) => {
            const key = normalizeModelLabel(name);
            if (!name || seen.has(key)) return;
            seen.add(key);
            entries.push({ name, priority });
        };

        if (Array.isArray(brandPartsModels)) {
            brandPartsModels.forEach((m) => {
                const slug = m.slug || "";
                const count = brandPartsBySlug && brandPartsBySlug[slug] ? brandPartsBySlug[slug].length : 0;
                if (count > 0) push(m.name || slug, 0);
            });
        }

        brandModelsList.forEach((name) => {
            const items = partsFromServerForModel(name);
            push(name, items && items.length > 0 ? 0 : 1);
        });

        entries.sort((a, b) => a.priority - b.priority || a.name.localeCompare(b.name, "ru"));
        return entries.map((x) => x.name);
    }

    function findFilterModelName(param) {
        const filters = buildPartsFilterModels();
        if (!param) return "";
        const paramNorm = normalizeModelLabel(param);
        const paramStripped = normalizeModelLabel(stripBrandFromModelLabel(param, catalogBrand));
        return (
            filters.find((n) => normalizeModelLabel(n) === paramNorm) ||
            filters.find((n) => normalizeModelLabel(stripBrandFromModelLabel(n, catalogBrand)) === paramStripped) ||
            filters.find((n) => {
                const rec = resolveServerModelRecord(n);
                if (!rec) return false;
                const rn = normalizeModelLabel(rec.name);
                return rn === paramNorm || rn === paramStripped;
            }) ||
            ""
        );
    }

    async function loadBrandPartsCatalog(brandSlug) {
        try {
            const res = await fetch(`parts-data.php?brand=${encodeURIComponent(brandSlug)}`, { cache: "no-store" });
            const data = await res.json();
            if (data && data.ok === true && data.parts) {
                brandPartsBySlug = data.parts;
                brandPartsModels = Array.isArray(data.models) ? data.models : [];
            }
        } catch (_) {
            brandPartsBySlug = null;
            brandPartsModels = [];
        }
    }

    function renderPartsGrid(modelName, modelIndex, brandName, multiplier) {
        const serverItems = partsFromServerForModel(modelName);
        const items =
            serverItems && serverItems.length > 0
                ? serverItems
                : brandHasServerParts()
                  ? []
                  : partsForModel(modelName, modelIndex, multiplier);
        if (items.length === 0) {
            partsGrid.innerHTML = `<p class="brands-error">Для этой модели запчасти пока не добавлены. Оставьте заявку — подберём под заказ.</p>`;
            return;
        }
        partsGrid.innerHTML = items
            .map((item) => {
                const priceLabel = formatPartPriceLabel(item.price);
                return `
                    <a class="part-card-compact js-quick-card" href="#" data-service="parts" data-brand="${brandName}" data-title="${item.title} (${modelName})" data-price="${priceLabel}" data-meta="${brandName} ${modelName} · ${item.meta}">
                        <div class="part-card-thumb">Запчасть</div>
                        <h4>${item.title}</h4>
                        <p class="part-card-meta">${modelName}</p>
                        <p class="part-card-price">${priceLabel}</p>
                    </a>
                `;
            })
            .join("");
    }

    function setActivePartsModel(modelName) {
        if (!modelName || !catalogBrand) return;
        activePartsModel = modelName;
        const modelIndex = brandModelsList.indexOf(modelName);
        partsFilters.querySelectorAll(".parts-filter-btn").forEach((btn) => {
            const isActive = btn.dataset.model === modelName;
            btn.classList.toggle("is-active", isActive);
            btn.setAttribute("aria-selected", isActive ? "true" : "false");
        });
        renderPartsGrid(modelName, modelIndex >= 0 ? modelIndex : 0, catalogBrand, catalogMultiplier);
    }

    function goToPartsForModel(modelName) {
        if (!modelName) return;
        setActivePartsModel(modelName);
        const section = document.getElementById("brand-parts-catalog");
        if (section) {
            section.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    }

    function initPartsCatalog(brandName, multiplier) {
        catalogBrand = brandName;
        catalogMultiplier = multiplier;
        const filterModels = buildPartsFilterModels();
        if (filterModels.length === 0) {
            partsFilters.innerHTML = "";
            partsGrid.innerHTML = `<p class="brands-error">Нет моделей для подбора запчастей.</p>`;
            return;
        }
        const firstWithParts = filterModels.find((name) => {
            const items = partsFromServerForModel(name);
            return items && items.length > 0;
        });
        activePartsModel = firstWithParts || filterModels[0];
        renderPartsFilters(filterModels, brandName, multiplier);
        setActivePartsModel(activePartsModel);
    }

    function renderAutoCatalog(brandName, multiplier) {
        autoGrid.innerHTML = autoCatalogBase
            .map((item, index) => {
                const price = formatPrice(item.basePrice * multiplier);
                const image = catalogClassImage(item.title) || modelImageUrl(brandName, item.title, index + 100);
                const disabledClick = item.key === "family" || item.key === "business";
                const tag = disabledClick ? "article" : "a";
                const hrefAttr = disabledClick ? "" : ` href="#"`;
                const quickClass = disabledClick ? "" : " js-quick-card";
                return `
                    <${tag} class="catalog-card catalog-card-link${quickClass}"${hrefAttr} data-service="order" data-brand="${brandName}" data-title="${item.title}" data-price="${price}" data-meta="${item.meta}" data-image="${image}">
                        <img class="part-card-image" src="${image}" alt="${item.title} ${brandName}" loading="lazy" onerror="this.onerror=null;this.src='assets/images/car-placeholder.svg';">
                        <h4>${item.title} (${brandName})</h4>
                        <p>${item.desc}</p>
                        <div class="meta">${item.meta}</div>
                        <div class="price">от ${price}</div>
                    </${tag}>
                `;
            })
            .join("");
    }

    modelsGrid.addEventListener("click", (event) => {
        const partsLink = event.target.closest(".car-card-parts-link");
        if (!partsLink) return;
        event.preventDefault();
        event.stopPropagation();
        goToPartsForModel(partsLink.dataset.model || "");
    });

    function bindQuickCards() {
        if (quickCardsBound) return;
        quickCardsBound = true;
        document.addEventListener("click", (event) => {
            const card = event.target.closest(".js-quick-card");
            if (!card) return;
            event.preventDefault();
            openQuickModal({
                service: card.dataset.service || "order",
                brand: card.dataset.brand || "",
                title: card.dataset.title || "Позиция",
                price: card.dataset.price || "",
                meta: card.dataset.meta || "",
                image: card.dataset.image || "",
            });
        });
    }

    fetch("assets/data/brands.json", { cache: "no-store" })
        .then((res) => {
            if (!res.ok) throw new Error("Не удалось загрузить данные марки");
            return res.json();
        })
        .then((data) => {
            const brands = Array.isArray(data.brands) ? data.brands : [];
            const currentBrand = brands.find((b) => b.slug.toLowerCase() === brandSlug);

            if (!currentBrand) {
                brandNameEl.textContent = "не выбрана";
                brandLeadEl.textContent = "Марка не найдена. Вернитесь в раздел «Все марки» и выберите бренд из списка.";
                brandModelsNameEl.textContent = "—";
                modelsGrid.innerHTML = `<p class="brands-error">Каталог моделей недоступен: выберите корректную марку.</p>`;
                autoGrid.innerHTML = `<p class="brands-error">Каталог авто недоступен: выберите корректную марку.</p>`;
                partsGrid.innerHTML = `<p class="brands-error">Каталог запчастей недоступен: выберите корректную марку.</p>`;
                return;
            }

            const brandName = currentBrand.name;
            const multiplier = priceMultipliers[currentBrand.slug] || 1.2;
            document.title = `${brandName} | AutoLine`;
            brandNameEl.textContent = brandName;
            brandLeadEl.textContent = "Клик по модели — заявка на авто. Кнопка «Запчасти» — каталог деталей для выбранной машины.";

            loadBrandPartsCatalog(brandSlug).finally(() => {
                renderBrandModels(brandName, multiplier);
                renderAutoCatalog(brandName, multiplier);
                bindQuickCards();
                initPartsCatalog(brandName, multiplier);
            });

            const modelParam = (params.get("model") || "").trim();
            const modelFromUrl = findFilterModelName(modelParam);
            if (modelFromUrl) {
                requestAnimationFrame(() => goToPartsForModel(modelFromUrl));
            } else if (window.location.hash === "#brand-parts-catalog" && activePartsModel) {
                requestAnimationFrame(() => {
                    document.getElementById("brand-parts-catalog")?.scrollIntoView({ behavior: "smooth", block: "start" });
                });
            }
        })
        .catch(() => {
            brandNameEl.textContent = "ошибка";
            brandLeadEl.textContent = "Не удалось загрузить данные марки. Обновите страницу.";
            brandModelsNameEl.textContent = "—";
            modelsGrid.innerHTML = `<p class="brands-error">Каталог моделей недоступен.</p>`;
            autoGrid.innerHTML = `<p class="brands-error">Каталог авто недоступен.</p>`;
            partsGrid.innerHTML = `<p class="brands-error">Каталог запчастей недоступен.</p>`;
        });
});
