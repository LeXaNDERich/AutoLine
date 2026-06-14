document.addEventListener("DOMContentLoaded", () => {
    const yearEl = document.getElementById("year");
    const menuToggle = document.getElementById("menu-toggle");
    const nav = document.getElementById("main-nav");
    const navOverlay = document.getElementById("nav-overlay");
    const backToTop = document.getElementById("back-to-top");
    const form = document.getElementById("request-form");
    const message = document.getElementById("form-message");
    const nameInput = document.getElementById("name");
    const phoneInput = document.getElementById("phone");
    const adminLink = document.getElementById("admin-link");
    const nameField = document.getElementById("name-field");
    const phoneField = document.getElementById("phone-field");
    let loggedInUser = null;

    function formatPhoneDisplay(phone) {
        if (!phone || !window.AutoLinePhone) return phone || "";
        return AutoLinePhone.formatRuPhone(phone);
    }

    function applyLoggedInUi(user) {
        if (!user || user.loggedIn !== true) return;
        loggedInUser = user;
        if (adminLink && user.isAdmin === true) {
            adminLink.hidden = false;
        }
        const displayPhone = formatPhoneDisplay(user.phone);
        if (nameInput && user.name) {
            nameInput.value = user.name;
            nameInput.required = false;
        }
        if (phoneInput && user.phone) {
            phoneInput.value = displayPhone;
            phoneInput.required = false;
        }
        if (nameField) nameField.style.display = "none";
        if (phoneField) phoneField.style.display = "none";
        const qName = document.getElementById("quick-name");
        const qPhone = document.getElementById("quick-phone");
        const qRow = qName && qName.closest(".quick-form-row");
        if (qName) {
            qName.required = false;
            if (user.name) qName.value = user.name;
        }
        if (qPhone) {
            qPhone.required = false;
            if (user.phone) qPhone.value = displayPhone;
        }
        if (qRow) qRow.style.display = "none";
    }

    function applyAccountFromSubmit(data) {
        if (!data || data.loggedIn !== true || !data.user) return;
        applyLoggedInUi({
            loggedIn: true,
            name: data.user.name,
            phone: data.user.phone,
            isAdmin: loggedInUser && loggedInUser.isAdmin === true,
        });
    }

    if (window.AutoLinePhone) {
        AutoLinePhone.bindAll();
    }

    function isGuestForm() {
        return !loggedInUser || loggedInUser.loggedIn !== true;
    }

    function validateContactFields() {
        const api = window.AutoLinePhone;
        if (!api || !isGuestForm()) {
            return true;
        }
        let ok = true;
        const nameEl = document.getElementById("name");
        const phoneEl = document.getElementById("phone");
        const emailEl = document.getElementById("email");
        if (nameEl && !nameEl.value.trim()) {
            api.setFieldError(nameEl, "Введите имя");
            ok = false;
        } else if (nameEl) {
            api.clearFieldError(nameEl);
        }
        if (!api.validatePhoneField(phoneEl)) {
            ok = false;
        }
        if (!api.validateEmailField(emailEl, true)) {
            ok = false;
        }
        return ok;
    }

    function validateQuickFields() {
        const api = window.AutoLinePhone;
        if (!api || !isGuestForm()) {
            return true;
        }
        let ok = true;
        const qName = document.getElementById("quick-name");
        const qPhone = document.getElementById("quick-phone");
        const qEmail = document.getElementById("quick-email");
        if (qName && !qName.value.trim()) {
            api.setFieldError(qName, "Введите имя");
            ok = false;
        } else if (qName) {
            api.clearFieldError(qName);
        }
        if (!api.validatePhoneField(qPhone)) {
            ok = false;
        }
        if (!api.validateEmailField(qEmail, true)) {
            ok = false;
        }
        return ok;
    }

    document.querySelectorAll('input[type="email"]').forEach((input) => {
        input.addEventListener("blur", () => {
            if (!window.AutoLinePhone) {
                return;
            }
            const required = isGuestForm() && input.offsetParent !== null;
            AutoLinePhone.validateEmailField(input, required);
        });
    });

    const ACCENT = "#f39200";
    const SUCCESS = "#0f7a3f";
    const ERROR = "#b42318";

    if (yearEl) {
        yearEl.textContent = String(new Date().getFullYear());
    }

    const brandFromUrl = new URLSearchParams(window.location.search).get("brand");
    const commentInput = document.getElementById("comment");
    const serviceSelect = document.getElementById("service");
    const contactSection = document.getElementById("contact");
    if (brandFromUrl && commentInput) {
        const prefix = `Интересует марка: ${brandFromUrl}. `;
        if (!commentInput.value.startsWith(prefix)) {
            commentInput.value = prefix + commentInput.value;
        }
        if (serviceSelect && !serviceSelect.value) {
            serviceSelect.value = "parts";
        }
        if (window.location.hash !== "#contact") {
            window.location.hash = "contact";
        }
    }

    const brandTrack = document.querySelector(".brand-marquee-track");
    const brandOriginal = document.getElementById("brand-marquee-original");

    function bindBrandSlideImages(root) {
        if (!root) return;
        root.querySelectorAll(".brand-slide img").forEach((img) => {
            if (img.dataset.bound === "1") return;
            img.dataset.bound = "1";
            img.addEventListener("error", () => {
                if (!img.dataset.triedPng) {
                    img.dataset.triedPng = "1";
                    img.src = img.src.replace(/\.svg$/i, ".png");
                    return;
                }
                img.hidden = true;
                const fallback = img.parentElement && img.parentElement.querySelector(".brand-slide-fallback");
                if (fallback) fallback.hidden = false;
            });
        });
    }

    function waitBrandImages(root) {
        const imgs = root ? [...root.querySelectorAll("img")] : [];
        if (imgs.length === 0) return Promise.resolve();
        return Promise.all(
            imgs.map(
                (img) =>
                    new Promise((resolve) => {
                        if (img.complete) {
                            resolve();
                            return;
                        }
                        img.addEventListener("load", resolve, { once: true });
                        img.addEventListener("error", resolve, { once: true });
                    })
            )
        );
    }

    function updateMarqueeShift() {
        if (!brandTrack || !brandOriginal) return;
        const clone = brandTrack.querySelector('.brand-marquee-set[aria-hidden="true"]');
        if (!clone) return;
        const distance = clone.offsetLeft - brandOriginal.offsetLeft;
        if (distance > 0) {
            brandTrack.style.setProperty("--marquee-shift", `${distance}px`);
        }
    }

    function initBrandMarquee() {
        if (!brandTrack || !brandOriginal) return;

        brandTrack.classList.remove("is-ready");
        brandTrack.style.removeProperty("--marquee-shift");
        brandTrack.querySelectorAll('.brand-marquee-set[aria-hidden="true"]').forEach((node) => node.remove());

        bindBrandSlideImages(brandOriginal);

        waitBrandImages(brandOriginal).then(() => {
            const brandClone = brandOriginal.cloneNode(true);
            brandClone.removeAttribute("id");
            brandClone.setAttribute("aria-hidden", "true");
            brandClone.querySelectorAll(".brand-slide").forEach((slide) => {
                slide.setAttribute("tabindex", "-1");
            });
            brandTrack.appendChild(brandClone);
            bindBrandSlideImages(brandClone);

            requestAnimationFrame(() => {
                updateMarqueeShift();
                brandTrack.classList.add("is-ready");
            });
        });
    }

    if (brandTrack && brandOriginal) {
        initBrandMarquee();
        window.addEventListener("resize", () => {
            window.requestAnimationFrame(updateMarqueeShift);
        });
        if (typeof ResizeObserver !== "undefined") {
            const marqueeObserver = new ResizeObserver(() => updateMarqueeShift());
            marqueeObserver.observe(brandOriginal);
        }
    }

    if (brandTrack) {
        brandTrack.addEventListener("click", (event) => {
            const slide = event.target.closest(".brand-slide");
            if (!slide) return;
            event.preventDefault();
            event.stopPropagation();

            let brand = (slide.dataset.brand || "").trim().toLowerCase();
            if (!brand) {
                const href = slide.getAttribute("href") || "";
                const m = href.match(/[?&]brand=([^&]+)/i);
                if (m) brand = decodeURIComponent(m[1]).toLowerCase();
            }
            if (!brand) return;
            window.location.assign(`brand.php?brand=${encodeURIComponent(brand)}`);
        });
    }

    function setNavOpen(open) {
        if (!nav || !menuToggle) return;
        nav.classList.toggle("open", open);
        menuToggle.setAttribute("aria-expanded", open ? "true" : "false");
        if (navOverlay) {
            navOverlay.hidden = !open;
        }
        document.body.style.overflow = open ? "hidden" : "";
    }

    function closeNav() {
        setNavOpen(false);
    }

    // Если пользователь залогинен — подставляем его данные в форму заявки.
    // Делаем поля readOnly, чтобы они не уходили в POST пустыми.
    if (nameInput && phoneInput) {
        fetch("me.php", { cache: "no-store" })
            .then((r) => r.json().catch(() => null))
            .then((data) => {
                if (data && data.loggedIn === true) {
                    applyLoggedInUi(data);
                }
            })
            .catch(() => {});
    }

    window.AutoLineAccount = {
        getUser: () => loggedInUser,
        applyLoggedInUi,
        formatPhoneDisplay,
    };

    window.AutoLineFormValidate = {
        validateContactFields,
        validateQuickFields,
    };

    if (menuToggle && nav) {
        menuToggle.addEventListener("click", () => {
            setNavOpen(!nav.classList.contains("open"));
        });

        nav.querySelectorAll("a").forEach((link) => {
            link.addEventListener("click", closeNav);
        });
    }

    if (navOverlay) {
        navOverlay.addEventListener("click", closeNav);
    }

    if (backToTop) {
        backToTop.addEventListener("click", () => {
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    }

    let contactSubmitting = false;
    if (form) {
        form.addEventListener("submit", (event) => {
        event.preventDefault();
        if (contactSubmitting) return;
        contactSubmitting = true;
        const name = document.getElementById("name").value.trim();
        const phone = document.getElementById("phone").value.trim();
        const service = document.getElementById("service").value;
        const comment = document.getElementById("comment").value.trim();

        if (!service) {
            message.textContent = "Выберите услугу.";
            message.style.color = ERROR;
            contactSubmitting = false;
            return;
        }
        if (!validateContactFields()) {
            message.textContent = "Проверьте имя, телефон и email.";
            message.style.color = ERROR;
            contactSubmitting = false;
            return;
        }

        message.textContent = "Отправляем заявку...";
        message.style.color = ACCENT;

        const emailEl = document.getElementById("email");
        const email = emailEl ? emailEl.value.trim() : "";
        const body = new URLSearchParams({ name, phone, email, service, comment });

        fetch(form.action, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
            },
            body,
        })
            .then(async (res) => {
                const data = await res.json().catch(() => null);
                if (!res.ok || !data || data.ok !== true) {
                    const err = data && data.error ? data.error : "Ошибка отправки. Попробуйте еще раз.";
                    throw new Error(err);
                }
                return data;
            })
            .then((data) => {
                applyAccountFromSubmit(data);
                const statusHint = data.status_label ? ` Статус: ${data.status_label}.` : " Статус: Принята.";
                const regHint = data.autoRegistered ? " Аккаунт создан, вы вошли автоматически." : "";
                message.textContent = `Заявка принята. Номер: ${data.id}.${statusHint}${regHint} Отслеживайте выполнение в личном кабинете.`;
                message.style.color = SUCCESS;
                form.reset();
                applyLoggedInUi(loggedInUser);
            })
            .catch((err) => {
                message.textContent = err.message || "Ошибка отправки. Попробуйте еще раз.";
                message.style.color = ERROR;
            })
            .finally(() => {
                contactSubmitting = false;
            });
        });
    }

    // ===== Каталог автозапчастей (демо: рандомные карточки) =====
    const partsRandomizeBtn = document.getElementById("parts-randomize");
    const partsGrids = {
        "lada-granta": document.getElementById("parts-grid-lada-granta"),
        "hyundai-solaris": document.getElementById("parts-grid-hyundai-solaris"),
        "kia-rio": document.getElementById("parts-grid-kia-rio"),
        "toyota-corolla": document.getElementById("parts-grid-toyota-corolla"),
    };

    const DEFAULT_PARTS_BY_MODEL = {
        "lada-granta": [
            { title: "Тормозные колодки передние", desc: "Полуметалл, проверка износа и суппортов.", meta: "Срок: 1-2 дня", price: "от 1 950 ₽", image: "https://source.unsplash.com/460x260/?brake,pads,car" },
            { title: "Диск тормозной передний", desc: "Антикор, подбор по ВИН и модификации.", meta: "Срок: 1-3 дня", price: "от 2 600 ₽", image: "https://source.unsplash.com/460x260/?brake,disc,auto" },
            { title: "Амортизатор передний", desc: "Установка с регулировкой и проверкой развал-схождения.", meta: "Срок: 1-2 дня", price: "от 5 200 ₽", image: "https://source.unsplash.com/460x260/?shock,absorber,car" },
            { title: "Стойка стабилизатора", desc: "Замена комплектом с осмотром втулок и рычагов.", meta: "Срок: до 3 дней", price: "от 1 300 ₽", image: "https://source.unsplash.com/460x260/?suspension,auto" },
            { title: "Воздушный фильтр", desc: "Чистая тяга и стабильная работа двигателя.", meta: "Срок: 1 день", price: "от 650 ₽", image: "https://source.unsplash.com/460x260/?air,filter,car" },
            { title: "Масляный фильтр", desc: "Подбор по мотору, замена вместе с маслом.", meta: "Срок: сегодня", price: "от 480 ₽", image: "https://source.unsplash.com/460x260/?oil,filter,engine" },
            { title: "Свечи зажигания", desc: "Улучшенная искра, стабильный холодный пуск.", meta: "Срок: 1-2 дня", price: "от 1 150 ₽", image: "https://source.unsplash.com/460x260/?spark,plug,car" },
            { title: "Сальник привода", desc: "Ремкомплект с проверкой шруса и пыльника.", meta: "Срок: 2-4 дня", price: "от 2 900 ₽", image: "https://source.unsplash.com/460x260/?car,mechanic,part" },
        ],
        "hyundai-solaris": [
            { title: "Тормозные колодки задние", desc: "Комплект колодок с осмотром направляющих.", meta: "Срок: 1-2 дня", price: "от 1 750 ₽", image: "https://source.unsplash.com/460x260/?brakes,car,parts" },
            { title: "Ремкомплект суппорта", desc: "Пыльники, направляющие, смазка — по регламенту.", meta: "Срок: 2-3 дня", price: "от 2 950 ₽", image: "https://source.unsplash.com/460x260/?caliper,brake,auto" },
            { title: "Фильтр салона угольный", desc: "Комфорт в салоне и защита системы вентиляции.", meta: "Срок: сегодня", price: "от 920 ₽", image: "https://source.unsplash.com/460x260/?cabin,filter,car" },
            { title: "Топливный фильтр", desc: "Чистое топливо и стабильная работа двигателя.", meta: "Срок: 1-3 дня", price: "от 1 600 ₽", image: "https://source.unsplash.com/460x260/?fuel,filter,engine" },
            { title: "Рычаг передней подвески", desc: "Замена с контролем геометрии и люфтов.", meta: "Срок: 3-5 дней", price: "от 4 950 ₽", image: "https://source.unsplash.com/460x260/?car,suspension,service" },
            { title: "Опора стойки", desc: "Поддержка стойки, устранение стуков на неровностях.", meta: "Срок: 1-2 дня", price: "от 1 350 ₽", image: "https://source.unsplash.com/460x260/?car,repair,garage" },
            { title: "Комплект ГРМ (ремень)", desc: "Подбор по мотору и установочным параметрам.", meta: "Срок: 2-4 дня", price: "от 7 200 ₽", image: "https://source.unsplash.com/460x260/?timing,belt,engine" },
            { title: "Термостат", desc: "Стабильная температура двигателя и корректная работа печки.", meta: "Срок: 1-3 дня", price: "от 2 300 ₽", image: "https://source.unsplash.com/460x260/?engine,cooling,car" },
        ],
        "kia-rio": [
            { title: "Тормозные колодки передние", desc: "Подбор по комплектации, установка с проверкой тормозного контура.", meta: "Срок: 1-2 дня", price: "от 2 050 ₽", image: "https://source.unsplash.com/460x260/?brake,pad,automotive" },
            { title: "Датчик ABS", desc: "Считывание сигналов, устранение ошибок по ABS.", meta: "Срок: до 3 дней", price: "от 3 200 ₽", image: "https://source.unsplash.com/460x260/?abs,sensor,car" },
            { title: "Амортизатор задний", desc: "Комфорт на трассе и предсказуемая работа подвески.", meta: "Срок: 2-4 дня", price: "от 4 600 ₽", image: "https://source.unsplash.com/460x260/?shock,car,repair" },
            { title: "Сайлентблок рычага", desc: "Устранение вибраций и люфтов на малой скорости.", meta: "Срок: 1-3 дня", price: "от 1 480 ₽", image: "https://source.unsplash.com/460x260/?car,parts,metal" },
            { title: "Масло + фильтр", desc: "Готовый набор для ТО по вашему регламенту.", meta: "Срок: сегодня", price: "от 6 800 ₽", image: "https://source.unsplash.com/460x260/?motor,oil,car" },
            { title: "Свечи зажигания", desc: "Чистая работа цилиндров и ровный ХХ.", meta: "Срок: 1-2 дня", price: "от 1 250 ₽", image: "https://source.unsplash.com/460x260/?spark,plug,engine" },
            { title: "Ремень генератора", desc: "Без скрипа и с корректной зарядкой АКБ.", meta: "Срок: 1-3 дня", price: "от 1 750 ₽", image: "https://source.unsplash.com/460x260/?engine,belt,car" },
            { title: "Пыльник ШРУС", desc: "Защита привода, замена пыльника комплектом.", meta: "Срок: 2-4 дня", price: "от 1 900 ₽", image: "https://source.unsplash.com/460x260/?cv,joint,car" },
        ],
        "toyota-corolla": [
            { title: "Фильтр масла", desc: "Подбор по мотору, совместим с плановым ТО.", meta: "Срок: 1 день", price: "от 520 ₽", image: "https://source.unsplash.com/460x260/?oil,filter,automotive" },
            { title: "Воздушный фильтр", desc: "Чистый воздух для стабильной тяги.", meta: "Срок: сегодня", price: "от 710 ₽", image: "https://source.unsplash.com/460x260/?air,filter,automotive" },
            { title: "Свечи зажигания", desc: "Стабильный запуск и ровная динамика.", meta: "Срок: 1-2 дня", price: "от 1 430 ₽", image: "https://source.unsplash.com/460x260/?spark,car,part" },
            { title: "Тормозные колодки передние", desc: "Устранение скрипа, подбор по износу дисков.", meta: "Срок: 1-2 дня", price: "от 2 250 ₽", image: "https://source.unsplash.com/460x260/?brake,service,car" },
            { title: "Суппорт/ремкомплект", desc: "Направляющие, пыльники и смазка — по регламенту.", meta: "Срок: 2-4 дня", price: "от 3 900 ₽", image: "https://source.unsplash.com/460x260/?brake,caliper,service" },
            { title: "Стойка стабилизатора", desc: "Тишина в подвеске и точность в поворотах.", meta: "Срок: до 3 дней", price: "от 1 250 ₽", image: "https://source.unsplash.com/460x260/?suspension,mechanic,car" },
            { title: "Амортизатор передний", desc: "Плавный ход и проверка опор.", meta: "Срок: 2-5 дней", price: "от 5 700 ₽", image: "https://source.unsplash.com/460x260/?car,garage,repair" },
            { title: "Термостат", desc: "Корректная температура и работа отопителя.", meta: "Срок: 1-3 дня", price: "от 2 600 ₽", image: "https://source.unsplash.com/460x260/?engine,mechanic,auto" },
        ],
    };

    let PARTS_BY_MODEL = DEFAULT_PARTS_BY_MODEL;

    async function loadPartsFromServer() {
        try {
            const res = await fetch("parts-data.php", { cache: "no-store" });
            const data = await res.json();
            if (data && data.ok === true && data.parts) {
                PARTS_BY_MODEL = data.parts;
            }
        } catch (_) {
            // Если не получилось загрузить — используем демо-данные
        }
    }

    function pickRandom(list, count) {
        const copy = Array.from(list);
        for (let i = copy.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [copy[i], copy[j]] = [copy[j], copy[i]];
        }
        return copy.slice(0, Math.min(count, copy.length));
    }

    function renderPartsForModel(modelKey) {
        const grid = partsGrids[modelKey];
        const items = PARTS_BY_MODEL[modelKey] || [];
        if (!grid) return;
        if (items.length === 0) {
            grid.innerHTML = `<p class="muted">Позиции для этой модели пока не добавлены.</p>`;
            return;
        }

        grid.innerHTML = items
            .map((p) => {
                const priceRaw = String(p.price || "").replace(/^от\s*/i, "").trim();
                return `
                    <button type="button" class="catalog-card catalog-card-action js-catalog-order" data-service="parts" data-title="${p.title}" data-price="${priceRaw}" data-meta="${p.meta || ""}">
                        <h4>${p.title}</h4>
                        <p>${p.desc || ""}</p>
                        <div class="meta">${p.meta || ""}</div>
                        <div class="price">${p.price || ""}</div>
                        <span class="catalog-card-cta">Заявка</span>
                    </button>
                `;
            })
            .join("");
    }

    function renderAllParts() {
        Object.keys(partsGrids).forEach((modelKey) => renderPartsForModel(modelKey));
    }

    if (partsRandomizeBtn) {
        partsRandomizeBtn.addEventListener("click", renderAllParts);
    }

    // Загружаем админские данные, потом рендерим каталог
    loadPartsFromServer().finally(() => {
        renderAllParts();
    });

    const SERVICE_LABELS = {
        order: "Автомобиль под заказ",
        maintenance: "Техническое обслуживание",
        repair: "Ремонт",
        parts: "Подбор автозапчастей",
    };

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
    let lastQuickPayload = null;

    function scrollToBlock(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    function goToContactForm(service, presetComment) {
        if (serviceSelect && service) {
            serviceSelect.value = service;
        }
        if (commentInput && typeof presetComment === "string") {
            commentInput.value = presetComment;
        }
        if (contactSection) {
            contactSection.scrollIntoView({ behavior: "smooth", block: "start" });
        }
        if (window.location.hash !== "#contact") {
            window.location.hash = "contact";
        }
    }

    function applyQuickAccountFields() {
        const qRow = quickNameInput && quickNameInput.closest(".quick-form-row");
        if (!loggedInUser || loggedInUser.loggedIn !== true) {
            if (qRow) qRow.style.display = "";
            return;
        }
        if (quickNameInput && typeof loggedInUser.name === "string") {
            quickNameInput.value = loggedInUser.name;
        }
        if (quickPhoneInput && typeof loggedInUser.phone === "string") {
            quickPhoneInput.value = formatPhoneDisplay(loggedInUser.phone);
        }
        if (qRow) qRow.style.display = "none";
    }

    function buildHomeQuickComment() {
        const p = lastQuickPayload;
        if (!p) return (quickNoteInput && quickNoteInput.value.trim()) || "";
        const lines = [`Услуга: ${SERVICE_LABELS[p.service] || p.service}`, `Позиция: ${p.title}`];
        if (p.meta) lines.push(p.meta);
        if (p.price) lines.push(`Ориентир: от ${p.price}`);
        const note = quickNoteInput && quickNoteInput.value.trim();
        if (note) lines.push(`Уточнение: ${note}`);
        return lines.join("\n");
    }

    function openHomeQuickModal(payload) {
        if (!quickModal) return;
        lastQuickPayload = payload;
        const isParts = payload.service === "parts";
        if (quickModalImage) {
            quickModalImage.hidden = isParts;
            if (!isParts) {
                quickModalImage.src = payload.image || "assets/images/car-placeholder.svg";
                quickModalImage.alt = payload.title || "Услуга";
            }
        }
        if (quickModalPartThumb) quickModalPartThumb.hidden = !isParts;
        if (quickModalBadge) {
            quickModalBadge.textContent = SERVICE_LABELS[payload.service] || "";
        }
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

    function closeHomeQuickModal() {
        if (!quickModal) return;
        quickModal.hidden = true;
        document.body.style.overflow = "";
    }

    if (quickModal && quickRequestForm && !document.getElementById("brand-name")) {
        quickModal.addEventListener("click", (event) => {
            if (event.target.closest("[data-close-modal='true']")) {
                closeHomeQuickModal();
            }
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && !quickModal.hidden) {
                closeHomeQuickModal();
            }
        });

        let quickSubmitting = false;
        quickRequestForm.addEventListener("submit", (event) => {
            event.preventDefault();
            if (quickSubmitting) return;
            if (!validateQuickFields()) {
                if (quickFormMessage) {
                    quickFormMessage.textContent = "Проверьте имя, телефон и email.";
                    quickFormMessage.style.color = ERROR;
                }
                return;
            }
            quickSubmitting = true;
            if (quickCommentInput) quickCommentInput.value = buildHomeQuickComment();
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
                    if (quickServiceInput && lastQuickPayload) {
                        quickServiceInput.value = lastQuickPayload.service || "order";
                    }
                    applyQuickAccountFields();
                    setTimeout(closeHomeQuickModal, 900);
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
    }

    function catalogOrderFromElement(el) {
        if (!el) return;
        openHomeQuickModal({
            service: el.dataset.service || "order",
            title: el.dataset.title || "Услуга",
            price: el.dataset.price || "",
            meta: el.dataset.meta || "",
            image: el.dataset.image || "",
        });
    }

    document.addEventListener("click", (event) => {
        const orderBtn = event.target.closest(".js-catalog-order");
        if (orderBtn) {
            event.preventDefault();
            catalogOrderFromElement(orderBtn);
            return;
        }

        const jumpLink = event.target.closest(".js-catalog-jump");
        if (jumpLink) {
            event.preventDefault();
            const service = jumpLink.dataset.service || "";
            const label = SERVICE_LABELS[service] || "услуга";
            goToContactForm(service, `Интересует: ${label}. `);
            return;
        }

        const scrollLink = event.target.closest(".js-catalog-scroll");
        if (scrollLink) {
            const href = scrollLink.getAttribute("href") || "";
            if (href.startsWith("#") && href.length > 1) {
                event.preventDefault();
                scrollToBlock(href.slice(1));
            }
        }
    });

    const pageParams = new URLSearchParams(window.location.search);
    const catalogTarget = pageParams.get("catalog");
    if (catalogTarget) {
        requestAnimationFrame(() => scrollToBlock(`catalog-${catalogTarget}`));
    }

    const serviceParam = pageParams.get("service");
    const itemParam = pageParams.get("item");
    if (serviceParam && itemParam && quickModal) {
        requestAnimationFrame(() => {
            openHomeQuickModal({
                service: serviceParam,
                title: itemParam,
                price: pageParams.get("price") || "",
                meta: pageParams.get("meta") || "",
            });
        });
    } else if (serviceParam && (window.location.hash === "#contact" || pageParams.get("form") === "1")) {
        const preset = itemParam
            ? `Интересует: ${itemParam}. `
            : `Интересует: ${SERVICE_LABELS[serviceParam] || serviceParam}. `;
        requestAnimationFrame(() => goToContactForm(serviceParam, preset));
    }
});
